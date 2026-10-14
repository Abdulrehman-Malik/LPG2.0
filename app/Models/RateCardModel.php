<?php

namespace App\Models;

use CodeIgniter\Model;

class RateCardModel extends Model
{
    protected $table='rate_cards';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=false;
    protected $allowedFields=['location_id','rate_type','cylinder_type_id','rate_value','effective_from','effective_to','created_by','created_at'];

    public function history(?int $locationId=null): array
    {
        $builder=$this->select('rate_cards.*, cylinder_types.code AS cylinder_code, cylinder_types.name AS cylinder_name, users.full_name AS created_by_name')
            ->join('cylinder_types','cylinder_types.id=rate_cards.cylinder_type_id','left')
            ->join('users','users.id=rate_cards.created_by','left')
            ->orderBy('effective_from','DESC');
        if($locationId) $builder->where('rate_cards.location_id',$locationId);
        return $builder->findAll();
    }
}