<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.hier-summary { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; color: var(--lpg-muted); font-size: .9rem; margin-bottom: 1rem; }
.hier-summary strong { color: var(--lpg-text); font-size: 1.05rem; }
.hier-layout { display: grid; grid-template-columns: minmax(300px, 420px) minmax(0, 1fr); gap: 1rem; align-items: start; }
.hier-tree-card .card-body { padding: .5rem; max-height: calc(100vh - 15rem); overflow: auto; }
.hier-detail { position: sticky; top: calc(var(--lpg-topbar-h) + 1rem); }
.hier-toolbar { display: flex; gap: .5rem; padding: .6rem .75rem; border-bottom: 1px solid var(--lpg-border); }
.hier-toolbar .position-relative { flex: 1; }
.hier-toolbar .bi-search { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); color: var(--lpg-muted); pointer-events: none; }
.hier-toolbar input { padding-left: 1.9rem; }

.hier-tree, .hier-tree ul { list-style: none; margin: 0; padding: 0; }
.hier-tree ul { padding-left: 1.15rem; margin-left: .55rem; border-left: 1px dashed var(--lpg-border-strong); }
.hier-tree li[aria-expanded="false"] > ul { display: none; }
.hier-row { display: flex; align-items: center; gap: .45rem; width: 100%; min-height: 2.35rem; padding: .3rem .5rem; border: 0; border-radius: var(--lpg-radius-sm); background: transparent; color: var(--lpg-text); text-align: left; cursor: pointer; }
.hier-row:hover { background: var(--lpg-surface-2); }
.hier-row:focus-visible { outline: 2px solid var(--lpg-accent); outline-offset: -2px; }
[aria-selected="true"] > .hier-row { background: rgba(var(--lpg-accent-rgb), .14); box-shadow: inset 3px 0 0 var(--lpg-accent); }
.hier-caret { flex: 0 0 1rem; color: var(--lpg-muted); font-size: .75rem; transition: transform .15s ease; }
li[aria-expanded="true"] > .hier-row > .hier-caret { transform: rotate(90deg); }
.hier-caret.is-leaf { visibility: hidden; }
.hier-icon { flex: 0 0 1.75rem; height: 1.75rem; border-radius: .45rem; display: grid; place-items: center; font-size: .9rem; }
.hier-icon.business { background: rgba(var(--lpg-accent-rgb), .15); color: var(--lpg-link); }
.hier-icon.group { background: var(--lpg-info-soft); color: var(--lpg-info); }
.hier-icon.shop { background: var(--lpg-success-soft); color: var(--lpg-success); }
.hier-icon.shop.is-inactive { background: var(--lpg-neutral-soft); color: var(--lpg-neutral); }
.hier-icon.user { background: var(--lpg-neutral-soft); color: var(--lpg-text-2); border-radius: 50%; font-size: .7rem; font-weight: 700; }
.hier-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hier-label small { color: var(--lpg-muted); margin-left: .3rem; }
.hier-meta { flex: 0 0 auto; display: flex; gap: .3rem; align-items: center; }
.hier-count { font-size: .72rem; color: var(--lpg-muted); background: var(--lpg-surface-2); border: 1px solid var(--lpg-border); border-radius: 999px; padding: 0 .45rem; }
.hier-dot { width: .55rem; height: .55rem; border-radius: 50%; background: var(--lpg-success); }
.hier-dot.is-warn { background: var(--lpg-warning); }
.hier-dot.is-off { background: var(--lpg-neutral); }
.hier-add { color: var(--lpg-link); font-weight: 600; font-size: .88rem; }
.hier-add .hier-icon { background: transparent; border: 1px dashed var(--lpg-accent); color: var(--lpg-link); }
.hier-tree li.is-hidden { display: none; }
.hier-tree mark { padding: 0 .1rem; background: rgba(var(--lpg-accent-rgb), .3); color: inherit; border-radius: 2px; }

.hier-detail-head { display: flex; gap: .85rem; align-items: center; margin-bottom: 1rem; }
.hier-detail-head .hier-icon { flex-basis: 2.75rem; height: 2.75rem; font-size: 1.25rem; }
.hier-detail-head .hier-icon.user { font-size: .95rem; }
.hier-detail-head h2 { font-size: 1.2rem; margin: 0; }
.hier-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .6rem; margin-bottom: 1rem; }
.hier-facts > div { border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); padding: .55rem .7rem; background: var(--lpg-surface-2); }
.hier-facts span { display: block; font-size: .75rem; color: var(--lpg-muted); }
.hier-facts strong { font-variant-numeric: tabular-nums; }
.hier-section { font-size: .78rem; font-weight: 650; letter-spacing: .05em; text-transform: uppercase; color: var(--lpg-muted); margin: 1.1rem 0 .45rem; }
.hier-checklist { list-style: none; margin: 0; padding: 0; display: grid; gap: .35rem; }
.hier-checklist li { display: flex; gap: .5rem; align-items: flex-start; font-size: .9rem; }
.hier-checklist .bi-check-circle-fill { color: var(--lpg-success); }
.hier-checklist .bi-exclamation-circle-fill { color: var(--lpg-warning); }
.hier-people { list-style: none; margin: 0; padding: 0; border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); }
.hier-people li { display: flex; align-items: center; gap: .6rem; padding: .5rem .7rem; border-top: 1px solid var(--lpg-border); }
.hier-people li:first-child { border-top: 0; }
.hier-people button { all: unset; cursor: pointer; display: flex; align-items: center; gap: .6rem; flex: 1; min-width: 0; }
.hier-people button:focus-visible { outline: 2px solid var(--lpg-accent); outline-offset: 2px; border-radius: 4px; }
.hier-people .meta { color: var(--lpg-muted); font-size: .8rem; }
@media (max-width: 991.98px) {
    .hier-layout { grid-template-columns: 1fr; }
    .hier-detail { position: static; }
    .hier-tree-card .card-body { max-height: 55vh; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$initials = static function (string $name): string {
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out !== '' ? $out : '?';
};
$when = static fn (?string $at): string => $at ? date('d M Y, h:i A', strtotime($at)) : 'Never';

// One detail record per user, listing every place they appear in the tree.
$users = [];
$totalShops = $readyShops = 0;
foreach ($tree as $b) {
    foreach ($b['head_office'] as $u) {
        $users[(int) $u['id']] ??= $u + ['places' => []];
        $users[(int) $u['id']]['places'][] = ['type' => 'Head Office', 'name' => $b['name'], 'default' => (int) $u['is_default'] === 1, 'node' => 'business-' . $b['id']];
    }
    foreach ($b['shops'] as $s) {
        $totalShops++;
        $readyShops += $s['ready'] ? 1 : 0;
        foreach ($s['staff'] as $u) {
            $users[(int) $u['id']] ??= $u + ['places' => []];
            $users[(int) $u['id']]['places'][] = ['type' => 'Shop staff', 'name' => $s['name'] . ' (' . $b['name'] . ')', 'default' => (int) $u['is_default'] === 1, 'node' => 'shop-' . $s['id']];
        }
    }
}
$personRow = static function (array $u, string $extra = '') use ($initials): string {
    $status = (int) $u['is_active'] === 1 ? '' : ' <span class="status">Inactive</span>';
    return '<li><button type="button" data-goto="user-' . (int) $u['id'] . '"><span class="hier-icon user">' . esc($initials($u['full_name'])) . '</span><span class="min-w-0"><span class="fw-semibold d-block text-truncate">' . esc($u['full_name']) . $status . '</span><span class="meta">' . esc($u['username']) . ' · ' . esc($u['role_name'] ?? $u['role_code'] ?? '') . $extra . '</span></span></button></li>';
};
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Business Hierarchy</h1>
        <p class="page-subtitle">Every business you manage, its Head Office users, its shops and the staff of each shop. Select anything in the tree to see its details.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= site_url('business-management') ?>"><i class="bi bi-building-gear me-1"></i>Businesses &amp; Shops</a>
    </div>
</div>

<?php if (!$tree): ?>
    <div class="card"><div class="empty-state"><i class="bi bi-diagram-2"></i><div class="empty-state-title">No businesses to show</div>Your account has no business or shop access yet.</div></div>
<?php else: ?>
<div class="hier-summary" aria-label="Totals">
    <span><strong><?= count($tree) ?></strong> business<?= count($tree) === 1 ? '' : 'es' ?></span>
    <span><strong><?= $totalShops ?></strong> shop<?= $totalShops === 1 ? '' : 's' ?> (<?= $readyShops ?> ready)</span>
    <span><strong><?= count($users) ?></strong> user<?= count($users) === 1 ? '' : 's' ?></span>
</div>

<div class="hier-layout">
    <div class="card hier-tree-card">
        <div class="hier-toolbar">
            <div class="position-relative">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" class="form-control form-control-sm" id="hierSearch" placeholder="Search shops, users, codes…" aria-label="Search the hierarchy" autocomplete="off">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="hierExpand" title="Expand all"><i class="bi bi-arrows-expand"></i><span class="visually-hidden">Expand all</span></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="hierCollapse" title="Collapse all"><i class="bi bi-arrows-collapse"></i><span class="visually-hidden">Collapse all</span></button>
        </div>
        <div class="card-body">
            <ul class="hier-tree" role="tree" aria-label="Business hierarchy" id="hierTree">
            <?php foreach ($tree as $b): $bid = (int) $b['id']; ?>
                <li role="treeitem" aria-expanded="true" data-node="business-<?= $bid ?>">
                    <div class="hier-row" tabindex="-1" data-select="business-<?= $bid ?>">
                        <i class="bi bi-chevron-right hier-caret" data-toggle aria-hidden="true"></i>
                        <span class="hier-icon business"><i class="bi bi-building"></i></span>
                        <span class="hier-label" data-text><?= esc($b['name']) ?><small><?= esc($b['code']) ?></small></span>
                        <span class="hier-meta"><span class="hier-count" title="Shops"><?= (int) $b['totals']['shops'] ?> shop<?= $b['totals']['shops'] === 1 ? '' : 's' ?></span><?php if (!(int) $b['is_active']): ?><span class="status">Inactive</span><?php endif; ?></span>
                    </div>
                    <ul role="group">
                        <li role="treeitem" aria-expanded="false" data-node="ho-<?= $bid ?>">
                            <div class="hier-row" tabindex="-1" data-select="business-<?= $bid ?>">
                                <i class="bi bi-chevron-right hier-caret <?= $b['head_office'] ? '' : 'is-leaf' ?>" data-toggle aria-hidden="true"></i>
                                <span class="hier-icon group"><i class="bi bi-briefcase"></i></span>
                                <span class="hier-label" data-text>Head Office</span>
                                <span class="hier-meta"><span class="hier-count"><?= count($b['head_office']) ?> user<?= count($b['head_office']) === 1 ? '' : 's' ?></span></span>
                            </div>
                            <?php if ($b['head_office']): ?>
                            <ul role="group">
                                <?php foreach ($b['head_office'] as $u): ?>
                                <li role="treeitem" data-node="user-<?= (int) $u['id'] ?>">
                                    <div class="hier-row" tabindex="-1" data-select="user-<?= (int) $u['id'] ?>">
                                        <i class="bi bi-chevron-right hier-caret is-leaf" aria-hidden="true"></i>
                                        <span class="hier-icon user"><?= esc($initials($u['full_name'])) ?></span>
                                        <span class="hier-label" data-text><?= esc($u['full_name']) ?><small><?= esc($u['username']) ?></small></span>
                                        <span class="hier-meta"><?php if (!(int) $u['is_active']): ?><span class="hier-dot is-off" title="Inactive"></span><?php endif; ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php foreach ($b['shops'] as $s): $sid = (int) $s['id']; $active = (int) $s['is_active'] === 1; ?>
                        <li role="treeitem" aria-expanded="false" data-node="shop-<?= $sid ?>">
                            <div class="hier-row" tabindex="-1" data-select="shop-<?= $sid ?>">
                                <i class="bi bi-chevron-right hier-caret <?= $s['staff'] ? '' : 'is-leaf' ?>" data-toggle aria-hidden="true"></i>
                                <span class="hier-icon shop <?= $active ? '' : 'is-inactive' ?>"><i class="bi bi-shop"></i></span>
                                <span class="hier-label" data-text><?= esc($s['name']) ?><small><?= esc($s['code']) ?></small></span>
                                <span class="hier-meta">
                                    <span class="hier-count" title="Staff"><i class="bi bi-person"></i> <?= count($s['staff']) ?></span>
                                    <span class="hier-dot <?= !$active ? 'is-off' : ($s['ready'] ? '' : 'is-warn') ?>" title="<?= !$active ? 'Inactive' : ($s['ready'] ? 'Ready' : 'Needs setup') ?>"></span>
                                </span>
                            </div>
                            <?php if ($s['staff']): ?>
                            <ul role="group">
                                <?php foreach ($s['staff'] as $u): ?>
                                <li role="treeitem" data-node="user-<?= (int) $u['id'] ?>">
                                    <div class="hier-row" tabindex="-1" data-select="user-<?= (int) $u['id'] ?>">
                                        <i class="bi bi-chevron-right hier-caret is-leaf" aria-hidden="true"></i>
                                        <span class="hier-icon user"><?= esc($initials($u['full_name'])) ?></span>
                                        <span class="hier-label" data-text><?= esc($u['full_name']) ?><small><?= esc($u['role_code'] ?? '') ?></small></span>
                                        <span class="hier-meta"><?php if ((int) $u['is_default'] === 1): ?><span class="hier-count" title="Default branch">default</span><?php endif; ?><?php if (!(int) $u['is_active']): ?><span class="hier-dot is-off" title="Inactive"></span><?php endif; ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if ($canCreateShop && $b['can_manage'] && (int) $b['is_active'] === 1): ?>
                        <li role="none" class="hier-add-item">
                            <button type="button" class="hier-row hier-add" data-add-shop="<?= $bid ?>" data-business-name="<?= esc($b['name']) ?>">
                                <i class="bi bi-chevron-right hier-caret is-leaf" aria-hidden="true"></i>
                                <span class="hier-icon"><i class="bi bi-plus-lg"></i></span>
                                <span class="hier-label">Add shop</span>
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="empty-state py-4 d-none" id="hierNoMatch"><i class="bi bi-search"></i>Nothing matches your search.</div>
        </div>
    </div>

    <div class="card hier-detail">
        <div class="card-body" id="hierDetail" aria-live="polite">
            <div class="empty-state"><i class="bi bi-hand-index"></i><div class="empty-state-title">Select a business, shop or user</div>Details appear here.</div>
        </div>
    </div>
</div>

<?php /* Detail templates: one per business, shop and user. */ ?>
<?php foreach ($tree as $b): $bid = (int) $b['id']; ?>
<template id="detail-business-<?= $bid ?>">
    <div class="hier-detail-head">
        <span class="hier-icon business"><i class="bi bi-building"></i></span>
        <div><h2><?= esc($b['name']) ?></h2><div class="text-muted small">Business · <?= esc($b['code']) ?> · <?= (int) $b['is_active'] ? '<span class="status status-success">Active</span>' : '<span class="status">Inactive</span>' ?></div></div>
    </div>
    <div class="hier-facts">
        <div><span>Shops</span><strong><?= (int) $b['totals']['active_shops'] ?> active</strong><?= $b['totals']['shops'] > $b['totals']['active_shops'] ? ' <span class="small text-muted">+' . ($b['totals']['shops'] - $b['totals']['active_shops']) . ' inactive</span>' : '' ?></div>
        <div><span>Ready to trade</span><strong><?= (int) $b['totals']['ready_shops'] ?> of <?= (int) $b['totals']['shops'] ?></strong></div>
        <div><span>Users</span><strong><?= (int) $b['totals']['users'] ?></strong></div>
        <div><span>Gas in stock</span><strong><?= number_format($b['totals']['gas_kg'], 2) ?> KG</strong></div>
        <div><span>Sales today</span><strong><?= $money($b['totals']['sales_today']) ?></strong></div>
    </div>
    <?php if ($b['phone'] || $b['email'] || $b['address'] || $b['city']): ?>
    <div class="hier-section">Contact</div>
    <div class="small"><?= esc(implode(' · ', array_filter([$b['phone'], $b['email'], trim(($b['address'] ?? '') . ' ' . ($b['city'] ?? ''))]))) ?></div>
    <?php endif; ?>
    <div class="hier-section">Head Office users</div>
    <?php if ($b['head_office']): ?>
        <ul class="hier-people"><?php foreach ($b['head_office'] as $u): ?><?= $personRow($u, (int) $u['is_default'] === 1 ? ' · default' : '') ?><?php endforeach; ?></ul>
    <?php else: ?><p class="small text-muted mb-0">No Head Office users. Assign them in Businesses &amp; Shops.</p><?php endif; ?>
    <div class="hier-section">Shops</div>
    <?php if ($b['shops']): ?>
    <ul class="hier-people">
        <?php foreach ($b['shops'] as $s): ?>
        <li><button type="button" data-goto="shop-<?= (int) $s['id'] ?>"><span class="hier-icon shop <?= (int) $s['is_active'] ? '' : 'is-inactive' ?>"><i class="bi bi-shop"></i></span><span class="min-w-0"><span class="fw-semibold d-block"><?= esc($s['name']) ?></span><span class="meta"><?= esc($s['code']) ?> · <?= count($s['staff']) ?> staff · <?= number_format($s['live']['gas_kg'], 2) ?> KG gas</span></span></button>
            <?= !(int) $s['is_active'] ? '<span class="status">Inactive</span>' : ($s['ready'] ? '<span class="status status-success">Ready</span>' : '<span class="status status-warning">Needs setup</span>') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="small text-muted">No shops yet.</p><?php endif; ?>
    <?php if ($canCreateShop && $b['can_manage'] && (int) $b['is_active'] === 1): ?>
        <button type="button" class="btn btn-primary btn-sm mt-3" data-add-shop="<?= $bid ?>" data-business-name="<?= esc($b['name']) ?>"><i class="bi bi-plus-lg me-1"></i>Add shop</button>
    <?php endif; ?>
</template>

<?php foreach ($b['shops'] as $s): $sid = (int) $s['id']; $active = (int) $s['is_active'] === 1; $isCurrent = $sid === $currentShopId; ?>
<template id="detail-shop-<?= $sid ?>">
    <div class="hier-detail-head">
        <span class="hier-icon shop <?= $active ? '' : 'is-inactive' ?>"><i class="bi bi-shop"></i></span>
        <div><h2><?= esc($s['name']) ?></h2><div class="text-muted small">Shop · <?= esc($s['code']) ?> · <?= esc($b['name']) ?> · <?= !$active ? '<span class="status">Inactive</span>' : ($s['ready'] ? '<span class="status status-success">Ready</span>' : '<span class="status status-warning">Needs setup</span>') ?><?= $isCurrent ? ' · <span class="status status-info">You are here</span>' : '' ?></div></div>
    </div>
    <div class="hier-facts">
        <div><span>Gas in stock</span><strong><?= number_format($s['live']['gas_kg'], 2) ?> KG</strong></div>
        <div><span>Cylinders</span><strong><?= (int) $s['live']['filled'] ?> filled · <?= (int) $s['live']['empty'] ?> empty</strong></div>
        <div><span>Sales today</span><strong><?= $money($s['live']['sales_today']) ?></strong> <span class="small text-muted"><?= (int) $s['live']['sale_count_today'] ?> sale(s)</span></div>
        <div><span>Cash session</span><strong><?= $s['live']['cash_open'] ? 'Open · ' . esc($s['live']['register']) : 'Closed' ?></strong></div>
        <div><span>Customers</span><strong><?= (int) $s['live']['customers'] ?></strong></div>
        <div><span>Staff</span><strong><?= count($s['staff']) ?></strong></div>
    </div>
    <?php if ($s['phone'] || $s['address'] || $s['city']): ?>
    <div class="hier-section">Contact</div>
    <div class="small"><?= esc(implode(' · ', array_filter([$s['phone'], trim(($s['address'] ?? '') . ' ' . ($s['city'] ?? ''))]))) ?></div>
    <?php endif; ?>
    <div class="hier-section">Setup checklist</div>
    <ul class="hier-checklist">
        <?php foreach ($s['readiness'] as $check): ?>
        <li><i class="bi <?= $check['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>" aria-hidden="true"></i><span><strong><?= esc($check['label']) ?></strong> — <?= $check['ok'] ? 'done' : esc($check['hint']) ?><?php if (!$check['ok'] && $isCurrent): ?> <a href="<?= site_url($check['url']) ?>">Fix</a><?php endif; ?></span></li>
        <?php endforeach; ?>
    </ul>
    <div class="hier-section">Staff</div>
    <?php if ($s['staff']): ?>
        <ul class="hier-people"><?php foreach ($s['staff'] as $u): ?><?= $personRow($u, (int) $u['is_default'] === 1 ? ' · default branch' : '') ?><?php endforeach; ?></ul>
    <?php else: ?><p class="small text-muted mb-0">No staff assigned. Give users access to this shop in Users / Roles (from within the shop) or Businesses &amp; Shops.</p><?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($s['can_switch'] && !$isCurrent): ?>
        <form method="post" action="<?= site_url('shop-context/select') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="location_id" value="<?= $sid ?>"><button class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Switch to this shop</button></form>
        <?php endif; ?>
        <?php if ($isCurrent): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('shop-settings') ?>"><i class="bi bi-gear me-1"></i>Shop settings</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('dashboard') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <?php endif; ?>
    </div>
    <?php if (!$isCurrent && !$s['ready'] && $s['can_switch']): ?><p class="small text-muted mt-2 mb-0">Switch to this shop to complete its setup.</p><?php endif; ?>
</template>
<?php endforeach; ?>
<?php endforeach; ?>

<?php foreach ($users as $uid => $u): ?>
<template id="detail-user-<?= (int) $uid ?>">
    <div class="hier-detail-head">
        <span class="hier-icon user"><?= esc($initials($u['full_name'])) ?></span>
        <div><h2><?= esc($u['full_name']) ?></h2><div class="text-muted small">User · <?= esc($u['username']) ?> · <?= (int) $u['is_active'] ? '<span class="status status-success">Active</span>' : '<span class="status">Inactive</span>' ?></div></div>
    </div>
    <div class="hier-facts">
        <div><span>Role</span><strong><?= esc($u['role_name'] ?? '') ?></strong> <span class="small text-muted"><?= esc($u['role_code'] ?? '') ?></span></div>
        <div><span>Email</span><strong class="text-break"><?= esc($u['email'] ?: '—') ?></strong></div>
        <div><span>Last sign-in</span><strong><?= esc($when($u['last_login_at'] ?? null)) ?></strong></div>
    </div>
    <div class="hier-section">Access</div>
    <ul class="hier-people">
        <?php foreach ($u['places'] as $place): ?>
        <li><button type="button" data-goto="<?= esc($place['node']) ?>"><span class="hier-icon <?= $place['type'] === 'Head Office' ? 'group' : 'shop' ?>"><i class="bi <?= $place['type'] === 'Head Office' ? 'bi-briefcase' : 'bi-shop' ?>"></i></span><span class="min-w-0"><span class="fw-semibold d-block"><?= esc($place['name']) ?></span><span class="meta"><?= esc($place['type']) ?><?= $place['default'] ? ' · default' : '' ?></span></span></button></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($canManageUsers): ?><a class="btn btn-outline-secondary btn-sm mt-3" href="<?= site_url('users?edit=' . (int) $uid) ?>"><i class="bi bi-pencil me-1"></i>Edit user</a><?php endif; ?>
</template>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($canCreateShop): ?>
<div class="modal fade" id="addShopModal" tabindex="-1" aria-labelledby="addShopTitle" aria-hidden="true">
    <div class="modal-dialog"><form method="post" action="<?= site_url('business-management/shop/save') ?>" class="modal-content">
        <?= csrf_field() ?>
        <input type="hidden" name="business_id" id="addShopBusiness">
        <input type="hidden" name="return_to" value="hierarchy">
        <div class="modal-header"><h2 class="modal-title fs-5" id="addShopTitle">Add shop to <span id="addShopBusinessName"></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="small text-muted">The shop is created with default settings, a main cash counter and an inventory policy. Then add stock, a gas rate and staff.</p>
            <div class="row g-3">
                <div class="col-sm-4"><label class="form-label required" for="shopCode">Code</label><input class="form-control text-uppercase" id="shopCode" name="code" maxlength="30" pattern="[A-Za-z0-9_\-]{2,30}" required placeholder="e.g. LHR-02"><div class="form-text">2–30 letters, numbers, - or _</div></div>
                <div class="col-sm-8"><label class="form-label required" for="shopName">Name</label><input class="form-control" id="shopName" name="name" maxlength="120" required placeholder="e.g. Gulberg Branch"></div>
                <div class="col-sm-6"><label class="form-label" for="shopCity">City</label><input class="form-control" id="shopCity" name="city" maxlength="80"></div>
                <div class="col-sm-6"><label class="form-label" for="shopPhone">Phone</label><input class="form-control" id="shopPhone" name="phone" maxlength="30"></div>
                <div class="col-12"><label class="form-label" for="shopAddress">Address</label><input class="form-control" id="shopAddress" name="address" maxlength="255"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create shop</button></div>
    </form></div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    const tree = document.getElementById('hierTree');
    const detail = document.getElementById('hierDetail');
    if (!tree) return;
    const rows = () => [...tree.querySelectorAll('.hier-row')].filter(r => r.offsetParent !== null);
    const itemOf = (row) => row.closest('[role="treeitem"], .hier-add-item');
    const hasChildren = (item) => item && item.querySelector(':scope > ul');

    function setExpanded(item, open) {
        if (item && hasChildren(item)) item.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function focusRow(row) {
        tree.querySelectorAll('.hier-row').forEach(r => r.tabIndex = -1);
        row.tabIndex = 0;
        row.focus();
    }
    function select(nodeId, row) {
        const tpl = document.getElementById('detail-' + nodeId);
        if (!tpl) return;
        tree.querySelectorAll('[role="treeitem"][aria-selected]').forEach(i => i.removeAttribute('aria-selected'));
        row = row || tree.querySelector('.hier-row[data-select="' + nodeId + '"]');
        if (row) {
            for (let p = row.parentElement.closest('[role="treeitem"]')?.parentElement?.closest('[role="treeitem"]'); p; p = p.parentElement.closest('[role="treeitem"]')) setExpanded(p, true);
            row.closest('[role="treeitem"]')?.setAttribute('aria-selected', 'true');
            row.tabIndex = 0;
            row.scrollIntoView({ block: 'nearest' });
        }
        detail.replaceChildren(tpl.content.cloneNode(true));
        history.replaceState(null, '', '?node=' + nodeId);
        if (window.innerWidth < 992) detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    tree.addEventListener('click', (e) => {
        const row = e.target.closest('.hier-row');
        if (!row || row.dataset.addShop) return;
        const item = itemOf(row);
        if (e.target.closest('[data-toggle]') && hasChildren(item)) {
            setExpanded(item, item.getAttribute('aria-expanded') !== 'true');
            return;
        }
        if (hasChildren(item) && item.getAttribute('aria-selected') === 'true') setExpanded(item, item.getAttribute('aria-expanded') !== 'true');
        else if (hasChildren(item)) setExpanded(item, true);
        select(row.dataset.select, row);
        focusRow(row);
    });

    // Keyboard: Up/Down move, Right expands/enters, Left collapses/leaves, Home/End, Enter/Space select.
    tree.addEventListener('keydown', (e) => {
        const row = e.target.closest('.hier-row');
        if (!row) return;
        const list = rows(), i = list.indexOf(row), item = itemOf(row);
        if (e.key === 'ArrowDown' && list[i + 1]) { e.preventDefault(); focusRow(list[i + 1]); }
        else if (e.key === 'ArrowUp' && list[i - 1]) { e.preventDefault(); focusRow(list[i - 1]); }
        else if (e.key === 'Home') { e.preventDefault(); focusRow(list[0]); }
        else if (e.key === 'End') { e.preventDefault(); focusRow(list[list.length - 1]); }
        else if (e.key === 'ArrowRight' && hasChildren(item)) {
            e.preventDefault();
            if (item.getAttribute('aria-expanded') !== 'true') setExpanded(item, true); else if (list[i + 1]) focusRow(list[i + 1]);
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (hasChildren(item) && item.getAttribute('aria-expanded') === 'true') setExpanded(item, false);
            else { const parent = item.parentElement.closest('[role="treeitem"]'); if (parent) focusRow(parent.querySelector(':scope > .hier-row')); }
        } else if ((e.key === 'Enter' || e.key === ' ') && !row.dataset.addShop) { e.preventDefault(); select(row.dataset.select, row); }
    });

    // Links inside the detail panel jump to another node.
    detail.addEventListener('click', (e) => {
        const go = e.target.closest('[data-goto]');
        if (go) { select(go.dataset.goto); const r = tree.querySelector('[aria-selected="true"] > .hier-row'); if (r) r.focus({ preventScroll: true }); }
    });

    document.getElementById('hierExpand').onclick = () => tree.querySelectorAll('[role="treeitem"]').forEach(i => setExpanded(i, true));
    document.getElementById('hierCollapse').onclick = () => tree.querySelectorAll('[role="treeitem"]').forEach(i => setExpanded(i, i.parentElement === tree));

    // Search: show matching nodes with their ancestors; highlight the match.
    const search = document.getElementById('hierSearch');
    const originals = new Map();
    tree.querySelectorAll('[data-text]').forEach(el => originals.set(el, el.innerHTML));
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        originals.forEach((html, el) => { el.innerHTML = html; });
        const items = [...tree.querySelectorAll('[role="treeitem"], .hier-add-item')];
        if (!q) { items.forEach(i => i.classList.remove('is-hidden')); document.getElementById('hierNoMatch').classList.add('d-none'); return; }
        items.forEach(i => i.classList.add('is-hidden'));
        let any = false;
        tree.querySelectorAll('[role="treeitem"]').forEach(item => {
            const label = item.querySelector(':scope > .hier-row [data-text]');
            if (!label || !label.textContent.toLowerCase().includes(q)) return;
            any = true;
            const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
            for (let n = walker.nextNode(); n; n = walker.nextNode()) {
                const at = n.textContent.toLowerCase().indexOf(q);
                if (at < 0) continue;
                const mark = document.createElement('mark');
                const range = document.createRange();
                range.setStart(n, at); range.setEnd(n, at + q.length);
                range.surroundContents(mark);
                break;
            }
            for (let p = item; p; p = p.parentElement.closest('[role="treeitem"]')) {
                p.classList.remove('is-hidden');
                if (p !== item) setExpanded(p, true);
            }
        });
        document.getElementById('hierNoMatch').classList.toggle('d-none', any);
    });

    // Add shop
    const modalEl = document.getElementById('addShopModal');
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-add-shop]');
        if (!btn || !modalEl) return;
        document.getElementById('addShopBusiness').value = btn.dataset.addShop;
        document.getElementById('addShopBusinessName').textContent = btn.dataset.businessName;
        modalEl.addEventListener('shown.bs.modal', () => document.getElementById('shopCode').focus(), { once: true });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    document.getElementById('shopCode')?.addEventListener('input', (e) => { e.target.value = e.target.value.toUpperCase(); });

    // Initial selection: ?node=… (e.g. a newly created shop), else the current shop, else the first business.
    const params = new URLSearchParams(location.search);
    const initial = params.get('node') && document.getElementById('detail-' + params.get('node')) ? params.get('node')
        : (document.getElementById('detail-shop-<?= (int) $currentShopId ?>') ? 'shop-<?= (int) $currentShopId ?>' : tree.querySelector('.hier-row')?.dataset.select);
    if (initial) select(initial);
    const first = tree.querySelector('[aria-selected="true"] > .hier-row') || tree.querySelector('.hier-row');
    if (first) first.tabIndex = 0;
})();
</script>
<?= $this->endSection() ?>
