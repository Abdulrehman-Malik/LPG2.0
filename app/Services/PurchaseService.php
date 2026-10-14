<?php
namespace App\Services;
use Config\Database;
use RuntimeException;
class PurchaseService{
 protected $db; protected $inv; protected $cash; protected $cylinders;
 public function __construct(){ $this->db=Database::connect(); $this->inv=new InventoryService(); $this->cash=new CashService(); $this->cylinders=new CylinderUnitService(); }
 public function post(array $p,int $userId,int $locationId):array{
  $supplierId=(int)($p['supplier_id']??0); $lines=$p['lines']??[]; $payments=$p['payments']??[];
  if(!$supplierId||!$lines) throw new RuntimeException('Supplier and purchase lines are required.');
  $supplier=$this->db->table('suppliers')->where('id',$supplierId)->get()->getRowArray();
  if(!$supplier||(int)$supplier['is_active']!==1) throw new RuntimeException('Supplier is invalid or inactive.');
  $subtotal=0;$prepared=[];$inventory=[];$cash=0;$credit=0;
  foreach($lines as $i=>$l){
   $type=(string)($l['line_type']??'');$qty=(float)($l['quantity']??0);$rate=(float)($l['unit_rate']??0);$ct=isset($l['cylinder_type_id'])&&$l['cylinder_type_id']!==''?(int)$l['cylinder_type_id']:null;
   if($qty<=0||$rate<0) throw new RuntimeException('Invalid purchase line '.($i+1).'.');
   if(!in_array($type,['gas_kg','filled_cylinder','empty_cylinder'],true)) throw new RuntimeException('Invalid purchase type.');
   if(!$ct) throw new RuntimeException('Cylinder type required.');

   $actualRaw=$l['actual_gas_weight_kg']??null;
   $actual=$actualRaw!==null && $actualRaw!==''?(float)$actualRaw:0;
   if($type==='filled_cylinder'){
    if(floor($qty)!==$qty) throw new RuntimeException('Filled cylinder quantity must be a whole number.');
    $ctRow=$this->db->table('cylinder_types')->where('id',$ct)->get()->getRowArray();
    if(!$ctRow || !(int)$ctRow['is_active']) throw new RuntimeException('Invalid or inactive cylinder type.');
    $cap=(float)$ctRow['capacity_kg'];
    $actual=$actualRaw!==null && $actualRaw!==''?$actual:$cap;
    if($actual<=0||$actual>$cap) throw new RuntimeException('Actual gas weight must be greater than zero and cannot exceed cylinder capacity.');
   } else {
    $actual=0;
   }

   $lineTotal=$qty*$rate;$subtotal+=$lineTotal;
   $prepared[]=['line_no'=>$i+1,'line_type'=>$type,'cylinder_type_id'=>$ct,'quantity'=>$qty,'actual_gas_weight_kg'=>$actual,'unit_rate'=>$rate,'line_total'=>$lineTotal];
   $inventory[]=['inventory_type'=>$type,'cylinder_type_id'=>$ct,'quantity'=>$qty,'actual_gas_weight_kg'=>$actual];
  }
  $discount=max(0,(float)($p['discount_amount']??0));if($discount>$subtotal)throw new RuntimeException('Discount exceeds subtotal.');
  $total=$subtotal-$discount;$paid=0;$normalizedPayments=[];
  foreach($payments as $pay){$mode=(string)($pay['payment_mode']??'');$amount=(float)($pay['amount']??0);if($amount<=0)continue;if(!in_array($mode,['cash','cheque','online'],true)||$amount<=0)throw new RuntimeException('Invalid purchase payment.');$paid+=$amount;$normalizedPayments[]=['payment_mode'=>$mode,'amount'=>$amount,'reference_no'=>trim((string)($pay['reference_no']??''))?:null];}
  $currentPayable=$total;$previousPayable=$this->supplierBalance($supplierId,$locationId);
  if($paid>$previousPayable+$currentPayable+0.01)throw new RuntimeException('Amount paid cannot exceed the supplier payable balance of Rs. '.number_format(max(0,$previousPayable+$currentPayable),2).'.');
  $currentPurchasePaid=min($paid,$currentPayable);$credit=max(0,$currentPayable-$currentPurchasePaid);$priorPayableSettlement=max(0,$paid-$currentPurchasePaid);
  $newPayable=max(0,$previousPayable-$priorPayableSettlement+$credit);
  if($newPayable>(float)$supplier['credit_limit']+0.01)throw new RuntimeException('Supplier credit limit exceeded. Available credit is Rs. '.number_format(max(0,(float)$supplier['credit_limit']-$previousPayable),2).'.');
  $this->db->transBegin();
  try{
   $supplier=$this->db->query('SELECT * FROM suppliers WHERE id=? FOR UPDATE',[$supplierId])->getRowArray();
   if($credit>0){$currentCredit=$this->supplierBalance($supplierId,$locationId);if($currentCredit+$credit>(float)$supplier['credit_limit'])throw new RuntimeException('Supplier credit limit exceeded.');}
   foreach($inventory as $m){if($this->inv->stock($locationId,$m['inventory_type'],$m['cylinder_type_id'])<0)throw new RuntimeException('Invalid inventory state.');}
   $no='P'.date('YmdHis').'-'.random_int(100,999);
   $this->db->table('purchases')->insert(['purchase_no'=>$no,'location_id'=>$locationId,'supplier_id'=>$supplierId,'status'=>'posted','transaction_at'=>date('Y-m-d H:i:s'),'subtotal'=>$subtotal,'discount_amount'=>$discount,'total_amount'=>$total,'credit_amount'=>$credit,'notes'=>trim((string)($p['notes']??''))?:null,'created_by'=>$userId]);$id=(int)$this->db->insertID();
   $purchaseLineIds=[];
   foreach($prepared as $row){
    $row['purchase_id']=$id;
    $this->db->table('purchase_items')->insert($row);
    $purchaseLineIds[(int)$row['line_no']]=(int)$this->db->insertID();
   }
   foreach($inventory as $index=>$m){
    $this->inv->receivePurchase(
      $locationId,
      $m['inventory_type'],
      $m['cylinder_type_id'],
      (float)$m['quantity'],
      (float)$m['actual_gas_weight_kg'],
      $id,
      $userId,
      $purchaseLineIds[$index+1] ?? null
    );
   }
   $remainingCurrent=$currentPayable;$cashPurchase=0;
   foreach($normalizedPayments as $pay){$applyCurrent=min((float)$pay['amount'],$remainingCurrent);$applyPrior=max(0,(float)$pay['amount']-$applyCurrent);
    if($applyCurrent>0){$this->db->table('purchase_payments')->insert(['purchase_id'=>$id,'payment_mode'=>$pay['payment_mode'],'amount'=>$applyCurrent,'reference_no'=>$pay['reference_no'],'payment_at'=>date('Y-m-d H:i:s'),'paid_by'=>$userId]);if($pay['payment_mode']==='cash')$cashPurchase+=$applyCurrent;}
    if($applyPrior>0){$paymentNo='SP'.date('YmdHis').'-'.random_int(100,999);$this->db->table('supplier_payments')->insert(['payment_no'=>$paymentNo,'location_id'=>$locationId,'supplier_id'=>$supplierId,'status'=>'posted','amount'=>$applyPrior,'payment_mode'=>$pay['payment_mode'],'payment_at'=>date('Y-m-d H:i:s'),'reference_no'=>$pay['reference_no'],'notes'=>'Applied to previous supplier payable during purchase '.$no,'created_by'=>$userId]);$supplierPaymentId=(int)$this->db->insertID();if($pay['payment_mode']==='cash'){ $session=$this->cash->openSessionForLocation($locationId);if(!$session)throw new RuntimeException('Open the counter cash session before posting a cash purchase.');$this->cash->postGeneric((int)$session['id'],'supplier_payment','out',$applyPrior,'supplier_payment',$supplierPaymentId,$userId,'Supplier payable settlement during purchase '.$no);}}
    $remainingCurrent=max(0,$remainingCurrent-(float)$pay['amount']);
   }
   if($cashPurchase>0){$session=$this->cash->openSessionForLocation($locationId);if(!$session)throw new RuntimeException('Open the counter cash session before posting a cash purchase.');$this->cash->postGeneric((int)$session['id'],'purchase_cash','out',$cashPurchase,'purchase',$id,$userId,'Purchase cash');}
   if(!$this->db->transStatus())throw new RuntimeException('Purchase posting failed.');
   $this->db->transCommit();AuditService::log('CREATE','purchase',$id,null,['purchase_no'=>$no,'total'=>$total,'amount_paid'=>$paid,'current_credit'=>$credit,'previous_payable'=>$previousPayable,'new_payable'=>$newPayable],$userId,$locationId);return ['id'=>$id,'purchase_no'=>$no,'total'=>$total,'previous_payable'=>$previousPayable,'amount_paid'=>$paid,'balance_payable'=>$newPayable];
  }catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
 public function supplierBalance(int $supplierId, ?int $locationId=null): float
 {
  $locationId=$locationId ?? (int)(session()->get('location_id') ?? 0);

  // credit_amount is already the unpaid portion of each posted purchase,
  // so purchase payments must NOT be subtracted again here.
  $s=$this->db->table('purchases')->selectSum('credit_amount','credit')
   ->where('supplier_id',$supplierId)->where('status','posted');
  if($locationId>0) $s->where('location_id',$locationId);
  $s=$s->get()->getRowArray();

  // These are only payments made against an older supplier payable.
  $ap=$this->db->table('supplier_payments')->selectSum('amount','account_paid')
   ->where('supplier_id',$supplierId)->where('status','posted');
  if($locationId>0) $ap->where('location_id',$locationId);
  $ap=$ap->get()->getRowArray();

  $supplier=$this->db->table('suppliers')->where('id',$supplierId)->get()->getRowArray();
  return (float)($supplier['opening_balance']??0)
      +(float)($s['credit']??0)
      -(float)($ap['account_paid']??0);
 }

 public function canVoidPurchase(int $userId,int $locationId): bool
 {
  if($userId<=0 || $locationId<=0) return false;

  try{
   $settings=$this->db->table('shop_settings')
    ->select('purchase_void_enabled')
    ->where('location_id',$locationId)
    ->get()->getRowArray();

   if(!$settings || (int)($settings['purchase_void_enabled']??0)!==1) return false;

   return (int)$this->db->table('shop_purchase_void_users')
    ->where('location_id',$locationId)
    ->where('user_id',$userId)
    ->countAllResults()>0;
  }catch(\Throwable $e){
   return false;
  }
 }

 public function void(int $purchaseId,int $userId,int $locationId,string $reason): string
 {
  if(!$this->canVoidPurchase($userId,$locationId)){
   throw new RuntimeException('You are not authorized to void purchases. Purchase Void must be enabled in Shop Settings and your user must be assigned to this function.');
  }

  $reason=trim($reason);
  if($reason==='') throw new RuntimeException('Void reason is required.');

  $this->db->transBegin();
  try{
   $purchase=$this->db->query(
    'SELECT * FROM purchases WHERE id=? AND location_id=? FOR UPDATE',
    [$purchaseId,$locationId]
   )->getRowArray();
   if(!$purchase) throw new RuntimeException('Purchase not found.');
   if($purchase['status']!=='posted') throw new RuntimeException('Only posted purchases can be voided.');

   $supplier=$this->db->query(
    'SELECT * FROM suppliers WHERE id=? FOR UPDATE',
    [(int)$purchase['supplier_id']]
   )->getRowArray();
   if(!$supplier) throw new RuntimeException('Purchase supplier not found.');

   $movements=$this->db->table('inventory_movements')
    ->where('source_type','purchase')->where('source_id',$purchaseId)
    ->orderBy('id','ASC')->get()->getResultArray();

   $physicalUnits=[];
   foreach($movements as $m){
    $unitId=(int)($m['cylinder_unit_id']??0);
    if($unitId>0) $physicalUnits[$unitId]=true;
   }

   $units=[];
   foreach(array_keys($physicalUnits) as $unitId){
    $unit=$this->db->query(
     'SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg
      FROM cylinder_units cu
      JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id
      WHERE cu.id=? AND cu.location_id=? FOR UPDATE',
     [$unitId,$locationId]
    )->getRowArray();
    if(!$unit) throw new RuntimeException('Purchased physical cylinder '.$unitId.' could not be found.');

    if((string)$unit['source_type']!=='purchase' || (int)$unit['source_id']!==$purchaseId){
     throw new RuntimeException('Physical cylinder '.$unit['unit_code'].' does not belong exclusively to purchase '.$purchase['purchase_no'].'.');
    }

    $laterMovement=(int)$this->db->table('inventory_movements')
     ->where('cylinder_unit_id',$unitId)
     ->groupStart()
      ->where('source_type !=','purchase')
      ->orWhere('source_id !=',$purchaseId)
     ->groupEnd()
     ->countAllResults();
    if($laterMovement>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' has already been used in another stock transaction.');
    }

    $custodyCount=(int)$this->db->table('cylinder_custody')
     ->where('cylinder_unit_id',$unitId)->countAllResults();
    if($custodyCount>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' has already entered customer custody.');
    }

    $saleReferenceCount=(int)$this->db->table('sale_items')
     ->where('customer_cylinder_unit_id',$unitId)->countAllResults();
    if($saleReferenceCount>0){
     throw new RuntimeException('Purchase '.$purchase['purchase_no'].' cannot be voided because physical cylinder '.$unit['unit_code'].' is already referenced by a sale.');
    }

    $units[$unitId]=$unit;
   }

   foreach($movements as $m){
    $unitId=(int)($m['cylinder_unit_id']??0);
    $note='Reversal of purchase '.$purchase['purchase_no'];
    if($unitId>0 && isset($units[$unitId])) $note.=' — physical cylinder '.$units[$unitId]['unit_code'];
    $reverse=$m['direction']==='in'?'out':'in';

    $this->db->table('inventory_movements')->insert([
     'location_id'=>$m['location_id'],'inventory_type'=>$m['inventory_type'],
     'cylinder_type_id'=>$m['cylinder_type_id'],'quantity'=>$m['quantity'],
     'direction'=>$reverse,'movement_at'=>date('Y-m-d H:i:s'),
     'source_type'=>'purchase_void','source_id'=>$purchaseId,
     'source_line_id'=>$m['source_line_id']??null,
     'cylinder_unit_id'=>$unitId>0?$unitId:null,'created_by'=>$userId,'notes'=>$note
    ]);
   }

   $cashRows=$this->db->table('cash_transactions')
    ->where([
     'reference_type'=>'purchase',
     'reference_id'=>$purchaseId,
     'transaction_type'=>'purchase_cash',
     'direction'=>'out'
    ])->get()->getResultArray();

   foreach($cashRows as $cash){
    $already=(int)$this->db->table('cash_transactions')
     ->where([
      'reference_type'=>'purchase_void',
      'reference_id'=>$purchaseId,
      'transaction_type'=>'purchase_cash',
      'direction'=>'in',
      'cash_session_id'=>$cash['cash_session_id']
     ])->countAllResults();
    if($already>0) continue;

    $this->db->table('cash_transactions')->insert([
     'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'purchase_cash','direction'=>'in',
     'amount'=>$cash['amount'],'transaction_at'=>date('Y-m-d H:i:s'),
     'reference_type'=>'purchase_void','reference_id'=>$purchaseId,'created_by'=>$userId,
     'notes'=>'Cash reversal of purchase '.$purchase['purchase_no']
    ]);
   }

   $settlementNote='Applied to previous supplier payable during purchase '.$purchase['purchase_no'];
   $settlements=$this->db->table('supplier_payments')
    ->where([
     'location_id'=>$locationId,
     'supplier_id'=>(int)$purchase['supplier_id'],
     'status'=>'posted',
     'notes'=>$settlementNote
    ])->get()->getResultArray();

   foreach($settlements as $settlement){
    $settlementId=(int)$settlement['id'];
    $this->db->table('supplier_payments')->where('id',$settlementId)->update(['status'=>'voided']);

    if((string)$settlement['payment_mode']==='cash'){
     $cashBuilder=$this->db->table('cash_transactions')
      ->where('transaction_type','supplier_payment')
      ->where('direction','out')
      ->where('reference_type','supplier_payment')
      ->groupStart()
       ->where('reference_id',$settlementId)
       ->orGroupStart()
        ->where('reference_id',0)
        ->where('notes',$settlementNote)
       ->groupEnd()
      ->groupEnd();
     $settlementCashRows=$cashBuilder->get()->getResultArray();

     foreach($settlementCashRows as $cash){
      $already=(int)$this->db->table('cash_transactions')
       ->where([
        'reference_type'=>'purchase_void','reference_id'=>$purchaseId,
        'transaction_type'=>'supplier_payment','direction'=>'in',
        'cash_session_id'=>$cash['cash_session_id']
       ])->where('amount',$cash['amount'])->countAllResults();
      if($already>0) continue;

      $this->db->table('cash_transactions')->insert([
       'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'supplier_payment','direction'=>'in',
       'amount'=>$cash['amount'],'transaction_at'=>date('Y-m-d H:i:s'),
       'reference_type'=>'purchase_void','reference_id'=>$purchaseId,'created_by'=>$userId,
       'notes'=>'Cash reversal of supplier payment '.$settlement['payment_no'].' from voided purchase '.$purchase['purchase_no']
      ]);
     }
    }
   }

   $this->db->table('purchases')->where('id',$purchaseId)->update([
    'status'=>'voided','voided_by'=>$userId,'voided_at'=>date('Y-m-d H:i:s'),'void_reason'=>$reason
   ]);

   // A purchased physical cylinder did not exist before this purchase.
   // Keep its record for audit/history, but remove it from current stock exactly as
   // a sold physical cylinder. This also avoids breaking historical references.
   foreach($units as $unitId=>$unit){
    $this->db->table('cylinder_units')->where('id',$unitId)->update([
     'status'=>'sold',
     'custody_customer_id'=>null,
     'gas_weight_kg'=>0
    ]);
   }

   if(!$this->db->transStatus()) throw new RuntimeException('Purchase void failed.');
   $this->db->transCommit();

   AuditService::log('VOID','purchase',$purchaseId,
    ['purchase_no'=>$purchase['purchase_no'],'status'=>'posted'],
    ['purchase_no'=>$purchase['purchase_no'],'status'=>'voided','void_reason'=>$reason],
    $userId,$locationId
   );

   return $purchase['purchase_no'];
  }catch(\Throwable $e){
   $this->db->transRollback();
   throw $e;
  }
 }

}
