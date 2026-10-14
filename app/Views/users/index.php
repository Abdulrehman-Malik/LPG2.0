<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Users & Roles</h4>
        <div class="text-muted small">Manage user accounts and exactly which application permissions each role receives.</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userForm">
        <i class="bi bi-person-plus me-1"></i>Add User
    </button>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Users</strong>
            <div class="small text-muted">Users inherit the permissions assigned to their selected role.</div>
        </div>
        <span class="badge text-bg-secondary"><?= count($users) ?> user<?= count($users)===1?'':'s' ?></span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            <?php foreach($users as $u): ?>
                <tr>
                    <td><strong><?= esc($u['full_name']) ?></strong></td>
                    <td><?= esc($u['username']) ?></td>
                    <td><?= esc($u['email'] ?? '') ?></td>
                    <td><span class="badge text-bg-light border"><?= esc($u['role_code']) ?></span><div class="small text-muted"><?= esc($u['role_name']) ?></div></td>
                    <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('users?edit='.$u['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <strong>Current signed-in permissions</strong>
        <div class="small text-muted">These are the permissions currently used to build the sidebar menu.</div>
    </div>
    <div class="card-body">
        <?php if($currentPermissions): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach(array_keys($currentPermissions) as $code): ?>
                    <span class="badge text-bg-success"><?= esc($code) ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0">No permissions are assigned to the current account. Navigation items will remain hidden until permissions are granted.</div>
        <?php endif; ?>
    </div>
</div>

<div class="mb-2">
    <h5 class="mb-1">Role Permissions</h5>
    <div class="text-muted small">Each checked permission is applied immediately to every user assigned to that role on their next request.</div>
</div>

<?php foreach($roles as $role): ?>
<?php $assigned=$rolePermissions[(int)$role['id']]??[]; ?>
<form method="post" action="<?= site_url('users/role-permissions') ?>" class="card mb-4 shadow-sm role-permission-card" data-role="<?= esc($role['code']) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-0"><?= esc($role['code'].' — '.$role['name']) ?></h6>
            <div class="small text-muted"><?= esc($role['description'] ?? 'Role permissions') ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light border permission-count"><?= count($assigned) ?> / <?= count($permissions) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary select-all-permissions">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary clear-all-permissions">Clear All</button>
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>Save Permissions</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach($permissions as $p): ?>
                <div class="col-md-6 col-xl-4">
                    <label class="border rounded p-2 w-100 h-100 d-flex gap-2 align-items-start permission-option">
                        <input class="form-check-input permission-checkbox mt-1 flex-shrink-0" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" <?= in_array((int)$p['id'],$assigned,true)?'checked':'' ?>>
                        <span>
                            <strong class="d-block"><?= esc($p['name']) ?></strong>
                            <span class="small text-muted"><?= esc($p['code']) ?></span>
                        </span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</form>
<?php endforeach; ?>

<div class="alert alert-info">
    <strong>Navigation rule:</strong> the sidebar only shows pages for permissions held by the signed-in user's role.
    For example, <code>REPORT_VIEW</code> shows Reports; <code>USER_MANAGE</code> controls Users / Roles;
    Shop Settings is available with <code>USER_MANAGE</code> or <code>INVENTORY_MANAGE</code>.
</div>

<div class="modal fade" id="userForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= site_url('users/save') ?>" class="modal-content">
            <?= csrf_field() ?>
            <?php if($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $edit ? 'Edit User' : 'Add User' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?= esc($edit['full_name']??'') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= esc($edit['username']??'') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= esc($edit['email']??'') ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach($roles as $r): ?><option value="<?= $r['id'] ?>" <?= isset($edit)&&$edit['role_id']==$r['id']?'selected':'' ?>><?= esc($r['code'].' — '.$r['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input name="password" type="password" class="form-control" <?= $edit ? '' : 'required' ?>>
                        <div class="form-text">Leave blank when editing to keep the current password.</div>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input name="is_active" value="1" type="checkbox" <?= !isset($edit)||$edit['is_active'] ? 'checked' : '' ?> class="form-check-input">
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary"><?= $edit ? 'Update User' : 'Create User' ?></button></div>
        </form>
    </div>
</div>

<?php if(isset($edit)&&$edit): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{const m=document.getElementById('userForm');if(m)new bootstrap.Modal(m).show();});</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.role-permission-card').forEach(card=>{
        const boxes=[...card.querySelectorAll('.permission-checkbox')];
        const count=card.querySelector('.permission-count');
        const refresh=()=>{ if(count) count.textContent=boxes.filter(b=>b.checked).length+' / '+boxes.length; };
        card.querySelector('.select-all-permissions')?.addEventListener('click',()=>{boxes.forEach(b=>b.checked=true);refresh();});
        card.querySelector('.clear-all-permissions')?.addEventListener('click',()=>{boxes.forEach(b=>b.checked=false);refresh();});
        boxes.forEach(b=>b.addEventListener('change',refresh));
        refresh();
    });
});
</script>

<?= $this->endSection() ?>