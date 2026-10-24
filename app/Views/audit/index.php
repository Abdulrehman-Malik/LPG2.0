<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$pretty = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $data = json_decode($json, true);
    return is_array($data) ? (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $json;
};
$summary = static function (?string $json): string {
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data)) {
        return (string) $json;
    }
    $parts = [];
    foreach (array_slice($data, 0, 3, true) as $k => $v) {
        $parts[] = $k . ': ' . (is_scalar($v) || $v === null ? (string) $v : '…');
    }
    return implode(' · ', $parts) . (count($data) > 3 ? ' …' : '');
};
$tone = static function (string $action): string {
    $a = strtoupper($action);
    return match (true) {
        str_contains($a, 'VOID'), str_contains($a, 'DELETE'), str_contains($a, 'FAIL'), str_contains($a, 'DISABLE') => 'status-danger',
        str_contains($a, 'CREATE'), str_contains($a, 'OPEN'), str_contains($a, 'LOGIN') => 'status-success',
        str_contains($a, 'UPDATE'), str_contains($a, 'CLOSE'), str_contains($a, 'CHANGE') => 'status-info',
        default => '',
    };
};
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">The latest <?= count($rows) ?> recorded actions in this branch: who did what and when. Select a row's details to see the full before/after values.</p>
    </div>
</div>
<form method="get" action="<?= site_url('audit') ?>" class="card mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="auditAction">Action</label>
        <select class="form-select" id="auditAction" name="action"><option value="">All actions</option>
        <?php foreach ($actions as $a): ?><option value="<?= esc($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= esc(ucwords(strtolower(str_replace('_', ' ', (string) $a)))) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="auditEntity">Record</label>
        <select class="form-select" id="auditEntity" name="entity_type"><option value="">All records</option>
        <?php foreach ($entityTypes as $e): ?><option value="<?= esc($e) ?>" <?= $filters['entity_type'] === $e ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', (string) $e))) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="auditFrom">From</label><input class="form-control" type="date" id="auditFrom" name="from" value="<?= esc($filters['from']) ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="auditTo">To</label><input class="form-control" type="date" id="auditTo" name="to" value="<?= esc($filters['to']) ?>"></div>
    <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button><a class="btn btn-outline-secondary" href="<?= site_url('audit') ?>">Clear</a></div>
</div></form>
<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-shield-check"></i><div class="empty-state-title">No audit entries yet</div></div>
    <?php else: ?>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 datatable">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-nowrap" data-order="<?= esc($r['created_at']) ?>"><?= esc(date('d M Y, h:i A', strtotime($r['created_at']))) ?></td>
                    <td><?= esc($r['full_name'] ?? 'System') ?></td>
                    <td><span class="status <?= $tone((string) $r['action']) ?>"><?= esc(ucwords(strtolower(str_replace('_', ' ', (string) $r['action'])))) ?></span></td>
                    <td class="text-nowrap"><?= esc(ucwords(str_replace('_', ' ', (string) $r['entity_type']))) ?><?= !empty($r['entity_id']) ? ' #' . esc($r['entity_id']) : '' ?></td>
                    <td style="min-width:16rem">
                        <?php if (!empty($r['new_values']) || !empty($r['old_values'])): ?>
                        <details>
                            <summary class="small text-muted text-truncate" style="max-width:32rem"><?= esc($summary($r['new_values'] ?? $r['old_values'] ?? '')) ?></summary>
                            <div class="row g-2 mt-1">
                                <?php if (!empty($r['old_values'])): ?><div class="col-md-6"><div class="small fw-semibold">Before</div><pre class="small mb-0 p-2 rounded" style="background:var(--lpg-surface-2);white-space:pre-wrap"><?= esc($pretty($r['old_values'])) ?></pre></div><?php endif; ?>
                                <?php if (!empty($r['new_values'])): ?><div class="col-md-6"><div class="small fw-semibold">After</div><pre class="small mb-0 p-2 rounded" style="background:var(--lpg-surface-2);white-space:pre-wrap"><?= esc($pretty($r['new_values'])) ?></pre></div><?php endif; ?>
                            </div>
                        </details>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
