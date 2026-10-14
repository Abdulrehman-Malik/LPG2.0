<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table='users';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=true;
    protected $createdField='created_at';
    protected $updatedField='updated_at';
    protected $allowedFields=['location_id','role_id','full_name','username','email','password_hash','is_active','last_login_at'];

    public function findByLogin(string $login): ?array
    {
        return $this->select('users.*, roles.code AS role, roles.name AS role_name')
            ->join('roles','roles.id = users.role_id','left')
            ->groupStart()->where('users.username',$login)->orWhere('users.email',$login)->groupEnd()
            ->first();
    }

    public function touchLastLogin(int $userId): void
    {
        $this->update($userId,['last_login_at'=>date('Y-m-d H:i:s')]);
    }

    public function createWithPassword(array $data): int|false
    {
        if(isset($data['password'])){
            $data['password_hash']=password_hash($data['password'],PASSWORD_DEFAULT);
            unset($data['password']);
        }
        return $this->insert($data);
    }
}
