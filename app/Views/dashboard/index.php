<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.dashboard-hero{display:flex;align-items:center;gap:.8rem;padding:.8rem 1rem;border-radius:1rem;background:linear-gradient(135deg,#13283b,#1e4f68);color:#fff;box-shadow:0 12px 28px rgba(15,23,42,.12);overflow:hidden;position:relative}.dashboard-hero:after{content:"";position:absolute;width:240px;height:240px;border-radius:50%;right:-80px;top:-130px;background:rgba(255,255,255,.08)}.dashboard-hero-art{display:flex;align-items:center;gap:.35rem;z-index:1}.dashboard-cylinder{width:42px;height:58px;border-radius:16px 16px 10px 10px;background:linear-gradient(90deg,#dce6ed,#fff,#aebdca);display:flex;align-items:center;justify-content:center;color:#176b83;font-size:1.7rem;box-shadow:inset 0 -8px 0 rgba(0,0,0,.08)}.dashboard-flame{font-weight:800;font-size:.72rem;background:#f59e0b;border-radius:999px;padding:.35rem .45rem;align-self:flex-end;margin-bottom:.55rem}.dashboard-period-pill{margin-left:auto;z-index:1;white-space:nowrap;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);padding:.45rem .7rem;border-radius:999px;font-size:.78rem}.dashboard-summary-grid .col-12{display:flex}.dashboard-card{position:relative;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;padding:.75rem .85rem;min-height:142px;box-shadow:0 3px 14px rgba(15,23,42,.055);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.dashboard-card:hover{transform:translateY(-3px);box-shadow:0 10px 25px rgba(15,23,42,.11);border-color:#cbd5e1}.dashboard-card-top{display:flex;justify-content:space-between;align-items:center}.dashboard-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.35rem}.dashboard-icon.gas,.type-color.gas{background:#dbeafe;color:#2563eb}.dashboard-icon.empty,.type-color.empty{background:#ffedd5;color:#c2410c}.dashboard-icon.issued,.type-color.issued{background:#ede9fe;color:#7c3aed}.dashboard-icon.sale{background:#dcfce7;color:#15803d}.dashboard-icon.cash,.type-color.cash{background:#fef3c7;color:#a16207}.dashboard-icon.receivable,.type-color.receivable{background:#fee2e2;color:#b91c1c}.dashboard-icon.expense{background:#fce7f3;color:#be185d}.dashboard-chevron{color:#94a3b8}.dashboard-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:750;margin-top:.55rem}.dashboard-value{font-size:1.45rem;font-weight:800;line-height:1.1;color:#172033;margin-top:.2rem}.dashboard-value small{font-size:.75rem;font-weight:700;color:#64748b}.dashboard-sub{font-size:.72rem;color:#64748b;margin-top:.25rem}.dashboard-hint{font-size:.68rem;color:#94a3b8;margin-top:.45rem}.dashboard-payment-mini{display:flex;gap:.25rem;flex-wrap:wrap;margin-top:.4rem}.dashboard-payment-mini span{font-size:.62rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;padding:.18rem .4rem;color:#475569}.dashboard-note{padding:.7rem .9rem;border:1px dashed #cbd5e1;border-radius:.65rem;background:#f8fafc;color:#64748b;font-size:.78rem}.detail-intro{display:flex;align-items:center;gap:.9rem;padding:.85rem 1rem;border-radius:.7rem;margin-bottom:1rem}.detail-intro i{font-size:1.5rem}.detail-intro strong{display:block;font-size:1.15rem}.detail-intro span{display:block;font-size:.78rem;color:#64748b}.gas-bg{background:#eff6ff;color:#1d4ed8}.empty-bg{background:#fff7ed;color:#c2410c}.issued-bg{background:#f5f3ff;color:#6d28d9}.receivable-bg{background:#fef2f2;color:#b91c1c}.dashboard-drill-card{border:1px solid #e2e8f0;border-radius:.65rem;margin-bottom:.65rem;overflow:hidden;background:#fff}.dashboard-drill-card summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:.55rem;padding:.8rem .9rem;font-size:.84rem}.dashboard-drill-card summary::-webkit-details-marker{display:none}.dashboard-drill-card summary:after{content:"\\f282";font-family:"bootstrap-icons";margin-left:.55rem;color:#94a3b8}.dashboard-drill-card[open] summary{background:#f8fafc;border-bottom:1px solid #e2e8f0}.type-color{display:inline-flex;width:12px;height:12px;border-radius:50%;flex:0 0 12px}.type-color.gas{background:#3b82f6}.type-color.empty{background:#f97316}.type-color.issued{background:#8b5cf6}.type-color.cash{background:#eab308}.type-color.receivable{background:#ef4444}.detail-kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin-bottom:1rem}.detail-kpi-grid>div{border:1px solid #e2e8f0;border-radius:.65rem;padding:.7rem;background:#f8fafc}.detail-kpi-grid span{display:block;font-size:.7rem;color:#64748b}.detail-kpi-grid strong{display:block;margin-top:.15rem;font-size:.95rem}.expense-category{border:1px solid #e2e8f0;border-radius:.65rem;padding:.65rem;background:#fff}.expense-category span,.expense-category small{display:block;color:#64748b}.expense-category strong{display:block;font-size:.95rem}@media(max-width:767.98px){.dashboard-hero{align-items:flex-start}.dashboard-period-pill{display:none}.dashboard-hero-art{display:none}.dashboard-value{font-size:1.3rem}.detail-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}

.dashboard-top-header{padding:.65rem .85rem;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 2px 10px rgba(15,23,42,.045)}.dashboard-top-header h4{font-weight:800;color:#172033}.dashboard-top-header #dashboardFilterToggle{font-weight:650}.dashboard-top-header + #dashboardDateFilter .card{border:1px solid #e2e8f0!important}.dashboard-top-header + #dashboardDateFilter .card-body{padding:.75rem 1rem!important}
.dashboard-hero-top{margin-bottom:.65rem!important;flex-wrap:wrap}.dashboard-hero-top #dashboardFilterToggle{position:relative;z-index:2;flex:0 0 auto;white-space:nowrap}.dashboard-hero-top .dashboard-period-pill{margin-left:auto}@media(max-width:767.98px){.dashboard-hero-top{gap:.75rem}.dashboard-hero-top .flex-grow-1{flex-basis:calc(100% - 1rem)}.dashboard-hero-top .dashboard-period-pill{margin-left:0}.dashboard-hero-top #dashboardFilterToggle{margin-left:auto}}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){
  // Dashboard auto-refresh is intentionally disabled; refresh manually to reload data.

  // Promote the shop overview hero to the top of the dashboard and place the date filter inside it.
  const hero=document.querySelector('.dashboard-hero');
  const contentWrap=document.querySelector('.content-wrap');
  const filterToggle=document.getElementById('dashboardFilterToggle');
  const dashboardHeader=document.querySelector('.dashboard-top-header');
  const dateFilter=document.getElementById('dashboardDateFilter');
  if(hero && contentWrap){
    contentWrap.insertBefore(hero, contentWrap.firstChild);
    hero.classList.add('dashboard-hero-top');
    if(filterToggle){
      filterToggle.classList.remove('btn-outline-primary');
      filterToggle.classList.add('btn-outline-light');
      hero.appendChild(filterToggle);
    }
    if(dashboardHeader) dashboardHeader.remove();
    if(dateFilter && dateFilter.parentNode!==contentWrap){
      contentWrap.insertBefore(dateFilter, hero.nextSibling);
    }
  }
  const modal=document.getElementById('dashboardDetailModal'); if(!modal)return;
  const body=document.getElementById('dashboardDetailBody'), title=document.getElementById('dashboardDetailTitle');
  const titles={gas:'Available Gas KG — Cylinder Type & Individual Cylinders',empty:'Empty Gas Cylinders in Shop — Type & Individual Cylinders',issued:'Issued Cylinders — Customers & Cylinder Details',sales:'Sales — Payment & Transaction Details',cash:'Cash Counter — Register In / Out Movements',receivable:'Credit Sales / Accounts Receivable — Customer OS',expenses:'Expenses — Category & Transaction Details'};
  modal.addEventListener('show.bs.modal',function(e){const target=e.relatedTarget?.getAttribute('data-detail-target');const source=document.getElementById(target);if(!source)return;title.textContent=titles[target.replace('detail-','')]||'Details';body.innerHTML=source.innerHTML;});
});
</script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$money = static fn(float $v): string => 'Rs. ' . number_format($v, 2);
$qty = static fn(float $v): string => number_format($v, 2);
$stockTab = trim((string)(service('request')->getGet('stock_tab') ?? ''));
if (!in_array($stockTab, ['filled', 'empty', 'issued'], true)) {
    $stockTab = '';
}
$displayStockTab = $stockTab !== '' ? $stockTab : 'filled';
?>

<div class="dashboard-top-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-warning"></i>Dashboard</h4>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="dashboardFilterToggle"
            data-bs-toggle="collapse" data-bs-target="#dashboardDateFilter"
            aria-expanded="false" aria-controls="dashboardDateFilter">
        <i class="bi bi-funnel me-1"></i><span>Show Date Filter</span>
    </button>
</div>

<div class="collapse mb-3" id="dashboardDateFilter">
    <div class="card shadow-sm border-0">
        <div class="card-body py-3">
            <form method="get" action="<?= site_url('dashboard') ?>" class="row g-2 align-items-end">
                <div class="col-sm-5 col-md-3">
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" class="form-control" value="<?= esc($range['from']) ?>" required>
                </div>
                <div class="col-sm-5 col-md-3">
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" class="form-control" value="<?= esc($range['to']) ?>" required>
                </div>
                <div class="col-sm-2 col-md-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply Filter</button>
                </div>
                <div class="col-12 col-md-auto">
                    <div class="small text-muted mt-1">Current range: <?= esc($range['label']) ?></div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filter = document.getElementById('dashboardDateFilter');
    const toggle = document.getElementById('dashboardFilterToggle');
    if (!filter || !toggle) return;
    filter.addEventListener('show.bs.collapse', function () {
        toggle.querySelector('span').textContent = 'Hide Date Filter';
        toggle.querySelector('i').className = 'bi bi-funnel-fill me-1';
    });
    filter.addEventListener('hide.bs.collapse', function () {
        toggle.querySelector('span').textContent = 'Show Date Filter';
        toggle.querySelector('i').className = 'bi bi-funnel me-1';
    });
});
</script>

<ul class="nav nav-tabs dashboard-tabs mb-3" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab === '' ? 'active' : '' ?> fw-semibold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-pane" type="button" role="tab">
            <i class="bi bi-grid-1x2 me-1"></i>Summary
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab !== '' ? 'active' : '' ?> fw-semibold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-pane" type="button" role="tab">
            <i class="bi bi-boxes me-1"></i>Current Stock
        </button>
    </li>
</ul>

<div class="tab-content" id="dashboardTabContent">
    <div class="tab-pane fade <?= $stockTab === '' ? 'show active' : '' ?>" id="summary-pane" role="tabpanel" aria-labelledby="summary-tab">
        <div class="dashboard-hero mb-4">
            <div class="dashboard-hero-art"><div class="dashboard-cylinder"><i class="bi bi-fuel-pump-fill"></i></div><div class="dashboard-flame">KG</div></div>
            <div class="flex-grow-1">
                <div class="small text-uppercase fw-bold opacity-75">Shop overview</div>
                <h3 class="mb-1">LPG Operations Dashboard</h3>
                <div class="small opacity-75">Click any summary card to drill into the underlying cylinders, customers, payments or cash movements.</div>
            </div>
            <div class="dashboard-period-pill"><i class="bi bi-calendar3 me-1"></i><?= esc($range['label']) ?></div>
        </div>

        <div class="row g-2 dashboard-summary-grid">
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card gas-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-gas">
                    <div class="dashboard-card-top"><span class="dashboard-icon gas"><i class="bi bi-fuel-pump-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Available Gas KG</div>
                    <div class="dashboard-value"><?= number_format((float)$availableGasKg,2) ?> <small>KG</small></div>
                    <div class="dashboard-sub"><?= number_format(count($gasRows)) ?> filled cylinders · <?= number_format(count($gasTypes)) ?> types</div>
                    <div class="dashboard-hint">Cylinder type → individual cylinder</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card empty-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-empty">
                    <div class="dashboard-card-top"><span class="dashboard-icon empty"><i class="bi bi-circle-square"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Empty Gas Cylinders in Shop</div>
                    <div class="dashboard-value"><?= number_format((int)$emptyCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($emptyTypes)) ?> cylinder types available</div>
                    <div class="dashboard-hint">Cylinder type → individual cylinder</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card issued-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-issued">
                    <div class="dashboard-card-top"><span class="dashboard-icon issued"><i class="bi bi-person-badge-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Issued Cylinders</div>
                    <div class="dashboard-value"><?= number_format((int)$issuedCylinderCount) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($issuedCustomers)) ?> customers · <?= $money((float)$issuedDepositTotal) ?> deposit</div>
                    <div class="dashboard-hint">Customer → cylinder details</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card sale-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-sales">
                    <div class="dashboard-card-top"><span class="dashboard-icon sale"><i class="bi bi-cart-check-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Sales</div>
                    <div class="dashboard-value"><?= $money((float)$saleSummary['total']) ?></div>
                    <div class="dashboard-sub"><?= number_format((int)$saleSummary['count']) ?> sales · <?= $range['isToday'] ? 'Today' : esc($range['label']) ?></div>
                    <div class="dashboard-payment-mini"><span>Cash <?= $money((float)$saleSummary['cash']) ?></span><span>Online <?= $money((float)$saleSummary['online']) ?></span><span>Credit <?= $money((float)$saleSummary['credit']) ?></span></div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card cash-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-cash">
                    <div class="dashboard-card-top"><span class="dashboard-icon cash"><i class="bi bi-cash-stack"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Cash Counter</div>
                    <div class="dashboard-value"><?= $money((float)$cashIn - (float)$cashOut) ?></div>
                    <div class="dashboard-sub">In <?= $money((float)$cashIn) ?> · Out <?= $money((float)$cashOut) ?></div>
                    <div class="dashboard-hint"><?= number_format(count($cashRegisters)) ?> register(s) in selected period</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card receivable-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-receivable">
                    <div class="dashboard-card-top"><span class="dashboard-icon receivable"><i class="bi bi-person-lines-fill"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Credit Sales / Accounts Receivable</div>
                    <div class="dashboard-value"><?= $money((float)$receivableTotal) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($receivables)) ?> customers with balance due</div>
                    <div class="dashboard-hint">Current OS as of today</div>
                </button>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <button type="button" class="dashboard-card expense-card w-100 text-start" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-expenses">
                    <div class="dashboard-card-top"><span class="dashboard-icon expense"><i class="bi bi-receipt-cutoff"></i></span><span class="dashboard-chevron"><i class="bi bi-arrow-up-right"></i></span></div>
                    <div class="dashboard-label">Expenses</div>
                    <div class="dashboard-value"><?= $money((float)$expenseTotal) ?></div>
                    <div class="dashboard-sub"><?= number_format(count($expenseRows)) ?> expense entries</div>
                    <div class="dashboard-hint">Category and payment breakdown</div>
                </button>
            </div>
        </div>

        <div class="dashboard-note mt-3"><i class="bi bi-info-circle me-2"></i>Sales, Cash Counter and Expenses follow the selected date range. Accounts Receivable is the current outstanding OS as of today.</div>

        <div class="d-none">
            <div id="detail-gas">
                <div class="detail-intro gas-bg"><i class="bi bi-fuel-pump-fill"></i><div><strong><?= number_format((float)$availableGasKg,2) ?> KG</strong><span>available gas in filled cylinders</span></div></div>
                <?php if (!$gasTypes): ?><div class="alert alert-light border">No filled cylinders are currently available.</div><?php endif; ?>
                <?php foreach($gasTypes as $type): ?>
                    <details class="dashboard-drill-card" data-detail-type="gas">
                        <summary><span class="type-color gas"></span><strong><?= esc($type['code'].' — '.$type['name']) ?></strong><span class="ms-auto"><?= number_format((float)$type['gas_kg'],2) ?> KG · <?= number_format((int)$type['cylinder_count']) ?> cylinders</span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Capacity</th><th>Gas KG</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                        <?php foreach($type['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= number_format((float)$row['capacity_kg'],2) ?> KG</td><td class="fw-semibold"><?= number_format((float)$row['gas_weight_kg'],2) ?> KG</td><?php $gasKg=(float)$row['gas_weight_kg']; $capacityKg=(float)$row['capacity_kg']; $cylinderStatus=$gasKg<=0.00001?'Empty':($gasKg<$capacityKg-0.00001?'Partially Filled':'Filled'); $statusClass=$cylinderStatus==='Empty'?'text-bg-warning':($cylinderStatus==='Partially Filled'?'text-bg-info':'text-bg-success'); ?>
                        <td><span class="badge <?= $statusClass ?>"><?= esc($cylinderStatus) ?></span></td><td><?= esc($row['updated_at']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-empty">
                <div class="detail-intro empty-bg"><i class="bi bi-circle-square"></i><div><strong><?= number_format((int)$emptyCylinderCount) ?> cylinders</strong><span>empty physical cylinders in shop</span></div></div>
                <?php if (!$emptyTypes): ?><div class="alert alert-light border">No empty cylinders are currently in the shop.</div><?php endif; ?>
                <?php foreach($emptyTypes as $type): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color empty"></span><strong><?= esc($type['code'].' — '.$type['name']) ?></strong><span class="ms-auto"><?= number_format((int)$type['count']) ?> cylinders</span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Capacity</th><th>Gas KG</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                        <?php foreach($type['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= number_format((float)$row['capacity_kg'],2) ?> KG</td><td>0.00 KG</td><td><span class="badge text-bg-warning">Empty</span></td><td><?= esc($row['updated_at']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-issued">
                <div class="detail-intro issued-bg"><i class="bi bi-person-badge-fill"></i><div><strong><?= number_format((int)$issuedCylinderCount) ?> cylinders</strong><span>currently issued to customers</span></div></div>
                <?php if (!$issuedCustomers): ?><div class="alert alert-light border">No cylinders are currently issued to customers.</div><?php endif; ?>
                <?php foreach($issuedCustomers as $customer): ?>
                    <details class="dashboard-drill-card"><summary><span class="type-color issued"></span><strong><?= esc($customer['name']) ?></strong><span class="small text-muted ms-2"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= number_format((int)$customer['count']) ?> cylinders · <?= $money((float)$customer['deposit']) ?></span></summary>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Type</th><th>Condition</th><th>Gas KG</th><th>Issued</th><th>Deposit</th></tr></thead><tbody>
                        <?php foreach($customer['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td><td><?= esc(ucfirst($row['issued_condition'])) ?></td><td><?= number_format((float)$row['gas_weight_kg'],2) ?></td><td><?= esc($row['issued_at']) ?></td><td><?= $money((float)$row['deposit_amount']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div id="detail-sales">
                <div class="detail-kpi-grid">
                    <div><span>Total</span><strong><?= $money((float)$saleSummary['total']) ?></strong></div><div><span>Cash</span><strong><?= $money((float)$saleSummary['cash']) ?></strong></div><div><span>Online</span><strong><?= $money((float)$saleSummary['online']) ?></strong></div><div><span>Cheque</span><strong><?= $money((float)$saleSummary['cheque']) ?></strong></div><div><span>Credit</span><strong><?= $money((float)$saleSummary['credit']) ?></strong></div>
                </div>
                <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Sale No.</th><th>Date / Time</th><th>Customer</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Cash</th><th class="text-end">Online</th><th class="text-end">Credit</th></tr></thead><tbody>
                <?php foreach($salesRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['sale_no']) ?></td><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['customer_name'] ?: 'Walk-in / Cash') ?></td><td><?= esc(ucwords(str_replace('_',' ',$row['transaction_type']))) ?></td><td class="text-end fw-semibold"><?= $money((float)$row['total_amount']) ?></td><td class="text-end"><?= $money((float)$row['cash_amount']) ?></td><td class="text-end"><?= $money((float)$row['online_amount']) ?></td><td class="text-end"><?= $money((float)$row['credit_amount']) ?></td></tr><?php endforeach; ?>
                <?php if(!$salesRows): ?><tr><td colspan="8" class="text-center text-muted py-4">No sales found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
            </div>

            <div id="detail-cash">
                <div class="detail-kpi-grid"><div><span>Cash In</span><strong><?= $money((float)$cashIn) ?></strong></div><div><span>Cash Out</span><strong><?= $money((float)$cashOut) ?></strong></div><div><span>Net Counter Movement</span><strong><?= $money((float)$cashIn-(float)$cashOut) ?></strong></div></div>
                <?php if(!$cashRegisters): ?><div class="alert alert-light border">No cash movements found for the selected date range.</div><?php endif; ?>
                <?php foreach($cashRegisters as $register): ?><details class="dashboard-drill-card"><summary><span class="type-color cash"></span><strong><?= esc($register['code'].' — '.$register['name']) ?></strong><span class="ms-auto">In <?= $money((float)$register['in']) ?> · Out <?= $money((float)$register['out']) ?></span></summary>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date / Time</th><th>Movement</th><th>Direction</th><th class="text-end">Amount</th><th>Reference</th></tr></thead><tbody>
                    <?php foreach($register['rows'] as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc(ucwords(str_replace('_',' ',$row['transaction_type']))) ?></td><td><span class="badge <?= $row['direction']==='in'?'text-bg-success':'text-bg-danger' ?>"><?= strtoupper(esc($row['direction'])) ?></span></td><td class="text-end fw-semibold"><?= $money((float)$row['amount']) ?></td><td><?= esc(($row['reference_type']??'').' '.($row['reference_id']??'')) ?></td></tr><?php endforeach; ?></tbody></table></div>
                </details><?php endforeach; ?>
            </div>

            <div id="detail-receivable">
                <div class="detail-intro receivable-bg"><i class="bi bi-person-lines-fill"></i><div><strong><?= $money((float)$receivableTotal) ?></strong><span>current outstanding customer OS as of today</span></div></div>
                <?php if(!$receivables): ?><div class="alert alert-light border">No customer receivables are currently outstanding.</div><?php endif; ?>
                <?php foreach($receivables as $customer): ?><details class="dashboard-drill-card"><summary><span class="type-color receivable"></span><strong><?= esc($customer['name']) ?></strong><span class="small text-muted ms-2"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= $money((float)$customer['balance']) ?> due</span></summary>
                    <div class="p-3 border-top bg-light-subtle"><div class="row g-2 small mb-2"><div class="col-md-4">Opening: <strong><?= $money((float)$customer['opening_balance']) ?></strong></div><div class="col-md-4">Credit sales: <strong><?= $money((float)$customer['credit_sales']) ?></strong></div><div class="col-md-4">Receipts: <strong><?= $money((float)$customer['receipts']) ?></strong></div></div>
                    <?php $ledger=$receivableLedger[$customer['id']]??[]; ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Reference</th><th class="text-end">Credit</th><th class="text-end">Receipt</th></tr></thead><tbody>
                    <?php foreach($ledger as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['sale_no']??$row['receipt_no']??'') ?></td><td class="text-end"><?= $money((float)$row['credit_amount']) ?></td><td class="text-end"><?= $money((float)$row['receipt_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                </details><?php endforeach; ?>
            </div>

            <div id="detail-expenses">
                <div class="detail-kpi-grid"><div><span>Total Expenses</span><strong><?= $money((float)$expenseTotal) ?></strong></div><div><span>Entries</span><strong><?= number_format(count($expenseRows)) ?></strong></div></div>
                <div class="row g-2 mb-3"><?php foreach($expenseCategories as $cat): ?><div class="col-md-4"><div class="expense-category"><span><?= esc($cat['name']) ?></span><strong><?= $money((float)$cat['amount']) ?></strong><small><?= number_format((int)$cat['count']) ?> entries</small></div></div><?php endforeach; ?></div>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Expense No.</th><th>Date</th><th>Category</th><th>Mode</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
                <?php foreach($expenseRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['expense_no']) ?></td><td><?= esc($row['expense_at']) ?></td><td><?= esc($row['category_name']??'Other') ?></td><td><span class="badge text-bg-secondary"><?= esc(ucfirst($row['payment_mode'])) ?></span></td><td><?= esc($row['description']??'') ?></td><td class="text-end fw-semibold"><?= $money((float)$row['amount']) ?></td></tr><?php endforeach; ?>
                <?php if(!$expenseRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No expenses found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
            </div>
        </div>

        <div class="modal fade" id="dashboardDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="dashboardDetailTitle">Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="dashboardDetailBody"></div>
            </div></div>
        </div>
    </div>

    <div class="tab-pane fade <?= $stockTab !== '' ? 'show active' : '' ?>" id="stock-pane" role="tabpanel" aria-labelledby="stock-tab">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-1">Current Stock Details</h5>
                <div class="text-muted small">Live physical cylinder position in this branch.</div>
            </div>
            <a href="<?= site_url('reports/inventory-detail') ?>" class="btn btn-sm btn-outline-secondary">Inventory Detail Report</a>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $stockCards = [
                'filled' => ['title'=>'Filled Cylinders','icon'=>'bi-fuel-pump-fill','value'=>number_format($stock['filled']['count']),'sub'=>$qty((float)$stock['filled']['gas_kg']).' KG gas'],
                'empty' => ['title'=>'Empty Cylinders','icon'=>'bi-circle-square','value'=>number_format($stock['empty']['count']),'sub'=>'Empty physical cylinders'],
                'issued' => ['title'=>'Issued Temporarily Against Deposit','icon'=>'bi-person-badge','value'=>number_format($stock['issued']['count']),'sub'=>$money((float)$stock['issued']['deposit']).' deposit held'],
            ];
            foreach ($stockCards as $key=>$card):
                $active = $stockTab === $key;
                $url = site_url('dashboard?from='.urlencode($range['from']).'&to='.urlencode($range['to']).'&stock_tab='.$key);
            ?>
            <div class="col-md-4">
                <a href="<?= esc($url) ?>" class="text-decoration-none stock-type-card">
                    <div class="card h-100 shadow-sm <?= $active ? 'border-primary border-2' : 'border-0' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small"><?= esc($card['title']) ?></div>
                                <i class="bi <?= esc($card['icon']) ?> fs-5 text-primary"></i>
                            </div>
                            <div class="display-6 fw-semibold mt-2"><?= esc($card['value']) ?></div>
                            <div class="small text-muted mt-1"><?= esc($card['sub']) ?></div>
                            <div class="small text-primary mt-3"><i class="bi bi-arrow-right-circle me-1"></i>View details</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <strong><?= esc($stock[$displayStockTab]['title']) ?></strong>
                <span class="badge text-bg-secondary float-end"><?= number_format((int)$stock[$displayStockTab]['count']) ?> record(s)</span>
            </div>

            <?php if ($displayStockTab === 'filled'): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Current Gas KG</th><th>Fill Status</th><th>Last Updated</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['filled']['rows'] as $i=>$row): ?>
                            <?php $isPartial = (float)$row['gas_weight_kg'] < (float)$row['capacity_kg']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td class="fw-semibold"><?= $qty((float)$row['gas_weight_kg']) ?></td>
                                <td><span class="badge <?= $isPartial ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $isPartial ? 'Partially Filled' : 'Full' ?></span></td>
                                <td><?= esc($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($displayStockTab === 'empty'): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Gas KG</th><th>Last Updated</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['empty']['rows'] as $i=>$row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td>0.00</td>
                                <td><?= esc($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 datatable">
                        <thead>
                            <tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th>Capacity KG</th><th>Customer</th><th>Phone</th><th>Deposit</th><th>Issued At</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock['issued']['rows'] as $i=>$row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($row['unit_code']) ?></td>
                                <td><?= esc($row['type_code'].' — '.$row['type_name']) ?></td>
                                <td><?= $qty((float)$row['capacity_kg']) ?></td>
                                <td><strong><?= esc($row['customer_name']) ?></strong><div class="small text-muted"><?= esc($row['customer_code'] ?? '') ?></div></td>
                                <td><?= esc($row['customer_phone'] ?? '') ?></td>
                                <td class="fw-semibold"><?= $money((float)$row['deposit_amount']) ?></td>
                                <td><?= esc($row['issued_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!$stock[$displayStockTab]['rows']): ?>
                <div class="alert alert-light border rounded-0 mb-0">No <?= esc(strtolower($stock[$displayStockTab]['title'])) ?> found in the current physical stock.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
