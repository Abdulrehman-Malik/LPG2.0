<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;

class Inventory extends Controller
{
    private function guard()
    {
        return \App\Services\PermissionService::allows('INVENTORY_MANAGE')
            ? null
            : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    private function locationId(): int
    {
        return (int) session()->get('location_id');
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;

        $db = Database::connect();
        $locationId = $this->locationId();

        $summary = $db->query(
            "SELECT ct.id, ct.code, ct.name, ct.capacity_kg,
                    SUM(CASE WHEN cu.status='filled' AND cu.gas_weight_kg >= ct.capacity_kg THEN 1 ELSE 0 END) AS filled_count,
                    SUM(CASE WHEN cu.status='filled' AND cu.gas_weight_kg > 0 AND cu.gas_weight_kg < ct.capacity_kg THEN 1 ELSE 0 END) AS partial_count,
                    SUM(CASE WHEN cu.status='empty' OR (cu.status='filled' AND cu.gas_weight_kg <= 0) THEN 1 ELSE 0 END) AS empty_count,
                    COUNT(cu.id) AS total_cylinders,
                    COALESCE(SUM(CASE WHEN cu.status='filled' THEN cu.gas_weight_kg ELSE 0 END),0) AS current_gas_kg,
                    COALESCE(SUM(CASE WHEN cu.status IN ('filled','empty') THEN ct.capacity_kg ELSE 0 END),0) AS total_capacity_kg
             FROM cylinder_types ct
             LEFT JOIN cylinder_units cu
               ON cu.cylinder_type_id=ct.id
              AND cu.location_id=?
              AND cu.status IN ('filled','empty')
             WHERE ct.is_active=1
             GROUP BY ct.id,ct.code,ct.name,ct.capacity_kg
             ORDER BY ct.sort_order,ct.id",
            [$locationId]
        )->getResultArray();

        $totalGas = 0.0;
        $totalCylinders = 0;
        $filled = 0;
        $partial = 0;
        $empty = 0;
        foreach ($summary as $row) {
            $totalGas += (float)$row['current_gas_kg'];
            $totalCylinders += (int)$row['total_cylinders'];
            $filled += (int)$row['filled_count'];
            $partial += (int)$row['partial_count'];
            $empty += (int)$row['empty_count'];
        }

        return view('inventory/index', [
            'title' => 'Inventory',
            'summary' => $summary,
            'totalGas' => $totalGas,
            'totalCylinders' => $totalCylinders,
            'filledCount' => $filled,
            'partialCount' => $partial,
            'emptyCount' => $empty,
        ]);
    }

    public function cylinders(int $typeId)
    {
        if ($r = $this->guard()) return $r;

        $db = Database::connect();
        $locationId = $this->locationId();
        $type = $db->table('cylinder_types')->where('id',$typeId)->where('is_active',1)->get()->getRowArray();
        if (!$type) return $this->response->setStatusCode(404)->setBody('Cylinder type not found.');

        $search = trim((string)$this->request->getGet('q'));
        $status = trim((string)$this->request->getGet('status'));

        $builder = $db->table('cylinder_units cu')
            ->select("cu.id,cu.unit_code,cu.gas_weight_kg,cu.status,cu.created_at,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg,
                      lm.last_activity_at,
                      lm.last_source_type,lm.last_source_id,lm.last_notes")
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->join("(SELECT cylinder_unit_id,MAX(movement_at) last_activity_at,
                            SUBSTRING_INDEX(GROUP_CONCAT(source_type ORDER BY movement_at DESC,id DESC),',',1) last_source_type,
                            SUBSTRING_INDEX(GROUP_CONCAT(source_id ORDER BY movement_at DESC,id DESC),',',1) last_source_id,
                            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(notes,'') ORDER BY movement_at DESC,id DESC SEPARATOR '||'),'||',1) last_notes
                       FROM inventory_movements
                      WHERE cylinder_unit_id IS NOT NULL
                      GROUP BY cylinder_unit_id) lm",'lm.cylinder_unit_id=cu.id','left')
            ->where('cu.location_id',$locationId)
            ->where('cu.cylinder_type_id',$typeId)
            ->whereIn('cu.status',['filled','empty']);

        if ($search !== '') $builder->like('cu.unit_code',$search);
        if (in_array($status,['filled','partial','empty'],true)) {
            if ($status === 'empty') $builder->where("(cu.status='empty' OR (cu.status='filled' AND cu.gas_weight_kg<=0))",null,false);
            elseif ($status === 'partial') $builder->where("cu.status='filled' AND cu.gas_weight_kg>0 AND cu.gas_weight_kg<ct.capacity_kg",null,false);
            else $builder->where("cu.status='filled' AND cu.gas_weight_kg>=ct.capacity_kg",null,false);
        }

        $units = $builder->orderBy('cu.id')->get()->getResultArray();

        return view('inventory/cylinders', [
            'title' => 'Physical Cylinders',
            'type' => $type,
            'units' => $units,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function cylinder(int $unitId)
    {
        if ($r = $this->guard()) return $r;

        $db = Database::connect();
        $locationId = $this->locationId();

        $unit = $db->table('cylinder_units cu')
            ->select('cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg,c.name custody_customer_name')
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->join('customers c','c.id=cu.custody_customer_id','left')
            ->where('cu.id',$unitId)
            ->where('cu.location_id',$locationId)
            ->get()->getRowArray();

        if (!$unit) return $this->response->setStatusCode(404)->setBody('Physical cylinder not found.');

        $fromDate = $this->validDate($this->request->getGet('from_date'),date('Y-m-d'));
        $toDate = $this->validDate($this->request->getGet('to_date'),date('Y-m-d'));
        $allHistory = (string)$this->request->getGet('all_history') === '1';
        if ($fromDate > $toDate) [$fromDate,$toDate]=[$toDate,$fromDate];

        $openingGas = 0.0;
        if (!$allHistory) {
            $opening = $db->query(
                "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity WHEN direction='out' THEN -quantity ELSE 0 END),0) AS opening_gas
                   FROM inventory_movements
                  WHERE location_id=? AND cylinder_unit_id=? AND inventory_type='gas_kg' AND movement_at < ?",
                [$locationId,$unitId,$fromDate.' 00:00:00']
            )->getRowArray();
            $openingGas = max(0.0,(float)($opening['opening_gas']??0));
        }

        $historyQuery = $db->table('inventory_movements im')
            ->select("im.*,s.sale_no,p.purchase_no,ia.adjustment_no,c.name customer_name,u.full_name")
            ->join('sales s',"s.id=im.source_id AND im.source_type IN ('sale','sale_void','security_deposit','cylinder_return')",'left')
            ->join('customers c','c.id=s.customer_id','left')
            ->join('purchases p',"p.id=im.source_id AND im.source_type='purchase'",'left')
            ->join('inventory_adjustments ia',"ia.id=im.source_id AND im.source_type='adjustment'",'left')
            ->join('users u','u.id=im.created_by','left')
            ->where('im.location_id',$locationId)
            ->where('im.cylinder_unit_id',$unitId);
        if (!$allHistory) {
            $historyQuery->where('im.movement_at >=',$fromDate.' 00:00:00')->where('im.movement_at <=',$toDate.' 23:59:59');
        }
        $movements = $historyQuery->orderBy('im.movement_at','ASC')->orderBy('im.id','ASC')->get()->getResultArray();

        $runningGas = $openingGas;
        foreach ($movements as &$movement) {
            if ($movement['inventory_type'] === 'gas_kg') {
                if ($movement['direction'] === 'in') $runningGas += (float)$movement['quantity'];
                elseif ($movement['direction'] === 'out') $runningGas -= (float)$movement['quantity'];
            }
            $movement['running_gas'] = max(0.0,$runningGas);
            $movement['source_reference'] = $this->movementReference($movement);
            $movement['status_after'] = $this->gasStatus($movement['running_gas'],(float)$unit['capacity_kg']);
        }
        unset($movement);

        return view('inventory/cylinder', [
            'title' => 'Cylinder Details',
            'unit' => $unit,
            'movements' => $movements,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'allHistory' => $allHistory,
        ]);
    }

    public function typeHistory(int $typeId)
    {
        if ($r = $this->guard()) return $r;

        $db = Database::connect();
        $locationId = $this->locationId();
        $type = $db->table('cylinder_types')->where('id',$typeId)->where('is_active',1)->get()->getRowArray();
        if (!$type) return $this->response->setStatusCode(404)->setBody('Cylinder type not found.');

        $from = $this->validDate($this->request->getGet('from_date'),date('Y-m-d'));
        $to = $this->validDate($this->request->getGet('to_date'),date('Y-m-d'));
        $allHistory = (string)$this->request->getGet('all_history') === '1';
        if ($from > $to) [$from,$to]=[$to,$from];

        $openingGas = 0.0;
        if (!$allHistory) {
            $opening = $db->query(
                "SELECT COALESCE(SUM(CASE WHEN im.direction='in' THEN im.quantity WHEN im.direction='out' THEN -im.quantity ELSE 0 END),0) AS opening_gas
                   FROM inventory_movements im
                   LEFT JOIN cylinder_units cu ON cu.id=im.cylinder_unit_id
                  WHERE im.location_id=? AND im.inventory_type='gas_kg' AND (cu.cylinder_type_id=? OR im.cylinder_type_id=?) AND im.movement_at < ?",
                [$locationId,$typeId,$typeId,$from.' 00:00:00']
            )->getRowArray();
            $openingGas = max(0.0,(float)($opening['opening_gas']??0));
        }

        $movementsQuery = $db->table('inventory_movements im')
            ->select('im.*,cu.unit_code,s.sale_no,p.purchase_no,ia.adjustment_no')
            ->join('cylinder_units cu','cu.id=im.cylinder_unit_id','left')
            ->join('sales s',"s.id=im.source_id AND im.source_type IN ('sale','sale_void','security_deposit','cylinder_return')",'left')
            ->join('purchases p',"p.id=im.source_id AND im.source_type='purchase'",'left')
            ->join('inventory_adjustments ia',"ia.id=im.source_id AND im.source_type='adjustment'",'left')
            ->where('im.location_id',$locationId)
            ->groupStart()
                ->where('im.cylinder_type_id',$typeId)
                ->orGroupStart()
                    ->where('im.inventory_type','gas_kg')
                    ->where('cu.cylinder_type_id',$typeId)
                ->groupEnd()
            ->groupEnd();

        if (!$allHistory) {
            $movementsQuery->where("im.movement_at >=", $from.' 00:00:00')
                ->where("im.movement_at <=", $to.' 23:59:59');
        }

        $movements = $movementsQuery
            ->orderBy('im.movement_at','ASC')
            ->orderBy('im.id','ASC')
            ->get()->getResultArray();

        $runningGas=$openingGas;
        foreach($movements as &$movement){
            if($movement['inventory_type']==='gas_kg'){
                if($movement['direction']==='in') $runningGas+=(float)$movement['quantity'];
                elseif($movement['direction']==='out') $runningGas-=(float)$movement['quantity'];
            }
            $movement['running_gas']=max(0.0,$runningGas);
            $movement['source_reference']=$this->movementReference($movement);
        }
        unset($movement);

        return view('inventory/type_history',[
            'title'=>'Cylinder Type History',
            'type'=>$type,
            'movements'=>$movements,
            'fromDate'=>$from,
            'toDate'=>$to,
            'allHistory'=>$allHistory,
        ]);
    }

    private function validDate($value,string $default): string
    {
        $value=trim((string)$value);
        if($value==='') return $default;
        $date=\DateTime::createFromFormat('!Y-m-d',$value);
        $errors=\DateTime::getLastErrors();
        $hasErrors=is_array($errors)&&($errors['warning_count']>0||$errors['error_count']>0);
        return $date&&!$hasErrors&&$date->format('Y-m-d')===$value?$value:$default;
    }

    private function movementReference(array $movement): string
    {
        $source=(string)($movement['source_type']??'');
        if(!empty($movement['sale_no'])) return 'Sale '.$movement['sale_no'];
        if(!empty($movement['purchase_no'])) return 'Purchase '.$movement['purchase_no'];
        if($source==='adjustment') return !empty($movement['adjustment_no']) ? 'Stock Adjustment '.$movement['adjustment_no'] : 'Stock Adjustment';
        if($source==='sale_void') return 'Sale Void / Reversal';
        if($source==='security_deposit') return 'Security Deposit';
        if($source==='cylinder_return') return 'Cylinder Return';
        return ucwords(str_replace('_',' ',$source));
    }

    private function gasStatus(float $gas,float $capacity): string
    {
        if($gas<=0.00001) return 'Empty';
        if($gas < $capacity-0.00001) return 'Partially Filled';
        return 'Filled';
    }

    public function adjustments()
    {
        if($r=$this->guard()) return $r;
        $db=Database::connect(); $locationId=$this->locationId();

        $types=$db->query(
            "SELECT ct.id,ct.code,ct.name,ct.capacity_kg,ct.is_active,ct.sort_order,
                    COALESCE(SUM(CASE WHEN cu.status IN ('filled','empty') THEN 1 ELSE 0 END),0) shop_count,
                    COALESCE(SUM(CASE WHEN cu.status='filled' THEN 1 ELSE 0 END),0) shop_filled,
                    COALESCE(SUM(CASE WHEN cu.status='empty' THEN 1 ELSE 0 END),0) shop_empty,
                    COALESCE(SUM(CASE WHEN cu.status='custody' THEN 1 ELSE 0 END),0) issued_count
             FROM cylinder_types ct
             LEFT JOIN cylinder_units cu ON cu.cylinder_type_id=ct.id AND cu.location_id=?
             WHERE ct.is_active=1
             GROUP BY ct.id,ct.code,ct.name,ct.capacity_kg,ct.is_active,ct.sort_order
             HAVING shop_count>0 OR issued_count>0
             ORDER BY ct.sort_order,ct.id",
            [$locationId]
        )->getResultArray();

        $availableTypes=array_values(array_filter($types,static fn($t)=>(int)$t['shop_count']>0));
        $inv=new \App\Services\InventoryService();
        $rows=[];
        $gasStock=(float)$inv->stock($locationId,'gas_kg');
        if($gasStock>0.00001) $rows[]=['type'=>'gas_kg','id'=>'','name'=>'Gas (KG)','stock'=>$gasStock,'class'=>'gas'];
        foreach($types as $t){
            if((int)$t['shop_filled']>0) $rows[]=['type'=>'filled_cylinder','id'=>(int)$t['id'],'name'=>'Filled '.$t['name'],'stock'=>(int)$t['shop_filled'],'class'=>'filled','issued'=>(int)$t['issued_count']];
            if((int)$t['shop_empty']>0) $rows[]=['type'=>'empty_cylinder','id'=>(int)$t['id'],'name'=>'Empty '.$t['name'],'stock'=>(int)$t['shop_empty'],'class'=>'empty','issued'=>(int)$t['issued_count']];
        }

        $units=$db->table('cylinder_units cu')->select('cu.id,cu.unit_code,cu.status,cu.gas_weight_kg,cu.cylinder_type_id,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where('cu.location_id',$locationId)->whereIn('cu.status',['filled','empty'])->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();

        $from=$this->validDate($this->request->getGet('from_date'),date('Y-m-d',strtotime('-30 days')));
        $to=$this->validDate($this->request->getGet('to_date'),date('Y-m-d'));
        if($from>$to)[$from,$to]=[$to,$from];

        $filterType=trim((string)$this->request->getGet('inventory_type'));
        $filterDirection=trim((string)$this->request->getGet('direction'));
        $filterScope=trim((string)$this->request->getGet('adjustment_scope'));
        $filterCylinder=(int)$this->request->getGet('cylinder_type_id');
        $filterUnit=trim((string)$this->request->getGet('unit_code'));
        $search=trim((string)$this->request->getGet('q'));

        $historyQuery=$db->table('inventory_adjustments ia')->select('ia.*,ct.code cylinder_code,ct.name cylinder_name,cu.unit_code source_unit_code,u.full_name')->join('cylinder_types ct','ct.id=ia.cylinder_type_id','left')->join('cylinder_units cu','cu.id=ia.source_cylinder_unit_id','left')->join('users u','u.id=ia.created_by','left')->where('ia.location_id',$locationId)->where('ia.created_at>=',$from.' 00:00:00')->where('ia.created_at<=',$to.' 23:59:59');
        if(in_array($filterType,['gas_kg','filled_cylinder','empty_cylinder'],true))$historyQuery->where('ia.inventory_type',$filterType);
        if(in_array($filterDirection,['in','out'],true))$historyQuery->where('ia.direction',$filterDirection);
        if(in_array($filterScope,['bulk','specific'],true))$historyQuery->where('ia.adjustment_scope',$filterScope);
        if($filterCylinder>0)$historyQuery->where('ia.cylinder_type_id',$filterCylinder);
        if($search!=='')$historyQuery->groupStart()->like('ia.adjustment_no',$search)->orLike('ia.reason',$search)->orLike('ia.notes',$search)->orLike('cu.unit_code',$search)->groupEnd();

        $history=$historyQuery->orderBy('ia.created_at','DESC')->orderBy('ia.id','DESC')->limit(500)->get()->getResultArray();
        foreach($history as &$h){$h['before_state']=json_decode((string)$h['before_state'],true)?:[];$h['after_state']=json_decode((string)$h['after_state'],true)?:[];} unset($h);

        return view('inventory/adjustments',['title'=>'Stock Adjustment & History','rows'=>$rows,'types'=>$availableTypes,'allTypes'=>$types,'units'=>$units,'history'=>$history,'fromDate'=>$from,'toDate'=>$to,'filterType'=>$filterType,'filterDirection'=>$filterDirection,'filterScope'=>$filterScope,'filterCylinder'=>$filterCylinder,'filterUnit'=>$filterUnit,'search'=>$search]);
    }

    public function adjustmentDetails(int $adjustmentId)
    {
        if($r=$this->guard()) return $r;
        $db=Database::connect(); $locationId=$this->locationId();
        $adjustment=$db->table('inventory_adjustments ia')
            ->select('ia.*,ct.code cylinder_code,ct.name cylinder_name,cu.unit_code,u.full_name')
            ->join('cylinder_types ct','ct.id=ia.cylinder_type_id','left')
            ->join('cylinder_units cu','cu.id=ia.source_cylinder_unit_id','left')
            ->join('users u','u.id=ia.created_by','left')
            ->where('ia.id',$adjustmentId)->where('ia.location_id',$locationId)->get()->getRowArray();
        if(!$adjustment) return $this->response->setStatusCode(404)->setBody('Stock adjustment not found.');
        $movements=$db->table('inventory_movements im')->select('im.*,cu.unit_code,u.full_name')->join('cylinder_units cu','cu.id=im.cylinder_unit_id','left')->join('users u','u.id=im.created_by','left')->where('im.source_type','adjustment')->where('im.source_id',$adjustmentId)->where('im.location_id',$locationId)->orderBy('im.id')->get()->getResultArray();
        $adjustment['before_state']=json_decode((string)$adjustment['before_state'],true)?:[];
        $adjustment['after_state']=json_decode((string)$adjustment['after_state'],true)?:[];
        return view('inventory/adjustment_details',['title'=>'Adjustment Details','adjustment'=>$adjustment,'movements'=>$movements]);
    }

    public function adjust(){if($r=$this->guard())return $r;try{$sourceRaw=$this->request->getPost('source_cylinder_unit_id');$sourceId=$sourceRaw!==null&&$sourceRaw!==''?(int)$sourceRaw:null;$adjustmentNo=(new \App\Services\InventoryService())->adjust((int)session()->get('location_id'),(string)$this->request->getPost('inventory_type'),$this->request->getPost('cylinder_type_id')!==''?(int)$this->request->getPost('cylinder_type_id'):null,(float)$this->request->getPost('quantity'),(string)$this->request->getPost('direction'),(int)session()->get('user_id'),$this->request->getPost('notes'),(float)$this->request->getPost('actual_gas_weight_kg'),$sourceId,(string)($this->request->getPost('adjustment_scope')??'bulk'),(string)$this->request->getPost('reason'));return redirect()->to('/inventory/adjustments')->with('success','Inventory adjustment '.$adjustmentNo.' posted successfully.');}catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}}
}
