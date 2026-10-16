<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Customer Ordering Catalog</h4>
        <p class="text-muted mb-0">Products belong to a business; selling availability is configured separately for every shop.</p>
    </div>
</div>

<?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $class): ?>
    <?php if (session()->getFlashdata($flashKey)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata($flashKey)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="card mb-3">
    <div class="card-body d-flex align-items-end justify-content-between flex-wrap gap-3">
        <div>
            <div class="small text-muted">Business / Head Office</div>
            <div class="fw-semibold"><?= esc($business['name'] ?? '—') ?></div>
            <div class="small text-muted">Catalog ownership is at the business level.</div>
        </div>
        <?php if ($shops): ?>
        <form method="get" action="<?= site_url('mobile-catalog') ?>" class="d-flex gap-2 align-items-end">
            <div><label for="catalog-shop" class="form-label">Selected shop</label><select class="form-select" id="catalog-shop" name="shop_id" required><?php foreach ($shops as $availableShop): ?><option value="<?= (int)$availableShop['id'] ?>" <?= (int)$availableShop['id'] === (int)$selectedShopId ? 'selected' : '' ?>><?= esc(($availableShop['business_name'] ?? '') . ' — ' . $availableShop['name']) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-outline-primary" type="submit">Switch shop</button>
        </form>
        <?php else: ?>
            <div><div class="small text-muted">Selected shop</div><div class="fw-semibold"><?= esc($shop['name'] ?? '—') ?></div></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManageBusinessCatalog): ?>
<div class="card mb-4">
    <div class="card-header"><strong><?= !empty($editProduct) ? 'Edit business catalog product' : 'Add business catalog product' ?></strong></div>
    <div class="card-body">
        <form method="post" action="<?= site_url('mobile-catalog/product/save?shop_id=' . (int)$selectedShopId) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int)($editProduct['id'] ?? 0) ?>">
            <div class="col-lg-4">
                <label class="form-label" for="product-cylinder-type">Cylinder type *</label>
                <select class="form-select" id="product-cylinder-type" name="cylinder_type_id" required>
                    <option value="">Select cylinder type</option>
                    <?php foreach ($cylinderTypes as $type): ?>
                    <option value="<?= (int)$type['id'] ?>" <?= (int)($editProduct['cylinder_type_id'] ?? 0) === (int)$type['id'] ? 'selected' : '' ?>><?= esc($type['code'] . ' · ' . $type['name'] . ' (' . number_format((float)$type['capacity_kg'], 3) . ' kg)') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="product-sale-mode">Sale mode *</label>
                <select class="form-select" id="product-sale-mode" name="sale_mode" required>
                    <option value="sell_gas_only" <?= ($editProduct['sale_mode'] ?? '') === 'sell_gas_only' ? 'selected' : '' ?>>Gas refill by KG</option>
                    <option value="sell_filled" <?= ($editProduct['sale_mode'] ?? '') === 'sell_filled' ? 'selected' : '' ?>>Filled cylinder sale</option>
                    <option value="sell_empty" <?= ($editProduct['sale_mode'] ?? '') === 'sell_empty' ? 'selected' : '' ?>>Empty cylinder sale</option>
                </select>
            </div>
            <div class="col-lg-5">
                <label class="form-label" for="product-name">Product display name *</label>
                <input class="form-control" id="product-name" name="display_name" maxlength="150" required value="<?= esc($editProduct['display_name'] ?? '') ?>" placeholder="For example, 11.8 kg gas refill">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="product-minimum">Minimum quantity *</label>
                <input class="form-control" type="number" id="product-minimum" name="min_quantity" step="0.001" min="0.1" required value="<?= esc((string)($editProduct['min_quantity'] ?? 0.1)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="product-step">Quantity step *</label>
                <input class="form-control" type="number" id="product-step" name="quantity_step" step="0.001" min="0.1" required value="<?= esc((string)($editProduct['quantity_step'] ?? 0.1)) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="product-order">Display order</label>
                <input class="form-control" type="number" id="product-order" name="sort_order" min="0" step="1" value="<?= (int)($editProduct['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="product-active">Catalog status</label>
                <select class="form-select" name="is_active" id="product-active"><option value="1" <?= (int)($editProduct['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($editProduct['is_active'] ?? 1) !== 1 ? 'selected' : '' ?>>Inactive</option></select>
            </div>
            <div class="col-12">
                <label class="form-label" for="product-description">Customer-facing description</label>
                <textarea class="form-control" id="product-description" name="description" maxlength="500" rows="2"><?= esc($editProduct['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= !empty($editProduct) ? 'Save product' : 'Add product' ?></button>
                <?php if (!empty($editProduct)): ?><a class="btn btn-outline-secondary" href="<?= site_url('mobile-catalog?shop_id=' . (int)$selectedShopId) ?>">Cancel edit</a><?php endif; ?>
            </div>
        </form>
        <div class="form-text mt-2">A product in the business catalog is not automatically offered by every shop. Use the shop availability actions below to activate it.</div>
    </div>
</div>
<?php elseif (!$canViewBusinessCatalog && !$canManageShopAvailability): ?>
<div class="alert alert-info">You can only use this page if your role has the relevant catalog or shop product permissions.</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong>Products offered by <?= esc($shop['name'] ?? 'selected shop') ?></strong>
        <span class="badge text-bg-light border"><?= count($products) ?> catalog product(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Product</th><th>Sale mode</th><th>Quantity rule</th><th>Business catalog</th><th>Shop availability</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No products are configured for this business. A user with business catalog permission can add products above.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><div class="fw-semibold"><?= esc($product['display_name']) ?></div><div class="small text-muted"><?= esc($product['cylinder_code'] . ' · ' . $product['cylinder_name'] . ' (' . number_format((float)$product['capacity_kg'], 3) . ' kg)') ?></div><?php if (!empty($product['description'])): ?><div class="small text-muted"><?= esc($product['description']) ?></div><?php endif; ?></td>
                    <td><?= esc(match ($product['sale_mode']) {'sell_gas_only' => 'Gas refill', 'sell_filled' => 'Filled cylinder', 'sell_empty' => 'Empty cylinder', default => $product['sale_mode']}) ?></td>
                    <td><?= number_format((float)$product['min_quantity'], 3) ?> min · <?= number_format((float)$product['quantity_step'], 3) ?> step <?= esc($product['quantity_unit']) ?></td>
                    <td><?php if ((int)$product['catalog_active'] === 1): ?><span class="status status-success">Active</span><?php else: ?><span class="badge text-bg-secondary">Inactive</span><?php endif; ?></td>
                    <td><?php if ((int)$product['catalog_active'] !== 1): ?><span class="text-muted">Unavailable — business catalog inactive</span><?php elseif ((int)$product['shop_active'] === 1): ?><span class="status status-success">Enabled</span><?php else: ?><span class="badge text-bg-secondary">Not offered</span><?php endif; ?></td>
                    <td class="text-end">
                        <?php if ($canManageBusinessCatalog): ?><a class="btn btn-sm btn-outline-primary mb-1" href="<?= site_url('mobile-catalog?shop_id=' . (int)$selectedShopId . '&edit=' . (int)$product['product_id']) ?>">Edit</a><?php endif; ?>
                        <?php if ($canManageShopAvailability && (int)$product['catalog_active'] === 1): ?>
                            <form class="d-inline" method="post" action="<?= site_url('mobile-catalog/product/' . (int)$product['product_id'] . '/availability?shop_id=' . (int)$selectedShopId) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="is_active" value="<?= (int)$product['shop_active'] === 1 ? '0' : '1' ?>">
                                <button class="btn btn-sm <?= (int)$product['shop_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"><?= (int)$product['shop_active'] === 1 ? 'Disable at shop' : 'Enable at shop' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(() => {
    const mode = document.getElementById('product-sale-mode');
    const minimum = document.getElementById('product-minimum');
    const step = document.getElementById('product-step');
    if (!mode || !minimum || !step) return;

    const applyQuantityRules = (resetInvalidValues) => {
        const gasMode = mode.value === 'sell_gas_only';
        const floor = gasMode ? 0.1 : 1;
        minimum.min = String(floor);
        minimum.step = String(gasMode ? 0.1 : 1);
        step.min = String(floor);
        step.step = String(gasMode ? 0.1 : 1);

        if (resetInvalidValues) {
            const currentMin = Number(minimum.value);
            const currentStep = Number(step.value);
            if (!Number.isFinite(currentMin) || currentMin < floor || (!gasMode && !Number.isInteger(currentMin))) {
                minimum.value = String(floor);
            }
            if (!Number.isFinite(currentStep) || currentStep < floor || (!gasMode && !Number.isInteger(currentStep))) {
                step.value = String(floor);
            }
        }
    };

    mode.addEventListener('change', () => applyQuantityRules(true));
    applyQuantityRules(false);
})();
</script>
<?= $this->endSection() ?>
