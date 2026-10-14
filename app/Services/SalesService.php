<?php

namespace App\Services;

use App\Models\CylinderTypeModel;
use App\Models\CustomerModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use Config\Database;
use RuntimeException;

class SalesService
{
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

    public function __construct()
    {
        $this->currentLocationId=(int)session()->get('location_id');
        $this->db=Database::connect();
        $this->types=new CylinderTypeModel();
        $this->customers=new CustomerModel();
        $this->rates=new GasRateModel();
        $this->cash=new CashService();
        $this->cylinders=new CylinderUnitService();
    }

    protected function nextSaleNo(string $transactionAt,int $locationId): string
    {
        $date=date('Ymd',strtotime($transactionAt));
        $prefix='S'.$date.'-';
        $row=$this->db->query(
            "SELECT MAX(CAST(SUBSTRING(sale_no,11) AS UNSIGNED)) AS max_seq
             FROM sales
             WHERE location_id=? AND sale_no LIKE ?",
            [$locationId,$prefix.'%']
        )->getRowArray();
        $next=((int)($row['max_seq']??0))+1;
        return $prefix.str_pad((string)$next,5,'0',STR_PAD_LEFT);
    }

    /**
     * Internal service entry point for validated customer-order fulfillment.
     * Delivery fees are accepted only through this path, never from a normal POS request.
     */
    public function postForCustomerOrder(array $payload,int $userId,int $locationId,int $orderId,float $deliveryFee): array
    {
        if ($orderId <= 0 || $deliveryFee < 0) {
            throw new RuntimeException('Invalid customer-order fulfillment context.');
        }
        $previous = $this->orderFulfillmentContext;
        $this->orderFulfillmentContext = true;
        $payload['order_id'] = $orderId;
        $payload['delivery_fee_amount'] = round($deliveryFee, 2);
        try {
            return $this->post($payload, $userId, $locationId);
        } finally {
            $this->orderFulfillmentContext = $previous;
        }
    }

    public function post(array $payload,int $userId,int $locationId): array
    {
        $customerId=!empty($payload['customer_id'])?(int)$payload['customer_id']:null;
        $headerType=(string)($payload['transaction_type']??'');
        if(in_array($headerType,['gas_sale','cylinder_sale','security_deposit','cylinder_return'],true)){
            return $this->postHeaderTransaction($payload,$userId,$locationId,$headerType);
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

            $type=$typeId?(new CylinderTypeModel())->find($typeId):null;
            if(in_array($mode,['sell_gas_only','replace_same','sell_filled','replace_different','sell_empty'],true) && !$typeId) throw new RuntimeException('Cylinder type is required on line '.($i+1).'.');
            if($typeId && (!$type || !(int)$type['is_active'])) throw new RuntimeException('Invalid or inactive cylinder type on line '.($i+1).'.');

            $gasRate=0;$cylinderRate=0;$gasKg=0;$standardGasRate=null;$standardCylinderRate=null;
            $emptyReceived=0;$previewUnits=[];

            if(in_array($mode,['sell_gas_only','replace_same','sell_filled','replace_different'],true)){
                $standardGasRate=$this->rates->currentKgRate($transactionAt,$locationId);
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
                $standardCylinderRate=$this->rates->currentCylinderRate($typeId,$transactionAt,$locationId);
                if($standardCylinderRate===null) throw new RuntimeException('No effective cylinder price exists for '.$type['name'].'.');
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
                    $receivedType=$this->types->find($receivedTypeId);
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
        $total=round($subtotal-$discount+$deliveryFee,2);$paymentTotal=0;$credit=0;$cashAmount=0;
        foreach($payments as $p){
            $mode=(string)($p['payment_mode']??'');$amount=(float)($p['amount']??0);
            if(!in_array($mode,['cash','cheque','online','credit'],true)||$amount<=0) throw new RuntimeException('Invalid payment.');
            if(!$customerId && $mode!=='cash') throw new RuntimeException($mode==='credit'?'Credit sale is not allowed for Walk-in / Cash customer. Select an actual customer and enable Allow Credit Sale.':'Walk-in sales are cash only.');
            $paymentTotal+=$amount;if($mode==='credit') $credit+=$amount;if($mode==='cash') $cashAmount+=$amount;
        }
        if(abs($paymentTotal-$total)>0.01) throw new RuntimeException('Payment total must equal sale total.');

        $this->db->transBegin();
        try{
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
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','unit_id'=>$sourceId];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>$sourceId,'transition'=>'to_empty'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in','unit_id'=>$sourceId,'transition'=>'from_filled'];
                    }else{
                        $this->db->table('cylinder_units')->where('id',$sourceId)->update(['gas_weight_kg'=>$after,'status'=>'filled']);
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','unit_id'=>$sourceId];
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
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>(float)$unit['gas_weight_kg'],'direction'=>'out','unit_id'=>(int)$unit['id']];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'transition'=>'sold'];
                    }
                    if(abs($actualGas-(float)$row['gas_weight_kg'])>0.00001) throw new RuntimeException('Filled-cylinder gas weight changed while posting. Please retry the sale.');
                    if($row['received_cylinder_type_id']){
                        for($n=0;$n<(int)$qty;$n++) $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>(int)$row['received_cylinder_type_id'],'quantity'=>1,'direction'=>'in','unit_id'=>null,'transition'=>'new_empty'];
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
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'transition'=>'sold'];
                    }
                }
            }
            unset($row);

            $preparedSubtotal=array_sum(array_map(static fn($r)=>(float)$r['line_total'],$prepared));
            $discount=max(0,(float)($payload['discount_amount']??0));if($discount>$preparedSubtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
            $deliveryFee=(float)($payload['delivery_fee_amount']??0);
            if($deliveryFee<0) throw new RuntimeException('Delivery fee cannot be negative.');
            if($deliveryFee>0 && !$this->orderFulfillmentContext) throw new RuntimeException('A delivery fee can only be posted against a validated customer order.');
            $total=round($preparedSubtotal-$discount+$deliveryFee,2);$paymentTotal=0;$credit=0;$cashAmount=0;
            foreach($payments as $p){
                $mode=(string)$p['payment_mode'];$amount=(float)$p['amount'];$paymentTotal+=$amount;
                if($mode==='credit')$credit+=$amount;if($mode==='cash')$cashAmount+=$amount;
            }
            if(abs($paymentTotal-$total)>0.01) throw new RuntimeException('Payment total must equal the recalculated sale total.');
            if(!$customerId && abs($paymentTotal-$total)>0.01) throw new RuntimeException('Walk-in sale must be fully paid. Received amount must equal sale total.');
            if($customerId && $credit>0.01){
                if(!(int)($customer['allow_credit_sale']??0)) throw new RuntimeException('Credit sale is not allowed for this customer. Enable Allow Credit Sale on the customer record before posting a credit sale.');
                $newBalance=$this->customerBalance($customerId)+$credit;
                if($newBalance>(float)$customer['credit_limit']+0.01) throw new RuntimeException('Customer credit limit exceeded.');
            }

            $saleNo=$this->nextSaleNo($transactionAt,$locationId);
            $scenarioTypes=array_values(array_unique(array_column($prepared,'sale_mode')));
            $transactionType=count($scenarioTypes)>1?'mixed':match($scenarioTypes[0]){
                'sell_gas_only'=>'refill_service','replace_same'=>'cylinder_exchange','sell_filled'=>'filled_cylinder',
                'replace_different'=>'mixed','sell_empty'=>'empty_sale'
            };
            $this->db->table('sales')->insert(['sale_no'=>$saleNo,'location_id'=>$locationId,'customer_id'=>$customerId,'transaction_type'=>$transactionType,'status'=>'posted','transaction_at'=>$transactionAt,'total_kg'=>array_sum(array_map(static fn($r)=>(float)$r['gas_weight_kg'],$prepared)),'subtotal'=>$preparedSubtotal,'discount_amount'=>$discount,'delivery_fee_amount'=>$deliveryFee,'total_amount'=>$total,'credit_amount'=>$credit,'custom_rate_flag'=>$customRate?1:0,'notes'=>$notes,'created_by'=>$userId]);
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

            foreach($inventory as $idx=>$m){
                $this->db->table('inventory_movements')->insert([
                    'location_id'=>$locationId,'inventory_type'=>$m['type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                    'quantity'=>$m['quantity'],'direction'=>$m['direction'],'movement_at'=>$transactionAt,'source_type'=>'sale',
                    'source_id'=>$saleId,'source_line_id'=>$lineIds[(int)$m['line_no']]??null,'cylinder_unit_id'=>$m['unit_id']??null,
                    'created_by'=>$userId,'notes'=>($m['transition']??null)
                ]);
                $inventory[$idx]['movement_id']=(int)$this->db->insertID();
            }

            foreach($inventory as $m){
                if($m['type']==='empty_cylinder' && $m['direction']==='in' && empty($m['unit_id'])){
                    $ids=$this->cylinders->createUnits($locationId,(int)$m['cylinder_type_id'],1,'empty',0,$userId,'sale',$saleId);
                    $this->db->table('inventory_movements')->where('id',$m['movement_id'])->update(['cylinder_unit_id'=>$ids[0]]);
                }
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
        $deposit=max(0,(float)($payload['security_deposit_amount']??0));
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
            $type=$this->types->find($typeId);
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

            $standardRate=$this->rates->currentKgRate($transactionAt,$locationId);
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
                $lineTotal=$enteredAmount;
            }else{
                if($qty<=0) throw new RuntimeException('Gas quantity on line '.$n.' must be greater than zero.');
                $gasRate=$gasRateInput??$standardRate;
                if($gasRate===null || $gasRate<=0){
                    throw new RuntimeException($standardRate===null
                        ? 'No effective gas/kg rate exists. Enter a positive Gas Rate for this line or configure an effective rate in Gas Rate Settings.'
                        : 'Gas rate must be greater than zero on line '.$n.'.');
                }
                $lineTotal=$qty*$gasRate;
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
        $total=$subtotal-$discount;

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

                    $movementRows=[['type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$used,'direction'=>'out','unit_id'=>(int)$source['id'],'notes'=>($allowSourceSelection?'Gas sold from selected filled cylinder':'Gas auto-allocated from filled cylinder in sequence')]];
                    if($newStatus==='empty'){
                        $movementRows[]=['type'=>'filled_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'out','unit_id'=>(int)$source['id'],'notes'=>'Cylinder became empty after gas sale'];
                        $movementRows[]=['type'=>'empty_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'in','unit_id'=>(int)$source['id'],'notes'=>'Cylinder became empty after gas sale'];
                    }
                    foreach($movementRows as $m){
                        $this->db->table('inventory_movements')->insert([
                            'location_id'=>$locationId,'inventory_type'=>$m['type'],'cylinder_type_id'=>$m['cylinder_type_id'],
                            'quantity'=>$m['quantity'],'direction'=>$m['direction'],'movement_at'=>$transactionAt,
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
            $type=$this->types->find($typeId);
            if(!$type||!(int)$type['is_active']) throw new RuntimeException('Invalid or inactive cylinder type on line '.$n.'.');

            $gasKg=0;$gasRate=0;$stdGas=null;
            // Cylinder Sale pricing priority: a positive rate configured against this cylinder type
            // overrides the Cylinder Type master price. A zero/negative configured rate means
            // no rate is defined there, so fall back to the Cylinder Type empty-cylinder price.
            $configuredCylinderRate=$this->rates->currentCylinderRate($typeId,$transactionAt,$locationId);
            $configuredCylinderRate=($configuredCylinderRate!==null && $configuredCylinderRate>0)?$configuredCylinderRate:null;
            $masterCylinderRate=(float)($type['empty_cylinder_price']??0);
            $stdCylinderRate=$configuredCylinderRate??$masterCylinderRate;
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
                $stdGas=$this->rates->currentKgRate($transactionAt,$locationId);
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
                    if(count($rows)<$limit) throw new RuntimeException('Insufficient empty cylinders of '.$this->types->find($row['type_id'])['name'].'.');
                    $row['gas_kg']=0;$row['selected_codes']=[];
                    foreach($rows as $unit){
                        if(isset($reservedEmptyIds[(int)$unit['id']])) throw new RuntimeException('Duplicate empty cylinder allocation detected.');
                        $reservedEmptyIds[(int)$unit['id']]=true;
                        $row['selected_codes'][]=$unit['unit_code'];
                        $inventory[]=['line_no'=>$row['line_no'],'type'=>'empty_cylinder','cylinder_type_id'=>$row['type_id'],'quantity'=>1,'direction'=>'out','unit_id'=>(int)$unit['id'],'notes'=>'Empty cylinder sold'];
                    }
                }

                $lineTotal=$row['gas_kg']*$row['gas_rate']+$row['quantity']*$row['cyl_rate'];
                $row['line_total']=$lineTotal;
                $lineAmounts[]=$lineTotal;$subtotal+=$lineTotal;$totalKg+=(float)$row['gas_kg'];
            }
            unset($row);

            $discount=max(0,(float)($payload['discount_amount']??0));
            if($discount>$subtotal) throw new RuntimeException('Discount cannot exceed subtotal.');
            $total=$subtotal-$discount;

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
            $lineTotal=$cfg['status']==='filled'?($gas*$gasRate+$cylRate):$cylRate;
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
        $this->db->transBegin();
        try{
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
            foreach($selected as $id){
                $locked=$this->db->query("SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id JOIN cylinder_custody cc ON cc.cylinder_unit_id=cu.id AND cc.status='issued' WHERE cu.id=? AND cc.customer_id=? FOR UPDATE",[$id,$customerId])->getRowArray();
                if(!$locked)throw new RuntimeException('Cylinder selection changed while posting. Refresh and retry.');
            }
            $customer=$this->db->query("SELECT * FROM customers WHERE id=? AND location_id=? FOR UPDATE",[$customerId,$locationId])->getRowArray();
            if(!$customer||!(int)$customer['is_active'])throw new RuntimeException('Customer is unavailable.');

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
            $this->db->transCommit();
            return ['id'=>$saleId,'sale_no'=>$saleNo,'total'=>0,'customer_id'=>$customerId,'credit_amount'=>0];
        }catch(\Throwable $e){$this->db->transRollback();throw $e;}
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

    protected function shopOutstanding(int $locationId): float
    {
        $sql="SELECT COALESCE(SUM(CASE WHEN (c.opening_balance+COALESCE(s.credit,0)+COALESCE(s.return_ledger,0)-COALESCE(r.paid,0))>0 THEN (c.opening_balance+COALESCE(s.credit,0)+COALESCE(s.return_ledger,0)-COALESCE(r.paid,0)) ELSE 0 END),0) AS shop_os
              FROM customers c
              LEFT JOIN (SELECT customer_id,SUM(credit_amount) credit,SUM(return_gas_ledger_amount) return_ledger FROM sales WHERE location_id=? AND status='posted' AND customer_id IS NOT NULL GROUP BY customer_id) s ON s.customer_id=c.id
              LEFT JOIN (SELECT customer_id,SUM(amount) paid FROM customer_receipts WHERE location_id=? AND status='posted' GROUP BY customer_id) r ON r.customer_id=c.id";
        $row=$this->db->query($sql,[$locationId,$locationId])->getRowArray();
        return (float)($row['shop_os']??0);
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
            $no='R'.date('YmdHis').'-'.random_int(100,999);
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
        $row=$this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END),0) AS balance
             FROM customer_security_deposits WHERE location_id=? AND customer_id=?",
            [$locationId,$customerId]
        )->getRowArray();
        return max(0,(float)($row['balance']??0));
    }

    protected function customerGasBalance(int $customerId): float
    {
        $s=$this->db->table('sales')->selectSum('credit_amount','credit')->selectSum('return_gas_ledger_amount','return_ledger')
            ->where('customer_id',$customerId)->where('status','posted')->where('location_id',$this->currentLocationId)->get()->getRowArray();
        $r=$this->db->table('customer_receipts')->selectSum('amount','paid')
            ->where('customer_id',$customerId)->where('status','posted')->where('location_id',$this->currentLocationId)->get()->getRowArray();
        $c=$this->customers->find($customerId);
        return (float)($c['opening_balance']??0)+(float)($s['credit']??0)+(float)($s['return_ledger']??0)-(float)($r['paid']??0);
    }

    protected function paymentCash(array $payments): float {return array_sum(array_map(static fn($p)=>(string)($p['payment_mode'])==='cash'?(float)$p['amount']:0,$payments));}
    protected function paymentCredit(array $payments): float {return array_sum(array_map(static fn($p)=>(string)($p['payment_mode'])==='credit'?(float)$p['amount']:0,$payments));}
    protected function paymentCustom(array $rows): int {return array_reduce($rows,static fn($c,$r)=>$c||(int)($r['custom_rate_flag']??0)?1:0,0);}

    public function void(int $saleId,int $userId,int $locationId,string $reason): string
    {
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
            foreach($cashRows as $cash){
                $this->db->table('cash_transactions')->insert([
                    'cash_session_id'=>$cash['cash_session_id'],'transaction_type'=>'sale_cash','direction'=>'out',
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
                            'cash_session_id'=>$receiptCash['cash_session_id']
                        ])->where('notes','Reversal of customer receipt '.$receipt['receipt_no'])->countAllResults();
                    if($alreadyReversed===0){
                        $this->db->table('cash_transactions')->insert([
                            'cash_session_id'=>$receiptCash['cash_session_id'],
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
