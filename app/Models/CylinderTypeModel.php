<?php
namespace App\Models;
use CodeIgniter\Model;
use RuntimeException;
class CylinderTypeModel extends TenantModel
{
    protected $table='cylinder_types'; protected $primaryKey='id'; protected $returnType='array';
    protected $useTimestamps=true; protected $createdField='created_at'; protected $updatedField='updated_at';
    // BL-030: a deleted master keeps its row (history and sync); lists and finds skip it.
    protected $useSoftDeletes=true; protected $deletedField='deleted_at';
    protected $allowedFields=['business_id','code','name','capacity_kg','tare_weight_kg','empty_cylinder_price','deposit_amount','is_active','sort_order'];
    /** Active types of a business (default: the business of the current branch). */
    public function activeOrdered(?int $businessId=null): array { return $this->where('business_id',$businessId ?? \App\Services\BusinessContext::currentBusinessId())->where('is_active',1)->orderBy('sort_order')->findAll(); }
    /** All types of a business (default: the business of the current branch), for management screens. */
    public function forBusiness(?int $businessId=null): array { return $this->where('business_id',$businessId ?? \App\Services\BusinessContext::currentBusinessId())->orderBy('sort_order')->orderBy('capacity_kg')->findAll(); }
    public function deleteIfUnused(int $id): bool
    {
        $row=$this->find($id); if(!$row) throw new RuntimeException('Cylinder type not found.');
        $db=$this->db;
        $stock=(int)$db->table('cylinder_units')->where('cylinder_type_id',$id)->whereIn('status',['filled','empty'])->countAllResults();
        if($stock>0) throw new RuntimeException('This cylinder type cannot be deleted because '.$stock.' physical cylinder(s) are currently in stock. Deactivate it instead.');
        foreach([['inventory_opening_balances','opening inventory'],['inventory_movements','inventory movement history'],['sale_items','sales history'],['purchase_items','purchase history'],['rate_cards','rate cards'],['inventory_policies','inventory control policies'],['inventory_wastage_logs','wastage history']] as [$table,$label]){
            if($db->table($table)->where('cylinder_type_id',$id)->countAllResults()>0) throw new RuntimeException('This cylinder type cannot be deleted because it has '.$label.'. Deactivate it instead.');
        }
        // BL-030: soft delete; deactivated too so raw is_active lists drop it as well.
        $this->update($id,['is_active'=>0]);
        return (bool)$this->delete($id);
    }
}