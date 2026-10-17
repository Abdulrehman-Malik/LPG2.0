<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

/**
 * Cylinder Sale posting (transaction_type cylinder_sale).
 * Part of SalesService (Phase 10.7 split); moved unchanged, uses SalesService state and helpers.
 */
trait PostsCylinderSales
{
    protected function postCylinderSaleHeader(array $payload,int $userId,int $locationId,?int $customerId,string $transactionAt,?string $notes,array $lines,array $payments): array
    {
        if(!$lines) throw new RuntimeException('At least one cylinder sale line is required.');
        if(!$payments) throw new RuntimeException('At least one payment is required.');

        $prepared=[];$subtotal=0;$customRate=false;$selectedAcrossLines=[];
        foreach($lines as $i=>$line){
            $n=$i+1;
            $typeId=isset($line['cylinder_type_id'])&&$line['cylinder_type_id']!==''?(int)$line['cylinder_type_id']:0;
            $status=(string)($line['cylinder_status']??'empty');
            $qty=(float)($line['quantity']??0);
            $gasRateInput=trim((string)($line['gas_rate']??''))===''?null:(float)$line['gas_rate'];
            $cylRateInput=trim((string)($line['cylinder_rate']??''))===''?null:(float)$line['cylinder_rate'];
            $selectedIds=array_values(array_unique(array_map('intval',is_array($line['selected_cylinder_unit_ids']??null)?$line['selected_cylinder_unit_ids']:[])));

            if(!$typeId||!in_array($status,['filled','empty'],true)) throw new RuntimeException('Cylinder type and sale type are required on line '.$n.'.');
            if($qty<=0||floor($qty)!==$qty) throw new RuntimeException('Cylinder quantity on line '.$n.' must be a whole number.');
            $type=BusinessContext::cylinderType($typeId,$locationId);
            if(!$type||!(int)$type['is_active']) throw new RuntimeException('Invalid or inactive cylinder type on line '.$n.'.');

            $gasKg=0;$gasRate=0;$stdGas=null;
            // Cylinder Sale pricing priority: a positive rate configured against this cylinder type
            // overrides the Cylinder Type master price. A zero/negative configured rate means
            // no rate is defined there, so fall back to the Cylinder Type empty-cylinder price.
            $stdCylinderRate=$this->rateService()->emptyCylinderPrice($locationId,$customerId,$type,$transactionAt);
            // A manually entered cylinder price is valid even when it is 0.00.
            // Only require a configured/default price when the user leaves the price blank.
            if($cylRateInput===null && $stdCylinderRate<=0) throw new RuntimeException('No empty-cylinder price is defined for '.$type['name'].'. Enter a cylinder price on the POS line or configure a default rate.');
            $cylRate=$cylRateInput??$stdCylinderRate;
            if($cylRate<0) throw new RuntimeException('Cylinder price cannot be negative on line '.$n.'.');

            if($status==='filled'){
                if(count($selectedIds)!==(int)$qty) throw new RuntimeException('Filled cylinder line '.$n.' must select exactly '.(int)$qty.' physical cylinder(s).');
                foreach($selectedIds as $selectedId){
                    if(isset($selectedAcrossLines[$selectedId])) throw new RuntimeException('Physical cylinder '.$selectedId.' is selected more than once in this sale.');
                    $selectedAcrossLines[$selectedId]=$n;
                }
                // Gas in a filled cylinder: filled cylinder rate as a per-KG equivalent (pro-rata for partly filled).
                $stdGas=$this->rateService()->filledCylinderRate($locationId,$customerId,$type,$transactionAt)['per_kg'];
                if($stdGas!==null && $stdGas<=0) $stdGas=null;
                $gasRate=$gasRateInput??$stdGas;
                if($gasRate===null || $gasRate<=0){
                    throw new RuntimeException($gasRateInput===null
                        ? 'No effective gas/kg rate exists. Enter a positive Gas Rate for this line or configure an effective rate in Gas Rate Settings.'
                        : 'Gas rate must be greater than zero on line '.$n.'.');
                }
            }else{
                if($selectedIds) throw new RuntimeException('Physical cylinder selection is only allowed for filled cylinder sale lines.');
            }

            $custom=(($status==='filled'&&$gasRateInput!==null&&($stdGas===null||abs($gasRate-$stdGas)>0.00001))||($cylRateInput!==null&&abs($cylRate-$stdCylinderRate)>0.00001));
            $customRate=$customRate||$custom;
            $prepared[]=[
                'line_no'=>$n,'type_id'=>$typeId,'status'=>$status,'quantity'=>(int)$qty,
                'selected_ids'=>$selectedIds,'gas_rate'=>$gasRate,'cyl_rate'=>$cylRate,
                'std_gas'=>$stdGas,'std_cyl_rate'=>$stdCylinderRate,'custom_rate'=>$custom?1:0
            ];
        }

        $this->db->transBegin();
        try{
            $customer=null;
            if($customerId){
                $customer=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
                if(!$customer||!(int)$customer['is_active']) throw new RuntimeException('Customer is unavailable.');
            }

            $lockRows=[];
            foreach($prepared as $row){
                $lockRows[]=['type'=>$row['status']==='filled'?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>$row['type_id']];
                if($row['status']==='filled') $lockRows[]=['type'=>'gas_kg','cylinder_type_id'=>null];
            }
            $this->acquireInventoryLocks($locationId,$lockRows);

            $inventory=[];$lineAmounts=[];$totalKg=0;$reservedEmptyIds=[];
            foreach($prepared as $idx=>&$row){
                $selected=[];
                if($row['status']==='filled'){
                    $ids=$row['selected_ids'];
                    $placeholders=implode(',',array_fill(0,count($ids),'?'));
                    $rows=$this->db->query(
                        "SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg
                         FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
                         WHERE cu.location_id=? AND cu.status='filled' AND cu.id IN ($placeholders)
                         ORDER BY cu.id FOR UPDATE",
                        array_merge([$locationId],$ids)
                    )->getResultArray();
                    if(count($rows)!==count($ids)) throw new RuntimeException('One or more selected filled cylinders are no longer available. Refresh the list and retry.');
                    foreach($rows as $unit){
                        if((int)$unit['cylinder_type_id']!==$row['type_id']) throw new RuntimeException('Selected cylinder '.$unit['unit_code'].' does not belong to the selected cylinder type.');
                        if((float)$unit['gas_weight_kg']<=0.00001) throw new RuntimeException('Selected cylinder '.$unit['unit_code'].' has no gas available.');
                        $selected[]=$unit;
                    }
                    $row['gas_kg']=array_sum(array_map(static fn($u)=>(float)$u['gas_weight_kg'],$selected));
                    $row['quantity']=count($selected);
                    $row['selected_codes']=array_map(static fn($u)=>$u['unit_code'],$selected);
                    foreach($selected as $unit){
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>(float)$unit['gas_weight_kg'],'direction'=>'out','unit_id'=>(int)$unit['id'],'notes'=>'Gas sold from selected physical cylinder'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'filled_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'notes'=>'Filled cylinder sold'];
                    }
                }else{
                    $limit=(int)$row['quantity'];
                    $rows=$this->db->query(
                        "SELECT * FROM cylinder_units
                         WHERE location_id=? AND cylinder_type_id=? AND status='empty'
                         ORDER BY id LIMIT $limit FOR UPDATE",
                        [$locationId,$row['type_id']]
                    )->getResultArray();
                    if(count($rows)<$limit) throw new RuntimeException('Insufficient empty cylinders of '.(BusinessContext::cylinderType((int)$row['type_id'],$locationId)['name']??'this type').'.');
                    $row['gas_kg']=0;$row['selected_codes']=[];
                    foreach($rows as $unit){
                        if(isset($reservedEmptyIds[(int)$unit['id']])) throw new RuntimeException('Duplicate empty cylinder allocation detected.');
                        $reservedEmptyIds[(int)$unit['id']]=true;
                        $row['selected_codes'][]=$unit['unit_code'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'notes'=>'Empty cylinder sold'];
                    }
                }

                $lineTotal=$this->rounding($locationId)->line($row['gas_kg']*$row['gas_rate']+$row['quantity']*$row['cyl_rate']);
                $row['line_total']=$lineTotal;
                $lineAmounts[]=$lineTotal;$subtotal+=$lineTotal;$totalKg+=(float)$row['gas_kg'];
            }
            unset($row);

            $discount=max(0,(float)($payload['discount_amount']??0));
            if($discount>$subtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
            $total=$this->rounding($locationId)->total($subtotal-$discount);

            $paymentPlan=$this->prepareSalePaymentPlan($payments,$customerId,$total);
            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $this->db->table('sales')->insert([
                'sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>'cylinder_sale',
                'status'=>'posted','transaction_at'=>$transactionAt,'total_kg'=>$totalKg,'subtotal'=>$subtotal,
                'discount_amount'=>$discount,'total_amount'=>$total,'security_deposit_amount'=>0,'security_deposit_refund_amount'=>0,
                'credit_amount'=>$paymentPlan['credit_amount'],'previous_os_balance'=>$paymentPlan['previous_os'],
                'receipt_amount'=>$paymentPlan['payment_total'],'net_receivable_amount'=>$paymentPlan['net_receivable'],
                'os_balance'=>$paymentPlan['remaining_os'],'custom_rate_flag'=>$customRate?1:0,'notes'=>$notes,'created_by'=>$userId
            ]);
            $saleId=(int)$this->db->insertID();

            foreach($prepared as $row){
                $appliedRate=$row['status']==='filled'
                    ? (($row['gas_kg']/max($row['quantity'],1))*$row['gas_rate'])+$row['cyl_rate']
                    : $row['cyl_rate'];
                $standardRate=$row['status']==='filled'
                    ? (($row['gas_kg']/max($row['quantity'],1))*$row['std_gas'])+$row['std_cyl_rate']
                    : $row['std_cyl_rate'];
                $this->db->table('sale_items')->insert([
                    'sale_id'=>$saleId,'line_no'=>$row['line_no'],'line_type'=>$row['status']==='filled'?'filled_cylinder':'empty_cylinder',
                    'cylinder_type_id'=>$row['type_id'],'customer_cylinder_unit_id'=>null,'quantity'=>$row['quantity'],
                    'gas_weight_kg'=>$row['gas_kg'],'applied_rate'=>$appliedRate,'gas_rate'=>$row['gas_rate'],'cylinder_price'=>$row['cyl_rate'],'standard_rate'=>$standardRate,
                    'custom_rate_flag'=>$row['custom_rate'],'empty_cylinder_received'=>0,'line_discount'=>0,
                    'line_total'=>$row['line_total'],'notes'=>'Cylinder sale | '.$row['status'].' | '.implode(', ',$row['selected_codes'])
                ]);
            }

            $lineIds=[];
            foreach($this->db->table('sale_items')->select('id,line_no')->where('sale_id',$saleId)->get()->getResultArray() as $row) $lineIds[(int)$row['line_no']]=(int)$row['id'];

            $soldUnitIds=[];
            foreach($inventory as $m){
                $this->db->table('inventory_movements')->insert([
                    'location_id'=>$locationId,'inventory_type'=>$m['type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                    'quantity'=>$m['quantity'],'direction'=>$m['direction'],'movement_at'=>$transactionAt,'source_type'=>'sale',
                    'source_id'=>$saleId,'source_line_id'=>$lineIds[(int)$m['line_no']]??null,'cylinder_unit_id'=>$m['unit_id'],
                    'created_by'=>$userId,'notes'=>$m['notes']
                ]);
                $unitId=(int)($m['unit_id']??0);
                if($unitId>0) $soldUnitIds[$unitId]=true;
            }
            foreach(array_keys($soldUnitIds) as $unitId) $this->cylinders->markSold((int)$unitId);

            $this->insertSalePayments($saleId,$paymentPlan['sale_payments'],$transactionAt,$userId);
            $this->postCustomerSettlement($paymentPlan['settlements'],$locationId,$saleId,$customerId,$transactionAt,$userId);
            $cash=$this->paymentCash($paymentPlan['sale_payments']);
            if($cash>0){
                $session=$this->cash->openSessionForLocation($locationId);
                if(!$session) throw new RuntimeException('Open the counter cash session before posting a cash sale.');
                $this->cash->postSaleCash((int)$session['id'],$saleId,$cash,$userId,$transactionAt);
            }
            if(!$this->db->transStatus()) throw new RuntimeException('Cylinder sale posting failed.');
            $this->db->transCommit();$this->releaseInventoryLocks();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>$total,'customer_id'=>$customerId,'credit_amount'=>$paymentPlan['credit_amount']];
        }catch(\Throwable $e){
            $this->releaseInventoryLocks();$this->db->transRollback();throw $e;
        }
    }
}
