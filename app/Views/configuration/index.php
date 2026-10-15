<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Central Configuration</h4>
        <p class="text-muted mb-0">Manage only the application, business and shop settings your account is authorized to change.</p>
    </div>
</div>

<?php if ($canSeeApplication): ?>
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Application Settings</h5>
            <div class="text-muted small">Platform defaults, security limits, supported languages and global feature guardrails.</div>
        </div>
        <a class="btn btn-outline-primary" href="<?= site_url('configuration/application') ?>">Open Application Settings</a>
    </div>
</div>
<?php endif; ?>

<?php if ($businesses): ?>
<div class="card mb-3">
    <div class="card-header"><strong>Head Office / Business Settings</strong></div>
    <div class="list-group list-group-flush">
        <?php foreach ($businesses as $business): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= site_url('configuration/business/'.(int)$business['id']) ?>">
            <span><?= esc($business['name']) ?><span class="text-muted small d-block"><?= esc($business['code']) ?></span></span>
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($shops): ?>
<div class="card mb-3">
    <div class="card-header"><strong>Shop Settings</strong></div>
    <div class="list-group list-group-flush">
        <?php foreach ($shops as $shop): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= site_url('configuration/shop/'.(int)$shop['id']) ?>">
            <span><?= esc($shop['name']) ?><span class="text-muted small d-block"><?= esc($shop['business_name'] ?? 'Unassigned business') ?></span></span>
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$canSeeApplication && !$businesses && !$shops): ?>
<div class="alert alert-info mb-0">No configuration scope has been assigned to this account.</div>
<?php endif; ?>
<?= $this->endSection() ?>
