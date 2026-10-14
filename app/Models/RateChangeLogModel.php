<?php

namespace App\Models;

use CodeIgniter\Model;

class RateChangeLogModel extends Model
{
    protected $table='rate_change_log';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=false;
    protected $allowedFields=['rate_card_id','location_id','rate_type','cylinder_type_id','old_rate','new_rate','changed_by','changed_at','reason'];
}