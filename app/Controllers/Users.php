<?php

namespace App\Controllers;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

class Users extends Controller
{
    protected UserModel $users;
    protected RoleModel $roles;
    protected PermissionModel $permissions;

    public function __construct()
    {
        $this->users=new UserModel();
        $this->roles=new RoleModel();
        $this->permissions=new PermissionModel();
    }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows('USER_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }

    public function index()
    {
        if($r=$this->guard()) return $r;

        $currentPermissions=PermissionService::current();
        $db=Database::connect();
        $rolePermissions=$db->table('role_permissions rp')
            ->select('rp.role_id,rp.permission_id')
            ->get()
            ->getResultArray();

        $map=[];
        foreach($rolePermissions as $row) {
            $map[(int)$row['role_id']][]=(int)$row['permission_id'];
        }

        return view('users/index',[
            'title'=>'Users / Roles',
            'edit'=>(int)$this->request->getGet('edit') ? $this->users->find((int)$this->request->getGet('edit')) : null,
            'users'=>$this->users
                ->select('users.*, roles.code AS role_code, roles.name AS role_name')
                ->join('roles','roles.id=users.role_id')
                ->orderBy('full_name')
                ->findAll(),
            'roles'=>$this->roles->orderBy('name')->findAll(),
            'permissions'=>$this->permissions->orderBy('code')->findAll(),
            'rolePermissions'=>$map,
            'currentPermissions'=>$currentPermissions,
        ]);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $data=[
            'location_id'=>session()->get('location_id')?:null,
            'role_id'=>(int)$this->request->getPost('role_id'),
            'full_name'=>trim((string)$this->request->getPost('full_name')),
            'username'=>trim((string)$this->request->getPost('username')),
            'email'=>trim((string)$this->request->getPost('email'))?:null,
            'is_active'=>$this->request->getPost('is_active')?1:0
        ];
        $password=(string)$this->request->getPost('password');
        if($data['full_name']===''||$data['username']===''||$data['role_id']<=0) return redirect()->back()->withInput()->with('error','Name, username and role are required.');

        try {
            if($id){
                if($password!=='') $data['password_hash']=password_hash($password,PASSWORD_DEFAULT);
                $this->users->update($id,$data);
            } else {
                if($password==='') return redirect()->back()->withInput()->with('error','Password is required for a new user.');
                $data['password_hash']=password_hash($password,PASSWORD_DEFAULT);
                $this->users->insert($data);
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error','User could not be saved. Username or email may already exist.');
        }

        return redirect()->to('/users')->with('success',$id?'User updated.':'User created.');
    }

    public function rolePermissions()
    {
        if($r=$this->guard()) return $r;

        $roleId=(int)$this->request->getPost('role_id');
        if($roleId<=0) return redirect()->to('/users')->with('error','Invalid role.');

        $permissionIds=array_values(array_unique(array_filter(
            array_map('intval',(array)$this->request->getPost('permissions')),
            static fn(int $id): bool => $id>0
        )));

        $db=Database::connect();
        $db->transStart();
        $db->table('role_permissions')->where('role_id',$roleId)->delete();
        foreach($permissionIds as $permissionId) {
            $db->table('role_permissions')->insert([
                'role_id'=>$roleId,
                'permission_id'=>$permissionId
            ]);
        }
        $db->transComplete();

        if(!$db->transStatus()) return redirect()->to('/users')->with('error','Role permissions could not be updated.');

        return redirect()->to('/users')->with('success','Role permissions updated.');
    }
}
