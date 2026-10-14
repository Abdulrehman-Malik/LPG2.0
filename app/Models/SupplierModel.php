<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table='suppliers';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=true;
    protected $createdField='created_at';
    protected $updatedField='updated_at';
    protected $allowedFields=['code','name','phone','city','address','credit_limit','opening_balance','is_active'];

    public function activeDirectory(): array
    {
        return $this->where('is_active',1)->orderBy('name','ASC')->findAll();
    }
}