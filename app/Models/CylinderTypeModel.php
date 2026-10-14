<?php
namespace App\Models;
use CodeIgniter\Model;
use RuntimeException;
class CylinderTypeModel extends Model
{
    protected $table='cylinder_types'; protected $primaryKey='id'; protected $returnType='array';
    protected $useTimestamps=true; protected $createdField='created_at'; protected $updatedField='updated_at';
    protected $allowedFields=['code','name','capacity_kg','tare_weight_kg','empty_cylinder_price','is_active','sort_order'];
    public function activeOrdered(): array { return $this->where('is_active',1)->orderBy('sort_order')->findAll(); }
    public function deleteIfUnused(int $id): bool
    {
        $row=$this->find($id); if(!$row) throw new RuntimeException('Cylinder type not found.');
        $db=$this->db;
        $stock=(int)$db->table('cylinder_units')->where('cylinder_type_id',$id)->whereIn('status',['filled','empty'])->countAllResults();
        if($stock>0) throw new RuntimeException('This cylinder type cannot be deleted because '.$stock.' physical cylinder(s) are currently in stock. Deactivate it instead.');
        foreach([['inventory_opening_balances','opening inventory'],['inventory_movements','inventory movement history'],['sale_items','sales history'],['purchase_items','purchase history'],['rate_cards','rate cards'],['inventory_policies','inventory control policies'],['inventory_wastage_logs','wastage history']] as [$table,$label]){
            if($db->table($table)->where('cylinder_type_id',$id)->countAllResults()>0) throw new RuntimeException('This cylinder type cannot be deleted because it has '.$label.'. Deactivate it instead.');
        }
        return (bool)$this->delete($id);
    }
}