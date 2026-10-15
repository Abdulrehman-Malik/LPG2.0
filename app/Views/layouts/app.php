<?php
$location = $location ?? [];
$layoutDb = null;
$availableShops = [];
try {
    $layoutDb = \Config\Database::connect();
    if (!$location && session()->get('location_id')) {
        $location = $layoutDb->table('locations l')
            ->select('l.*, b.name AS business_name')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->where('l.id', (int) session()->get('location_id'))
            ->get()->getRowArray() ?: [];
    }
    $allowedShopIds = (new \App\Services\ShopScopeService())->accessibleShopIds();
    if ($allowedShopIds) {
        $availableShops = $layoutDb->table('locations l')
            ->select('l.id, l.name, l.business_id, b.name AS business_name')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->whereIn('l.id', $allowedShopIds)
            ->orderBy('b.name')
            ->orderBy('l.name')
            ->get()->getResultArray();
    }
} catch (Throwable $e) {
    $location = $location ?: [];
    $availableShops = [];
}
$branchName = trim((string)($location['name'] ?? ''));
$branchName = $branchName !== '' ? $branchName : 'Perfect LPG';
$appSettings = (new \App\Models\ShopSettingsModel())->forLocation((int) session()->get('location_id'));
$fontStacks = ['system'=>'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif','arial'=>'Arial,sans-serif','verdana'=>'Verdana,sans-serif','tahoma'=>'Tahoma,sans-serif','trebuchet'=>'"Trebuchet MS",sans-serif','georgia'=>'Georgia,serif','times'=>'"Times New Roman",serif'];
$fontStack = $fontStacks[$appSettings['font_family'] ?? 'system'] ?? $fontStacks['system'];
$current=uri_string(); $isActive=static fn(string $n):string=>str_starts_with($current,$n)?'active':'';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= esc($title ?? 'Dashboard') ?> — <?= esc($location['name'] ?? 'Perfect LPG') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
:root{--lpg-dark:<?= esc($appSettings['primary_color'] ?? '#1b2a3a') ?>;--lpg-accent:<?= esc($appSettings['accent_color'] ?? '#ff7a1a') ?>;--sidebar-w:250px;--sidebar-collapsed-w:72px;--app-font-size:<?= esc((float)($appSettings['pos_font_size_px'] ?? 14)) ?>px;--app-font-family:<?= esc($fontStack ?? 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif') ?>}
html,body{font-family:var(--app-font-family);font-size:var(--app-font-size)}
body{background:<?= ($appSettings['theme_mode'] ?? 'light') === 'dark' ? '#12171d' : '#f4f6f9' ?>;color:<?= ($appSettings['theme_mode'] ?? 'light') === 'dark' ? '#e8edf2' : '#212529' ?>}
.sidebar{position:fixed;top:0;bottom:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--lpg-dark);color:#cfd8e3;overflow:hidden;z-index:1030;display:flex;flex-direction:column;transition:width .2s ease}.sidebar > .nav{flex:1 1 0;min-height:0;height:auto;overflow-y:auto;overflow-x:hidden;margin:0;padding:.35rem .15rem 1.5rem 0;align-content:flex-start;scrollbar-width:auto;scrollbar-color:rgba(255,255,255,.9) rgba(255,255,255,.18);overscroll-behavior:contain;display:flex;flex-direction:column}.sidebar > .nav::-webkit-scrollbar{width:12px}.sidebar > .nav::-webkit-scrollbar-track{background:rgba(255,255,255,.14);border-radius:6px}.sidebar > .nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.88);border-radius:8px;border:2px solid rgba(255,255,255,.12);min-height:40px}.sidebar > .nav::-webkit-scrollbar-thumb:hover{background:#fff}.sidebar > .p-3{flex:0 0 auto;background:var(--lpg-dark)}.sidebar .brand{flex:0 0 auto;min-height:76px;padding:1rem;border-bottom:1px solid rgba(255,255,255,.08)}.sidebar .brand .bi{color:var(--lpg-accent);font-size:1.5rem}.sidebar .brand-text{min-width:0;overflow:hidden}.sidebar .nav-item{width:100%;flex:0 0 auto}.sidebar .nav-link{color:#cfd8e3;padding:.68rem 1.1rem;font-size:.925em;border-left:3px solid transparent;display:flex;align-items:center;gap:.5rem;white-space:nowrap;line-height:1.25;min-height:40px}.sidebar .nav-link i{width:1.25rem;min-width:1.25rem;text-align:center;margin-right:0}.sidebar .nav-link:hover{background:rgba(255,255,255,.05);color:#fff}.sidebar .nav-link.active{background:color-mix(in srgb,var(--lpg-accent) 12%,transparent);border-left-color:var(--lpg-accent);color:#fff;font-weight:600}.sidebar .nav-section{margin:.5rem .75rem .25rem;border-top:1px solid rgba(255,255,255,.1)}.sidebar .sidebar-group{margin-bottom:.15rem;flex:0 0 auto}.sidebar .sidebar-group-toggle{width:100%;background:transparent;border:0;border-left:3px solid transparent;cursor:pointer}.sidebar .sidebar-group-toggle .group-chevron{width:auto;min-width:auto;margin-left:auto;transition:transform .2s ease}.sidebar .sidebar-group-toggle.group-open .group-chevron{transform:rotate(180deg)}.sidebar .sidebar-submenu-container{display:none}.sidebar .sidebar-submenu-container.is-open{display:block}.sidebar .sidebar-submenu{padding:.1rem 0 .25rem 1rem}.sidebar .sidebar-submenu .nav-link{padding-top:.52rem;padding-bottom:.52rem;font-size:.88em;border-left:2px solid rgba(255,255,255,.08);margin-left:.65rem}.sidebar .sidebar-submenu .nav-link.active{border-left-color:var(--lpg-accent)}.sidebar .sidebar-submenu-container{background:rgba(0,0,0,.06)}.main-content{margin-left:var(--sidebar-w);min-height:100vh;transition:margin-left .2s ease}.topbar{background:<?= ($appSettings['theme_mode'] ?? 'light') === 'dark' ? '#1b222b' : '#fff' ?>;border-bottom:1px solid <?= ($appSettings['theme_mode'] ?? 'light') === 'dark' ? '#303944' : '#e6e9ee' ?>;padding:.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between}.content-wrap{padding:1.5rem}<?= ($appSettings['theme_mode'] ?? 'light') === 'dark' ? '.card,.modal-content,.dropdown-menu,.list-group-item,.table{background-color:#1b222b;color:#e8edf2;border-color:#303944}.bg-white{background-color:#1b222b!important;color:#e8edf2}.text-muted{color:#aeb8c3!important}.form-control,.form-select{background-color:#151b22;color:#e8edf2;border-color:#3a4653}.form-control:focus,.form-select:focus{background-color:#151b22;color:#e8edf2}.table{--bs-table-bg:#1b222b;--bs-table-color:#e8edf2}.nav-tabs .nav-link{color:#aeb8c3}.nav-tabs .nav-link.active{background:#1b222b;color:#e8edf2}' : '' ?>@media(min-width:992px){body.sidebar-collapsed .sidebar{width:var(--sidebar-collapsed-w)}body.sidebar-collapsed .main-content{margin-left:var(--sidebar-collapsed-w)}body.sidebar-collapsed .sidebar .brand{justify-content:center;padding-left:.5rem;padding-right:.5rem}body.sidebar-collapsed .sidebar .brand-text,body.sidebar-collapsed .sidebar .nav-link span,body.sidebar-collapsed .sidebar .nav-link .menu-label{display:none}body.sidebar-collapsed .sidebar .nav-link{justify-content:center;padding-left:.5rem;padding-right:.5rem;border-left-width:0}body.sidebar-collapsed .sidebar .nav-link i{margin:0}body.sidebar-collapsed .sidebar .nav-section{margin-left:.5rem;margin-right:.5rem}body.sidebar-collapsed .sidebar .group-chevron{display:none}body.sidebar-collapsed .sidebar .sidebar-group-toggle{justify-content:center}.sidebar-toggle .bi{transition:transform .2s ease}body.sidebar-collapsed .sidebar-toggle .bi{transform:rotate(180deg)}}@media(max-width:991.98px){.sidebar{transform:translateX(-100%)}.sidebar.show{transform:translateX(0)}.main-content{margin-left:0}.sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1020}.sidebar-backdrop.show{display:block}}
/* Professional visual refresh — presentation only. */
:root{--lpg-surface:#fff;--lpg-page:#f5f7fa;--lpg-border:#e2e8f0;--lpg-text:#172033;--lpg-muted:#64748b;--lpg-radius:.65rem}
body{background:var(--lpg-page);color:var(--lpg-text);-webkit-font-smoothing:antialiased}
.sidebar{background:linear-gradient(180deg,var(--lpg-dark),color-mix(in srgb,var(--lpg-dark) 90%,#07111f));box-shadow:4px 0 18px rgba(15,23,42,.08)}
.sidebar > .nav{padding:.55rem .45rem 1.5rem;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.35) transparent}
.sidebar > .nav::-webkit-scrollbar{width:7px}.sidebar > .nav::-webkit-scrollbar-track{background:transparent}.sidebar > .nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.3);border-radius:8px}
.sidebar .brand{min-height:78px;padding:1rem 1.05rem;border-bottom-color:rgba(255,255,255,.1)}
.sidebar .brand .bi{color:#fbbf24}
.sidebar .nav-link{padding:.64rem .85rem;border-radius:.45rem;margin:.08rem 0}
.sidebar .nav-link:hover{background:rgba(255,255,255,.075)}
.sidebar .nav-link.active{background:color-mix(in srgb,var(--lpg-accent) 20%,transparent);border-left-color:#5eead4;box-shadow:inset 0 0 0 1px rgba(255,255,255,.04)}
.sidebar .sidebar-submenu{padding-left:.55rem}.sidebar .sidebar-submenu .nav-link{border-radius:0 .4rem .4rem 0}
.main-content{min-width:0}.topbar{position:sticky;top:0;z-index:1010;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);box-shadow:0 1px 8px rgba(15,23,42,.04)}
.topbar h5{font-weight:650;color:var(--lpg-text)}.topbar .sidebar-toggle{border-color:#cbd5e1;background:#fff}
.content-wrap{padding:1.25rem 1.5rem 2rem}.card{border:1px solid var(--lpg-border);border-radius:var(--lpg-radius);box-shadow:0 2px 10px rgba(15,23,42,.045)}
.card-header{border-bottom:1px solid var(--lpg-border);background:#fbfcfe}.btn{border-radius:.5rem;font-weight:550}.btn-primary{background:var(--lpg-accent);border-color:var(--lpg-accent)}
.btn-primary:hover,.btn-primary:focus{filter:brightness(.94);background:var(--lpg-accent);border-color:var(--lpg-accent)}
.form-control,.form-select{border-color:#cbd5e1;border-radius:.5rem}.form-control:focus,.form-select:focus{border-color:var(--lpg-accent);box-shadow:0 0 0 .2rem color-mix(in srgb,var(--lpg-accent) 14%,transparent)}
.form-label{font-weight:500;color:#475569}.table{--bs-table-border-color:#e5eaf0}.table thead th{background:#f8fafc;color:#475569;font-size:.78rem;font-weight:700;letter-spacing:.025em}.table-hover tbody tr:hover{--bs-table-hover-bg:#f8fafc}
.badge{border-radius:.4rem;font-weight:650}.alert{border-radius:.6rem}.nav-tabs{border-bottom-color:#dbe3ec}.nav-tabs .nav-link{color:#64748b;border:0;border-bottom:2px solid transparent}.nav-tabs .nav-link.active{color:var(--lpg-accent);background:transparent;border-bottom-color:var(--lpg-accent)}
.modal-content{border:0;border-radius:.75rem;box-shadow:0 18px 50px rgba(15,23,42,.2)}.modal-header,.modal-footer{border-color:#e5eaf0}.dropdown-menu{border:1px solid #e2e8f0;border-radius:.6rem;box-shadow:0 10px 30px rgba(15,23,42,.12)}
.text-muted{color:#64748b!important}
@media(max-width:767.98px){.content-wrap{padding:.9rem}.topbar{padding:.65rem .9rem}.topbar h5{font-size:.95rem}.topbar .small{display:none}}
</style><?= $this->renderSection('styles') ?></head>
<body>
<nav class="sidebar d-flex flex-column" id="sidebar"><div class="brand d-flex align-items-center"><i class="bi bi-fire me-2 flex-shrink-0"></i><div class="brand-text"><div class="fw-bold text-white"><?= esc($branchName) ?></div><div class="text-white-50" style="font-size:.7rem;">LPG POS & ERP</div></div></div>
<?php
$permissions=\App\Services\PermissionService::current();
$can=static fn(string $permission): bool => isset($permissions[$permission]);
$showManagement=$can('DASHBOARD_VIEW') || $can('CUSTOMER_MANAGE') || $can('SUPPLIER_MANAGE') || $can('INVENTORY_MANAGE') || $can('RATE_MANAGE') || $can('USER_MANAGE');
$showOperations=$can('POS_SALE') || $can('CASH_MANAGE') || $can('PURCHASE_MANAGE') || $can('INVENTORY_MANAGE') || $can('EXPENSE_MANAGE') || $can('REPORT_VIEW') || $can('AUDIT_VIEW');
?>
<ul class="nav nav-pills flex-column mt-2 mb-auto sidebar-menu" id="sidebarGroups">
<?php
$permissions=\App\Services\PermissionService::current();
$can=static fn(string $permission): bool => isset($permissions[$permission]);

$groups=[
    'sales'=>[
        'label'=>'Sales & Receipts',
        'icon'=>'bi-cart-check',
        'items'=>[
            ['permission'=>'POS_SALE','url'=>'sales','label'=>'POS Sales','icon'=>'bi-cart-plus'],
            ['permission'=>'POS_HISTORY','url'=>'sales/history','label'=>'Sale History','icon'=>'bi-clock-history'],
            ['permission'=>'POS_SALE','url'=>'receipts','label'=>'Customer Receipts','icon'=>'bi-wallet2'],
        ],
    ],
    'purchases'=>[
        'label'=>'Purchasing',
        'icon'=>'bi-bag-check',
        'items'=>[
            ['permission'=>'PURCHASE_MANAGE','url'=>'purchases','label'=>'Purchases','icon'=>'bi-bag-plus'],
            ['permission'=>'PURCHASE_MANAGE','url'=>'supplier-payments','label'=>'Supplier Payments','icon'=>'bi-credit-card'],
        ],
    ],
    'inventory'=>[
        'label'=>'Inventory',
        'icon'=>'bi-boxes',
        'items'=>[
            ['permission'=>'INVENTORY_MANAGE','url'=>'inventory','label'=>'Inventory','icon'=>'bi-boxes'],
            ['permission'=>'INVENTORY_MANAGE','url'=>'inventory/opening','label'=>'Opening Inventory','icon'=>'bi-archive'],
            ['permission'=>'INVENTORY_MANAGE','url'=>'cylinder-types','label'=>'Cylinder Types','icon'=>'bi-box'],
            ['permission'=>'INVENTORY_MANAGE','url'=>'inventory/adjustments','label'=>'Stock Adjustment & History','icon'=>'bi-sliders2-vertical'],
            ['permission'=>'INVENTORY_CONTROLS_MANAGE','url'=>'inventory/controls','label'=>'Inventory Controls','icon'=>'bi-sliders'],
            ['permission'=>'GAS_WASTAGE_MANAGE','url'=>'inventory/wastage','label'=>'Gas Wastage','icon'=>'bi-droplet-half'],
            ['permission'=>'STOCK_TRANSFER_MANAGE','url'=>'inventory/transfers','label'=>'Branch Stock Transfers','icon'=>'bi-arrow-left-right'],
        ],
    ],
    'parties'=>[
        'label'=>'Parties & Ledgers',
        'icon'=>'bi-people',
        'items'=>[
            ['permission'=>'CUSTOMER_MANAGE','url'=>'customers','label'=>'Customers / Parties','icon'=>'bi-people'],
            ['permission'=>'SUPPLIER_MANAGE','url'=>'suppliers','label'=>'Suppliers','icon'=>'bi-truck'],
        ],
    ],
    'mobile_operations'=>[
        'label'=>'Mobile Ordering Setup',
        'icon'=>'bi-phone',
        'items'=>[
            ['permission'=>'CUSTOMER_ACCOUNT_MANAGE','url'=>'customer-app-accounts','label'=>'Customer App Accounts','icon'=>'bi-person-check'],
            ['permission'=>['BUSINESS_CATALOG_VIEW','BUSINESS_CATALOG_MANAGE','SHOP_SETTINGS_VIEW','SHOP_SETTINGS_MANAGE'],'url'=>'mobile-catalog','label'=>'Customer Ordering Catalog','icon'=>'bi-basket2'],
        ],
    ],
    'ordering'=>[
        'label'=>'Customer Ordering',
        'icon'=>'bi-basket2',
        'items'=>[
            ['permission'=>['ORDER_VIEW','ORDER_MANAGE'],'url'=>'customer-orders','label'=>'Customer Orders','icon'=>'bi-basket2'],
        ],
    ],
    'cash'=>[
        'label'=>'Cash & Expenses',
        'icon'=>'bi-cash-coin',
        'items'=>[
            ['permission'=>'CASH_MANAGE','url'=>'cash','label'=>'Counter Cash','icon'=>'bi-cash-stack'],
            ['permission'=>'EXPENSE_MANAGE','url'=>'expenses','label'=>'Expenses','icon'=>'bi-receipt'],
        ],
    ],
    'reporting'=>[
        'label'=>'Reporting & Audit',
        'icon'=>'bi-bar-chart',
        'items'=>[
            ['permission'=>'REPORT_VIEW','url'=>'reports','label'=>'Reports','icon'=>'bi-bar-chart'],
            ['permission'=>'REPORT_VIEW','url'=>'reports/inventory-detail','label'=>'Inventory Detail Report','icon'=>'bi-clipboard-data'],
            ['permission'=>'AUDIT_VIEW','url'=>'audit','label'=>'Audit Log','icon'=>'bi-shield-check'],
        ],
    ],
    'configuration'=>[
        'label'=>'Configuration',
        'icon'=>'bi-gear',
        'items'=>[
            ['permission'=>'RATE_MANAGE','url'=>'rates','label'=>'LPG Rates','icon'=>'bi-tags'],
            ['permission'=>'USER_MANAGE','url'=>'users','label'=>'Users / Roles','icon'=>'bi-person-gear'],
            ['permission'=>['APPLICATION_SETTINGS_VIEW','BUSINESS_SETTINGS_VIEW','SHOP_SETTINGS_VIEW'],'url'=>'configuration','label'=>'Central Configuration','icon'=>'bi-sliders2'],
            ['permission'=>['APPLICATION_SETTINGS_VIEW','APPLICATION_SETTINGS_MANAGE','SHOP_ACCESS_MANAGE'],'url'=>'business-management','label'=>'Businesses & Shops','icon'=>'bi-building-gear'],
            ['permission'=>['USER_MANAGE','INVENTORY_MANAGE'],'url'=>'shop-settings','label'=>'Shop Settings','icon'=>'bi-gear-wide-connected'],
            ['permission'=>'BACKUP_MANAGE','url'=>'database-backup','label'=>'Database Backup & Restore','icon'=>'bi-database-gear'],
        ],
    ],
];

foreach($groups as $groupKey=>&$group){
    $group['items']=array_values(array_filter($group['items'],static function(array $item) use ($can): bool {
        $required=$item['permission'];
        return is_array($required) ? (bool) array_filter($required,$can) : $can($required);
    }));
    $group['visible']=count($group['items'])>0;
    $group['open']=false;
    foreach($group['items'] as $item){
        if(str_starts_with($current,$item['url'])){
            $group['open']=true;
            break;
        }
    }
}
unset($group);
?>
<?php if($can('DASHBOARD_VIEW')): ?>
<li class="nav-item mb-1">
    <a href="<?= site_url('dashboard') ?>" class="nav-link <?= $isActive('dashboard') ?>" title="Dashboard">
        <i class="bi bi-speedometer2"></i><span class="menu-label">Dashboard</span>
    </a>
</li>
<?php endif; ?>

<?php foreach($groups as $groupKey=>$group): if(!$group['visible']) continue; ?>
<li class="nav-item sidebar-group">
    <button class="nav-link sidebar-group-toggle <?= $group['open']?'group-open':'' ?>" type="button"
            data-sidebar-toggle="sidebarGroup-<?= esc($groupKey) ?>"
            aria-expanded="<?= $group['open']?'true':'false' ?>" aria-controls="sidebarGroup-<?= esc($groupKey) ?>">
        <i class="bi <?= esc($group['icon']) ?>"></i>
        <span class="menu-label flex-grow-1 text-start"><?= esc($group['label']) ?></span>
        <i class="bi bi-chevron-down group-chevron"></i>
    </button>
    <div class="sidebar-submenu-container <?= $group['open']?'is-open':'' ?>" id="sidebarGroup-<?= esc($groupKey) ?>">
        <div class="sidebar-submenu">
        <?php foreach($group['items'] as $item): ?>
            <a href="<?= site_url($item['url']) ?>" class="nav-link <?= $isActive($item['url']) ?>" title="<?= esc($item['label']) ?>">
                <i class="bi <?= esc($item['icon']) ?>"></i><span class="menu-label"><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
</li>
<?php endforeach; ?>
</ul><div class="p-3 border-top border-white border-opacity-10"><a href="<?= site_url('logout') ?>" class="nav-link text-white-50" title="Logout"><i class="bi bi-box-arrow-right"></i><span class="menu-label">Logout</span></a></div></nav><div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="main-content"><div class="topbar flex-wrap gap-2"><div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-secondary sidebar-toggle" id="sidebarToggle" type="button" aria-label="Hide or show sidebar" title="Hide / Show menu"><i class="bi bi-layout-sidebar-inset"></i></button><h5 class="mb-0"><?= esc($title ?? 'Dashboard') ?></h5></div><div class="d-flex align-items-center gap-2 ms-auto">
<?php if (count($availableShops) > 1): ?>
<form method="post" action="<?= site_url('shop-context/select') ?>" class="d-flex align-items-center gap-2 m-0">
<?= csrf_field() ?>
<label class="small text-muted" for="activeShopSelector">Shop</label>
<select id="activeShopSelector" name="location_id" class="form-select form-select-sm" style="max-width:270px" onchange="this.form.submit()" aria-label="Switch active shop">
<?php foreach ($availableShops as $availableShop): ?>
<?php $optionName = trim((string)($availableShop['business_name'] ?? '')) !== '' ? $availableShop['business_name'] . ' — ' . $availableShop['name'] : $availableShop['name']; ?>
<option value="<?= (int)$availableShop['id'] ?>" <?= (int)$availableShop['id'] === (int)session()->get('location_id') ? 'selected' : '' ?>><?= esc($optionName) ?></option>
<?php endforeach; ?>
</select>
<noscript><button class="btn btn-sm btn-primary" type="submit">Switch</button></noscript>
</form>
<?php else: ?>
<span class="small"><?= esc($branchName) ?></span>
<?php endif; ?>
<span class="text-muted small"><?= esc(session()->get('full_name')??'User') ?> <span class="badge bg-secondary"><?= esc(session()->get('role')??'') ?></span></span></div></div>
<div class="content-wrap"><?php
$pendingMigrationNotice = null;
try {
    $migrationStatus = (new \App\Services\MigrationRunnerService())->status();
    if (!$migrationStatus['ready']) {
        $pendingRows = $migrationStatus['migrations'] ?? [];
        $pendingFiles = array_map(static fn(array $row): string => (string)($row['file'] ?? 'migration'), $pendingRows);
        $pendingMigrationNotice = 'Database migrations are pending. Please log off and execute the pending migrations from the login page.';
        if ($pendingFiles) {
            $pendingMigrationNotice .= ' Pending: '.implode(', ', $pendingFiles).'.';
        }
    }
} catch (\Throwable $e) {
    $pendingMigrationNotice = 'Database migration status could not be verified. Please log off and check the migration status from the login page.';
}
?><?php if ($pendingMigrationNotice !== null): ?><div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3" role="alert"><div><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Migration Required:</strong> <?= esc($pendingMigrationNotice) ?></div><a class="btn btn-sm btn-warning" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-1"></i>Log Off</a></div><?php endif; ?><?php if(session()->getFlashdata('success')): ?><div class="alert alert-success d-flex justify-content-between align-items-center gap-2 flex-wrap"><span><?= esc(session()->getFlashdata('success')) ?></span><?php if(session()->getFlashdata('receipt_url')): ?><a class="btn btn-sm btn-success" href="<?= esc(session()->getFlashdata('receipt_url')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a><?php endif; ?></div><?php endif; ?><?php if(session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?><?= $this->renderSection('content') ?></div></div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script><script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script><script>const sidebar=document.getElementById('sidebar');const sidebarToggle=document.getElementById('sidebarToggle');const sidebarBackdrop=document.getElementById('sidebarBackdrop');const isMobile=()=>window.innerWidth<992;if(localStorage.getItem('lpgSidebarCollapsed')==='1'&&!isMobile())document.body.classList.add('sidebar-collapsed');sidebarToggle?.addEventListener('click',()=>{if(isMobile()){sidebar?.classList.toggle('show');sidebarBackdrop?.classList.toggle('show')}else{document.body.classList.toggle('sidebar-collapsed');localStorage.setItem('lpgSidebarCollapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')}});sidebarBackdrop?.addEventListener('click',()=>{sidebar?.classList.remove('show');sidebarBackdrop?.classList.remove('show')});window.addEventListener('resize',()=>{if(!isMobile()){sidebar?.classList.remove('show');sidebarBackdrop?.classList.remove('show')}});document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-sidebar-toggle]').forEach(button=>{button.addEventListener('click',()=>{const id=button.getAttribute('data-sidebar-toggle');const panel=document.getElementById(id);if(!panel)return;const open=panel.classList.toggle('is-open');button.classList.toggle('group-open',open);button.setAttribute('aria-expanded',open?'true':'false');if(open&& !isMobile()){setTimeout(()=>panel.scrollIntoView({block:'nearest',behavior:'smooth'}),30);}});});const activeGroup=document.querySelector('.sidebar-group .sidebar-submenu-container.is-open');if(activeGroup&&!isMobile()){setTimeout(()=>activeGroup.scrollIntoView({block:'nearest',behavior:'auto'}),50);}});// Prevent mouse-wheel scrolling from incrementing/decrementing any numeric input.
document.addEventListener('wheel',event=>{
 const field=event.target instanceof Element?event.target.closest('input[type="number"]'):null;
 if(field&&document.activeElement===field)field.blur();
},{capture:true,passive:true});
$('.datatable').DataTable({pageLength:25});</script><?= $this->renderSection('scripts') ?></body></html>