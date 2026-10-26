<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

/**
 * Gas Sale / Refill posting (transaction_type gas_sale).
 * Part of SalesService (Phase 10.7 split); moved unchanged, uses SalesService state and helpers.
 */
trait PostsGasSales
{
    protected function postGasSaleHeader(array $payload,int $userId,int $locationId,?int $customerId,string $transactionAt,?string $notes,array $lines,array $payments): array
    {
        if(!$lines) throw new RuntimeException('At least one gas line is required.');
        if(!$payments) throw new RuntimeException('At least one payment is required.');
        $shopSettings=(new ShopSettingsModel())->forLocation($locationId);
        $allowSourceSelection=(int)($shopSettings['allow_pos_source_cylinder_selection']??0)===1;
        // Cylinder-count entry allocates gas across available filled cylinders by stock order.
        if ((string)($lines[0]['gas_entry_mode']??'') === 'cylinders') $allowSourceSelection=false;

        $prepared=[];$subtotal=0;$totalKg=0;$customRate=false;
        foreach($lines as $i=>$line){
            $n=$i+1;
            $typeId=isset($line['cylinder_type_id'])&&$line['cylinder_type_id']!==''?(int)$line['cylinder_type_id']:0;
            $sourceId=isset($line['source_cylinder_unit_id'])&&$line['source_cylinder_unit_id']!==''?(int)$line['source_cylinder_unit_id']:0;
            $qty=(float)($line['quantity']??0);
            $entryMode=(string)($line['gas_entry_mode']??'quantity');
            $cylinderQty=(float)($line['cylinder_quantity']??0);
            $enteredAmount=trim((string)($line['entered_amount']??''))===''?null:(float)$line['entered_amount'];
            $gasRateInput=trim((string)($line['gas_rate']??''))===''?null:(float)$line['gas_rate'];
            $targetId=isset($line['customer_cylinder_unit_id'])&&$line['customer_cylinder_unit_id']!==''?(int)$line['customer_cylinder_unit_id']:null;

            if(!$typeId) throw new RuntimeException('Cylinder type is required on gas line '.$n.'.');
            if($allowSourceSelection && !$sourceId) throw new RuntimeException('Source filled cylinder is required on gas line '.$n.'.');
            if(!$allowSourceSelection) $sourceId=0;
            $type=BusinessContext::cylinderType($typeId,$locationId);
            if(!$type||!(int)$type['is_active']) throw new RuntimeException('Invalid or inactive cylinder type on gas line '.$n.'.');
            if($entryMode==='cylinders'){
                if($cylinderQty<=0 || floor($cylinderQty)!==$cylinderQty) throw new RuntimeException('Cylinder quantity on gas line '.$n.' must be a positive whole number.');
                $capacity=(float)($type['capacity_kg']??0);
                if($capacity<=0) throw new RuntimeException('A positive cylinder capacity is required for '.$type['name'].'.');
                $qty=$cylinderQty*$capacity;
                $enteredAmount=null;
                // Cylinder-count entry uses normal gas/kg pricing and stock allocation.
                $sourceId=0;
            }
            if($enteredAmount!==null && $enteredAmount<=0) throw new RuntimeException('Entered amount on gas line '.$n.' must be greater than zero.');
            if($gasRateInput!==null&&$gasRateInput<0) throw new RuntimeException('Gas rate cannot be negative on line '.$n.'.');

            // Gas Sale is priced per KG; the customer's own KG rate first (requirements.md, Rates).
            $standardRate=$this->rateService()->kgRate($locationId,$customerId,$transactionAt)['rate'];
            // A zero rate is not usable for calculating KG from an amount.
            if($standardRate!==null && $standardRate<=0) $standardRate=null;

            // Amount mode uses the entered gas rate when supplied; otherwise it falls
            // back to the configured rate. The server calculates KG from that rate so
            // stock, sale items, receipts, and customer balances use the same values.
            if($enteredAmount!==null){
                $gasRate=$gasRateInput??$standardRate;
                if($gasRate===null || $gasRate<=0){
                    throw new RuntimeException($standardRate===null
                        ? 'Enter a positive Gas Rate for Amount entry or configure an effective rate in Gas Rate Settings.'
                        : 'Gas rate must be greater than zero on line '.$n.'.');
                }
                $qty=$enteredAmount/$gasRate;
                if($qty<=0) throw new RuntimeException('Calculated gas quantity on line '.$n.' must be greater than zero.');
                $lineTotal=$this->rounding($locationId)->line($enteredAmount);
            }else{
                if($qty<=0) throw new RuntimeException('Gas quantity on line '.$n.' must be greater than zero.');
                $gasRate=$gasRateInput??$standardRate;
                if($gasRate===null || $gasRate<=0){
                    throw new RuntimeException($standardRate===null
                        ? 'No effective gas/kg rate exists. Enter a positive Gas Rate for this line or configure an effective rate in Gas Rate Settings.'
                        : 'Gas rate must be greater than zero on line '.$n.'.');
                }
                $lineTotal=$this->rounding($locationId)->line($qty*$gasRate);
            }
            $custom=$standardRate===null
                ? ($gasRateInput!==null && $gasRate>0)
                : ($gasRateInput!==null && abs($gasRate-$standardRate)>0.00001);
            $customRate=$customRate||$custom;

            $prepared[]=[
                'line_no'=>$n,'type_id'=>$typeId,'source_id'=>$sourceId,'quantity'=>$qty,'gas_weight_kg'=>$qty,
                'gas_rate'=>$gasRate,'standard_rate'=>$standardRate,'custom_rate_flag'=>$custom?1:0,
                'target_id'=>$targetId,'line_total'=>$lineTotal
            ];
            $subtotal+=$lineTotal;$totalKg+=$qty;
        }

        $discount=max(0,(float)($payload['discount_amount']??0));
        if($discount>$subtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
        $total=$this->rounding($locationId)->total($subtotal-$discount);

        $this->db->transBegin();
        try{
            if($customerId){
                $locked=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
                if(!$locked||!(int)$locked['is_active']) throw new RuntimeException('Customer is unavailable.');
            }

            $paymentPlan=$this->prepareSalePaymentPlan($payments,$customerId,$total);

            $lockKeys=array_map(static fn($row)=>['type'=>'filled_cylinder','cylinder_type_id'=>(int)$row['type_id']],$prepared);
            $this->acquireInventoryLocks($locationId,$lockKeys);

            $typeSeen=[];
            foreach($prepared as $row){
                if(isset($typeSeen[$row['type_id']])) throw new RuntimeException('Cylinder type cannot be used on multiple gas sale lines. Combine the quantity into one line.');
                $typeSeen[$row['type_id']]=true;
            }

            $sources=[];
            $sourceIds=$allowSourceSelection?array_values(array_unique(array_map(static fn($row)=>(int)$row['source_id'],$prepared))):[];
            if($allowSourceSelection){
                if(!$sourceIds) throw new RuntimeException('Source filled cylinder is required.');
                $placeholders=implode(',',array_fill(0,count($sourceIds),'?'));
                $sourceRows=$this->db->query(
                    "SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg
                     FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
                     WHERE cu.location_id=? AND cu.status='filled' AND cu.id IN ($placeholders) FOR UPDATE",
                    array_merge([$locationId],$sourceIds)
                )->getResultArray();
                foreach($sourceRows as $row)$sources[(int)$row['id']]=$row;
                $requestedBySource=[];
                foreach($prepared as $row){
                    $source=$sources[$row['source_id']]??null;
                    if(!$source) throw new RuntimeException('Selected source cylinder on gas line '.$row['line_no'].' is no longer available.');
                    if((int)$source['cylinder_type_id']!==$row['type_id']) throw new RuntimeException('Gas line '.$row['line_no'].' cylinder type does not match the selected source cylinder.');
                    if(isset($requestedBySource[$row['source_id']])) throw new RuntimeException('The same source filled cylinder cannot be selected on multiple gas sale lines.');
                    $requestedBySource[$row['source_id']]=(float)$row['quantity'];
                    if($requestedBySource[$row['source_id']]>(float)$source['gas_weight_kg']+0.00001) throw new RuntimeException('Gas line '.$row['line_no'].' exceeds selected cylinder '.$source['unit_code'].' stock. Available: '.number_format((float)$source['gas_weight_kg'],2).' KG.');
                }
            }

            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $this->db->table('sales')->insert([
                'sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>'gas_sale',
                'status'=>'posted','transaction_at'=>$transactionAt,'total_kg'=>$totalKg,'subtotal'=>$subtotal,
                'discount_amount'=>$discount,'total_amount'=>$total,'security_deposit_amount'=>0,
                'security_deposit_refund_amount'=>0,'credit_amount'=>$paymentPlan['credit_amount'],
                'previous_os_balance'=>$paymentPlan['previous_os'],'receipt_amount'=>round($paymentPlan['payment_total'],2),
                'net_receivable_amount'=>$paymentPlan['net_receivable'],'os_balance'=>$paymentPlan['remaining_os'],
                'custom_rate_flag'=>$customRate?1:0,'notes'=>$notes,'created_by'=>$userId
            ]);
            $saleId=(int)$this->db->insertID();

            foreach($prepared as $row){
                if($row['target_id']){
                    if(!$customerId) throw new RuntimeException('Select a named customer before choosing a customer custody cylinder.');
                    $target=$this->db->query(
                        "SELECT cu.*,ct.capacity_kg
                         FROM cylinder_units cu
                         JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
                         JOIN cylinder_custody cc ON cc.cylinder_unit_id=cu.id AND cc.status='issued' AND cc.customer_id=?
                         WHERE cu.id=? AND cu.location_id=? AND cu.status='custody'
                         FOR UPDATE",
                        [$customerId,$row['target_id'],$locationId]
                    )->getRowArray();
                    if(!$target) throw new RuntimeException('Selected customer custody cylinder is not available.');
                    if((int)$target['cylinder_type_id']!==$row['type_id']) throw new RuntimeException('Gas refill cylinder type must match the selected customer custody cylinder type.');
                    if((float)$target['gas_weight_kg']+(float)$row['quantity']>(float)$target['capacity_kg']+0.00001) throw new RuntimeException('Gas quantity exceeds the remaining capacity of the selected customer cylinder.');
                }

                $sourceLabel=$allowSourceSelection?'Source cylinder selected by user':'Source cylinders auto-allocated by sequence';
                $this->db->table('sale_items')->insert([
                    'sale_id'=>$saleId,'line_no'=>$row['line_no'],'line_type'=>'refill_kg','cylinder_type_id'=>$row['type_id'],
                    'customer_cylinder_unit_id'=>$row['target_id'],'quantity'=>$row['quantity'],'gas_weight_kg'=>$row['gas_weight_kg'],
                    'applied_rate'=>$row['gas_rate'],'standard_rate'=>$row['standard_rate'],'custom_rate_flag'=>$row['custom_rate_flag'],
                    'empty_cylinder_received'=>0,'line_discount'=>0,'line_total'=>$row['line_total'],'notes'=>($row['target_id']?'Refilled customer custody cylinder':'Gas sale / refill').' | '.$sourceLabel
                ]);
                $saleItemId=(int)$this->db->insertID();

                $remaining=(float)$row['quantity'];
                $allocationUnits=[];
                if($allowSourceSelection){
                    $source=$sources[$row['source_id']]??null;
                    if(!$source) throw new RuntimeException('Selected source cylinder on gas line '.$row['line_no'].' is no longer available.');
                    if((float)$source['gas_weight_kg']+0.00001<$remaining) throw new RuntimeException('Selected source cylinder '.$source['unit_code'].' has only '.number_format((float)$source['gas_weight_kg'],2).' KG available.');
                    $allocationUnits=[['unit'=>$source,'used'=>$remaining]];
                }else{
                    $autoUnits=$this->db->query(
                        "SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg
                         FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
                         WHERE cu.location_id=? AND cu.cylinder_type_id=? AND cu.status='filled' AND cu.gas_weight_kg>0
                         ORDER BY cu.id FOR UPDATE",
                        [$locationId,$row['type_id']]
                    )->getResultArray();
                    $availableAuto=array_sum(array_map(static fn($u)=>(float)$u['gas_weight_kg'],$autoUnits));
                    if($availableAuto+0.00001<$remaining) throw new RuntimeException('Insufficient filled gas stock for '.$row['line_no'].'. Available: '.number_format($availableAuto,2).' KG; required: '.number_format($remaining,2).' KG.');
                    foreach($autoUnits as $unit){
                        if($remaining<=0.00001) break;
                        $used=min($remaining,(float)$unit['gas_weight_kg']);
                        if($used>0) $allocationUnits[]=['unit'=>$unit,'used'=>$used];
                        $remaining-=$used;
                    }
                }

                foreach($allocationUnits as $allocation){
                    $source=$allocation['unit'];$used=(float)$allocation['used'];
                    $before=(float)$source['gas_weight_kg'];
                    if($used>$before+0.00001) throw new RuntimeException('Source cylinder '.$source['unit_code'].' stock changed while posting.');
                    $after=$before-$used;$newStatus=$after<=0.00001?'empty':'filled';
                    $this->db->table('cylinder_units')->where('id',(int)$source['id'])->update(['gas_weight_kg'=>max(0,$after),'status'=>$newStatus]);

                    $movementRows=[['type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','from'=>'filled','to'=>$newStatus,'unit_id'=>(int)$source['id'],'notes'=>($allowSourceSelection?'Gas sold from selected filled cylinder':'Gas auto-allocated from filled cylinder in sequence')]];
                    if($newStatus==='empty'){
                        $movementRows[]=['type'=>'filled_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'out','from'=>'filled','to'=>'empty','unit_id'=>(int)$source['id'],'notes'=>'Cylinder became empty after gas sale'];
                        $movementRows[]=['type'=>'empty_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'in','from'=>'filled','to'=>'empty','unit_id'=>(int)$source['id'],'notes'=>'Cylinder became empty after gas sale'];
                    }
                    foreach($movementRows as $m){
                        $this->db->table('inventory_movements')->insert([
                            'location_id'=>$locationId,'inventory_type'=>$m['type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                            'quantity'=>$m['quantity'],'direction'=>$m['direction'],'from_state'=>$m['from'],'to_state'=>$m['to'],'movement_at'=>$transactionAt,
                            'source_type'=>'sale','source_id'=>$saleId,'source_line_id'=>$saleItemId,
                            'cylinder_unit_id'=>$m['unit_id'],'created_by'=>$userId,'notes'=>$m['notes']
                        ]);
                    }
                    $sources[(int)$source['id']]=$source;$sources[(int)$source['id']]['gas_weight_kg']=max(0,$after);$sources[(int)$source['id']]['status']=$newStatus;
                }

                if($row['target_id']) $this->cylinders->addGasToCustody($row['target_id'],$customerId,$row['quantity']);
            }
            if(!$this->db->transStatus()) throw new RuntimeException('Gas sale posting failed.');
            $this->insertSalePayments($saleId,$paymentPlan['sale_payments'],$transactionAt,$userId);
            $this->postCustomerSettlement($paymentPlan['settlements'],$locationId,$saleId,$customerId,$transactionAt,$userId);
            $cash=$this->paymentCash($paymentPlan['sale_payments']);
            if($cash>0){
                $session=$this->cash->openSessionForLocation($locationId);
                if(!$session) throw new RuntimeException('Open the counter cash session before posting a cash sale.');
                $this->cash->postSaleCash((int)$session['id'],$saleId,$cash,$userId,$transactionAt);
            }

            if(!$this->db->transStatus()) throw new RuntimeException('Gas sale posting failed.');
            $this->db->transCommit();$this->releaseInventoryLocks();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>$total,'customer_id'=>$customerId,'credit_amount'=>$paymentPlan['credit_amount']];
        }catch(\Throwable $e){
            $this->releaseInventoryLocks();$this->db->transRollback();throw $e;
        }
    }
}
