<?php
/**
 * Optional transaction date field for financial postings. Rendered only when the
 * business allows back-dating; otherwise the server posts with the current time.
 * The server re-validates the value (TransactionDatePolicy); the min/max here are a convenience.
 *
 * @var string|null $dateFieldClass optional wrapper class
 */
$dateRules = (new \App\Services\TransactionDatePolicy())->rules((int) session()->get('location_id'));
if (!$dateRules['allows_backdate']) {
    return;
}
$dateValue = old('transaction_at') ?: date('Y-m-d\TH:i');
?>
<div class="<?= esc($dateFieldClass ?? 'mb-3') ?>">
    <label class="form-label" for="transaction_at">Transaction date &amp; time</label>
    <input type="datetime-local" class="form-control" id="transaction_at" name="transaction_at"
           value="<?= esc($dateValue) ?>"
           min="<?= esc($dateRules['earliest']) ?>T00:00" max="<?= esc(date('Y-m-d\TH:i')) ?>">
    <div class="form-text"><?= esc($dateRules['description']) ?></div>
</div>
