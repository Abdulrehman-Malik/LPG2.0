<?php
namespace App\Services;
use Config\Database;
use RuntimeException;

class CylinderUnitService
{
    protected $db;
    public function __construct(){ $this->db=Database::connect(); }

    public function createUnits(int $locationId,int $typeId,int $quantity,string $status,float $gasWeight,int $userId,string $sourceType,int $sourceId): array
    {
        if($quantity<1) throw new RuntimeException('Cylinder quantity must be a whole number.');
        $ct=$this->db->table('cylinder_types')->where('id',$typeId)->get()->getRowArray();
        if(!$ct) throw new RuntimeException('Cylinder type not found.');
        if($gasWeight<0 || $gasWeight>(float)$ct['capacity_kg']) throw new RuntimeException('Actual gas weight must be between 0 and the cylinder capacity.');
        if($status==='filled' && $gasWeight<=0) throw new RuntimeException('Filled cylinders must have actual gas weight greater than zero.');
        if($status==='empty') $gasWeight=0;
        $ids=[];
        for($i=0;$i<(int)$quantity;$i++){
            // Insert first so MySQL's AUTO_INCREMENT provides one global,
            // concurrency-safe sequence for the whole application. The final
            // physical cylinder code is based on the cylinder type code + ID.
            $temporaryCode='TMP-CYL-'.bin2hex(random_bytes(12));
            $this->db->table('cylinder_units')->insert([
                'location_id'=>$locationId,'cylinder_type_id'=>$typeId,'unit_code'=>$temporaryCode,
                'status'=>$status,'gas_weight_kg'=>$gasWeight,'source_type'=>$sourceType,
                'source_id'=>$sourceId,'created_by'=>$userId
            ]);

            $unitId=(int)$this->db->insertID();
            $unitCode=(string)$ct['code'].'-'.str_pad((string)$unitId,6,'0',STR_PAD_LEFT);

            $this->db->table('cylinder_units')
                ->where('id',$unitId)
                ->update(['unit_code'=>$unitCode]);

            $ids[]=$unitId;
        }
        return $ids;
    }

    public function available(int $locationId,int $typeId,string $status='filled'): array
    {
        return $this->db->query(
            "SELECT * FROM cylinder_units WHERE location_id=? AND cylinder_type_id=? AND status=? ORDER BY id FOR UPDATE",
            [$locationId,$typeId,$status]
        )->getResultArray();
    }

    public function availableForDisplay(int $locationId,int $typeId,string $status='filled'): array
    {
        return $this->db->table('cylinder_units cu')
            ->select('cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->where(['cu.location_id'=>$locationId,'cu.cylinder_type_id'=>$typeId,'cu.status'=>$status])
            ->orderBy('cu.id')->get()->getResultArray();
    }

    public function availableCompanyUnits(int $locationId, ?int $typeId=null, ?string $status=null): array
    {
        $q=$this->db->table('cylinder_units cu')
            ->select('cu.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->where('cu.location_id',$locationId)
            ->whereIn('cu.status',$status?[$status]:['filled','empty']);
        if($typeId) $q->where('cu.cylinder_type_id',$typeId);
        return $q->orderBy('cu.id')->get()->getResultArray();
    }

    public function customerCustody(int $locationId,int $customerId): array
    {
        return $this->db->table('cylinder_custody cc')
            ->select('cc.*,cu.unit_code,cu.status cylinder_status,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')
            ->join('cylinder_types ct','ct.id=cu.cylinder_type_id')
            ->where(['cc.location_id'=>$locationId,'cc.customer_id'=>$customerId,'cc.status'=>'issued'])
            ->orderBy('cc.id')->get()->getResultArray();
    }

    public function moveToCustody(int $unitId,int $customerId,int $issueSaleId,int $userId): void
    {
        $unit=$this->db->query('SELECT * FROM cylinder_units WHERE id=? FOR UPDATE',[$unitId])->getRowArray();
        if(!$unit || !in_array($unit['status'],['filled','empty'],true)) throw new RuntimeException('Cylinder is no longer available for custody.');
        $existing=$this->db->table('cylinder_custody')->where(['cylinder_unit_id'=>$unitId,'status'=>'issued'])->get()->getRowArray();
        if($existing) throw new RuntimeException('Cylinder is already on customer custody.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'custody','custody_customer_id'=>$customerId]);
        $this->db->table('cylinder_custody')->insert([
            'location_id'=>$unit['location_id'],'customer_id'=>$customerId,'cylinder_unit_id'=>$unitId,
            'status'=>'issued','issue_sale_id'=>$issueSaleId,'deposit_amount'=>0,'refund_amount'=>0,
            'issued_at'=>date('Y-m-d H:i:s'),'created_by'=>$userId
        ]);
    }

    public function returnFromCustody(int $custodyId,int $customerId,int $returnSaleId,int $userId): array
    {
        $custody=$this->db->query(
            'SELECT cc.*,cu.status cylinder_status,cu.gas_weight_kg,cu.cylinder_type_id
             FROM cylinder_custody cc JOIN cylinder_units cu ON cu.id=cc.cylinder_unit_id
             WHERE cc.id=? AND cc.customer_id=? AND cc.status=\'issued\' FOR UPDATE',
            [$custodyId,$customerId]
        )->getRowArray();
        if(!$custody) throw new RuntimeException('Cylinder custody record not found or already returned.');
        if((float)$custody['gas_weight_kg']>0.00001) throw new RuntimeException('Cylinder '.$custodyId.' must be empty before it can be returned.');
        $this->db->table('cylinder_units')->where('id',$custody['cylinder_unit_id'])->update(['status'=>'empty','custody_customer_id'=>null,'gas_weight_kg'=>0]);
        $this->db->table('cylinder_custody')->where('id',$custodyId)->update([
            'status'=>'returned','return_sale_id'=>$returnSaleId,'refund_amount'=>(float)$custody['deposit_amount'],'returned_at'=>date('Y-m-d H:i:s'),'updated_by'=>$userId
        ]);
        return $custody;
    }

    public function addGasToCustody(int $unitId,int $customerId,float $gasKg): float
    {
        if($gasKg<=0) throw new RuntimeException('Gas quantity must be greater than zero.');
        $unit=$this->db->query('SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? AND cu.custody_customer_id=? AND cu.status=\'custody\' FOR UPDATE',[$unitId,$customerId])->getRowArray();
        if(!$unit) throw new RuntimeException('Selected customer custody cylinder is not available.');
        $current=(float)$unit['gas_weight_kg']; $capacity=(float)$unit['capacity_kg'];
        if($current+$gasKg>$capacity+0.00001) throw new RuntimeException('Gas quantity exceeds the selected customer cylinder capacity. Available capacity: '.number_format(max(0,$capacity-$current),2).' KG.');
        $after=$current+$gasKg;
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['gas_weight_kg'=>$after]);
        return $after;
    }

    public function setGasWeightOnCustody(int $unitId,float $gasKg): void
    {
        $unit=$this->db->query('SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? AND cu.status=\'custody\' FOR UPDATE',[$unitId])->getRowArray();
        if(!$unit) throw new RuntimeException('Customer custody cylinder not found.');
        if($gasKg<0 || $gasKg>(float)$unit['capacity_kg']) throw new RuntimeException('Invalid gas weight for customer custody cylinder.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['gas_weight_kg'=>$gasKg]);
    }

    public function markSold(int $unitId): void
    {
        $u=$this->db->query("SELECT * FROM cylinder_units WHERE id=? FOR UPDATE",[$unitId])->getRowArray();
        if(!$u || $u['status']==='sold') throw new RuntimeException('Cylinder is no longer available.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'sold','gas_weight_kg'=>0]);
    }

    public function restoreGas(int $unitId,float $gasWeight): void
    {
        if($gasWeight<=0) throw new RuntimeException('Restored gas quantity must be greater than zero.');
        $u=$this->db->query("SELECT cu.*,ct.capacity_kg FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.id=? FOR UPDATE",[$unitId])->getRowArray();
        if(!$u) throw new RuntimeException('Cylinder unit not found.');
        $after=(float)$u['gas_weight_kg']+$gasWeight;
        if($after>(float)$u['capacity_kg']+0.00001) throw new RuntimeException('Restored gas would exceed cylinder capacity.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update([
            'status'=>'filled',
            'gas_weight_kg'=>$after
        ]);
    }

    public function restoreFilled(int $unitId,float $gasWeight): void
    {
        $u=$this->db->query("SELECT * FROM cylinder_units WHERE id=? FOR UPDATE",[$unitId])->getRowArray();
        if(!$u) throw new RuntimeException('Cylinder unit not found.');
        $ct=$this->db->table('cylinder_types')->where('id',$u['cylinder_type_id'])->get()->getRowArray();
        if($gasWeight<=0 || $gasWeight>(float)$ct['capacity_kg']) throw new RuntimeException('Invalid restored cylinder gas weight.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'filled','gas_weight_kg'=>$gasWeight]);
    }

    public function restoreEmpty(int $unitId): void
    {
        $u=$this->db->query("SELECT * FROM cylinder_units WHERE id=? FOR UPDATE",[$unitId])->getRowArray();
        if(!$u) throw new RuntimeException('Cylinder unit not found.');
        $this->db->table('cylinder_units')->where('id',$unitId)->update(['status'=>'empty','gas_weight_kg'=>0]);
    }
}