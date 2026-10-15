<?php

namespace App\Models;

use App\Services\BusinessContext;
use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table='suppliers';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=true;
    protected $createdField='created_at';
    protected $updatedField='updated_at';
    protected $allowedFields=['business_id','location_id','code','name','phone','city','address','credit_limit','opening_balance','is_active'];

    /** Suppliers a branch may use: its business's business-wide suppliers plus its own branch-only suppliers. */
    public function visibleTo(int $locationId, bool $activeOnly=false): array
    {
        $builder=$this->builder();
        BusinessContext::scopeSuppliers($builder,$locationId);
        if($activeOnly) $builder->where('suppliers.is_active',1);
        return $builder->orderBy('suppliers.name','ASC')->get()->getResultArray();
    }

    public function activeDirectory(?int $locationId=null): array
    {
        return $this->visibleTo($locationId ?? (int) session()->get('location_id'),true);
    }
}
