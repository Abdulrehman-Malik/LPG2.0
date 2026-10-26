<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

/**
 * POS posting. Each header transaction type's posting lives in its own trait (Phase 10.7):
 * PostsGasSales, PostsCylinderSales, PostsSecurityDeposits, PostsCylinderReturns. This class keeps
 * the dispatcher, the customer-order line path, payment planning, credit checks, voids and locks.
 */
class SalesService
{
    use PostsGasSales;
    use PostsCylinderSales;
    use PostsSecurityDeposits;
    use PostsCylinderReturns;

    protected $db;
    protected $types;
    protected $customers;
    protected $rates;
    protected $cash;
    protected $cylinders;
    protected array $inventoryLocks = [];
    protected array $creditLocks = [];
    protected ?int $currentLocationId = null;
    protected bool $orderFulfillmentContext = false;
    protected ?int $currentOrderId = null;
    protected ?float $currentOrderExpectedTotal = null;

    public function __construct()
    {
        // post()/void() set the posting branch explicitly; this is only the request's default.
        $this->currentLocationId=\App\Libraries\TenantContext::branchId() ?? 0;
        $this->db=Database::connect();
        $this->types=new CylinderTypeModel();
        $this->customers=new CustomerModel();
        $this->rates=new GasRateModel();
        $this->cash=new CashService();
        $this->cylinders=new CylinderUnitService();
    }

    private ?RateService $rateService = null;

    /** Rate rules: customer -> branch -> global, per-cylinder pricing (requirements.md, Rates). */
    protected function rateService(): RateService
    {
        return $this->rateService ??= new RateService($this->db);
    }

    /** @var array<int,MoneyRounding> */
    private array $roundingByShop = [];

    /** The business money-rounding rule for a branch (owner decision D10-2). */
    protected function rounding(int $locationId): MoneyRounding
    {
        return $this->roundingByShop[$locationId] ??= MoneyRounding::forShop($locationId);
    }

    protected function nextSaleNo(string $transactionAt,int $locationId): string
    {
        return (new DocumentNumberService($this->db))->next('sale',$locationId,$transactionAt);
    }

    /**
     * A previously posted sale may only be replayed or recovered when all financial and ownership
     * fields still agree with the approved customer-order snapshot.
     */
    protected function assertCustomerOrderSaleMatches(array $order, array $sale, int $orderId, int $locationId): void
    {
        if ((string) ($sale['status'] ?? '') !== 'posted'
            || (int) ($sale['customer_order_id'] ?? 0) !== $orderId
            || (int) ($sale['location_id'] ?? 0) !== $locationId
            || (int) ($sale['customer_id'] ?? 0) !== (int) $order['customer_id']
            || abs((float) ($sale['subtotal'] ?? 0) - (float) $order['subtotal']) > 0.009
            || abs((float) ($sale['discount_amount'] ?? 0) - (float) $order['discount_amount']) > 0.009
            || abs((float) ($sale['delivery_fee_amount'] ?? 0) - (float) $order['delivery_fee']) > 0.009
            || abs((float) ($sale['total_amount'] ?? 0) - (float) $order['total_amount']) > 0.009) {
            throw new RuntimeException('Customer order POS sale link is inconsistent with the approved order totals; manual review is required.');
        }
    }

    /**
     * Internal service entry point for validated customer-order fulfillment.
     * Delivery fees are accepted only through this path, never from a normal POS request.
     */
    public function postForCustomerOrder(array $payload,int $userId,int $locationId,int $orderId,float $deliveryFee): array
    {
        \App\Libraries\TenantContext::ensureBranch($locationId);
        if ($orderId <= 0 || $deliveryFee < 0) {
            throw new RuntimeException('Invalid customer-order fulfillment context.');
        }

        $order = $this->db->table('customer_orders')
            ->where(['id' => $orderId, 'location_id' => $locationId])
            ->get()->getRowArray();
        if (!$order) {
            throw new RuntimeException('Customer order was not found in this shop.');
        }
        if ((int) $order['price_reconfirmation_required'] === 1) {
            throw new RuntimeException('Customer price reconfirmation is required before fulfillment.');
        }
        if (abs((float) $order['delivery_fee'] - $deliveryFee) > 0.009) {
            throw new RuntimeException('ORDER_PRICE_RECONFIRMATION_REQUIRED: the saved delivery fee changed before posting.');
        }
        if (!empty($payload['customer_id']) && (int) $payload['customer_id'] !== (int) $order['customer_id']) {
            throw new RuntimeException('Sale customer does not match the customer order.');
        }
        $payload['customer_id'] = (int) $order['customer_id'];

        $existing = $this->db->table('sales')->where('customer_order_id', $orderId)->get()->getRowArray();
        if ($existing) {
            $this->assertCustomerOrderSaleMatches($order, $existing, $orderId, $locationId);
            if (!empty($order['pos_sale_id']) && (int) $order['pos_sale_id'] !== (int) $existing['id']) {
                throw new RuntimeException('Customer order is linked to a different POS sale.');
            }
            if ((int) ($order['pos_sale_id'] ?? 0) === 0 || (string) $order['status'] !== 'completed') {
                $this->db->transBegin();
                try {
                    $lockedOrder = $this->db->query(
                        'SELECT * FROM customer_orders WHERE id=? AND location_id=? LIMIT 1 FOR UPDATE',
                        [$orderId, $locationId]
                    )->getRowArray();
                    if (!$lockedOrder) throw new RuntimeException('Customer order was not found in this shop.');
                    $this->assertCustomerOrderSaleMatches($lockedOrder, $existing, $orderId, $locationId);
                    if (!empty($lockedOrder['pos_sale_id']) && (int) $lockedOrder['pos_sale_id'] !== (int) $existing['id']) {
                        throw new RuntimeException('Customer order is linked to a different POS sale.');
                    }
                    if (!in_array((string) $lockedOrder['status'], ['accepted', 'preparing', 'ready', 'out_for_delivery', 'completed'], true)) {
                        throw new RuntimeException('A posted sale exists for an order that is no longer fulfillable; manual review is required.');
                    }
                    if ((string) $lockedOrder['status'] !== 'completed' || empty($lockedOrder['pos_sale_id'])) {
                        $this->db->table('customer_orders')->where('id', $orderId)->update([
                            'pos_sale_id' => (int) $existing['id'],
                            'status' => 'completed',
                            'completed_by' => $userId,
                            'completed_at' => $lockedOrder['completed_at'] ?: date('Y-m-d H:i:s'),
                        ]);
                        $this->db->table('customer_order_status_history')->insert([
                            'order_id' => $orderId,
                            'business_id' => (int) $lockedOrder['business_id'],
                            'from_status' => (string) $lockedOrder['status'],
                            'to_status' => 'completed',
                            'actor_type' => 'staff',
                            'customer_app_account_id' => null,
                            'user_id' => $userId,
                            'reason' => 'Recovered existing POS sale ' . (string) $existing['sale_no'] . ' during an idempotent fulfillment retry.',
                            'request_id' => null,
                        ]);
                    }
                    if (!$this->db->transStatus()) throw new RuntimeException('Existing POS sale link could not be recovered.');
                    $this->db->transCommit();
                } catch (\Throwable $e) {
                    $this->db->transRollback();
                    throw $e;
                }
            }
            return [
                'id' => (int) $existing['id'],
                'sale_no' => (string) $existing['sale_no'],
                'total' => (float) $existing['total_amount'],
                'customer_id' => (int) $existing['customer_id'],
                'credit_amount' => (float) $existing['credit_amount'],
                'idempotent_replay' => true,
            ];
        }

        if (!in_array((string) $order['status'], ['accepted', 'preparing', 'ready', 'out_for_delivery'], true)) {
            throw new RuntimeException('Only an accepted order can be fulfilled.');
        }
        if (!empty($order['pos_sale_id'])) {
            throw new RuntimeException('Customer order has a POS sale link but the sale idempotency record is missing; manual review is required.');
        }

        $previous = $this->orderFulfillmentContext;
        $previousOrderId = $this->currentOrderId;
        $previousExpectedTotal = $this->currentOrderExpectedTotal;
        $this->orderFulfillmentContext = true;
        $this->currentOrderId = $orderId;
        $this->currentOrderExpectedTotal = (float) $order['total_amount'];
        $payload['order_id'] = $orderId;
        $payload['transaction_type'] = 'customer_order';
        $payload['delivery_fee_amount'] = round((float) $order['delivery_fee'], 2);
        try {
            return $this->post($payload, $userId, $locationId);
        } finally {
            $this->orderFulfillmentContext = $previous;
            $this->currentOrderId = $previousOrderId;
            $this->currentOrderExpectedTotal = $previousExpectedTotal;
        }
    }

    public function post(array $payload,int $userId,int $locationId): array
    {
        \App\Libraries\TenantContext::ensureBranch($locationId);
        // Balance, credit-limit and settings lookups must use the posting shop, not the
        // browser session: API fulfilment has no session and staff may switch shops.
        $this->currentLocationId=$locationId;
        // Business back-dating rule (D10-1) is enforced for every POS transaction type.
        $payload['transaction_at']=(new TransactionDatePolicy())->resolve(isset($payload['transaction_at'])?(string)$payload['transaction_at']:null,$locationId);
        $customerId=!empty($payload['customer_id'])?(int)$payload['customer_id']:null;
        // BL-031: a blocked customer stays in lists and history but takes no new transactions.
        if($customerId && (int)($this->db->table('customers')->select('is_blocked')->where(['id'=>$customerId,'location_id'=>$locationId])->get()->getRow('is_blocked')??0)===1){
            throw new RuntimeException('This customer is blocked. Unblock the customer on the Customers screen before posting a new transaction.');
        }
        $headerType=(string)($payload['transaction_type']??'');
        if(in_array($headerType,['gas_sale','cylinder_sale','security_deposit','cylinder_return'],true)){
            return $this->postHeaderTransaction($payload,$userId,$locationId,$headerType);
        }
        // The line-mode path below is kept only for customer-order fulfilment, where one order
        // (possibly gas + filled + empty cylinders) must become exactly one sale. POS and any other
        // caller must use one of the four header transaction types (Phase 10.6).
        if(!$this->orderFulfillmentContext){
            throw new RuntimeException('Unsupported transaction type. Choose Gas Sale, Cylinder Sale, Security Deposit or Cylinder Return.');
        }

        $lines=is_array($payload['lines']??null)?$payload['lines']:[];
        $payments=is_array($payload['payments']??null)?$payload['payments']:[];
        if(!$lines) throw new RuntimeException('At least one sale line is required.');
        if(!$payments) throw new RuntimeException('At least one payment is required.');
        // Reject walk-in credit immediately so the cashier receives the business-rule error
        // before unrelated line validation can mask the reason the sale is not allowed.
        if(!$customerId){
            foreach($payments as $p){
                if((string)($p['payment_mode']??'')==='credit') throw new RuntimeException('Credit sale is not allowed for Walk-in / Cash customer. Select an actual customer and enable Allow Credit Sale.');
            }
        }

        $transactionAt=str_replace('T',' ',trim((string)($payload['transaction_at']??date('Y-m-d H:i:s'))));
        $notes=trim((string)($payload['notes']??''))?:null;
        $customer=$customerId?$this->customers->findForLocation($customerId,$locationId):null;
        if($customerId && !$customer) throw new RuntimeException('Customer not found.');
        if($customerId && !(int)$customer['is_active']) throw new RuntimeException('Customer is inactive.');

        $prepared=[];$subtotal=0;$totalKg=0;$customRate=false;$gasRequirements=[];
        foreach($lines as $i=>$line){
            $mode=(string)($line['sale_mode']??'');
            $allowed=['sell_gas_only','replace_same','sell_filled','replace_different','sell_empty'];
            if(!in_array($mode,$allowed,true)) throw new RuntimeException('Unsupported POS transaction type on line '.($i+1).'.');

            $qty=(float)($line['quantity']??0);
            if(!$customerId && in_array($mode,['replace_same','replace_different'],true)) throw new RuntimeException('A named customer is required when an empty cylinder is returned.');
            $typeId=isset($line['cylinder_type_id'])&&$line['cylinder_type_id']!==''?(int)$line['cylinder_type_id']:null;
            $receivedTypeId=isset($line['received_cylinder_type_id'])&&$line['received_cylinder_type_id']!==''?(int)$line['received_cylinder_type_id']:null;
            $gasRateInput=trim((string)($line['gas_rate']??''))===''?null:(float)$line['gas_rate'];
            $cylinderRateInput=trim((string)($line['cylinder_rate']??''))===''?null:(float)$line['cylinder_rate'];
            $enteredAmount=trim((string)($line['entered_amount']??''))===''?null:(float)$line['entered_amount'];
            if($enteredAmount!==null && $enteredAmount<=0) throw new RuntimeException('Line '.($i+1).' amount must be greater than zero.');
            if(($gasRateInput!==null && $gasRateInput<0)||($cylinderRateInput!==null && $cylinderRateInput<0)) throw new RuntimeException('Line '.($i+1).' rate cannot be negative.');

            $type=$typeId?BusinessContext::cylinderType($typeId,$locationId):null;
            if(in_array($mode,['sell_gas_only','replace_same','sell_filled','replace_different','sell_empty'],true) && !$typeId) throw new RuntimeException('Cylinder type is required on line '.($i+1).'.');
            if($typeId && (!$type || !(int)$type['is_active'])) throw new RuntimeException('Invalid or inactive cylinder type on line '.($i+1).'.');

            $gasRate=0;$cylinderRate=0;$gasKg=0;$standardGasRate=null;$standardCylinderRate=null;
            $emptyReceived=0;$previewUnits=[];

            if(in_array($mode,['sell_gas_only','replace_same','sell_filled','replace_different'],true)){
                // Gas in a whole cylinder uses the filled cylinder rate (per-KG equivalent); refill by KG uses the KG rate.
                $standardGasRate=in_array($mode,['sell_filled','replace_different'],true)
                    ? $this->rateService()->filledCylinderRate($locationId,$customerId,$type,$transactionAt)['per_kg']
                    : $this->rateService()->kgRate($locationId,$customerId,$transactionAt)['rate'];
                if($standardGasRate!==null && $standardGasRate<=0) $standardGasRate=null;
                // A positive line rate is an allowed override. Only require a configured
                // effective rate when the cashier has not supplied a usable line rate.
                $gasRate=$gasRateInput??$standardGasRate;
                if($gasRate===null || $gasRate<=0){
                    throw new RuntimeException($gasRateInput===null
                        ? 'No effective gas/kg rate exists. Enter a positive Gas Rate for this line or configure an effective rate in Gas Rate Settings.'
                        : 'Gas rate must be greater than zero on line '.($i+1).'.');
                }
            }
            if(in_array($mode,['sell_filled','replace_different','sell_empty'],true)){
                $standardCylinderRate=$this->rateService()->emptyCylinderPrice($locationId,$customerId,$type,$transactionAt);
                if($standardCylinderRate<=0 && $cylinderRateInput===null) throw new RuntimeException('No effective cylinder price exists for '.$type['name'].'.');
                $cylinderRate=$cylinderRateInput??$standardCylinderRate;
            }

            if($mode==='sell_gas_only'){
                if($enteredAmount!==null){
                    $gasKg=$enteredAmount/$gasRate;
                    if($gasKg<=0) throw new RuntimeException('Calculated gas quantity must be greater than zero on line '.($i+1).'.');
                }else{
                    $gasKg=(float)($line['gas_weight_kg']??$qty);
                }
                $sourceUnitId=isset($line['source_cylinder_unit_id'])&&$line['source_cylinder_unit_id']!==''?(int)$line['source_cylinder_unit_id']:0;
                if($gasKg<=0) throw new RuntimeException('Gas KG must be greater than zero on line '.($i+1).'.');
                if($sourceUnitId<=0) throw new RuntimeException('Source filled cylinder is required on line '.($i+1).'.');
                $qty=$gasKg;
                $sourceUnit=$this->db->table('cylinder_units')
                    ->select('id,unit_code,cylinder_type_id,status,gas_weight_kg')
                    ->where(['id'=>$sourceUnitId,'location_id'=>$locationId,'cylinder_type_id'=>$typeId,'status'=>'filled'])
                    ->get()->getRowArray();
                if(!$sourceUnit) throw new RuntimeException('Selected source cylinder is invalid or no longer available on line '.($i+1).'.');
                if($gasKg>(float)$sourceUnit['gas_weight_kg']+0.00001) throw new RuntimeException('Gas quantity exceeds selected source cylinder stock on line '.($i+1).'. Available: '.number_format((float)$sourceUnit['gas_weight_kg'], 2).' kg.');
                $gasRequirements[]=['line_no'=>$i+1,'type_id'=>$typeId,'gas_kg'=>$gasKg];
            }elseif(in_array($mode,['replace_same','sell_filled','replace_different'],true)){
                if(floor($qty)!==$qty) throw new RuntimeException('Cylinder quantity on line '.($i+1).' must be a whole number.');
                $requestedUnitIds=array_values(array_unique(array_map('intval',is_array($line['selected_cylinder_unit_ids']??null)?$line['selected_cylinder_unit_ids']:[])));
                if($requestedUnitIds){
                    if(count($requestedUnitIds)!==(int)$qty || min($requestedUnitIds)<=0) throw new RuntimeException('Select exactly '.(int)$qty.' physical filled cylinder(s) on line '.($i+1).'.');
                    $previewUnits=$this->db->table('cylinder_units')->select('id,unit_code,cylinder_type_id,status,gas_weight_kg')
                        ->where('location_id',$locationId)->where('cylinder_type_id',$typeId)->where('status','filled')
                        ->whereIn('id',$requestedUnitIds)->get()->getResultArray();
                    if(count($previewUnits)!==(int)$qty) throw new RuntimeException('One or more selected filled cylinders are unavailable or belong to another cylinder type.');
                    usort($previewUnits,static fn(array $a,array $b): int => array_search((int)$a['id'],$requestedUnitIds,true)<=>array_search((int)$b['id'],$requestedUnitIds,true));
                }else{
                    $previewUnits=$this->cylinders->availableForDisplay($locationId,$typeId,'filled');
                    if(count($previewUnits)<(int)$qty) throw new RuntimeException('Insufficient filled cylinders of '.$type['name'].'.');
                    $previewUnits=array_slice($previewUnits,0,(int)$qty);
                }
                $selected=$previewUnits;
                $gasKg=array_sum(array_map(static fn($u)=>(float)$u['gas_weight_kg'],$selected));
                if($gasKg<=0) throw new RuntimeException('Selected filled cylinders contain no gas.');
                if($mode==='replace_same'){
                    $receivedTypeId=$typeId;
                }elseif($mode==='replace_different'){
                    if(!$receivedTypeId) throw new RuntimeException('Received empty cylinder type is required on line '.($i+1).'.');
                    if($receivedTypeId===$typeId) throw new RuntimeException('Different-capacity replacement must use a different received cylinder type.');
                    $receivedType=BusinessContext::cylinderType($receivedTypeId,$locationId);
                    if(!$receivedType || !(int)$receivedType['is_active']) throw new RuntimeException('Invalid or inactive received empty-cylinder type.');
                }
                $gasRequirements[]=['line_no'=>$i+1,'type_id'=>$typeId,'gas_kg'=>$gasKg];
                if($receivedTypeId) $emptyReceived=$qty;
            }elseif($mode==='sell_empty'){
                if(floor($qty)!==$qty) throw new RuntimeException('Empty-cylinder quantity on line '.($i+1).' must be a whole number.');
                $requestedUnitIds=array_values(array_unique(array_map('intval',is_array($line['selected_cylinder_unit_ids']??null)?$line['selected_cylinder_unit_ids']:[])));
                if($requestedUnitIds){
                    if(count($requestedUnitIds)!==(int)$qty || min($requestedUnitIds)<=0) throw new RuntimeException('Select exactly '.(int)$qty.' physical empty cylinder(s) on line '.($i+1).'.');
                    $availableEmpty=$this->db->table('cylinder_units')->select('id,unit_code,cylinder_type_id,status,gas_weight_kg')
                        ->where('location_id',$locationId)->where('cylinder_type_id',$typeId)->where('status','empty')
                        ->whereIn('id',$requestedUnitIds)->get()->getResultArray();
                    if(count($availableEmpty)!==(int)$qty) throw new RuntimeException('One or more selected empty cylinders are unavailable or belong to another cylinder type.');
                }else{
                    $availableEmpty=$this->cylinders->availableForDisplay($locationId,$typeId,'empty');
                    if(count($availableEmpty)<(int)$qty) throw new RuntimeException('Insufficient empty cylinders of '.$type['name'].'.');
                }
            }

            $customGas=$gasRateInput!==null && ($standardGasRate===null || abs($gasRate-$standardGasRate)>0.00001);
            $customCylinder=$standardCylinderRate!==null && abs($cylinderRate-$standardCylinderRate)>0.00001;
            $customRate=$customRate||$customGas||$customCylinder;
            $lineTotal=0;
            if(in_array($mode,['sell_gas_only','replace_same'],true)) $lineTotal=$gasKg*$gasRate;
            elseif(in_array($mode,['sell_filled','replace_different'],true)) $lineTotal=$gasKg*$gasRate+$qty*$cylinderRate;
            elseif($mode==='sell_empty') $lineTotal=$qty*$cylinderRate;
            $lineTotal=$this->rounding($locationId)->line($lineTotal);

            $subtotal+=$lineTotal;$totalKg+=$gasKg;
            $storedLineType=$mode==='sell_empty'?'empty_cylinder':($mode==='sell_gas_only'?'refill_kg':'filled_cylinder');
            $noteParts=[];
            if($mode==='sell_gas_only') $noteParts[]='Gas source: '.$type['code'].' — '.$type['name'];
            if($mode==='replace_different') $noteParts[]='Received empty: '.($receivedType['code']??'').' — '.($receivedType['name']??'');
            $prepared[]=[
                'line_no'=>$i+1,'sale_mode'=>$mode,'line_type'=>$storedLineType,'cylinder_type_id'=>$typeId,
                'source_cylinder_unit_id'=>($mode==='sell_gas_only'?(int)($line['source_cylinder_unit_id']??0):null),
                'selected_cylinder_unit_ids'=>array_values(array_unique(array_map('intval',is_array($line['selected_cylinder_unit_ids']??null)?$line['selected_cylinder_unit_ids']:[]))),
                'received_cylinder_type_id'=>$receivedTypeId,'quantity'=>$qty,'gas_weight_kg'=>$gasKg,
                'entered_amount'=>$enteredAmount,
                'gas_rate'=>$gasRate,'cylinder_rate'=>$cylinderRate,'standard_gas_rate'=>$standardGasRate,
                'standard_cylinder_rate'=>$standardCylinderRate,'custom_rate_flag'=>($customGas||$customCylinder)?1:0,
                'empty_cylinder_received'=>$emptyReceived,'line_discount'=>0,'line_total'=>$lineTotal,
                'notes'=>implode(' | ',$noteParts)?:null
            ];
        }

        $discount=max(0,(float)($payload['discount_amount']??0));
        $deliveryFee=(float)($payload['delivery_fee_amount']??0);
        if($deliveryFee<0) throw new RuntimeException('Delivery fee cannot be negative.');
        if($deliveryFee>0 && !$this->orderFulfillmentContext) throw new RuntimeException('A delivery fee can only be posted against a validated customer order.');
        if($discount>$subtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
        $total=round($subtotal-$discount+$deliveryFee,2);
        if($this->currentOrderId && ($this->currentOrderExpectedTotal===null || abs($total-$this->currentOrderExpectedTotal)>0.009)) {
            throw new RuntimeException('ORDER_PRICE_RECONFIRMATION_REQUIRED: the physical-cylinder total no longer matches the customer-confirmed quote.');
        }
        $paymentTotal=0;$credit=0;$cashAmount=0;
        foreach($payments as $p){
            $mode=(string)($p['payment_mode']??'');$amount=(float)($p['amount']??0);
            if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<=0) throw new RuntimeException('Invalid payment.');
            if(!$customerId && $mode!=='cash') throw new RuntimeException($mode==='credit'?'Credit sale is not allowed for Walk-in / Cash customer. Select an actual customer and enable Allow Credit Sale.':'Walk-in sales are cash only.');
            $paymentTotal+=$amount;if($mode==='credit') $credit+=$amount;if($mode==='cash') $cashAmount+=$amount;
        }
        if(abs($paymentTotal-$total)>0.01) throw new RuntimeException('Payment total must equal sale total.');

        $this->db->transBegin();
        try{
            $lockedOrder = null;
            if($this->currentOrderId){
                $lockedOrder=$this->db->query("SELECT * FROM customer_orders WHERE id=? AND location_id=? LIMIT 1 FOR UPDATE",[$this->currentOrderId,$locationId])->getRowArray();
                if(!$lockedOrder) throw new RuntimeException('Customer order was not found in this shop.');
                if((string)$lockedOrder['status']==='completed' && !empty($lockedOrder['pos_sale_id'])){
                    $existingSale=$this->db->table('sales')->where(['id'=>(int)$lockedOrder['pos_sale_id'],'customer_order_id'=>$this->currentOrderId])->get()->getRowArray();
                    if($existingSale){
                        $this->assertCustomerOrderSaleMatches($lockedOrder, $existingSale, (int)$this->currentOrderId, $locationId);
                        $this->db->transRollback();
                        return ['id'=>(int)$existingSale['id'],'sale_no'=>(string)$existingSale['sale_no'],'total'=>(float)$existingSale['total_amount'],'customer_id'=>(int)$existingSale['customer_id'],'credit_amount'=>(float)$existingSale['credit_amount'],'idempotent_replay'=>true];
                    }
                }
                if(!in_array((string)$lockedOrder['status'],['accepted','preparing','ready','out_for_delivery'],true)) throw new RuntimeException('Only an accepted order can be fulfilled.');
                if((int)$lockedOrder['price_reconfirmation_required']===1) throw new RuntimeException('ORDER_PRICE_RECONFIRMATION_REQUIRED: customer confirmation is pending.');
                if(!empty($lockedOrder['pos_sale_id'])) throw new RuntimeException('Customer order already has a POS sale link.');
                if(abs((float)$lockedOrder['total_amount']-(float)$this->currentOrderExpectedTotal)>0.009
                    || abs((float)$lockedOrder['delivery_fee']-(float)$deliveryFee)>0.009
                    || (int)$lockedOrder['customer_id']!==(int)$customerId){
                    throw new RuntimeException('ORDER_PRICE_RECONFIRMATION_REQUIRED: order details changed before posting.');
                }
            }
            if($customerId){
                $lockedCustomer=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
                if(!$lockedCustomer || !(int)$lockedCustomer['is_active']) throw new RuntimeException('Customer is unavailable.');
                $customer=$lockedCustomer;
            }

            $lockRows=[];
            foreach($prepared as $row){
                if(in_array($row['sale_mode'],['sell_gas_only','replace_same','sell_filled','replace_different'],true)){
                    $lockRows[]=['type'=>'gas_kg','cylinder_type_id'=>null];
                    $lockRows[]=['type'=>'filled_cylinder','cylinder_type_id'=>(int)$row['cylinder_type_id']];
                }
                if(in_array($row['sale_mode'],['replace_same','replace_different'],true)) $lockRows[]=['type'=>'empty_cylinder','cylinder_type_id'=>(int)$row['received_cylinder_type_id']];
                if($row['sale_mode']==='sell_empty') $lockRows[]=['type'=>'empty_cylinder','cylinder_type_id'=>(int)$row['cylinder_type_id']];
            }
            $this->acquireInventoryLocks($locationId,$lockRows);

            $gasAvailable=(float)($this->db->query("SELECT COALESCE(SUM(gas_weight_kg),0) AS gas_stock FROM cylinder_units WHERE location_id=? AND status='filled'",[$locationId])->getRowArray()['gas_stock']??0);
            $gasGroups=[];
            foreach($prepared as $idx=>$row){
                if(!$row['gas_weight_kg']) continue;
                if(!isset($gasGroups[$idx])) $gasGroups[$idx]=['qty'=>0,'type_id'=>(int)$row['cylinder_type_id']];
                $gasGroups[$idx]['qty']+=(float)$row['gas_weight_kg'];
            }
            $overrideConfirmed=!empty($payload['stock_override_confirmed']);
            $shopSettings=(new ShopSettingsModel())->forLocation($locationId);
            $allowStockOverride=(int)($shopSettings['allow_stock_override']??1)===1;
            $control=new InventoryControlService();
            $virtualGasStock=$gasAvailable;
            foreach($gasGroups as $group){
                $policy=$control->policy($locationId,$group['type_id']);
                $qty=(float)$group['qty'];
                if(!(int)$policy['stock_validation_enabled']){
                    if(!$allowStockOverride) throw new RuntimeException('Gas stock validation is OFF, but stock override is disabled in Shop Settings.');
                    if(!$overrideConfirmed) throw new RuntimeException('Gas stock validation is OFF. Confirm the stock override before posting this sale.');
                }elseif($virtualGasStock+0.00001<$qty){
                    throw new RuntimeException('Insufficient gas stock. Available: '.number_format($virtualGasStock, 2).' kg; required: '.number_format($qty, 2).' kg.');
                }
                $virtualGasStock-=$qty;
            }

            $inventory=[];
            $reservedFilledIds=[];
            $reservedEmptyIds=[];
            foreach($prepared as $idx=>&$row){
                $mode=$row['sale_mode'];$typeId=(int)$row['cylinder_type_id'];$qty=(float)$row['quantity'];
                if($mode==='sell_gas_only'){
                    $sourceId=(int)($this->db->table('cylinder_units')
                        ->select('id')
                        ->where(['id'=>(int)($row['source_cylinder_unit_id']??0),'location_id'=>$locationId,'cylinder_type_id'=>$typeId,'status'=>'filled'])
                        ->get()->getRowArray()['id']??0);
                    if($sourceId<=0) throw new RuntimeException('Selected source cylinder is no longer available while posting. Please retry the sale.');
                    $unit=$this->db->query("SELECT * FROM cylinder_units WHERE id=? FOR UPDATE",[$sourceId])->getRowArray();
                    if(!$unit || $unit['status']!=='filled' || (int)$unit['cylinder_type_id']!==$typeId) throw new RuntimeException('Selected source cylinder changed while posting. Please retry the sale.');
                    $used=(float)$row['gas_weight_kg'];
                    $before=(float)$unit['gas_weight_kg'];
                    if($used>$before+0.00001) throw new RuntimeException('Selected source cylinder gas changed while posting. Please retry the sale.');
                    $after=$before-$used;
                    if($after<=0.00001){
                        $this->db->table('cylinder_units')->where('id',$sourceId)->update(['status'=>'empty','gas_weight_kg'=>0]);
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','unit_id'=>$sourceId,'from'=>'filled','to'=>'empty'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>$sourceId,'transition'=>'to_empty','from'=>'filled','to'=>'empty'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in','unit_id'=>$sourceId,'transition'=>'from_filled','from'=>'filled','to'=>'empty'];
                    }else{
                        $this->db->table('cylinder_units')->where('id',$sourceId)->update(['gas_weight_kg'=>$after,'status'=>'filled']);
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','unit_id'=>$sourceId,'from'=>'filled','to'=>'filled'];
                    }
                }elseif(in_array($mode,['replace_same','sell_filled','replace_different'],true)){
                    $units=$this->cylinders->available($locationId,$typeId,'filled');
                    $units=array_values(array_filter($units,static fn(array $unit): bool => !isset($reservedFilledIds[(int)$unit['id']])));
                    $requestedUnitIds=$row['selected_cylinder_unit_ids']??[];
                    if($requestedUnitIds){
                        $units=array_values(array_filter($units,static fn(array $unit): bool => in_array((int)$unit['id'],$requestedUnitIds,true)));
                        usort($units,static fn(array $a,array $b): int => array_search((int)$a['id'],$requestedUnitIds,true)<=>array_search((int)$b['id'],$requestedUnitIds,true));
                    }
                    if(count($units)<(int)$qty) throw new RuntimeException('One or more selected filled cylinders changed while posting. Please retry the sale.');
                    $selected=array_slice($units,0,(int)$qty);$actualGas=0;
                    foreach($selected as $unit){
                        $reservedFilledIds[(int)$unit['id']]=true;
                        $actualGas+=(float)$unit['gas_weight_kg'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>(float)$unit['gas_weight_kg'],'direction'=>'out','unit_id'=>(int)$unit['id'],'from'=>'filled','to'=>'sold'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'transition'=>'sold','from'=>'filled','to'=>'sold'];
                    }
                    if(abs($actualGas-(float)$row['gas_weight_kg'])>0.00001) {
                        throw new RuntimeException($this->currentOrderId
                            ? 'ORDER_PRICE_RECONFIRMATION_REQUIRED: physical-cylinder gas weight changed during fulfillment.'
                            : 'Filled-cylinder gas weight changed while posting. Please retry the sale.');
                    }
                    if($row['received_cylinder_type_id']){
                        for($n=0;$n<(int)$qty;$n++) $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>(int)$row['received_cylinder_type_id'],'quantity'=>1,'direction'=>'in','unit_id'=>null,'transition'=>'new_empty','from'=>MovementStates::NONE,'to'=>'empty'];
                    }
                }elseif($mode==='sell_empty'){
                    $units=$this->cylinders->available($locationId,$typeId,'empty');
                    $units=array_values(array_filter($units,static fn(array $unit): bool => !isset($reservedEmptyIds[(int)$unit['id']])));
                    $requestedUnitIds=$row['selected_cylinder_unit_ids']??[];
                    if($requestedUnitIds){
                        $units=array_values(array_filter($units,static fn(array $unit): bool => in_array((int)$unit['id'],$requestedUnitIds,true)));
                        usort($units,static fn(array $a,array $b): int => array_search((int)$a['id'],$requestedUnitIds,true)<=>array_search((int)$b['id'],$requestedUnitIds,true));
                    }
                    if(count($units)<(int)$qty) throw new RuntimeException('One or more selected empty cylinders changed while posting. Please retry the sale.');
                    foreach(array_slice($units,0,(int)$qty) as $unit){
                        $reservedEmptyIds[(int)$unit['id']]=true;
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'transition'=>'sold','from'=>'empty','to'=>'sold'];
                    }
                }
            }
            unset($row);

            $preparedSubtotal=array_sum(array_map(static fn($r)=>(float)$r['line_total'],$prepared));
            $discount=max(0,(float)($payload['discount_amount']??0));if($discount>$preparedSubtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
            $deliveryFee=(float)($payload['delivery_fee_amount']??0);
            if($deliveryFee<0) throw new RuntimeException('Delivery fee cannot be negative.');
            if($deliveryFee>0 && !$this->orderFulfillmentContext) throw new RuntimeException('A delivery fee can only be posted against a validated customer order.');
            $total=round($preparedSubtotal-$discount+$deliveryFee,2);
            if($this->currentOrderId && ($this->currentOrderExpectedTotal===null || abs($total-$this->currentOrderExpectedTotal)>0.009)) {
                throw new RuntimeException('ORDER_PRICE_RECONFIRMATION_REQUIRED: the physical-cylinder total changed during fulfillment.');
            }
            $paymentTotal=0;$credit=0;$cashAmount=0;
            foreach($payments as $p){
                $mode=(string)$p['payment_mode'];$amount=(float)$p['amount'];$paymentTotal+=$amount;
                if($mode==='credit')$credit+=$amount;if($mode==='cash')$cashAmount+=$amount;
            }
            if(abs($paymentTotal-$total)>0.01) throw new RuntimeException('Payment total must equal the recalculated sale total.');
            if(!$customerId && abs($paymentTotal-$total)>0.01) throw new RuntimeException('Walk-in sale must be fully paid. Received amount must equal sale total.');
            if($customerId && $credit>0.01){
                if(!(int)($customer['allow_credit_sale']??0)) throw new RuntimeException('Credit sale is not allowed for this customer. Enable Allow Credit Sale on the customer record before posting a credit sale.');

                // Keep standard five-mode POS credit validation aligned with the header-sale
                // payment planner: a configured customer limit applies in customer mode, and
                // remains authoritative in "none" mode only when a positive customer limit
                // is configured. Shop mode must serialize and check the whole shop exposure.
                $creditMode=(string)($shopSettings['credit_limit_validation_mode']??'none');
                $customerCreditLimit=(float)($customer['credit_limit']??0);
                if($creditMode==='customer' || ($creditMode==='none' && $customerCreditLimit>0)){
                    $newBalance=$this->customerBalance($customerId)+$credit;
                    if($customerCreditLimit>0 && $newBalance>$customerCreditLimit+0.01){
                        throw new RuntimeException('Customer credit limit exceeded.');
                    }
                }elseif($creditMode==='shop'){
                    $this->acquireCreditLimitLock($locationId);
                    $shopOs=$this->shopOutstanding($locationId);
                    $previousGasOs=$this->customerGasBalance($customerId);
                    $advanceApplied=min($credit,max(0,-$previousGasOs));
                    $projectedShopOs=max(0,$shopOs+$credit-$advanceApplied);
                    $shopLimit=(float)($shopSettings['shop_credit_limit']??0);
                    if($projectedShopOs>$shopLimit+0.01){
                        $available=max(0,$shopLimit-$shopOs);
                        throw new RuntimeException('Shop credit limit exceeded. Current shop OS Rs. '.number_format($shopOs,2).'; available additional credit Rs. '.number_format($available,2).'.');
                    }
                }
            }

            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $scenarioTypes=array_values(array_unique(array_column($prepared,'sale_mode')));
            $transactionType=count($scenarioTypes)>1?'mixed':match($scenarioTypes[0]){
                'sell_gas_only'=>'refill_service','replace_same'=>'cylinder_exchange','sell_filled'=>'filled_cylinder',
                'replace_different'=>'mixed','sell_empty'=>'empty_sale'
            };
            $this->db->table('sales')->insert(['sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>$transactionType,'status'=>'posted','transaction_at'=>$transactionAt,'total_kg'=>array_sum(array_map(static fn($r)=>(float)$r['gas_weight_kg'],$prepared)),'subtotal'=>$preparedSubtotal,'discount_amount'=>$discount,'delivery_fee_amount'=>$deliveryFee,'customer_order_id'=>$this->currentOrderId,'total_amount'=>$total,'credit_amount'=>$credit,'custom_rate_flag'=>$customRate?1:0,'notes'=>$notes,'created_by'=>$userId]);
            $saleId=(int)$this->db->insertID();

            foreach($prepared as $row){
                $gasKg=(float)$row['gas_weight_kg'];$qty=(float)$row['quantity'];
                if(in_array($row['sale_mode'],['sell_filled','replace_different'],true)) $appliedRate=(($gasKg/max($qty,1))*$row['gas_rate'])+$row['cylinder_rate'];
                elseif(in_array($row['sale_mode'],['sell_gas_only','replace_same'],true)) $appliedRate=$row['gas_rate']; else $appliedRate=$row['cylinder_rate'];
                $standardRate=$row['standard_gas_rate']!==null?(in_array($row['sale_mode'],['sell_filled','replace_different'],true)?((($gasKg/max($qty,1))*$row['standard_gas_rate'])+$row['standard_cylinder_rate']):$row['standard_gas_rate']):$row['standard_cylinder_rate'];
                $this->db->table('sale_items')->insert([
                    'sale_id'=>$saleId,'line_no'=>$row['line_no'],'line_type'=>$row['line_type'],'cylinder_type_id'=>$row['cylinder_type_id'],
                    'quantity'=>$qty,'gas_weight_kg'=>$gasKg,'applied_rate'=>$appliedRate,'standard_rate'=>$standardRate,
                    'custom_rate_flag'=>$row['custom_rate_flag'],'empty_cylinder_received'=>$row['empty_cylinder_received'],
                    'line_discount'=>0,'line_total'=>$row['line_total'],'notes'=>$row['notes']
                ]);
            }
            $lineIds=[];foreach($this->db->table('sale_items')->select('id,line_no')->where('sale_id',$saleId)->get()->getResultArray() as $row)$lineIds[(int)$row['line_no']]=(int)$row['id'];

            foreach($inventory as $m){
                $unitId=$m['unit_id']??null;
                if($m['type']==='empty_cylinder' && $m['direction']==='in' && empty($unitId)){
                    $unitId=$this->cylinders->createUnits($locationId,(int)$m['cylinder_type_id'],1,'empty',0,$userId,'sale',$saleId)[0];
                }
                $this->db->table('inventory_movements')->insert([
                    'location_id'=>$locationId,'inventory_type'=>$m['type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                    'quantity'=>$m['quantity'],'direction'=>$m['direction'],'from_state'=>$m['from'],'to_state'=>$m['to'],'movement_at'=>$transactionAt,'source_type'=>'sale',
                    'source_id'=>$saleId,'source_line_id'=>$lineIds[(int)$m['line_no']]??null,'cylinder_unit_id'=>$unitId,
                    'created_by'=>$userId,'notes'=>($m['transition']??null)
                ]);
            }

            foreach($inventory as $m){
                if(($m['unit_id']??null) && $m['type']==='filled_cylinder' && $m['direction']==='out' && ($m['transition']??'')==='sold') $this->cylinders->markSold((int)$m['unit_id']);
                if(($m['unit_id']??null) && $m['type']==='empty_cylinder' && $m['direction']==='out') $this->cylinders->markSold((int)$m['unit_id']);
            }

            foreach($payments as $p) $this->db->table('sale_payments')->insert(['sale_id'=>$saleId,'payment_mode'=>$p['payment_mode'],'amount'=>(float)$p['amount'],'reference_no'=>trim((string)($p['reference_no']??''))?:null,'payment_at'=>$transactionAt,'received_by'=>$userId]);
            if($cashAmount>0){
                $session=$this->cash->openSessionForLocation($locationId);
                if(!$session) throw new RuntimeException('Open the counter cash session before posting a cash sale.');
                $this->cash->postSaleCash((int)$session['id'],$saleId,$cashAmount,$userId,$transactionAt);
            }
            if($this->currentOrderId && $lockedOrder){
                $completedAt=date('Y-m-d H:i:s');
                $this->db->table('customer_orders')->where(['id'=>$this->currentOrderId,'location_id'=>$locationId])->update([
                    'pos_sale_id'=>$saleId,
                    'status'=>'completed',
                    'completed_by'=>$userId,
                    'completed_at'=>$completedAt,
                ]);
                $this->db->table('customer_order_status_history')->insert([
                    'order_id'=>$this->currentOrderId,
                    'business_id'=>(int)$lockedOrder['business_id'],
                    'from_status'=>(string)$lockedOrder['status'],
                    'to_status'=>'completed',
                    'actor_type'=>'staff',
                    'customer_app_account_id'=>null,
                    'user_id'=>$userId,
                    'reason'=>'Fulfilled and posted as POS sale '.$saleNo.'.',
                    'request_id'=>null,
                ]);
            }
            if(!$this->db->transStatus()) throw new RuntimeException('Sale posting failed.');
            $this->db->transCommit();
            $this->releaseInventoryLocks();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>$total,'customer_id'=>$customerId,'credit_amount'=>$credit];
        }catch(\Throwable $e){
            $this->releaseInventoryLocks();
            $this->db->transRollback();
            throw $e;
        }
    }

    protected function postHeaderTransaction(array $payload,int $userId,int $locationId,string $headerType): array
    {
        $customerId=!empty($payload['customer_id'])?(int)$payload['customer_id']:null;
        $transactionAt=str_replace('T',' ',trim((string)($payload['transaction_at']??date('Y-m-d H:i:s'))));
        $notes=trim((string)($payload['notes']??''))?:null;
        $lines=is_array($payload['lines']??null)?$payload['lines']:[];
        $payments=is_array($payload['payments']??null)?$payload['payments']:[];
        $deposit=(float)($payload['security_deposit_amount']??0);
        $custodyUnitIds=array_values(array_unique(array_map('intval',is_array($payload['custody_unit_ids']??null)?$payload['custody_unit_ids']:[])));
        $returnUnitIds=array_values(array_unique(array_map('intval',is_array($payload['return_unit_ids']??null)?$payload['return_unit_ids']:[])));

        $customer=$customerId?$this->customers->findForLocation($customerId,$locationId):null;
        if($customerId && (!$customer || !(int)$customer['is_active'])) throw new RuntimeException('Customer is invalid or inactive.');
        if($headerType==='security_deposit' || $headerType==='cylinder_return'){
            if(!$customerId) throw new RuntimeException('A named customer is required for cylinder custody transactions.');
        }
        if($headerType!=='security_deposit' && $deposit>0) throw new RuntimeException('Security Deposit Amount is only allowed on a Security Deposit transaction.');

        if($headerType==='gas_sale') return $this->postGasSaleHeader($payload,$userId,$locationId,$customerId,$transactionAt,$notes,$lines,$payments);
        if($headerType==='cylinder_sale') return $this->postCylinderSaleHeader($payload,$userId,$locationId,$customerId,$transactionAt,$notes,$lines,$payments);
        if($headerType==='security_deposit') return $this->postSecurityDepositHeader($payload,$userId,$locationId,$customerId,$transactionAt,$notes,$payments,$custodyUnitIds,$deposit);
        return $this->postCylinderReturnHeader($payload,$userId,$locationId,$customerId,$transactionAt,$notes,$returnUnitIds);
    }

    protected function prepareSalePaymentPlan(array $payments,?int $customerId,float $saleTotal): array
    {
        foreach($payments as $p){
            $type=(string)($p['payment_type']??'sale');
            if($type!=='sale')throw new RuntimeException('Gas/cylinder sale payments must be classified as Sale.');
        }
        if(!$payments)throw new RuntimeException('At least one payment is required.');
        $paymentTotal=0;
        foreach($payments as $p){
            $mode=(string)($p['payment_mode']??'');$amount=(float)($p['amount']??0);
            if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<0)throw new RuntimeException('Invalid payment.');
            // Empty/zero payment rows are allowed in the POS. They carry no collection;
            // a zero Credit row may also explicitly represent an unpaid customer sale.
            if(!$customerId&&$mode!=='cash')throw new RuntimeException($mode==='credit'?'Credit sale is not allowed for Walk-in / Cash customer.':'Walk-in transactions are cash only.');
            $paymentTotal+=$amount;
        }
        // Walk-in / Cash is strictly cash-only and must be fully settled at posting.
        // Do not let an underpayment silently become credit via the unpaid remainder.
        if($customerId===null && abs($paymentTotal-$saleTotal)>0.01){
            throw new RuntimeException($paymentTotal<$saleTotal-0.01
                ? 'Credit sale is not allowed for Walk-in / Cash customer. A walk-in sale must be fully paid at posting time.'
                : 'Payment cannot exceed the walk-in sale amount.');
        }
        $previousOs=$customerId?$this->customerGasBalance($customerId):0;
        $maxReceivable=max(0,round($saleTotal+$previousOs,2));
        if($paymentTotal>$maxReceivable+0.01)throw new RuntimeException('Payment cannot exceed the customer net receivable of Rs. '.number_format($maxReceivable,2).'.');

        // A customer payment may be less than the current sale amount. The unpaid
        // remainder of the current sale is customer credit, subject to the same
        // credit-eligibility and credit-limit rules used by Gas Sale.
        $salePayments=[];$settlements=[];$remainingSale=$saleTotal;$remainingOs=$previousOs;
        foreach($payments as $p){
            $amount=(float)$p['amount'];$mode=(string)$p['payment_mode'];
            if($remainingOs>0.00001 && $amount>0){
                if($mode==='credit')throw new RuntimeException('Credit cannot be used to settle a previous customer OS balance.');
                $toOs=min($amount,$remainingOs);
                $settlements[]=['payment_mode'=>$mode,'amount'=>$toOs,'reference_no'=>$p['reference_no']??null];
                $remainingOs-=$toOs;$amount-=$toOs;
            }
            if($amount>0){
                $toSale=min($amount,$remainingSale);
                $salePayments[]=['payment_mode'=>$mode,'amount'=>$toSale,'reference_no'=>$p['reference_no']??null];
                $remainingSale-=$toSale;$amount-=$toSale;
            }
            if($amount>0.00001)throw new RuntimeException('Payment exceeds the customer net receivable.');
        }
        // The unpaid portion of the current sale becomes credit. If the cashier
        // explicitly uses a Credit tender, retain that credit tender as part of the
        // current-sale credit as well.
        $explicitCreditAmount=array_sum(array_map(static fn($p)=>(string)$p['payment_mode']==='credit'?(float)$p['amount']:0,$salePayments));
        $creditAmount=max(0,$remainingSale)+$explicitCreditAmount;
        $newOs=round($remainingOs+$creditAmount,2);
        if($customerId!==null && $newOs>0.01){
            $customerForCredit=$this->customers->find($customerId);
            if(!$customerForCredit || !(int)$customerForCredit['is_active']) throw new RuntimeException('Customer is unavailable.');
            if(!(int)($customerForCredit['allow_credit_sale']??0)){
                throw new RuntimeException('Credit sale is not allowed for this customer. Enable Allow Credit Sale on the customer record.');
            }

            $shopSettings=(new ShopSettingsModel())->forLocation($this->currentLocationId ?? 0);
            $creditMode=(string)($shopSettings['credit_limit_validation_mode']??'none');
            $settlementTotal=array_sum(array_map(static fn($p)=>(float)$p['amount'],$settlements));

            if($creditMode==='customer' || ($creditMode==='none' && (float)($customerForCredit['credit_limit']??0)>0)){
                // A configured customer credit limit is authoritative for that customer.
                // This also prevents a configured limit from being displayed as Unlimited
                // while the server silently allows unlimited credit.
                $creditLimit=(float)($customerForCredit['credit_limit']??0);
                if($creditLimit>0 && $newOs>$creditLimit+0.01){
                    $available=max(0,$creditLimit-max(0,$previousOs));
                    throw new RuntimeException('Customer credit limit exceeded. Existing OS Rs. '.number_format($previousOs,2).'; available additional credit Rs. '.number_format($available,2).'.');
                }
            }elseif($creditMode==='shop'){
                $this->acquireCreditLimitLock($this->currentLocationId ?? 0);
                $shopOs=$this->shopOutstanding($this->currentLocationId ?? 0);
                $advanceApplied=min($creditAmount,max(0,-$previousOs));
                $projectedShopOs=max(0,$shopOs-$settlementTotal+$creditAmount-$advanceApplied);
                $shopLimit=(float)($shopSettings['shop_credit_limit']??0);
                if($projectedShopOs>$shopLimit+0.01){
                    $available=max(0,$shopLimit-($shopOs-$settlementTotal));
                    throw new RuntimeException('Shop credit limit exceeded. Current shop OS Rs. '.number_format($shopOs,2).'; available additional credit Rs. '.number_format($available,2).'.');
                }
            }
        }
        return ['sale_payments'=>$salePayments,'settlements'=>$settlements,'credit_amount'=>$creditAmount,'previous_os'=>$previousOs,'payment_total'=>$paymentTotal,'net_receivable'=>$maxReceivable,'remaining_os'=>$newOs];
    }

    /** Sum of positive gas balances of this branch's customers (shop credit-limit mode). */
    protected function shopOutstanding(int $locationId): float
    {
        return array_sum(array_filter((new CustomerBalanceService($this->db))->gasBalances($locationId), static fn (float $b): bool => $b > 0));
    }

    protected function acquireCreditLimitLock(int $locationId): void
    {
        if($locationId<=0) throw new RuntimeException('Invalid shop location for credit-limit validation.');
        $lockKey='lpg_credit_limit_'.$locationId;
        $result=$this->db->query("SELECT GET_LOCK(?,5) AS locked",[$lockKey])->getRowArray();
        if((int)($result['locked']??0)!==1) throw new RuntimeException('Credit limit is busy; please retry the transaction.');
        $this->creditLocks[]=$lockKey;
    }

    protected function postCustomerSettlement(array $settlements,int $locationId,int $saleId,?int $customerId,string $at,int $userId): void
    {
        if(!$settlements || $customerId===null)return;
        foreach($settlements as $p){
            $amount=(float)$p['amount'];$mode=(string)$p['payment_mode'];
            $no=(new DocumentNumberService($this->db))->next('receipt',$locationId,$at);
            $this->db->table('customer_receipts')->insert([
                'receipt_no'=>$no,'location_id'=>$locationId,'customer_id'=>$customerId,'status'=>'posted',
                'amount'=>$amount,'payment_mode'=>$mode,'receipt_at'=>$at,
                'reference_no'=>trim((string)($p['reference_no']??''))?:null,
                'notes'=>'OS settlement collected with sale '.$saleId,'created_by'=>$userId
            ]);
            if($mode==='cash'){
                $s=$this->cash->openSessionForLocation($locationId);
                if(!$s)throw new RuntimeException('Open the counter cash session before receiving an OS settlement.');
                $this->cash->postGeneric((int)$s['id'],'customer_receipt','in',$amount,'customer_receipt',(int)$this->db->insertID(),$userId,'Customer OS settlement',$at);
            }
        }
    }

    protected function validateHeaderPayments(array $payments,?int $customerId,float $expected,bool $allowCredit=true): void
    {
        if(!$payments)throw new RuntimeException('At least one payment is required.');
        $total=0;
        foreach($payments as $p){$mode=(string)($p['payment_mode']??'');$amount=(float)($p['amount']??0);if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<=0)throw new RuntimeException('Invalid payment.');if(!$customerId&&$mode!=='cash')throw new RuntimeException('Walk-in transactions are cash only.');if(!$allowCredit&&$mode==='credit')throw new RuntimeException('Credit payment is not allowed for this transaction.');$total+=$amount;}
        if(abs($total-$expected)>0.01)throw new RuntimeException('Payment total must equal the net amount payable.');
    }

    protected function insertSalePayments(int $saleId,array $payments,string $at,int $userId,string $paymentType='sale'): void
    {
        foreach($payments as $p){
            $this->db->table('sale_payments')->insert([
                'sale_id'=>$saleId,'payment_type'=>$paymentType,'payment_mode'=>$p['payment_mode'],'amount'=>(float)$p['amount'],
                'reference_no'=>trim((string)($p['reference_no']??''))?:null,'payment_at'=>$at,'received_by'=>$userId
            ]);
        }
    }

    protected function securityDepositBalance(int $locationId,int $customerId): float
    {
        return (new CustomerBalanceService($this->db))->depositBalances($locationId,[$customerId])[$customerId] ?? 0.0;
    }

    protected function customerGasBalance(int $customerId): float
    {
        $balances=(new CustomerBalanceService($this->db))->gasBalances((int)$this->currentLocationId,[$customerId]);
        if(array_key_exists($customerId,$balances)) return $balances[$customerId];
        $c=$this->customers->find($customerId);
        return (float)($c['opening_balance']??0);
    }

    protected function paymentCash(array $payments): float {return array_sum(array_map(static fn($p)=>(string)($p['payment_mode'])==='cash'?(float)$p['amount']:0,$payments));}
    protected function paymentCredit(array $payments): float {return array_sum(array_map(static fn($p)=>(string)($p['payment_mode'])==='credit'?(float)$p['amount']:0,$payments));}
    protected function paymentCustom(array $rows): int {return array_reduce($rows,static fn($c,$r)=>$c||(int)($r['custom_rate_flag']??0)?1:0,0);}

    public function void(int $saleId,int $userId,int $locationId,string $reason): string
    {
        \App\Libraries\TenantContext::ensureBranch($locationId);
        $this->currentLocationId=$locationId;
        $reason=trim($reason);
        if($reason==='') throw new RuntimeException('Void reason is required.');
        $this->db->transBegin();
        try{
            $sale=$this->db->query("SELECT * FROM sales WHERE id=? AND location_id=? FOR UPDATE",[$saleId,$locationId])->getRowArray();
            if(!$sale) throw new RuntimeException('Sale not found.');
            if($sale['status']!=='posted') throw new RuntimeException('Only posted sales can be voided.');
            if(in_array($sale['transaction_type'],['security_deposit','cylinder_return'],true)) throw new RuntimeException('Security Deposit and Cylinder Return transactions cannot be voided. Use the custody workflow so the liability and physical cylinder remain consistent.');
            if($sale['transaction_type']==='gas_sale'){
                $custodyLine=(int)$this->db->table('sale_items')->where('sale_id',$saleId)->where('customer_cylinder_unit_id IS NOT NULL',null,false)->countAllResults();
                if($custodyLine>0) throw new RuntimeException('Gas sales that refill a customer custody cylinder cannot be voided after posting.');
            }

            $movements=$this->db->table('inventory_movements')->where('source_type','sale')->where('source_id',$saleId)->get()->getResultArray();
            $this->acquireInventoryLocks($locationId,$movements);
            $this->assertUnitsUnchangedSinceSale($movements,(string)$sale['sale_no']);

            // A single physical filled-cylinder sale/refill can have two OUT movements:
            // gas_kg and filled_cylinder. Restore the physical cylinder only once.
            // When a gas sale empties a filled cylinder, it also has an empty_cylinder IN
            // movement. In both cases restoreFilled() must handle the gas exactly once.
            $soldFilledUnitIds=[];
            foreach($movements as $m){
                $unitId=(int)($m['cylinder_unit_id']??0);
                if($unitId && $m['inventory_type']==='filled_cylinder' && $m['direction']==='out'){
                    $soldFilledUnitIds[$unitId]=true;
                }
            }

            foreach($movements as $m){
                $reverse=$m['direction']==='in'?'out':'in';
                $this->db->table('inventory_movements')->insert([
                    'location_id'=>$m['location_id'],'inventory_type'=>$m['inventory_type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                    'quantity'=>$m['quantity'],'direction'=>$reverse,'movement_at'=>date('Y-m-d H:i:s'),
                    ...MovementStates::reverse($m),
                    'source_type'=>'sale_void','source_id'=>$saleId,'source_line_id'=>$m['source_line_id']??null,
                    'cylinder_unit_id'=>$m['cylinder_unit_id']??null,'created_by'=>$userId,
                    'notes'=>'Reversal of sale '.$sale['sale_no']
                ]);

                $unitId=(int)($m['cylinder_unit_id']??0);
                if(!$unitId) continue;

                if($m['inventory_type']==='gas_kg' && $m['direction']==='out'){
                    // For a physical filled-cylinder OUT in this same sale, defer gas
                    // restoration to the filled_cylinder branch below to avoid double restore.
                    if(!isset($soldFilledUnitIds[$unitId])) $this->cylinders->restoreGas($unitId,(float)$m['quantity']);
                }elseif($m['inventory_type']==='filled_cylinder' && $m['direction']==='out'){
                    $gasRow=$this->db->table('inventory_movements')
                        ->where('source_type','sale')->where('source_id',$saleId)
                        ->where('inventory_type','gas_kg')->where('direction','out')
                        ->where('cylinder_unit_id',$unitId)->orderBy('id')->get()->getRowArray();
                    $this->cylinders->restoreFilled($unitId,(float)($gasRow['quantity']??0));
                }elseif($m['inventory_type']==='empty_cylinder' && $m['direction']==='out'){
                    $this->cylinders->restoreEmpty($unitId);
                }elseif($m['inventory_type']==='empty_cylinder' && $m['direction']==='in'){
                    // This represents a cylinder that became empty during a gas sale.
                    // restoreFilled() above already restores it to its original filled state.
                    $converted=isset($soldFilledUnitIds[$unitId]);
                    if(!$converted) $this->cylinders->markSold($unitId);
                }
            }
            $cashRows=$this->db->table('cash_transactions')->where('reference_type','sale')->where('reference_id',$saleId)->where('transaction_type','sale_cash')->where('direction','in')->get()->getResultArray();
            $existingReversal=(int)$this->db->table('cash_transactions')->where('reference_type','sale_void')->where('reference_id',$saleId)->where('transaction_type','sale_cash')->where('direction','out')->countAllResults();
            if($existingReversal>0) throw new RuntimeException('Cash reversal for this sale already exists.');
            // Cash paid back on a void leaves the drawer now, so it is posted to the shop's
            // current open session. A closed session's reconciliation must never change.
            $voidSessionId=null;
            $resolveVoidSession=function() use (&$voidSessionId,$locationId): int{
                if($voidSessionId!==null) return $voidSessionId;
                $open=$this->cash->openSessionForLocation($locationId);
                if(!$open) throw new RuntimeException('Open the counter cash session before voiding a sale that was paid in cash.');
                $locked=$this->db->query("SELECT id,status FROM cash_sessions WHERE id=? FOR UPDATE",[(int)$open['id']])->getRowArray();
                if(!$locked || $locked['status']!=='open') throw new RuntimeException('The counter cash session closed while voiding. Please retry.');
                return $voidSessionId=(int)$locked['id'];
            };
            foreach($cashRows as $cash){
                $this->db->table('cash_transactions')->insert([
                    'cash_session_id'=>$resolveVoidSession(),'transaction_type'=>'sale_cash','direction'=>'out',
                    'amount'=>$cash['amount'],'transaction_at'=>date('Y-m-d H:i:s'),'reference_type'=>'sale_void',
                    'reference_id'=>$saleId,'created_by'=>$userId,'notes'=>'Cash reversal of sale '.$sale['sale_no']
                ]);
            }

            $settlementReceipts=$this->db->table('customer_receipts')
                ->where(['location_id'=>$locationId,'status'=>'posted'])
                ->where('customer_id', $sale['customer_id'])
                ->where('notes','OS settlement collected with sale '.$saleId)
                ->get()->getResultArray();
            foreach($settlementReceipts as $receipt){
                $receiptId=(int)$receipt['id'];
                $this->db->table('customer_receipts')->where('id',$receiptId)->update(['status'=>'voided']);

                $receiptCashRows=$this->db->table('cash_transactions')
                    ->where([
                        'reference_type'=>'customer_receipt',
                        'reference_id'=>$receiptId,
                        'transaction_type'=>'customer_receipt',
                        'direction'=>'in'
                    ])->get()->getResultArray();

                foreach($receiptCashRows as $receiptCash){
                    $alreadyReversed=(int)$this->db->table('cash_transactions')
                        ->where([
                            'reference_type'=>'sale_void',
                            'reference_id'=>$saleId,
                            'transaction_type'=>'customer_receipt',
                            'direction'=>'out',
                        ])->like('notes','Reversal of customer receipt '.$receipt['receipt_no'].' ','after')->countAllResults();
                    if($alreadyReversed===0){
                        $this->db->table('cash_transactions')->insert([
                            'cash_session_id'=>$resolveVoidSession(),
                            'transaction_type'=>'customer_receipt',
                            'direction'=>'out',
                            'amount'=>$receiptCash['amount'],
                            'transaction_at'=>date('Y-m-d H:i:s'),
                            'reference_type'=>'sale_void',
                            'reference_id'=>$saleId,
                            'created_by'=>$userId,
                            'notes'=>'Reversal of customer receipt '.$receipt['receipt_no'].' from voided sale '.$sale['sale_no']
                        ]);
                    }
                }
            }

            $this->db->table('sales')->where('id',$saleId)->update(['status'=>'voided','voided_by'=>$userId,'voided_at'=>date('Y-m-d H:i:s'),'void_reason'=>$reason]);
            AuditService::log('VOID','sale',$saleId,
                ['status'=>'posted','sale_no'=>$sale['sale_no'],'transaction_type'=>$sale['transaction_type'],'customer_id'=>$sale['customer_id'],'total_amount'=>(float)$sale['total_amount']],
                ['status'=>'voided','void_reason'=>$reason],$userId,$locationId);
            if(!$this->db->transStatus()) throw new RuntimeException('Sale void failed.');
            $this->db->transCommit();
            $this->releaseInventoryLocks();
            return $sale['sale_no'];
        }catch(\Throwable $e){
            $this->releaseInventoryLocks();
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * A void may only reverse a physical cylinder that is still exactly as the sale left it.
     * If the unit has later inventory activity (sold, issued to custody, refilled, adjusted,
     * wasted) the reversal cannot be reconstructed safely, so the whole void is rejected.
     */
    protected function assertUnitsUnchangedSinceSale(array $movements,string $saleNo): void
    {
        $byUnit=[];
        foreach($movements as $m){
            $unitId=(int)($m['cylinder_unit_id']??0);
            if($unitId<=0) continue;
            $byUnit[$unitId]['last_id']=max((int)($byUnit[$unitId]['last_id']??0),(int)$m['id']);
            $byUnit[$unitId]['types'][]=$m['inventory_type'].'|'.$m['direction'];
        }
        foreach($byUnit as $unitId=>$info){
            $unit=$this->db->query("SELECT id,unit_code,status FROM cylinder_units WHERE id=? FOR UPDATE",[$unitId])->getRowArray();
            if(!$unit) throw new RuntimeException('Sale '.$saleNo.' cannot be voided: a physical cylinder from this sale no longer exists.');
            $later=(int)$this->db->table('inventory_movements')->where('cylinder_unit_id',$unitId)->where('id >',$info['last_id'])->countAllResults();
            $types=$info['types'];
            if(in_array('empty_cylinder|in',$types,true)) $expected='empty';
            elseif(in_array('filled_cylinder|out',$types,true)||in_array('empty_cylinder|out',$types,true)) $expected='sold';
            else $expected='filled';
            if($later>0 || (string)$unit['status']!==$expected){
                throw new RuntimeException('Sale '.$saleNo.' cannot be voided: cylinder '.$unit['unit_code'].' has been used in a later transaction. Correct stock with a stock adjustment instead.');
            }
        }
    }

    public function customerBalance(int $customerId): float
    {
        $locationId=(int)($this->currentLocationId??0);
        if (!$this->customers->findForLocation($customerId,$locationId)) {
            throw new RuntimeException('Customer not found in current shop.');
        }
        $gasBalance=$this->customerGasBalance($customerId);
        $settings=(new ShopSettingsModel())->forLocation($this->currentLocationId ?? 0);
        if((int)($settings['include_security_deposit_in_os']??0)!==1)return $gasBalance;
        return $gasBalance+$this->securityDepositBalance((int)$this->currentLocationId,$customerId);
    }

    protected function acquireInventoryLocks(int $locationId,array $movements): void
    {
        $keys=[];
        foreach($movements as $m){
            $type=(string)($m['type']??$m['inventory_type']??'');
            $typeId=$m['cylinder_type_id']??null;
            if($type==='') throw new RuntimeException('Invalid inventory lock key.');
            $keys[$type.'|'.($typeId===null?'null':(string)$typeId)]=true;
        }
        $keys=array_keys($keys);
        sort($keys,SORT_STRING);
        foreach($keys as $key){
            $lockKey='lpg_inv_'.$locationId.'_'.substr(hash('sha256',$key),0,48);
            $result=$this->db->query("SELECT GET_LOCK(?,5) AS locked",[$lockKey])->getRowArray();
            if((int)($result['locked']??0)!==1) throw new RuntimeException('Inventory is busy; please retry the transaction.');
            $this->inventoryLocks[]=$lockKey;
        }
    }

    protected function releaseInventoryLocks(): void
    {
        foreach(array_reverse($this->creditLocks) as $lockKey){
            $this->db->query("SELECT RELEASE_LOCK(?) AS released",[$lockKey]);
        }
        $this->creditLocks=[];
        foreach(array_reverse($this->inventoryLocks) as $lockKey){
            $this->db->query("SELECT RELEASE_LOCK(?) AS released",[$lockKey]);
        }
        $this->inventoryLocks=[];
    }

    protected function assertStock(int $locationId,string $type,?int $typeId,float $qty,string $direction,string $at): void
 {
  if($direction!=='out') return;

  if($type==='gas_kg'){
   $row=$this->db->table('cylinder_units')
    ->selectSum('gas_weight_kg','qty')
    ->where(['location_id'=>$locationId,'status'=>'filled'])
    ->get()->getRowArray();

   $stock=(float)($row['qty']??0);
   if($stock+0.00001<$qty){
    throw new RuntimeException(
     'Insufficient gas stock. Available gas is derived from filled physical cylinders: ' .
     number_format($stock, 2) . ' KG.'
    );
   }
   return;
  }

  $status=$type==='filled_cylinder'?'filled':($type==='empty_cylinder'?'empty':null);
  if($status===null) throw new RuntimeException('Invalid inventory type.');

  $q=$this->db->table('cylinder_units')->where([
   'location_id'=>$locationId,
   'cylinder_type_id'=>$typeId,
   'status'=>$status
  ])->countAllResults();

  if((float)$q+0.00001<$qty){
   throw new RuntimeException('Insufficient '.$type.' stock.');
  }
 }
}
