<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

/**
 * Security Deposit / Issue Cylinder posting (transaction_type security_deposit).
 * Part of SalesService (Phase 10.7 split); moved unchanged, uses SalesService state and helpers.
 */
trait PostsSecurityDeposits
{
    protected function postSecurityDepositHeader(array $payload,int $userId,int $locationId,int $customerId,string $transactionAt,?string $notes,array $payments,array $unitIds,float $deposit): array
    {
        $lines=is_array($payload['lines']??null)?$payload['lines']:[];
        if(!$unitIds && !$lines) throw new RuntimeException('Select at least one cylinder to place on customer custody.');
        if($deposit<0) throw new RuntimeException('Security Deposit Amount cannot be negative.');

        $shopSettings=(new ShopSettingsModel())->forLocation($locationId);
        $includeDepositOs=(int)($shopSettings['include_security_deposit_in_os']??0)===1;
        $selectedIds=[];
        foreach($lines as $line) foreach((array)($line['selected_cylinder_unit_ids']??[]) as $id) $selectedIds[]=(int)$id;
        $selectedIds=array_values(array_unique(array_filter($selectedIds,static fn($id)=>$id>0)));
        if(!$selectedIds)$selectedIds=$unitIds;
        if(!$selectedIds)throw new RuntimeException('Select at least one cylinder to place on customer custody.');

        $rows=$this->db->table('cylinder_units cu')
            ->select('cu.*,ct.name cylinder_name,ct.code cylinder_code,ct.capacity_kg,ct.empty_cylinder_price')
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->where('cu.location_id',$locationId)->whereIn('cu.id',$selectedIds)
            ->whereIn('cu.status',['filled','empty'])->orderBy('cu.id')->get()->getResultArray();
        if(count($rows)!==count($selectedIds))throw new RuntimeException('One or more selected cylinders are no longer available for custody.');

        $lineByUnit=[];$seenCombos=[];
        foreach($lines as $i=>$line){
            $status=(string)($line['cylinder_status']??'');$typeId=(int)($line['cylinder_type_id']??0);
            $ids=array_values(array_unique(array_map('intval',(array)($line['selected_cylinder_unit_ids']??[]))));
            if(!$typeId||!in_array($status,['filled','empty'],true)||!$ids)throw new RuntimeException('Issue line '.($i+1).' is incomplete.');
            $combo=$typeId.'|'.$status;
            if(isset($seenCombos[$combo]))throw new RuntimeException('The same Cylinder Type + Filled/Empty combination cannot be used twice in one issue transaction.');
            $seenCombos[$combo]=true;
            foreach($ids as $id){
                if(isset($lineByUnit[$id]))throw new RuntimeException('Physical cylinder '.$id.' is selected more than once.');
                $lineByUnit[$id]=['type_id'=>$typeId,'status'=>$status,'gas_rate'=>max(0,(float)($line['gas_rate']??0)),'cylinder_rate'=>max(0,(float)($line['cylinder_rate']??0))];
            }
        }
        foreach($rows as $row){
            $id=(int)$row['id'];
            if(!isset($lineByUnit[$id]))throw new RuntimeException('Selected cylinder '.$row['unit_code'].' is missing from an issue line.');
            if((int)$row['cylinder_type_id']!==$lineByUnit[$id]['type_id'])throw new RuntimeException('Selected cylinder '.$row['unit_code'].' does not match its issue line.');
            if($lineByUnit[$id]['status']==='filled' && $row['status']!=='filled')throw new RuntimeException('Selected cylinder '.$row['unit_code'].' is not filled/partially filled.');
            if($lineByUnit[$id]['status']==='empty' && $row['status']!=='empty')throw new RuntimeException('Selected cylinder '.$row['unit_code'].' is not empty.');
        }

        $prepared=[];$subtotal=0;$totalKg=0;$customRate=false;
        foreach($rows as $row){
            $cfg=$lineByUnit[(int)$row['id']];
            // Security Deposit / Issue Cylinder allows the cashier to use the gas rate
            // already supplied on the issue line. Only require an effective configured rate
            // when no usable rate was supplied by the POS. This keeps the custody workflow
            // independent from the normal gas-sale rate validation while preserving the
            // configured rate as the default.
            $stdGas=$cfg['status']==='filled'?$this->rates->currentKgRate($transactionAt,$locationId):0;
            $enteredGasRate=$cfg['status']==='filled'?(float)$cfg['gas_rate']:0;
            if($cfg['status']==='filled'&&$enteredGasRate<=0&&$stdGas===null){
                throw new RuntimeException('No effective gas/kg rate exists.');
            }
            $stdCyl=$this->rates->currentCylinderRate((int)$row['cylinder_type_id'],$transactionAt,$locationId);
            $stdCyl=($stdCyl!==null&&$stdCyl>0)?$stdCyl:(float)$row['empty_cylinder_price'];
            $gasRate=$enteredGasRate>0?$enteredGasRate:($stdGas??0);
            $cylRate=$cfg['cylinder_rate']>0?$cfg['cylinder_rate']:$stdCyl;
            $gas=(float)$row['gas_weight_kg'];
            $lineTotal=$this->rounding($locationId)->line($cfg['status']==='filled'?($gas*$gasRate+$cylRate):$cylRate);
            $customRate=$customRate||($cfg['status']==='filled'&&abs($gasRate-(float)$stdGas)>0.00001)||abs($cylRate-$stdCyl)>0.00001;
            $prepared[]=['row'=>$row,'status'=>$cfg['status'],'gas'=>$gas,'gas_rate'=>$gasRate,'cyl_rate'=>$cylRate,'line_total'=>$lineTotal,'std_gas'=>$stdGas,'std_cyl'=>$stdCyl];
            $subtotal+=$lineTotal;$totalKg+=$gas;
        }
        $chargeTotal=round($subtotal,2);

        // Combined collection is recorded as separate Sale and Security Deposit payment rows.
        // The cashier may enter one total payment; the shop setting controls how that amount is allocated.
        $shopSettings=(new ShopSettingsModel())->forLocation($locationId);
        $depositRequired=$deposit;
        $allocationRule=(string)($shopSettings['deposit_payment_allocation_rule']??'deposit_first');
        if(!in_array($allocationRule,['gas_first','deposit_first','manual'],true))$allocationRule='deposit_first';
        $depositPayments=[];$salePayments=[];
        $combinedPayments=[];
        foreach($payments as $p){
            $type=(string)($p['payment_type']??'');
            $mode=(string)($p['payment_mode']??'');$amount=(float)($p['amount']??0);
            if($type==='combined'){
                if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<0)throw new RuntimeException('Invalid combined payment.');
                // A blank/zero payment row means no money was received. It must not block
                // a Security Deposit / Issue Cylinder transaction when the gas/cylinder
                // amount is posted to customer credit within the normal credit rules.
                if($amount<=0)continue;
                $combinedPayments[]=['payment_mode'=>$mode,'amount'=>$amount,'reference_no'=>$p['reference_no']??null];
                continue;
            }
            if(!in_array($type,['security_deposit','sale'],true))throw new RuntimeException('Every Security Deposit payment must be classified as Security Deposit or Gas / Cylinder.');
            if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<0)throw new RuntimeException('Invalid payment.');
            if($type==='security_deposit'){
                if($mode==='credit' && $amount>0)throw new RuntimeException('Security Deposit cannot be received on credit.');
                if($amount>0)$depositPayments[]=['payment_mode'=>$mode,'amount'=>$amount,'reference_no'=>$p['reference_no']??null];
            }else{
                $salePayments[]=['payment_mode'=>$mode,'amount'=>$amount,'reference_no'=>$p['reference_no']??null];
            }
        }
        // Balance, payment-allocation and credit-limit checks run under the customer row lock
        // so concurrent postings for the same customer cannot both pass the credit check.
        $this->db->transBegin();
        try{
            $lockedCustomer=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
            if(!$lockedCustomer||!(int)$lockedCustomer['is_active'])throw new RuntimeException('Customer is unavailable.');
            $gasPreviousOs=$this->customerGasBalance($customerId);
            $gasDue=max(0,round($chargeTotal+$gasPreviousOs,2));
            $combinedReceived=array_sum(array_map(static fn($p)=>(float)$p['amount'],$combinedPayments));
            if($combinedPayments && $combinedReceived>$gasDue+$depositRequired+0.01){
                throw new RuntimeException('Payment exceeds the combined Gas / Cylinder balance and Security Deposit amount.');
            }
            if($combinedPayments){
                if($allocationRule==='manual')throw new RuntimeException('Manual payment allocation is enabled. Enter the Gas / Cylinder and Security Deposit allocations separately.');
                foreach($combinedPayments as $p){
                    $remaining=(float)$p['amount'];
                    if($allocationRule==='gas_first'){
                        $toGas=min($remaining,$gasDue); if($toGas>0){$salePayments[]=['payment_mode'=>$p['payment_mode'],'amount'=>$toGas,'reference_no'=>$p['reference_no']??null];$gasDue-=$toGas;$remaining-=$toGas;}
                        $toDeposit=min($remaining,$deposit); if($toDeposit>0){$depositPayments[]=['payment_mode'=>$p['payment_mode'],'amount'=>$toDeposit,'reference_no'=>$p['reference_no']??null];$deposit-=$toDeposit;$remaining-=$toDeposit;}
                    }else{
                        $toDeposit=min($remaining,$deposit); if($toDeposit>0){if($p['payment_mode']==='credit')throw new RuntimeException('Security Deposit cannot be received on credit.');$depositPayments[]=['payment_mode'=>$p['payment_mode'],'amount'=>$toDeposit,'reference_no'=>$p['reference_no']??null];$deposit-=$toDeposit;$remaining-=$toDeposit;}
                        $toGas=min($remaining,$gasDue); if($toGas>0){$salePayments[]=['payment_mode'=>$p['payment_mode'],'amount'=>$toGas,'reference_no'=>$p['reference_no']??null];$gasDue-=$toGas;$remaining-=$toGas;}
                    }
                    if($remaining>0.01)throw new RuntimeException('Payment exceeds the combined amount due.');
                }
            }
            // Security deposit receipt is independent of the amount entered for the issue transaction.
            // Accept any non-negative receipt amount; only actual receipts increase the refundable balance.
            $depositPaid=array_sum(array_map(static fn($p)=>(float)$p['amount'],$depositPayments));
            if($chargeTotal>0.00001){
                if(!$salePayments)$salePayments=[['payment_mode'=>'credit','amount'=>0,'reference_no'=>null]];
                $paymentPlan=$this->prepareSalePaymentPlan($salePayments,$customerId,$chargeTotal);
            }else{
                if($salePayments)throw new RuntimeException('No gas/cylinder amount is payable; sale payment rows are not allowed.');
                $paymentPlan=['sale_payments'=>[],'settlements'=>[],'credit_amount'=>0,'previous_os'=>$gasPreviousOs,'payment_total'=>0,'net_receivable'=>$gasPreviousOs,'remaining_os'=>$gasPreviousOs];
            }
            if($allocationRule==='manual'){
                // Manual mode is represented by explicit payment_type rows from the POS.
                if($combinedPayments)throw new RuntimeException('Manual allocation requires separate payment rows.');
            }
            $this->acquireInventoryLocks($locationId,array_map(static fn($x)=>['type'=>$x['status']==='filled'?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>(int)$x['row']['cylinder_type_id']],$prepared));
            foreach($selectedIds as $id){
                $locked=$this->db->query("SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? AND cu.location_id=? FOR UPDATE",[$id,$locationId])->getRowArray();
                if(!$locked||!in_array($locked['status'],['filled','empty'],true))throw new RuntimeException('One or more selected cylinders changed availability. Refresh and retry.');
            }

            $gasOsAfter=$paymentPlan['remaining_os'];
            $depositBalanceBefore=$this->securityDepositBalance($locationId,$customerId);
            $depositBalanceAfter=$depositBalanceBefore+$depositPaid;
            $overallOsAfter=$includeDepositOs?($gasOsAfter+$depositBalanceAfter):$gasOsAfter;

            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $this->db->table('sales')->insert([
                'sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>'security_deposit','status'=>'posted',
                'transaction_at'=>$transactionAt,'total_kg'=>$totalKg,'subtotal'=>$chargeTotal,'discount_amount'=>0,'total_amount'=>$chargeTotal,
                'security_deposit_amount'=>$depositRequired,'security_deposit_refund_amount'=>0,'credit_amount'=>$paymentPlan['credit_amount'],
                'previous_os_balance'=>$paymentPlan['previous_os'],'receipt_amount'=>$paymentPlan['payment_total'],
                'net_receivable_amount'=>$paymentPlan['net_receivable'],'os_balance'=>$overallOsAfter,'return_gas_ledger_amount'=>0,
                'custom_rate_flag'=>$customRate?1:0,'notes'=>$notes,'created_by'=>$userId
            ]);
            $saleId=(int)$this->db->insertID();

            $depositPerUnit=$depositRequired>0?round($depositRequired/count($prepared),2):0;$assigned=0;
            foreach($prepared as $idx=>$item){
                $row=$item['row'];$unitId=(int)$row['id'];$share=($idx===count($prepared)-1)?round($depositRequired-$assigned,2):$depositPerUnit;$assigned+=$share;
                $this->cylinders->moveToCustody($unitId,$customerId,$saleId,$userId);
                $custody=$this->db->table('cylinder_custody')->where(['cylinder_unit_id'=>$unitId,'status'=>'issued'])->orderBy('id','DESC')->get()->getRowArray();
                if(!$custody)throw new RuntimeException('Custody record could not be created for '.$row['unit_code'].'.');
                $this->db->table('cylinder_custody')->where('id',$custody['id'])->update([
                    'deposit_amount'=>$share,'issued_gas_weight_kg'=>$item['gas'],'returned_gas_weight_kg'=>0,'consumed_gas_weight_kg'=>0,
                    'issued_gas_rate'=>$item['gas_rate'],'return_gas_rate'=>0,'issued_condition'=>$item['status']
                ]);
                // Deposit ledger is posted from actual received payments, not custody/deposit amount.

                $this->db->table('sale_items')->insert([
                    'sale_id'=>$saleId,'line_no'=>$idx+1,'line_type'=>$item['status']==='filled'?'filled_cylinder':'empty_cylinder',
                    'cylinder_type_id'=>$row['cylinder_type_id'],'customer_cylinder_unit_id'=>$unitId,'quantity'=>1,'gas_weight_kg'=>$item['gas'],
                    'applied_rate'=>$item['status']==='filled'?($item['gas_rate']+$item['cyl_rate']):$item['cyl_rate'],
                    'gas_rate'=>$item['gas_rate'],'cylinder_price'=>$item['cyl_rate'],'standard_rate'=>$item['status']==='filled'?$item['std_gas']:$item['std_cyl'],
                    'custom_rate_flag'=>0,'empty_cylinder_received'=>0,'line_discount'=>0,'line_total'=>$item['line_total'],'notes'=>'Custody issued: '.$row['unit_code']
                ]);
                $lineId=(int)$this->db->insertID();
                $this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>$item['status']==='filled'?'filled_cylinder':'empty_cylinder','cylinder_type_id'=>$row['cylinder_type_id'],'quantity'=>1,'direction'=>'out','movement_at'=>$transactionAt,'source_type'=>'security_deposit','source_id'=>$saleId,'source_line_id'=>$lineId,'cylinder_unit_id'=>$unitId,'created_by'=>$userId,'notes'=>'Cylinder placed on customer custody']);
                if($item['status']==='filled'&&$item['gas']>0)$this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$item['gas'],'direction'=>'out','movement_at'=>$transactionAt,'source_type'=>'security_deposit','source_id'=>$saleId,'source_line_id'=>$lineId,'cylinder_unit_id'=>$unitId,'created_by'=>$userId,'notes'=>'Gas issued with custody cylinder']);
            }

            // Deposit receipt and gas/cylinder payments have distinct payment classifications.
            if($depositPaid>0)$this->db->table('customer_security_deposits')->insert(['location_id'=>$locationId,'customer_id'=>$customerId,'entry_type'=>'hold','amount'=>$depositPaid,'sale_id'=>$saleId,'custody_id'=>null,'transaction_at'=>$transactionAt,'created_by'=>$userId,'notes'=>'Security deposit actually received']);
            $this->insertSalePayments($saleId,$depositPayments,$transactionAt,$userId,'security_deposit');
            $this->insertSalePayments($saleId,$paymentPlan['sale_payments'],$transactionAt,$userId,'sale');

            $depositCash=$this->paymentCash($depositPayments);
            if($depositCash>0){
                $s=$this->cash->openSessionForLocation($locationId);if(!$s)throw new RuntimeException('Open the counter cash session before receiving a security deposit.');
                $this->cash->postGeneric((int)$s['id'],'security_deposit','in',$depositCash,'sale',$saleId,$userId,'Security deposit received',$transactionAt);
            }
            $saleCash=$this->paymentCash($paymentPlan['sale_payments']);
            if($saleCash>0){
                $s=$this->cash->openSessionForLocation($locationId);if(!$s)throw new RuntimeException('Open the counter cash session before receiving the gas/cylinder amount.');
                $this->cash->postGeneric((int)$s['id'],'sale_cash','in',$saleCash,'sale',$saleId,$userId,'Gas/cylinder amount received with custody issue',$transactionAt);
            }
            $this->postCustomerSettlement($paymentPlan['settlements'],$locationId,$saleId,$customerId,$transactionAt,$userId);

            if(!$this->db->transStatus())throw new RuntimeException('Security deposit / cylinder issue failed.');
            $this->db->transCommit();$this->releaseInventoryLocks();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>$chargeTotal,'customer_id'=>$customerId,'credit_amount'=>$paymentPlan['credit_amount']];
        }catch(\Throwable $e){$this->releaseInventoryLocks();$this->db->transRollback();throw $e;}
    }
}
