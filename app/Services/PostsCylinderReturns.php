<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

/**
 * Cylinder Return / Refund Deposit posting (transaction_type cylinder_return).
 * Part of SalesService (Phase 10.7 split); moved unchanged, uses SalesService state and helpers.
 */
trait PostsCylinderReturns
{
    protected function postCylinderReturnHeader(array $payload,int $userId,int $locationId,int $customerId,string $transactionAt,?string $notes,array $returnUnitIds): array
    {
        $lines=is_array($payload['lines']??null)?$payload['lines']:[];
        if(!$returnUnitIds&&!$lines)throw new RuntimeException('Select at least one customer custody cylinder to return.');
        $payments=is_array($payload['payments']??null)?$payload['payments']:[];
        foreach($payments as $p){
            if((string)($p['payment_type']??'')!=='security_deposit_refund')throw new RuntimeException('Cylinder Return payments must be classified as Security Deposit Refund.');
        }
        $shopSettings=(new ShopSettingsModel())->forLocation($locationId);
        $allowGas=(int)($shopSettings['allow_return_gas_qty']??0)===1;
        $affectsOs=(int)($shopSettings['return_gas_affects_os']??0)===1;
        $allowEmptyGas=(int)($shopSettings['allow_empty_issued_return_gas']??0)===1;
        $allowOver=(int)($shopSettings['allow_return_gas_over_issued']??0)===1;
        $includeDepositOs=(int)($shopSettings['include_security_deposit_in_os']??0)===1;
        $refund=max(0,(float)($payload['security_deposit_refund_amount']??0));

        $selected=[];
        foreach($lines as $line){$id=(int)($line['unit_id']??0);if($id>0)$selected[]=$id;}
        if(!$selected)$selected=$returnUnitIds;
        $selected=array_values(array_unique($selected));
        if(!$selected)throw new RuntimeException('Select at least one customer custody cylinder to return.');

        $rows=$this->db->table('cylinder_custody cc')->select('cc.*,cu.unit_code,cu.gas_weight_kg,cu.cylinder_type_id,cu.status cylinder_status,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->where(['cc.location_id'=>$locationId,'cc.customer_id'=>$customerId,'cc.status'=>'issued'])->whereIn('cc.cylinder_unit_id',$selected)->orderBy('cc.id')->get()->getResultArray();
        if(count($rows)!==count($selected))throw new RuntimeException('One or more selected custody cylinders are no longer active for this customer.');

        $returnById=[];
        foreach($lines as $line){
            $id=(int)($line['unit_id']??0);if($id<=0)continue;
            if(isset($returnById[$id]))throw new RuntimeException('Cylinder '.$id.' appears more than once in the return.');
            $qty=max(0,(float)($line['return_gas_kg']??0));$rate=max(0,(float)($line['return_gas_rate']??0));
            if(!$allowGas&&$qty>0.00001)throw new RuntimeException('Return gas quantity is disabled in Shop Settings.');
            $returnById[$id]=['gas'=>$qty,'rate'=>$rate];
        }
        foreach($rows as $row){
            $id=(int)$row['cylinder_unit_id'];$cfg=$returnById[$id]??['gas'=>0,'rate'=>0];$issued=(float)$row['issued_gas_weight_kg'];
            if($cfg['gas']<0)throw new RuntimeException('Return gas cannot be negative for '.$row['unit_code'].'.');
            if($cfg['gas']>0&&!$allowGas)throw new RuntimeException('Return gas quantity is disabled in Shop Settings.');
            if($cfg['gas']>0&&$issued<=0&&!$allowEmptyGas)throw new RuntimeException('Cylinder '.$row['unit_code'].' was issued empty and cannot be returned with gas under current Shop Settings.');
            if($cfg['gas']>$issued+0.00001&&!$allowOver)throw new RuntimeException('Return gas for '.$row['unit_code'].' cannot exceed the issued gas quantity of '.number_format($issued, 2).' KG.');
            $rate=$cfg['rate']>0?$cfg['rate']:$row['issued_gas_rate'];
            if($cfg['gas']>0&&$rate<0)throw new RuntimeException('Return gas rate cannot be negative.');
            $returnById[$id]['rate']=$rate;$returnById[$id]['consumed']=max(0,$issued-$cfg['gas']);
        }

        $depositBalance=$this->securityDepositBalance($locationId,$customerId);
        if($refund>$depositBalance+0.01)throw new RuntimeException('Refund amount cannot exceed the customer refundable security deposit balance of Rs. '.number_format($depositBalance,2).'.');
        if($refund>0)$this->validateHeaderPayments($payments,$customerId,$refund,false);

        $returnLedger=0;
        foreach($rows as $row){$id=(int)$row['cylinder_unit_id'];$cfg=$returnById[$id];$returnLedger+=($cfg['gas']*$cfg['rate']);}
        $returnLedger=$affectsOs?round($returnLedger,2):0;

        $this->db->transBegin();
        try{
            // Same lock order as the other posting paths: customer, inventory keys, units.
            $customer=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
            if(!$customer||!(int)$customer['is_active'])throw new RuntimeException('Customer is unavailable.');
            $lockRows=[];
            foreach($rows as $row){
                $lockRows[]=['type'=>($returnById[(int)$row['cylinder_unit_id']]['gas']??0)>0?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>(int)$row['cylinder_type_id']];
            }
            $this->acquireInventoryLocks($locationId,$lockRows);
            foreach($selected as $id){
                $locked=$this->db->query("SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id JOIN cylinder_custody cc ON cc.cylinder_unit_id=cu.id AND cc.status='issued' WHERE cu.id=? AND cc.customer_id=? FOR UPDATE",[$id,$customerId])->getRowArray();
                if(!$locked)throw new RuntimeException('Cylinder selection changed while posting. Refresh and retry.');
            }
            // Re-check under the customer lock so two concurrent refunds cannot both pass.
            $depositBalance=$this->securityDepositBalance($locationId,$customerId);
            if($refund>$depositBalance+0.01)throw new RuntimeException('Refund amount cannot exceed the customer refundable security deposit balance of Rs. '.number_format($depositBalance,2).'.');

            $previousGasOs=$this->customerGasBalance($customerId);
            $newGasOs=$previousGasOs-$returnLedger;
            $depositBalanceAfter=$depositBalance-$refund;
            $overallOsAfter=$includeDepositOs?($newGasOs+$depositBalanceAfter):$newGasOs;

            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $this->db->table('sales')->insert([
                'sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>'cylinder_return','status'=>'posted','transaction_at'=>$transactionAt,
                'total_kg'=>array_sum(array_map(static fn($x)=>(float)$x['gas'],array_values($returnById))),'subtotal'=>0,'discount_amount'=>0,'total_amount'=>0,
                'security_deposit_amount'=>0,'security_deposit_refund_amount'=>$refund,'credit_amount'=>0,'previous_os_balance'=>$previousGasOs,
                'receipt_amount'=>0,'net_receivable_amount'=>0,'os_balance'=>$overallOsAfter,'return_gas_ledger_amount'=>-$returnLedger,'custom_rate_flag'=>0,'notes'=>$notes,'created_by'=>$userId
            ]);
            $saleId=(int)$this->db->insertID();

            foreach($rows as $idx=>$row){
                $id=(int)$row['cylinder_unit_id'];$cfg=$returnById[$id];$gas=$cfg['gas'];$rate=$cfg['rate'];$consumed=$cfg['consumed'];
                if($gas>0)$this->db->table('cylinder_units')->where('id',$id)->update(['status'=>'filled','custody_customer_id'=>null,'gas_weight_kg'=>$gas]);
                else $this->db->table('cylinder_units')->where('id',$id)->update(['status'=>'empty','custody_customer_id'=>null,'gas_weight_kg'=>0]);
                $this->db->table('cylinder_custody')->where('id',$row['id'])->update(['status'=>'returned','return_sale_id'=>$saleId,'refund_amount'=>($refund>0&&$idx===0?$refund:0),'returned_at'=>$transactionAt,'updated_by'=>$userId,'returned_gas_weight_kg'=>$gas,'consumed_gas_weight_kg'=>$consumed,'return_gas_rate'=>$rate]);
                if($refund>0&&$idx===0)$this->db->table('customer_security_deposits')->insert(['location_id'=>$locationId,'customer_id'=>$customerId,'entry_type'=>'refund','amount'=>$refund,'sale_id'=>$saleId,'custody_id'=>$row['id'],'transaction_at'=>$transactionAt,'created_by'=>$userId,'notes'=>'Security deposit refund']);
                $this->db->table('sale_items')->insert([
                    'sale_id'=>$saleId,'line_no'=>$idx+1,'line_type'=>$gas>0?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>$row['cylinder_type_id'],
                    'customer_cylinder_unit_id'=>$id,'quantity'=>1,'gas_weight_kg'=>$gas,'applied_rate'=>$rate,'gas_rate'=>$rate,'cylinder_price'=>0,'standard_rate'=>$row['issued_gas_rate'],
                    'custom_rate_flag'=>abs($rate-(float)$row['issued_gas_rate'])>0.00001?1:0,'empty_cylinder_received'=>$gas<=0?1:0,'line_discount'=>0,'line_total'=>round($gas*$rate,2),
                    'notes'=>'Cylinder returned: '.$row['unit_code'].' | Issued '.$row['issued_gas_weight_kg'].' KG | Returned '.$gas.' KG | Consumed '.$consumed.' KG'
                ]);
                $lineId=(int)$this->db->insertID();
                $this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>$gas>0?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>$row['cylinder_type_id'],'quantity'=>1,'direction'=>'in','movement_at'=>$transactionAt,'source_type'=>'cylinder_return','source_id'=>$saleId,'source_line_id'=>$lineId,'cylinder_unit_id'=>$id,'created_by'=>$userId,'notes'=>$gas>0?'Partially filled cylinder returned':'Empty cylinder returned']);
                if($gas>0)$this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$gas,'direction'=>'in','movement_at'=>$transactionAt,'source_type'=>'cylinder_return','source_id'=>$saleId,'source_line_id'=>$lineId,'cylinder_unit_id'=>$id,'created_by'=>$userId,'notes'=>'Returned gas added to shop stock']);
            }

            if($refund>0){
                // Refund payment is explicitly classified as a deposit refund.
                $this->insertSalePayments($saleId,$payments,$transactionAt,$userId,'security_deposit_refund');
                $cash=$this->paymentCash($payments);
                if($cash>0){
                    $s=$this->cash->openSessionForLocation($locationId);if(!$s)throw new RuntimeException('Open the counter cash session before refunding a security deposit.');
                    $this->cash->postGeneric((int)$s['id'],'security_deposit_refund','out',$cash,'sale',$saleId,$userId,'Security deposit refund',$transactionAt);
                }
            }
            if(!$this->db->transStatus())throw new RuntimeException('Cylinder return failed.');
            $this->db->transCommit();$this->releaseInventoryLocks();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>0,'customer_id'=>$customerId,'credit_amount'=>0];
        }catch(\Throwable $e){$this->releaseInventoryLocks();$this->db->transRollback();throw $e;}
    }
}
