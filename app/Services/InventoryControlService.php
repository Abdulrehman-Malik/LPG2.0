<?php
namespace App\Services;
use Config\Database;
use RuntimeException;

class InventoryControlService
{
    protected $db;
    public function __construct(){ $this->db=Database::connect(); }

    public function policy(int $locationId, ?int $cylinderTypeId=null): array
    {
        $db=$this->db;
        if($cylinderTypeId){
            $row=$db->table('inventory_policies')->where(['location_id'=>$locationId,'cylinder_type_id'=>$cylinderTypeId,'is_active'=>1])->get()->getRowArray();
            if($row) return $row;
        }
        $row=$db->table('inventory_policies')->where(['location_id'=>$locationId,'cylinder_type_id'=>null,'is_active'=>1])->get()->getRowArray();
        return $row ?: ['stock_validation_enabled'=>1,'wastage_mode'=>'percent','wastage_percent'=>0,'wastage_fixed_kg'=>0];
    }

    public function savePolicy(int $locationId, ?int $cylinderTypeId, bool $validation, string $mode, float $percent, float $fixedKg, int $userId): void
    {
        if(!in_array($mode,['percent','fixed_kg'],true) || $percent<0 || $percent>100 || $fixedKg<0) throw new RuntimeException('Invalid inventory control settings.');
        $data=['stock_validation_enabled'=>$validation?1:0,'wastage_mode'=>$mode,'wastage_percent'=>$percent,'wastage_fixed_kg'=>$fixedKg,'is_active'=>1,'created_by'=>$userId];
        $existing=$this->db->table('inventory_policies')->where(['location_id'=>$locationId,'cylinder_type_id'=>$cylinderTypeId])->get()->getRowArray();
        if($existing) $this->db->table('inventory_policies')->where('id',$existing['id'])->update($data);
        else $this->db->table('inventory_policies')->insert(array_merge($data,['location_id'=>$locationId,'cylinder_type_id'=>$cylinderTypeId]));
    }

    public function recordWastage(int $locationId, int $unitId, float $gasKg, string $reason, int $userId): void
    {
        if($gasKg<=0) throw new RuntimeException('Wastage must be greater than zero.');
        $this->db->transBegin();
        try{
            $unit=$this->db->query('SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? AND cu.location_id=? FOR UPDATE',[$unitId,$locationId])->getRowArray();
            if(!$unit || $unit['status']!=='filled') throw new RuntimeException('Selected cylinder is not an available filled cylinder.');
            $current=(float)$unit['gas_weight_kg'];
            if($gasKg>$current+0.00001) throw new RuntimeException('Wastage cannot exceed the cylinder current gas weight ('.$current.' kg).');
            $policy=$this->policy($locationId,(int)$unit['cylinder_type_id']);
            $allowed=$policy['wastage_mode']==='fixed_kg'?(float)$policy['wastage_fixed_kg']:$current*((float)$policy['wastage_percent']/100);
            if($gasKg>$allowed+0.00001) throw new RuntimeException('Wastage exceeds the configured allowance of '.number_format($allowed, 2).' kg for this cylinder.');
            $remaining=$current-$gasKg;
            $this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>'gas_kg','cylinder_type_id'=>null,'quantity'=>$gasKg,'direction'=>'out','movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'wastage','source_id'=>0,'cylinder_unit_id'=>$unitId,'created_by'=>$userId,'notes'=>$reason]);
            $this->db->table('inventory_wastage_logs')->insert(['location_id'=>$locationId,'cylinder_type_id'=>$unit['cylinder_type_id'],'cylinder_unit_id'=>$unitId,'gas_weight_kg'=>$gasKg,'reason'=>$reason,'created_by'=>$userId]);
            if($remaining<=0.00001){
                $this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>'filled_cylinder','cylinder_type_id'=>$unit['cylinder_type_id'],'quantity'=>1,'direction'=>'out','movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'wastage','source_id'=>0,'cylinder_unit_id'=>$unitId,'created_by'=>$userId,'notes'=>$reason]);
                $this->db->table('inventory_movements')->insert(['location_id'=>$locationId,'inventory_type'=>'empty_cylinder','cylinder_type_id'=>$unit['cylinder_type_id'],'quantity'=>1,'direction'=>'in','movement_at'=>date('Y-m-d H:i:s'),'source_type'=>'wastage','source_id'=>0,'cylinder_unit_id'=>$unitId,'created_by'=>$userId,'notes'=>'Cylinder made empty after wastage: '.$reason]);
                $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'empty','gas_weight_kg'=>0]);
            }else{
                $this->db->table('cylinder_units')->where('id',$unitId)->update(['gas_weight_kg'=>$remaining]);
            }
            if(!$this->db->transStatus()) throw new RuntimeException('Wastage posting failed.');
            $this->db->transCommit();
        }catch(\Throwable $e){$this->db->transRollback();throw $e;}
    }

    public function wastage(int $locationId, ?string $from=null, ?string $to=null, ?int $typeId=null): array
    {
        $q=$this->db->table('inventory_wastage_logs w')->select('w.*,ct.code cylinder_code,ct.name cylinder_name,cu.unit_code,u.full_name')->join('cylinder_types ct','ct.id=w.cylinder_type_id','left')->join('cylinder_units cu','cu.id=w.cylinder_unit_id','left')->join('users u','u.id=w.created_by','left')->where('w.location_id',$locationId);
        if($from) $q->where('w.created_at >=',$from.' 00:00:00'); if($to) $q->where('w.created_at <=',$to.' 23:59:59'); if($typeId) $q->where('w.cylinder_type_id',$typeId);
        return $q->orderBy('w.created_at','DESC')->get()->getResultArray();
    }
}
