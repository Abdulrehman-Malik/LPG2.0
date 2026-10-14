<?php
namespace App\Services;
use Config\Database;
use RuntimeException;

class InventoryService{
 protected $db;
 protected $cylinders;
 public function __construct(){ $this->db=Database::connect(); $this->cylinders=new CylinderUnitService(); }

 public function stock(int $locationId,string $type,?int $typeId=null,?string $at=null):float{
  /*
   * Physical cylinder units are the authoritative current inventory state.
   * Gas is never an independent stock bucket: available gas is always the
   * sum of actual gas weight on company-owned filled cylinders.
   *
   * inventory_movements remains the immutable transaction/audit history.
   * The optional $at parameter is retained for compatibility.
   */
  if($type==='gas_kg'){
   $row=$this->db->table('cylinder_units')
      ->selectSum('gas_weight_kg','q')
      ->where(['location_id'=>$locationId,'status'=>'filled'])
      ->get()->getRowArray();
   return (float)($row['q']??0);
  }

  if(in_array($type,['filled_cylinder','empty_cylinder'],true)){
   if($typeId===null) return 0;
   $status=$type==='filled_cylinder'?'filled':'empty';
   return (float)$this->db->table('cylinder_units')
      ->where(['location_id'=>$locationId,'cylinder_type_id'=>$typeId,'status'=>$status])
      ->countAllResults();
  }

  throw new RuntimeException('Invalid inventory type.');
 }

 public function receivePurchase(int $locationId,string $type,?int $typeId,float $qty,float $actualGasWeight,int $purchaseId,int $userId,?int $purchaseLineId=null): void
 {
  if($qty<=0) throw new RuntimeException('Purchase quantity must be greater than zero.');
  if(!in_array($type,['gas_kg','filled_cylinder','empty_cylinder'],true)) throw new RuntimeException('Invalid purchase inventory type.');

  if($type==='gas_kg'){
   if($typeId!==null) throw new RuntimeException('Gas purchase does not use a cylinder type.');
   $this->db->table('inventory_movements')->insert([
    'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,
    'quantity'=>$qty,'direction'=>'in','movement_at'=>date('Y-m-d H:i:s'),
    'source_type'=>'purchase','source_id'=>$purchaseId,'source_line_id'=>$purchaseLineId,'created_by'=>$userId,
    'notes'=>'Stock received through purchase'
   ]);
   if(!$this->db->transStatus()) throw new RuntimeException('Gas inventory posting failed.');
   return;
  }

  if($typeId===null) throw new RuntimeException('Cylinder type is required for cylinder purchase.');
  if(floor($qty)!==$qty) throw new RuntimeException('Cylinder purchase quantity must be a whole number.');

  $ct=$this->db->table('cylinder_types')->where('id',$typeId)->get()->getRowArray();
  if(!$ct || !(int)$ct['is_active']) throw new RuntimeException('Invalid or inactive cylinder type.');

  $qtyInt=(int)$qty;
  $status=$type==='filled_cylinder'?'filled':'empty';
  $gasPerUnit=$type==='filled_cylinder'?$actualGasWeight:0;

  if($type==='filled_cylinder' && ($gasPerUnit<=0 || $gasPerUnit>(float)$ct['capacity_kg'])){
   throw new RuntimeException('Actual gas weight must be greater than zero and cannot exceed cylinder capacity.');
  }

  $unitIds=$this->cylinders->createUnits(
   $locationId,$typeId,$qtyInt,$status,$gasPerUnit,$userId,'purchase',$purchaseId
  );

  foreach($unitIds as $unitId){
   $this->db->table('inventory_movements')->insert([
    'location_id'=>$locationId,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,
    'quantity'=>1,'direction'=>'in','movement_at'=>date('Y-m-d H:i:s'),
    'source_type'=>'purchase','source_id'=>$purchaseId,'source_line_id'=>$purchaseLineId,'cylinder_unit_id'=>$unitId,
    'created_by'=>$userId,'notes'=>'Physical cylinder received through purchase'
   ]);

   if($type==='filled_cylinder'){
    $this->db->table('inventory_movements')->insert([
     'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,
     'quantity'=>$gasPerUnit,'direction'=>'in','movement_at'=>date('Y-m-d H:i:s'),
     'source_type'=>'purchase','source_id'=>$purchaseId,'source_line_id'=>$purchaseLineId,'cylinder_unit_id'=>$unitId,
     'created_by'=>$userId,'notes'=>'Gas contained in purchased filled cylinder'
    ]);
   }
  }

  if(!$this->db->transStatus()){
   $error=$this->db->error();
   $message=trim((string)($error['message']??''));
   throw new RuntimeException('Purchase inventory posting failed.'.($message!==''?' '.$message:''));
  }
 }

 public function adjust(int $locationId,string $type,?int $typeId,float $qty,string $direction,int $userId,string $notes='',float $actualGasWeight=0,?int $sourceCylinderUnitId=null,string $adjustmentScope='bulk',string $reason=''):string{
  $reason=trim($reason); if($reason==='') throw new RuntimeException('Adjustment reason is required.'); if(mb_strlen($reason)>120) throw new RuntimeException('Adjustment reason is too long.');
  if($qty<=0||!in_array($direction,['in','out'],true)) throw new RuntimeException('Invalid inventory adjustment.');
  if(!in_array($type,['gas_kg','filled_cylinder','empty_cylinder'],true)) throw new RuntimeException('Invalid inventory type.');
  if(!in_array($adjustmentScope,['bulk','specific'],true)) throw new RuntimeException('Invalid adjustment scope.');
  if($adjustmentScope==='specific' && $sourceCylinderUnitId===null) throw new RuntimeException('Select a specific source cylinder for this adjustment.');
  if($adjustmentScope==='bulk' && $sourceCylinderUnitId!==null) throw new RuntimeException('Specific source cylinder can only be used with specific adjustment scope.');

  if($adjustmentScope==='bulk'){
   $adjustmentId=null;
   $beforeState=null;
   if($type==='gas_kg'){
    throw new RuntimeException('Gas is not an independent stock item. Select Specific — Physical Cylinder and adjust the gas weight on that cylinder.');
   }

   if(!$typeId) throw new RuntimeException('Cylinder type is required.');
   if(floor($qty)!==$qty) throw new RuntimeException('Cylinder quantity must be a whole number.');
   $qtyInt=(int)$qty;
   $ct=$this->db->table('cylinder_types')->where('id',$typeId)->get()->getRowArray();
   if(!$ct || !(int)$ct['is_active']) throw new RuntimeException('Invalid or inactive cylinder type.');

   $this->db->transBegin();
   try{
    $beforeState=$this->adjustmentStockSnapshot($locationId,$type,$typeId,$sourceCylinderUnitId);
    $this->db->table('inventory_adjustments')->insert([
      'location_id'=>$locationId,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'adjustment_scope'=>'bulk','direction'=>$direction,
      'quantity'=>$qty,'actual_gas_weight_kg'=>$actualGasWeight,'source_cylinder_unit_id'=>null,'before_state'=>json_encode($beforeState),
      'reason'=>$reason,'notes'=>$notes!==''?$notes:null,'status'=>'posted','created_by'=>$userId
    ]);
    $adjustmentId=(int)$this->db->insertID();
    $adjustmentNo='ADJ-'.str_pad((string)$adjustmentId,6,'0',STR_PAD_LEFT);
    $this->db->table('inventory_adjustments')->where('id',$adjustmentId)->update(['adjustment_no'=>$adjustmentNo]);
    if($direction==='in'){
     $gasPerUnit=$type==='filled_cylinder'?$actualGasWeight:0;
     if($type==='filled_cylinder'){
      if($gasPerUnit<=0 || $gasPerUnit>(float)$ct['capacity_kg']) throw new RuntimeException('Actual gas weight must be greater than zero and cannot exceed cylinder capacity.');
     }
     $unitIds=$this->cylinders->createUnits($locationId,$typeId,$qtyInt,$type==='filled_cylinder'?'filled':'empty',$gasPerUnit,$userId,'adjustment',$adjustmentId);
     foreach($unitIds as $unitId){
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in',
       'movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$unitId,
       'created_by'=>$userId,'notes'=>$notes?:'Manual cylinder stock adjustment'
      ]);
      if($type==='filled_cylinder'){
       $this->db->table('inventory_movements')->insert([
        'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$gasPerUnit,'direction'=>'in',
        'movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$unitId,
        'created_by'=>$userId,'notes'=>'Gas contained in adjusted filled cylinder'.($notes?' — '.$notes:'')
       ]);
      }
     }
    }else{
     $units=$this->cylinders->available($locationId,$typeId,$type==='filled_cylinder'?'filled':'empty');
     if(count($units)<$qtyInt) throw new RuntimeException('Insufficient '.$type.' stock.');
     foreach(array_slice($units,0,$qtyInt) as $unit){
      $unitId=(int)$unit['id'];
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
       'movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$unitId,
       'created_by'=>$userId,'notes'=>$notes?:'Manual cylinder stock adjustment'
      ]);
      if($type==='filled_cylinder'){
       $gas=(float)$unit['gas_weight_kg'];
       if($gas>0) $this->db->table('inventory_movements')->insert([
        'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$gas,'direction'=>'out',
        'movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$unitId,
        'created_by'=>$userId,'notes'=>'Gas removed with adjusted filled cylinder'.($notes?' — '.$notes:'')
       ]);
      }
      $this->cylinders->markSold($unitId);
     }
    }
    if(!$this->db->transStatus()) throw new RuntimeException('Inventory adjustment failed.');
    $afterState=$this->adjustmentStockSnapshot($locationId,$type,$typeId,null);
    $this->db->table('inventory_adjustments')->where('id',$adjustmentId)->update(['after_state'=>json_encode($afterState)]);
    $this->db->transCommit();
    AuditService::log('CREATE','inventory_adjustment',$adjustmentId,['adjustment_no'=>$adjustmentNo,'before_state'=>$beforeState],['adjustment_no'=>$adjustmentNo,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'quantity'=>$qty,'direction'=>$direction,'actual_gas_weight_kg'=>$actualGasWeight,'reason'=>$reason,'notes'=>$notes,'adjustment_scope'=>'bulk','after_state'=>$afterState],$userId,$locationId);
   }catch(\Throwable $e){$this->db->transRollback();throw $e;}
   return $adjustmentNo;
  }

  if($type!=='gas_kg' && (floor($qty*100)!==$qty*100)) throw new RuntimeException('Cylinder quantity can have up to 2 decimal places.');
  if($adjustmentScope==='specific' && $type!=='gas_kg' && abs($qty-1)>0.00001) throw new RuntimeException('A specific-cylinder adjustment can affect only one cylinder at a time.');

  $this->db->transBegin();
  try{
   $unit=$this->db->query('SELECT cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? AND cu.location_id=? FOR UPDATE',[$sourceCylinderUnitId,$locationId])->getRowArray();
   if(!$unit) throw new RuntimeException('Selected source cylinder was not found in this branch.');
   if($typeId===null || (int)$unit['cylinder_type_id']!==$typeId) throw new RuntimeException('Selected source cylinder does not match the selected cylinder type.');
   if($unit['status']==='custody' || $unit['status']==='sold') throw new RuntimeException('Selected cylinder is not available for stock adjustment.');
   $now=date('Y-m-d H:i:s');
   $note=$notes!==''?$notes:'Specific physical cylinder stock adjustment';
   $beforeState=['unit_code'=>$unit['unit_code'],'status'=>$unit['status'],'gas_weight_kg'=>(float)$unit['gas_weight_kg'],'capacity_kg'=>(float)$unit['capacity_kg']];
   $this->db->table('inventory_adjustments')->insert([
    'location_id'=>$locationId,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'adjustment_scope'=>'specific','direction'=>$direction,
    'quantity'=>$qty,'actual_gas_weight_kg'=>$actualGasWeight,'source_cylinder_unit_id'=>$sourceCylinderUnitId,'before_state'=>json_encode($beforeState),
    'reason'=>$reason,'notes'=>$notes!==''?$notes:null,'status'=>'posted','created_by'=>$userId
   ]);
   $adjustmentId=(int)$this->db->insertID(); $adjustmentNo='ADJ-'.str_pad((string)$adjustmentId,6,'0',STR_PAD_LEFT);
   $this->db->table('inventory_adjustments')->where('id',$adjustmentId)->update(['adjustment_no'=>$adjustmentNo]);

   if($type==='gas_kg'){
    if($direction==='out'){
     if($unit['status']!=='filled') throw new RuntimeException('Gas KG decrease requires a filled source cylinder.');
     $current=(float)$unit['gas_weight_kg'];
     if($qty>$current+0.00001) throw new RuntimeException('Gas quantity cannot exceed the selected cylinder current gas weight ('.number_format($current, 2).' KG).');
     $remaining=$current-$qty;
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$qty,'direction'=>'out',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>$note
     ]);
     if($remaining<=0.00001){
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
       'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
       'created_by'=>$userId,'notes'=>'Selected cylinder became empty after gas adjustment: '.$note
      ]);
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in',
       'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
       'created_by'=>$userId,'notes'=>'Selected cylinder moved to empty after gas adjustment: '.$note
      ]);
      $this->db->table('cylinder_units')->where('id',$sourceCylinderUnitId)->update(['status'=>'empty','gas_weight_kg'=>0]);
     }else{
      $this->db->table('cylinder_units')->where('id',$sourceCylinderUnitId)->update(['gas_weight_kg'=>$remaining]);
     }
    }else{
     if(!in_array($unit['status'],['filled','empty'],true)) throw new RuntimeException('Gas KG increase requires an available filled or empty cylinder.');
     $current=(float)$unit['gas_weight_kg'];$capacity=(float)$unit['capacity_kg'];$after=$current+$qty;
     if($after>$capacity+0.00001) throw new RuntimeException('Gas quantity exceeds the selected cylinder capacity. Available capacity: '.number_format(max(0,$capacity-$current), 2).' KG.');
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$qty,'direction'=>'in',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>$note
     ]);
     if($unit['status']==='empty'){
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
       'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
       'created_by'=>$userId,'notes'=>'Selected empty cylinder filled by gas adjustment: '.$note
      ]);
      $this->db->table('inventory_movements')->insert([
       'location_id'=>$locationId,'inventory_type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in',
       'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
       'created_by'=>$userId,'notes'=>'Selected cylinder became filled after gas adjustment: '.$note
      ]);
      $this->db->table('cylinder_units')->where('id',$sourceCylinderUnitId)->update(['status'=>'filled','gas_weight_kg'=>$after]);
     }else{
      $this->db->table('cylinder_units')->where('id',$sourceCylinderUnitId)->update(['gas_weight_kg'=>$after]);
     }
    }
   }elseif($type==='filled_cylinder'){
    if($direction==='in'){
     if($unit['status']!=='empty') throw new RuntimeException('Filled-cylinder increase can only use an empty physical cylinder.');
     if($actualGasWeight<=0 || $actualGasWeight>(float)$unit['capacity_kg']) throw new RuntimeException('Actual gas weight must be greater than zero and cannot exceed cylinder capacity.');
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>'Selected empty cylinder refilled: '.$note
     ]);
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'in',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>'Selected cylinder became filled after refill adjustment: '.$note
     ]);
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$actualGasWeight,'direction'=>'in',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>'Gas added to selected filled cylinder: '.$note
     ]);
     $this->db->table('cylinder_units')->where('id',$sourceCylinderUnitId)->update(['status'=>'filled','gas_weight_kg'=>$actualGasWeight]);
    }else{
     if($unit['status']!=='filled') throw new RuntimeException('Filled-cylinder decrease requires a filled source cylinder.');
     $gas=(float)$unit['gas_weight_kg'];
     $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'filled_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>$note
     ]);
     if($gas>0) $this->db->table('inventory_movements')->insert([
      'location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$gas,'direction'=>'out',
      'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
      'created_by'=>$userId,'notes'=>'Gas removed with selected filled cylinder: '.$note
     ]);
     $this->cylinders->markSold($sourceCylinderUnitId);
    }
   }elseif($type==='empty_cylinder'){
    if($direction==='in') throw new RuntimeException('A specific empty-cylinder increase cannot use an existing source cylinder. Use Bulk / Cylinder Type adjustment to add new empty stock.');
    if($unit['status']!=='empty') throw new RuntimeException('Empty-cylinder decrease requires an empty source cylinder.');
    $this->db->table('inventory_movements')->insert([
     'location_id'=>$locationId,'inventory_type'=>'empty_cylinder','cylinder_type_id'=>$typeId,'quantity'=>1,'direction'=>'out',
     'movement_at'=>$now,'source_type'=>'adjustment','source_id'=>$adjustmentId,'cylinder_unit_id'=>$sourceCylinderUnitId,
     'created_by'=>$userId,'notes'=>$note
    ]);
    $this->cylinders->markSold($sourceCylinderUnitId);
   }

   if(!$this->db->transStatus()) throw new RuntimeException('Specific inventory adjustment failed.');
   $afterUnit=$this->db->table('cylinder_units cu')
    ->select('cu.unit_code,cu.status,cu.gas_weight_kg,ct.capacity_kg')
    ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
    ->where('cu.id',$sourceCylinderUnitId)
    ->get()->getRowArray();
   if(!$afterUnit) throw new RuntimeException('Selected source cylinder could not be read after the adjustment.');
   $afterState=['unit_code'=>$afterUnit['unit_code'],'status'=>$afterUnit['status'],'gas_weight_kg'=>(float)$afterUnit['gas_weight_kg'],'capacity_kg'=>(float)$afterUnit['capacity_kg']];
   $this->db->table('inventory_adjustments')->where('id',$adjustmentId)->update(['after_state'=>json_encode($afterState)]);
   $this->db->transCommit();
   AuditService::log('CREATE','inventory_adjustment',$adjustmentId,['adjustment_no'=>$adjustmentNo,'before_state'=>$beforeState],['adjustment_no'=>$adjustmentNo,'inventory_type'=>$type,'cylinder_type_id'=>$typeId,'quantity'=>$qty,'direction'=>$direction,'actual_gas_weight_kg'=>$actualGasWeight,'source_cylinder_unit_id'=>$sourceCylinderUnitId,'adjustment_scope'=>'specific','reason'=>$reason,'notes'=>$notes,'after_state'=>$afterState],$userId,$locationId);
   return $adjustmentNo;
  }catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }

 protected function adjustmentStockSnapshot(int $locationId,string $type,?int $typeId,?int $unitId=null): array
 {
  if($unitId!==null){
   $u=$this->db->table('cylinder_units cu')->select('cu.unit_code,cu.status,cu.gas_weight_kg,ct.capacity_kg')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where('cu.id',$unitId)->where('cu.location_id',$locationId)->get()->getRowArray();
   return $u ? ['unit_code'=>$u['unit_code'],'status'=>$u['status'],'gas_weight_kg'=>(float)$u['gas_weight_kg'],'capacity_kg'=>(float)$u['capacity_kg']] : [];
  }
  if($type==='gas_kg') return ['gas_kg'=>$this->stock($locationId,'gas_kg')];
  if($typeId===null) return [];
  $status=$type==='filled_cylinder'?'filled':'empty';
  $count=$this->db->table('cylinder_units')->where(['location_id'=>$locationId,'cylinder_type_id'=>$typeId,'status'=>$status])->countAllResults();
  $gas=(float)($this->db->table('cylinder_units')->selectSum('gas_weight_kg','q')->where(['location_id'=>$locationId,'cylinder_type_id'=>$typeId,'status'=>'filled'])->get()->getRow('q')??0);
  return ['cylinder_count'=>(int)$count,'gas_kg'=>$gas];
 }


}
