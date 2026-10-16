<?php
use App\Libraries\AppTheme;
use App\Libraries\Navigation;

$location = $location ?? [];
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
$branchName = trim((string) ($location['name'] ?? ''));
$branchName = $branchName !== '' ? $branchName : 'Perfect LPG';
$businessName = trim((string) ($location['business_name'] ?? ''));
$appSettings = (new \App\Models\ShopSettingsModel())->forLocation((int) session()->get('location_id'));
$nav = Navigation::build(\App\Services\PermissionService::current(), uri_string());
$isActive = static fn (string $url): string => $url === $nav['active'] ? 'active' : '';
$pageTitle = $title ?? 'Dashboard';
$userName = (string) (session()->get('full_name') ?? 'User');
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
$initials = $initials !== '' ? $initials : 'U';
$asset = static fn (string $path): string => base_url('assets/' . $path) . '?v=' . (is_file(FCPATH . 'assets/' . $path) ? filemtime(FCPATH . 'assets/' . $path) : '1');

$pendingMigrationNotice = null;
try {
    $migrationStatus = (new \App\Services\MigrationRunnerService())->status();
    if (!$migrationStatus['ready']) {
        $pendingFiles = array_map(static fn (array $row): string => (string) ($row['file'] ?? 'migration'), $migrationStatus['migrations'] ?? []);
        $pendingMigrationNotice = 'Database migrations are pending. Please log off and execute the pending migrations from the login page.';
        if ($pendingFiles) {
            $pendingMigrationNotice .= ' Pending: ' . implode(', ', $pendingFiles) . '.';
        }
    }
} catch (\Throwable $e) {
    $pendingMigrationNotice = 'Database migration status could not be verified. Please log off and check the migration status from the login page.';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= AppTheme::themeMode($appSettings) ?>" data-default-theme="<?= AppTheme::themeMode($appSettings) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($pageTitle) ?> — <?= esc($branchName) ?></title>
<script>(function(){try{var t=localStorage.getItem('lpgTheme');if(t==='auto')t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';if(t==='light'||t==='dark')document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>
<link rel="preload" href="<?= base_url('assets/vendor/inter/inter-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= $asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= $asset('vendor/datatables/dataTables.bootstrap5.min.css') ?>">
<link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
<style>:root{<?= AppTheme::cssVariables($appSettings) ?>}</style>
<?= $this->renderSection('styles') ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<nav class="app-sidebar" id="sidebar" aria-label="Main menu">
    <div class="app-brand">
        <div class="app-brand-mark" aria-hidden="true"><i class="bi bi-fire"></i></div>
        <div class="app-brand-text flex-grow-1">
            <div class="app-brand-name" title="<?= esc($branchName) ?>"><?= esc($branchName) ?></div>
            <div class="app-brand-sub"><?= esc($businessName !== '' ? $businessName : 'LPG POS & ERP') ?></div>
        </div>
        <button type="button" class="btn btn-sm app-nav-link w-auto app-sidebar-close d-lg-none" id="sidebarClose" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="app-nav-search">
        <div class="position-relative">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm" id="navSearch" placeholder="Find a page…" aria-label="Find a page in the menu" autocomplete="off">
        </div>
    </div>
    <ul class="app-nav" id="appNav">
        <?php if ($nav['dashboard']): ?>
        <li data-nav-item>
            <a href="<?= site_url('dashboard') ?>" class="app-nav-link <?= $isActive('dashboard') ?>" title="Dashboard" <?= $isActive('dashboard') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-speedometer2"></i><span class="menu-label">Dashboard</span>
            </a>
        </li>
        <?php endif; ?>
        <?php foreach ($nav['groups'] as $key => $group): $panelId = 'navGroup-' . $key; ?>
        <li data-nav-group-item data-label="<?= esc($group['label']) ?>">
            <button type="button" class="app-nav-link app-nav-group-toggle <?= $group['open'] ? 'has-active' : '' ?>" data-nav-group aria-expanded="<?= $group['open'] ? 'true' : 'false' ?>" aria-controls="<?= esc($panelId) ?>" title="<?= esc($group['label']) ?>">
                <i class="bi <?= esc($group['icon']) ?>"></i><span class="menu-label"><?= esc($group['label']) ?></span><i class="bi bi-chevron-down app-nav-chevron menu-label-icon"></i>
            </button>
            <ul class="app-nav-sub <?= $group['open'] ? 'is-open' : '' ?>" id="<?= esc($panelId) ?>">
                <?php foreach ($group['items'] as $item): ?>
                <li data-nav-item>
                    <a href="<?= site_url($item['url']) ?>" class="app-nav-link <?= $isActive($item['url']) ?>" title="<?= esc($item['label']) ?>" <?= $isActive($item['url']) ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= esc($item['icon']) ?>"></i><span class="menu-label"><?= esc($item['label']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
        <li class="app-nav-empty" id="navEmpty">No matching page.</li>
    </ul>
    <div class="app-sidebar-footer">
        <a href="<?= site_url('logout') ?>" class="app-nav-link" title="Log out"><i class="bi bi-box-arrow-right"></i><span class="menu-label">Log out</span></a>
    </div>
</nav>
<div class="app-backdrop" id="sidebarBackdrop"></div>

<div class="app-main">
    <header class="app-topbar">
        <button class="app-icon-btn sidebar-toggle" id="sidebarToggle" type="button" aria-label="Show or hide the menu" aria-controls="sidebar" aria-expanded="false" title="Show / hide menu"><i class="bi bi-list fs-5"></i></button>
        <div class="app-topbar-title">
            <?php if ($nav['trail']): ?>
            <ol class="app-breadcrumb" aria-label="Breadcrumb">
                <?php foreach ($nav['trail'] as $crumb): ?><li><?= esc($crumb) ?></li><?php endforeach; ?>
            </ol>
            <?php endif; ?>
            <h1><?= esc($pageTitle) ?></h1>
        </div>
        <div class="app-topbar-actions">
            <?php if (count($availableShops) > 1): ?>
            <form method="post" action="<?= site_url('shop-context/select') ?>" class="app-branch-switch m-0" data-no-loading>
                <?= csrf_field() ?>
                <label class="small text-muted" for="activeShopSelector"><i class="bi bi-shop"></i><span class="visually-hidden">Branch</span></label>
                <select id="activeShopSelector" name="location_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Switch active branch">
                    <?php foreach ($availableShops as $availableShop): ?>
                    <?php $optionName = trim((string) ($availableShop['business_name'] ?? '')) !== '' ? $availableShop['business_name'] . ' — ' . $availableShop['name'] : $availableShop['name']; ?>
                    <option value="<?= (int) $availableShop['id'] ?>" <?= (int) $availableShop['id'] === (int) session()->get('location_id') ? 'selected' : '' ?>><?= esc($optionName) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm btn-primary" type="submit">Switch</button></noscript>
            </form>
            <?php else: ?>
            <span class="app-branch-pill" title="Current branch"><i class="bi bi-shop"></i><span><?= esc($branchName) ?></span></span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="app-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= esc($userName) ?>">
                    <span class="app-avatar" aria-hidden="true"><?= esc($initials) ?></span>
                    <span class="app-user-meta d-none d-md-block"><span class="app-user-name d-block"><?= esc($userName) ?></span><span class="app-user-role d-block"><?= esc(session()->get('role') ?? '') ?></span></span>
                    <i class="bi bi-chevron-down small text-muted d-none d-md-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end" style="min-width:15rem">
                    <div class="px-2 py-1">
                        <div class="fw-semibold"><?= esc($userName) ?></div>
                        <div class="small text-muted"><?= esc(session()->get('role') ?? '') ?> · <?= esc($branchName) ?></div>
                    </div>
                    <hr class="dropdown-divider">
                    <div class="px-2 pb-1 small text-muted">Appearance</div>
                    <div class="btn-group w-100 px-2 pb-2" role="group" aria-label="Colour theme">
                        <button type="button" class="btn btn-sm btn-outline-secondary theme-choice" data-theme-choice="light" title="Light"><i class="bi bi-sun"></i><span class="visually-hidden">Light theme</span></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary theme-choice" data-theme-choice="dark" title="Dark"><i class="bi bi-moon-stars"></i><span class="visually-hidden">Dark theme</span></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary theme-choice" data-theme-choice="auto" title="Match device"><i class="bi bi-circle-half"></i><span class="visually-hidden">Match device theme</span></button>
                    </div>
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Log out</a>
                </div>
            </div>
        </div>
    </header>

    <main class="app-content" id="main" tabindex="-1">
        <?php if ($pendingMigrationNotice !== null): ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3" role="alert"><div><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Migration Required:</strong> <?= esc($pendingMigrationNotice) ?></div><a class="btn btn-sm btn-warning" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-1"></i>Log Off</a></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center gap-2 flex-wrap" role="status"><span><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></span><?php if (session()->getFlashdata('receipt_url')): ?><a class="btn btn-sm btn-success" href="<?= esc(session()->getFlashdata('receipt_url')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a><?php endif; ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="confirmModalTitle" data-confirm-title>Please confirm</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" data-confirm-message></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button></div>
        </div>
    </div>
</div>

<script src="<?= $asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= $asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $asset('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= $asset('vendor/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= $asset('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
