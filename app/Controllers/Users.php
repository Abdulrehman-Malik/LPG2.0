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
        $locationId = (int) (session()->get('location_id') ?? 0);
        if ($locationId <= 0 || !(new \App\Services\ShopScopeService())->canAccessCurrentShop()) {
            return $this->response->setStatusCode(403)->setBody('An authorized shop context is required.');
        }
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
            'edit'=>$this->scopedUser((int) $this->request->getGet('edit'), $locationId),
            'users'=>$db->query(
                'SELECT u.*, r.code AS role_code, r.name AS role_name
                 FROM users u
                 JOIN roles r ON r.id=u.role_id
                 WHERE u.location_id=? OR EXISTS (
                     SELECT 1 FROM user_shop_access usa
                     WHERE usa.user_id=u.id AND usa.location_id=?
                 )
                 ORDER BY u.full_name',
                [$locationId, $locationId]
            )->getResultArray(),
            'roles'=>array_values(array_filter(
                $this->roles->orderBy('name')->findAll(),
                static fn(array $role): bool => PermissionService::roleWithinActor((int)$role['id'],(int)session()->get('user_id'))
            )),
            'permissions'=>$this->permissions->orderBy('code')->findAll(),
            'rolePermissions'=>$map,
            'currentPermissions'=>$currentPermissions,
        ]+$this->branchViewData($locationId,(int) $this->request->getGet('edit')));
    }

    /** Branch checkboxes for the user form: branches of this business the signed-in user may assign. */
    private function branchViewData(int $locationId, int $editUserId): array
    {
        $canManage=PermissionService::allows('SHOP_ACCESS_MANAGE');
        $businessId=\App\Services\BusinessContext::businessIdForLocation($locationId);
        $access=new \App\Services\StaffAccessService();
        $assignment=$editUserId && $this->scopedUser($editUserId,$locationId) ? $access->assignment($editUserId,$businessId) : ['branch_ids'=>[$locationId],'default_id'=>$locationId];
        return [
            'canManageBranches'=>$canManage,
            'branches'=>$canManage ? $access->manageableBranches((int)session()->get('user_id'),$businessId) : [],
            'branchAssignment'=>$assignment,
        ];
    }

    private function scopedUser(int $userId, int $locationId): ?array
    {
        if ($userId <= 0 || $locationId <= 0) {
            return null;
        }

        return Database::connect()->query(
            'SELECT u.*
             FROM users u
             WHERE u.id=? AND (
                 u.location_id=? OR EXISTS (
                     SELECT 1 FROM user_shop_access usa
                     WHERE usa.user_id=u.id AND usa.location_id=?
                 )
             )
             LIMIT 1',
            [$userId, $locationId, $locationId]
        )->getRowArray() ?: null;
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $locationId=(int)(session()->get('location_id')??0);
        if($locationId<=0 || !(new \App\Services\ShopScopeService())->canAccessCurrentShop()) {
            return $this->response->setStatusCode(403)->setBody('An authorized shop context is required.');
        }
        $existingUser = $id ? $this->scopedUser($id, $locationId) : null;
        if($id && !$existingUser) return $this->response->setStatusCode(404)->setBody('User not found in this shop.');
        $data=[
            'location_id'=>$existingUser ? ($existingUser['location_id'] ?: $locationId) : $locationId,
            'role_id'=>(int)$this->request->getPost('role_id'),
            'full_name'=>trim((string)$this->request->getPost('full_name')),
            'username'=>trim((string)$this->request->getPost('username')),
            'email'=>trim((string)$this->request->getPost('email'))?:null,
            'is_active'=>$this->request->getPost('is_active')?1:0
        ];
        $password=(string)$this->request->getPost('password');
        if($data['full_name']===''||$data['username']===''||$data['role_id']<=0) return redirect()->back()->withInput()->with('error','Name, username and role are required.');

        // Branch access (Phase 9.3.2) is edited here only by users allowed to manage staff access.
        $actorId=(int)session()->get('user_id');

        // No privilege escalation: a manager can neither hand out nor act on a role with more
        // permissions than their own, cannot touch System Administrators, and cannot change
        // their own role.
        $actorIsSystemAdmin=(new \App\Services\ShopScopeService())->isSystemAdministrator($actorId);
        if($existingUser && (int)($existingUser['is_system_admin'] ?? 0)===1 && !$actorIsSystemAdmin){
            return $this->response->setStatusCode(403)->setBody('Only a System Administrator can change a System Administrator account.');
        }
        if($existingUser && !PermissionService::roleWithinActor((int)$existingUser['role_id'],$actorId)){
            return $this->response->setStatusCode(403)->setBody('You cannot change a user whose role has more permissions than yours.');
        }
        if($id===$actorId && $existingUser && (int)$existingUser['role_id']!==$data['role_id']){
            return redirect()->back()->withInput()->with('error','You cannot change your own role.');
        }
        if(!PermissionService::roleWithinActor($data['role_id'],$actorId)){
            return redirect()->back()->withInput()->with('error','You cannot assign a role with permissions you do not have.');
        }
        if($password!==''){
            try { \App\Services\PasswordPolicy::assertAcceptable($password); }
            catch(\RuntimeException $e){ return redirect()->back()->withInput()->with('error',user_error_message($e)); }
        }
        $businessId=\App\Services\BusinessContext::businessIdForLocation($locationId);
        $access=new \App\Services\StaffAccessService();
        $manageBranches=PermissionService::allows('SHOP_ACCESS_MANAGE') && $this->request->getPost('branch_access_submitted')==='1';
        $branchIds=[];$defaultBranchId=null;
        if($manageBranches){
            $defaultBranchId=(int)$this->request->getPost('default_branch_id') ?: null;
            try { $branchIds=$access->validate($actorId,$businessId,array_map('intval',(array)$this->request->getPost('branch_ids')),$defaultBranchId); }
            catch(\Throwable $e){ return redirect()->back()->withInput()->with('error',user_error_message($e)); }
        }

        try {
            if($id){
                if($password!=='') $data['password_hash']=password_hash($password,PASSWORD_DEFAULT);
                $this->users->update($id,$data);
                $targetUserId = $id;
            } else {
                if($password==='') return redirect()->back()->withInput()->with('error','Password is required for a new user.');
                $data['password_hash']=password_hash($password,PASSWORD_DEFAULT);
                $this->users->insert($data);
                $targetUserId = (int) $this->users->getInsertID();
            }

            $db = Database::connect();
            if ($manageBranches) {
                $access->sync($actorId, $targetUserId, $businessId, $branchIds, $defaultBranchId);
            }
            $hasAssignments = $db->table('user_shop_access')->where('user_id', $targetUserId)->countAllResults() > 0;
            if (!$hasAssignments) {
                $db->table('user_shop_access')->insert([
                    'user_id' => $targetUserId,
                    'location_id' => $locationId,
                    'is_default' => 1,
                ]);
            }

            // Password changes and account disablement invalidate all mobile sessions.
            if ($password !== '' || (int) $data['is_active'] !== 1) {
                (new \App\Services\ApiSessionService())->revokeAllForStaff($targetUserId);
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error','User could not be saved. Username or email may already exist.');
        }

        return redirect()->to('/users')->with('success',$id?'User updated.':'User created.');
    }

    public function rolePermissions()
    {
        if($r=$this->guard()) return $r;

        if (!(new \App\Services\ShopScopeService())->isSystemAdministrator()) {
            return $this->response->setStatusCode(403)->setBody('Only a System Administrator may edit platform-wide role permissions.');
        }

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
