<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleItemModel extends Model
{
    protected $table='sale_items';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=false;
    protected $allowedFields=[
        'sale_id','line_no','line_type','cylinder_type_id','quantity','gas_weight_kg',
        'applied_rate','standard_rate','custom_rate_flag','empty_cylinder_received',
        'line_discount','line_total','notes'
    ];

    public function forSale(int $saleId): array
    {
        return $this->where('sale_id',$saleId)->orderBy('line_no')->findAll();
    }
}