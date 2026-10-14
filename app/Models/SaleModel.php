<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleModel extends Model
{
    protected $table='sales';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=true;
    protected $createdField='created_at';
    protected $updatedField='updated_at';
    protected $allowedFields=[
        'sale_no','location_id','customer_id','transaction_type','status','transaction_at',
        'total_kg','subtotal','discount_amount','total_amount','credit_amount',
        'custom_rate_flag','notes','created_by','voided_by','voided_at','void_reason'
    ];
}