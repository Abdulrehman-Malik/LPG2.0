<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table='permissions';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $allowedFields=['code','name'];
}