<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<style>
.pos-workspace {
  display:grid !important;
  grid-template-columns:minmax(0,1fr) 310px;
  align-items:start;
  gap:1rem;
  margin-left:0 !important;
  margin-right:0 !important;
}
.pos-workspace > .pos-lines-panel,
.pos-workspace > .pos-summary-panel {
  width:auto !important;
  max-width:none !important;
  min-width:0;
  padding-left:0 !important;
  padding-right:0 !important;
  flex:none !important;
}
.pos-workspace.mode-security_deposit,
.pos-workspace.mode-cylinder_return {
  grid-template-columns:minmax(0,1fr) 330px;
}
.pos-workspace.mode-security_deposit > .pos-lines-panel,
.pos-workspace.mode-cylinder_return > .pos-lines-panel { min-width:0; }
#lines { width:100%; table-layout:fixed; }
#lines th,#lines td { padding:.65rem .5rem; vertical-align:middle; }
/* Each standard transaction type owns its own table geometry. Hidden source cells do not reserve width. */
#lines.gas-sale-mode:not(.source-enabled) th { padding:.32rem .28rem; font-size:.72rem !important; font-weight:700; line-height:1.05; white-space:normal; overflow-wrap:anywhere; }
#lines.gas-sale-mode:not(.source-enabled) { min-width:720px; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(1){width:22%}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(2){width:14%}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(3){width:20%}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(4){width:20%}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(5){width:16%; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(6){width:8%; text-align:right; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(7){width:8%; text-align:right; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(5){width:16%; text-align:right; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(6){width:8%; text-align:right; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(7){width:8%; text-align:right; white-space:nowrap}
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(7) .remove { min-width:0; min-height:28px; width:auto; font-size:.62rem; line-height:1; padding:.18rem .32rem; white-space:nowrap; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(7) { padding-right:.15rem; }
#lines.gas-sale-mode:not(.source-enabled) th .gas-sale-header-wrap { display:inline-block; line-height:1.05; }
#lines.gas-sale-mode.source-enabled th { font-size:.72rem !important; font-weight:700; line-height:1.05; }
#lines.gas-sale-mode.source-enabled th:nth-child(1){width:18%}
#lines.gas-sale-mode.source-enabled th:nth-child(2){width:21%}
#lines.gas-sale-mode.source-enabled th:nth-child(3){width:11%}
#lines.gas-sale-mode.source-enabled th:nth-child(4){width:13%}
#lines.gas-sale-mode.source-enabled th:nth-child(5){width:18%}
#lines.gas-sale-mode.source-enabled th:nth-child(6){width:13%}
#lines.gas-sale-mode.source-enabled th:nth-child(7){width:6%; text-align:right}
#lines.gas-sale-mode.source-enabled td:nth-child(7){text-align:right}
#lines.gas-sale-mode.source-enabled td:nth-child(7) .remove { min-width:0; min-height:28px; width:auto; font-size:.62rem; line-height:1; padding:.18rem .32rem; white-space:nowrap; }
#lines.cylinder-sale-mode th { font-size:.72rem !important; line-height:1.05; padding:.28rem .3rem; white-space:nowrap; }
#lines.cylinder-sale-mode th:nth-child(1){width:21%}
#lines.cylinder-sale-mode th:nth-child(2){width:16%}
#lines.cylinder-sale-mode th:nth-child(3){width:8%}
#lines.cylinder-sale-mode th:nth-child(4){width:8%}
#lines.cylinder-sale-mode th:nth-child(5){width:15%}
#lines.cylinder-sale-mode th:nth-child(6){width:15%}
#lines.cylinder-sale-mode th:nth-child(7){width:10%; text-align:right}
#lines.cylinder-sale-mode th:nth-child(8){width:7%; text-align:right}
#lines.cylinder-sale-mode td:nth-child(5),
#lines.cylinder-sale-mode td:nth-child(6) { min-width:0; }
#lines.cylinder-sale-mode .input-group { min-width:0; width:100%; }
#lines.cylinder-sale-mode .input-group-text { flex:0 0 auto; padding:.25rem .35rem; font-size:.72rem; }
#lines.cylinder-sale-mode .input-group .form-control { min-width:0; width:1%; }
#lines.cylinder-sale-mode td:nth-child(7) { text-align:right; white-space:nowrap; padding-left:.2rem; padding-right:.2rem; }
#lines.cylinder-sale-mode td:nth-child(8) { text-align:right; white-space:nowrap; padding-left:.15rem; padding-right:.15rem; }
#lines.cylinder-sale-mode td:nth-child(8) .remove { width:28px; height:28px; min-width:28px; min-height:28px; padding:0; font-size:.8rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; }
#lines.gas-sale-mode .sourceCell { display:table-cell; }
/* Customer Cylinder is intentionally hidden on the POS face; keep the existing field/serialization intact. */
#lines.gas-sale-mode th:nth-child(5),
#lines.gas-sale-mode td:nth-child(5) { display:none !important; }
/* Rebalance every visible Gas Sale header and body column after hiding Customer Cylinder. */
#lines.gas-sale-mode { width:100%; table-layout:fixed; }
/* Cylinder Type is 1.5x wider; remaining visible columns are proportionally rebalanced. */
/* Visible Gas Sale columns only: keep Amount compact so it doesn't create a large blank gap. */
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(1),
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(1) { width:38%; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(3),
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(3) { width:14%; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(4),
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(4) { width:20%; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(6),
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(6) { width:14%; text-align:right; }
#lines.gas-sale-mode:not(.source-enabled) th:nth-child(7),
#lines.gas-sale-mode:not(.source-enabled) td:nth-child(7) { width:14%; text-align:right; }
#lines.gas-sale-mode.source-enabled th:nth-child(1),
#lines.gas-sale-mode.source-enabled td:nth-child(1) { width:27%; }
#lines.gas-sale-mode.source-enabled th:nth-child(2),
#lines.gas-sale-mode.source-enabled td:nth-child(2) { width:19.6%; }
#lines.gas-sale-mode.source-enabled th:nth-child(3),
#lines.gas-sale-mode.source-enabled td:nth-child(3) { width:10.7%; }
#lines.gas-sale-mode.source-enabled th:nth-child(4),
#lines.gas-sale-mode.source-enabled td:nth-child(4) { width:13.4%; }
#lines.gas-sale-mode.source-enabled th:nth-child(6),
#lines.gas-sale-mode.source-enabled td:nth-child(6) { width:20.4%; text-align:right; }
#lines.gas-sale-mode.source-enabled th:nth-child(7),
#lines.gas-sale-mode.source-enabled td:nth-child(7) { width:8.9%; text-align:right; }
#lines.gas-sale-mode .lineTotal { min-width:0; }
#lines.gas-sale-mode:not(.source-enabled) .sourceCell { display:none; }
#lines.gas-sale-mode:not(.source-enabled) .sourceHead { display:none !important; }
#lines.cylinder-sale-mode .cylStatus { min-width:0; }
#lines.cylinder-sale-mode select { min-width:0; width:100%; }
#lines .form-select,#lines .form-control { min-height:42px; width:100%; min-width:0; }
#lines.gas-sale-mode .lineTotal { min-width:96px; text-align:right; }
#lines.gas-sale-mode .sourceCell select,
#lines.gas-sale-mode .targetCyl,
#lines.gas-sale-mode .cyl { min-width:0; width:100%; }
#lines .lineTotal { font-size:1.05rem; white-space:nowrap; }
.pos-lines-panel > .card > .card-body { padding: .6rem; }
.pos-summary-card .card-body { padding: .75rem; }
.pos-header-row { margin-bottom:.5rem !important; row-gap:.45rem !important; }
.pos-header-row .form-label { margin-bottom:.25rem; }
.pos-header-row .credit-status-row { margin-top:-.15rem; }
/* Security Deposit header: use the full row when Gas Entry is hidden. */
.pos-workspace.mode-security_deposit .pos-header-row > .col-md-3:not(#gasEntryModeWrap),
.pos-workspace.mode-cylinder_return .pos-header-row > .col-md-3:not(#gasEntryModeWrap) { flex:0 0 33.333333%; max-width:33.333333%; }
/* Combined payment rows: when Payment Type is hidden, let Mode and Amount fill the row. */
#paymentSection .payment:has(.paymentType.is-hidden) { grid-template-columns:minmax(0,1fr) minmax(0,1fr) 40px; }
#paymentSection .payment:has(.paymentType.is-hidden) .mode,
#paymentSection .payment:has(.paymentType.is-hidden) .payment-amount { width:100%; min-width:0; }
#posSuccessAlert { display:none; }
#posReceiptActions { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.55rem; }
#posReceiptActions .btn { min-width:130px; }
#receiptPreviewOverlay { position:fixed; inset:0; z-index:2000; display:none; align-items:center; justify-content:center; background:rgba(15,23,42,.55); padding:1rem; }
#receiptPreviewOverlay.is-visible { display:flex; }
#receiptPreviewDialog { width:min(520px,96vw); height:min(760px,92vh); background:#fff; border-radius:var(--lpg-radius-lg); box-shadow:0 18px 50px rgba(0,0,0,.25); display:flex; flex-direction:column; overflow:hidden; }
#receiptPreviewDialog .receipt-preview-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.6rem .75rem; border-bottom:1px solid var(--lpg-border); background:var(--lpg-surface); color:var(--lpg-text); font-weight:700; }
#receiptPreviewFrame { flex:1; width:100%; border:0; background:#fff; }

.pos-workspace,
.pos-workspace .form-control,
.pos-workspace .form-select,
.pos-workspace .btn,
.pos-workspace th,
.pos-workspace td,
.pos-workspace .input-group-text {
  font-size:1rem !important;
}
.pos-workspace .form-label,
.pos-workspace .form-text,
.pos-workspace .small {
  font-size:.86rem !important;
}
.pos-workspace h6 { font-size:1.05rem !important; }
.pos-workspace .badge { font-size:.75rem !important; }
#standardTransaction { margin-top:0 !important; padding-top:0 !important; }
#standardTransaction .table-responsive { overflow-x:auto; }
#securityTransaction, #returnTransaction { width:100%; }
#securityTransaction .table-responsive, #returnTransaction .table-responsive { overflow-x:auto; }
#securityTransaction table, #returnTransaction table { width:100%; table-layout:fixed; }
#securityTransaction th, #securityTransaction td,
#returnTransaction th, #returnTransaction td { white-space:normal; overflow-wrap:anywhere; }
#securityTransaction th:nth-child(1){width:18%} #securityTransaction th:nth-child(2){width:28%}
#securityTransaction th:nth-child(3){width:10%} #securityTransaction th:nth-child(4){width:11%}
#securityTransaction th:nth-child(5){width:12%} #securityTransaction th:nth-child(6){width:12%} #securityTransaction th:nth-child(7){width:5%}
#returnTransaction th:nth-child(1){width:19%} #returnTransaction th:nth-child(2){width:11%}
#returnTransaction th:nth-child(3){width:14%} #returnTransaction th:nth-child(4){width:14%}
#returnTransaction th:nth-child(5){width:13%} #returnTransaction th:nth-child(6){width:13%} #returnTransaction th:nth-child(7){width:6%}
#securityTransaction .card-body, #returnTransaction .card-body { padding:.8rem; }
#securityTransaction .table td, #securityTransaction .table th,
#returnTransaction .table td, #returnTransaction .table th { padding:.45rem .4rem; }
.pos-workspace.mode-security_deposit #standardTransaction,
.pos-workspace.mode-cylinder_return #standardTransaction { display:none !important; }
.pos-workspace.mode-security_deposit #securityTransaction,
.pos-workspace.mode-cylinder_return #returnTransaction { min-width:0; }
.pos-workspace.mode-gas_sale #securityTransaction,
.pos-workspace.mode-gas_sale #returnTransaction,
.pos-workspace.mode-cylinder_sale #securityTransaction,
.pos-workspace.mode-cylinder_sale #returnTransaction { display:none !important; }
#standardTransaction .table-responsive { margin:0 !important; padding:0 !important; }
#lines { margin-top:0 !important; margin-bottom:.25rem !important; }
#lines thead { margin:0 !important; }
#lines thead th { padding-top:.2rem !important; padding-bottom:.2rem !important; }
#lines tbody tr:first-child td { padding-top:.2rem !important; }
.pos-header-row + #standardTransaction { margin-top:-.2rem !important; }
#lines thead th { padding:.35rem .4rem; line-height:1.15; font-size:.82rem; }
#lines tbody td { padding:.4rem .35rem; }
#paymentReferenceNote { width:100%; min-height:72px; resize:vertical; }
#paymentSection .payment { display:grid; grid-template-columns:auto minmax(0,1fr) minmax(90px,130px) 28px; gap:.3rem; align-items:center; }
#paymentSection .payment .paymentType.is-hidden { display:none !important; }
#paymentSection .payment .mode { min-width:0; }
#paymentSection .payment .payment-amount { min-width:0; }
#paymentSection .payment .remove { width:28px; height:28px; padding:0; font-size:.75rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; }
#paymentSection #addPayment { margin-bottom:.35rem !important; }
#lines .form-select,#lines .form-control { min-height:38px; }
#lines .input-group-sm > .form-control,#lines .input-group-sm > .input-group-text { min-height:34px; }
#lines.cylinder-sale-mode .input-group-sm > .form-control { min-width:0; }
.cylinderPickerRow { display:none; }
.cylinderPickerRow.is-visible { display:table-row; }
.cylinderPickerRow td { padding:.15rem .35rem !important; border-top:0 !important; background:var(--lpg-surface-2); }
.cylinderPicker { padding:.35rem !important; max-height:300px; overflow:auto; }
.cylinderPicker .cylinder-options{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:.5rem!important;align-items:stretch}
.cylinderPicker .cylinder-option{position:relative;box-sizing:border-box;width:100%;min-width:0!important;max-width:none;min-height:78px;padding:.55rem .6rem!important;border:1px solid var(--lpg-border)!important;border-left:4px solid var(--lpg-neutral)!important;border-radius:var(--lpg-radius)!important;display:flex;align-items:center;gap:.55rem;white-space:normal;overflow:hidden;background:var(--lpg-surface);color:var(--lpg-text);box-shadow:var(--lpg-shadow-sm);transition:border-color .15s,box-shadow .15s,transform .15s}
.cylinderPicker .cylinder-option:hover{transform:translateY(-1px);box-shadow:var(--lpg-shadow)}
.cylinderPicker .cylinder-option.status-filled{border-left-color:var(--lpg-success)!important}
.cylinderPicker .cylinder-option.status-filled .cylinder-art{color:var(--lpg-success)}
.cylinderPicker .cylinder-option.status-partial{border-left-color:var(--lpg-warning)!important}
.cylinderPicker .cylinder-option.status-partial .cylinder-art{color:var(--lpg-warning)}
.cylinderPicker .cylinder-option .cylinder-art{flex:0 0 28px;width:28px;height:36px}
.cylinderPicker .cylinder-option .cylinder-copy{display:block;flex:1;min-width:0;line-height:1.2;overflow:hidden}
.cylinderPicker .cylinder-option .cylinder-code{display:block;font-size:.75rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cylinderPicker .cylinder-option .cylinder-name{display:block;font-size:.64rem;font-weight:600;color:var(--lpg-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.12rem}
.cylinderPicker .cylinder-option .cylinder-gas{display:block;font-size:.68rem;font-weight:700;margin-top:.25rem;white-space:normal;overflow-wrap:anywhere}
.cylinderPicker .cylinder-option .cylinder-status{display:inline-flex;align-items:center;width:max-content;max-width:100%;margin:.3rem 0 0;padding:.12rem .42rem;border-radius:999px;font-size:.58rem;line-height:1.25;font-weight:800;letter-spacing:.025em;white-space:normal}
.cylinderPicker .cylinder-option.status-filled .cylinder-status{background:var(--lpg-success-soft);color:var(--lpg-success)}
.cylinderPicker .cylinder-option.status-partial .cylinder-status{background:var(--lpg-warning-soft);color:var(--lpg-warning)}
@media(max-width:575.98px){.cylinderPicker .cylinder-options{grid-template-columns:repeat(2,minmax(0,1fr))}.cylinderPicker .cylinder-option{min-height:74px;padding:.45rem!important;gap:.4rem}.cylinderPicker .cylinder-option .cylinder-art{flex-basis:23px;width:23px;height:31px}.cylinderPicker .cylinder-option .cylinder-code{font-size:.68rem}.cylinderPicker .cylinder-option .cylinder-gas{font-size:.63rem}}
.cylinder-option .filledUnitCheck { position:absolute; opacity:0; pointer-events:none; }
.cylinder-option { cursor:pointer; }
.cylinder-option:has(input:checked) { border-width:2px !important; border-color:var(--lpg-accent) !important; background:rgba(var(--lpg-accent-rgb),.08); box-shadow:0 0 0 2px rgba(var(--lpg-accent-rgb),.2); }
.cylinder-option:has(input:focus-visible) { outline:2px solid var(--lpg-accent); outline-offset:2px; }

.pos-summary-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin-bottom:.75rem; }
#securityDepositSummary { grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
#securityDepositSummary .pos-summary-metric { min-width:0; }
.pos-summary-metric .form-label { font-size:.8rem; margin-bottom:.25rem; white-space:nowrap; }
.pos-summary-metric .form-control { padding:.35rem .5rem; min-height:36px; }
#lines .remove { min-width:38px; min-height:38px; }
.custody-list { max-height:250px; overflow:auto; }
.custody-list option { padding:4px; }
@media (max-width:1199.98px){
  .pos-workspace {
    grid-template-columns:minmax(0,1fr);
  }
  .pos-workspace.mode-security_deposit,
  .pos-workspace.mode-cylinder_return {
    grid-template-columns:minmax(0,1fr);
  }
  .pos-workspace > .pos-lines-panel,
  .pos-workspace > .pos-summary-panel {
    width:100% !important;
  }
}
input[type="number"]::-webkit-outer-spin-button,input[type="number"]::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
input[type="number"]{ -moz-appearance:textfield; appearance:textfield; }

/* POS visual layer (Phase 11.4) — design tokens only, so light and dark themes both work. */
.pos-workspace{--pos-accent:var(--lpg-accent)}
.pos-statusbar{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1rem;margin-bottom:.85rem}
.pos-statusbar .status a{color:inherit;font-weight:700}
.pos-keys{display:flex;flex-wrap:wrap;gap:.35rem .75rem;margin-left:auto;color:var(--lpg-muted);font-size:.8rem}
.pos-keys kbd,.pos-summary-card kbd{background:var(--lpg-surface);color:var(--lpg-text-2);border:1px solid var(--lpg-border-strong);border-bottom-width:2px;border-radius:.3rem;padding:.05rem .35rem;font-size:.72rem;font-family:inherit;font-weight:650}
.pos-workspace>.pos-lines-panel>.card,.pos-summary-card{box-shadow:var(--lpg-shadow)}
.pos-header-row{background:var(--lpg-surface-2);border:1px solid var(--lpg-border);border-radius:var(--lpg-radius);padding:.7rem .7rem .35rem}
.pos-header-row .form-label{color:var(--lpg-text-2)}.pos-header-row .form-select,.pos-header-row .form-control{background-color:var(--lpg-surface)}
.pos-customer-search{position:relative;margin-bottom:.3rem}
.pos-customer-search .bi{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);color:var(--lpg-muted);pointer-events:none;font-size:.85rem}
.pos-customer-search input{padding-left:1.9rem}
#customerSearchHint{font-size:.75rem;color:var(--lpg-muted);margin-top:.2rem}
#customerCreditStatus.text-success{color:var(--lpg-success)!important}#customerCreditStatus.text-danger{color:var(--lpg-danger)!important}
#standardTransaction{padding-top:.35rem!important}
#lines thead th{background:var(--lpg-surface-2);color:var(--lpg-muted);font-size:.76rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase;border-bottom:1px solid var(--lpg-border)}
#lines tbody tr{border-bottom:1px solid var(--lpg-border)}#lines tbody tr:hover{background:var(--lpg-surface-2)}
#lines .form-control,#lines .form-select{background-color:var(--lpg-surface);border-color:var(--lpg-border-strong)}
#lines .lineTotal{font-weight:700;color:var(--lpg-text)}
.pos-summary-card{position:sticky;top:calc(var(--lpg-topbar-h) + 1rem)}.pos-summary-card .card-body{padding:1rem}
.pos-summary-metrics{gap:.6rem}.pos-summary-metric .form-control{border-color:var(--lpg-border);background:var(--lpg-surface-2)!important;font-weight:650}
.pos-summary-card .form-control.bg-light{background:var(--lpg-surface-2)!important;border-color:var(--lpg-border)}
/* The amount the counter collects is the headline of the panel. */
#netReceivableBox{background:rgba(var(--lpg-accent-rgb),.08);border:1px solid rgba(var(--lpg-accent-rgb),.35);border-radius:var(--lpg-radius);padding:.6rem .75rem .7rem}
#netReceivableBox .form-label{font-weight:650;color:var(--lpg-text-2);margin-bottom:.1rem}
#netReceivableBox .form-control{background:transparent!important;border:0;padding:0;font-size:1.75rem!important;font-weight:750;line-height:1.2;min-height:0;box-shadow:none}
#netReceivableBox .form-control::before{content:"Rs. ";font-size:1rem;font-weight:600;color:var(--lpg-muted)}
#combinedPaymentSection{background:var(--lpg-surface-2);border-color:var(--lpg-border)!important}
#combinedPaymentSection .fw-semibold,#combinedPaymentSection #combinedOsAfterPayment{font-variant-numeric:tabular-nums}
#paymentSection{border:1px solid var(--lpg-border);border-radius:var(--lpg-radius-sm);padding:.5rem!important;background:var(--lpg-surface)}
#paymentSection .paymentCollectionTitle,#paymentPurposeLabel{font-size:.72rem!important;text-transform:uppercase;letter-spacing:.04em;color:var(--lpg-muted)!important;font-weight:650;margin-bottom:.35rem}
#payments{width:100%}
#payments .payment{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr) 40px;gap:.4rem;margin-bottom:.4rem!important;width:100%;align-items:center}
#payments .payment .paymentType,#payments .payment .mode,#payments .payment .payment-amount{min-width:0;width:100%;box-sizing:border-box}
#payments .payment .form-control,#payments .payment .form-select{min-height:36px;padding:.3rem .45rem;width:100%;box-sizing:border-box}
#payments .payment .remove{width:40px;min-width:40px;height:36px;padding:0;font-size:.8rem;line-height:1;display:flex;align-items:center;justify-content:center}
#payments .payment .paymentType.is-hidden{display:none}
#paymentSection #addPayment{display:block;width:100%;margin:.45rem 0 .5rem!important}
#paymentSection .payment-reference{width:100%;min-height:72px;resize:vertical}
#amountToCollectHelpMessage{margin-bottom:.35rem!important}
#saleTotal,#netPayable,#receiptAmountValue,#customerOsBalanceValue{font-variant-numeric:tabular-nums}
#saleTotal{color:var(--lpg-success)}#netPayable{color:var(--lpg-text)}#customerOsBalanceValue{color:var(--lpg-info)!important}
#posValidationAlert{display:flex;gap:.6rem;align-items:flex-start;font-weight:600}
#posValidationAlert::before{content:"\f33a";font-family:"bootstrap-icons";font-weight:400;font-size:1.1rem;line-height:1.3}
#posValidationAlert.d-none{display:none!important}
#securityTransaction .card,#returnTransaction .card{border:1px solid var(--lpg-border)!important;box-shadow:none}#securityTransaction .card{border-top:3px solid var(--lpg-warning)!important}#returnTransaction .card{border-top:3px solid var(--lpg-info)!important}
#securityTransaction .table thead th,#returnTransaction .table thead th{background:var(--lpg-surface-2);color:var(--lpg-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.02em}
#issueEmpty,#returnEmpty{background:var(--lpg-surface-2);border:1px dashed var(--lpg-border-strong);border-radius:var(--lpg-radius-sm);padding:.75rem!important;text-align:center}
/* Issue-cylinder picker: status-driven cards. Styling only; selection/data logic is unchanged. */
#issuePickerCards{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.65rem!important;align-items:stretch}
#issuePickerCards .cylinder-option{position:relative;width:100%;min-width:0!important;max-width:none;min-height:92px;padding:.65rem .7rem!important;border:1px solid var(--lpg-border)!important;border-left:5px solid var(--lpg-neutral)!important;border-radius:var(--lpg-radius)!important;box-shadow:var(--lpg-shadow-sm);background:var(--lpg-surface);color:var(--lpg-text);display:flex;align-items:center;gap:.65rem;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
#issuePickerCards .cylinder-option:hover{transform:translateY(-2px);box-shadow:var(--lpg-shadow)}
#issuePickerCards .cylinder-option:focus-within{outline:2px solid var(--lpg-accent);outline-offset:1px}
#issuePickerCards .cylinder-option .cylinder-art{flex:0 0 34px;width:34px;height:48px;border-radius:12px 12px 9px 9px;background:linear-gradient(90deg,var(--lpg-border-strong),var(--lpg-surface-2),var(--lpg-border-strong));border:1px solid var(--lpg-neutral);position:relative}
#issuePickerCards .cylinder-option .cylinder-art:before{content:"";position:absolute;top:-5px;left:9px;width:14px;height:7px;border-radius:3px 3px 1px 1px;background:var(--lpg-neutral)}
#issuePickerCards .cylinder-option .cylinder-copy{flex:1;min-width:0;line-height:1.15}
#issuePickerCards .cylinder-option .cylinder-code{display:block;font-size:.82rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--lpg-text)}
#issuePickerCards .cylinder-option .cylinder-name{display:block;font-size:.68rem;font-weight:600;color:var(--lpg-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.12rem}
#issuePickerCards .cylinder-option .cylinder-gas{display:block;font-size:.68rem;margin-top:.3rem;white-space:nowrap;color:var(--lpg-text-2)}
#issuePickerCards .cylinder-option .cylinder-status{display:inline-flex;align-items:center;margin-left:.25rem;padding:.12rem .38rem;border-radius:999px;font-size:.6rem;font-weight:800;letter-spacing:.01em}
#issuePickerCards .cylinder-option.issue-status-filled{border-left-color:var(--lpg-success)!important}
#issuePickerCards .cylinder-option.issue-status-filled .cylinder-art{border-color:var(--lpg-success)}
#issuePickerCards .cylinder-option.issue-status-filled .cylinder-status{background:var(--lpg-success-soft);color:var(--lpg-success)}
#issuePickerCards .cylinder-option.issue-status-partial{border-left-color:var(--lpg-warning)!important}
#issuePickerCards .cylinder-option.issue-status-partial .cylinder-art{border-color:var(--lpg-warning)}
#issuePickerCards .cylinder-option.issue-status-partial .cylinder-status{background:var(--lpg-warning-soft);color:var(--lpg-warning)}
#issuePickerCards .cylinder-option.issue-status-empty{border-left-color:var(--lpg-neutral)!important;background:var(--lpg-surface-2)}
#issuePickerCards .cylinder-option.issue-status-empty .cylinder-art{opacity:.72}
#issuePickerCards .cylinder-option.issue-status-empty .cylinder-status{background:var(--lpg-neutral-soft);color:var(--lpg-neutral)}
#issuePickerCards .cylinder-option:has(input:checked){border-color:var(--pos-accent)!important;background:rgba(var(--lpg-accent-rgb),.08);box-shadow:0 0 0 2px rgba(var(--lpg-accent-rgb),.25),var(--lpg-shadow)}
#issuePickerCards .cylinder-option:has(input:checked) .cylinder-art{border-color:var(--pos-accent)}
#issuePickerCards .cylinder-option:has(input:checked):after{content:"\2713";position:absolute;top:.45rem;right:.45rem;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--pos-accent);color:var(--lpg-accent-contrast);font-size:.75rem;font-weight:800}
@media(max-width:575.98px){#issuePickerCards{grid-template-columns:1fr 1fr}#issuePickerCards .cylinder-option{min-height:88px;padding:.55rem!important}#issuePickerCards .cylinder-option .cylinder-art{flex-basis:29px;width:29px;height:43px}}
#addLine,#openIssuePicker,#openReturnPicker{font-weight:600}
#saveBtn{min-height:50px;font-size:1.05rem;font-weight:700;box-shadow:var(--lpg-shadow)}
/* Touch screens: larger targets. */
@media (pointer:coarse){.pos-workspace .form-control,.pos-workspace .form-select,.pos-workspace .btn{min-height:44px}#lines .remove,#payments .payment .remove{min-width:44px;min-height:44px}.pos-keys{display:none}}
@media(max-width:1199.98px){.pos-summary-card{position:static}}
@media(max-width:767.98px){.pos-header-row{padding:.65rem .55rem .25rem}.pos-summary-metrics{grid-template-columns:1fr}.pos-workspace .btn{min-height:40px}#saveBtn{min-height:50px}.pos-keys{display:none}}
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<?php
$posVisibleTypes = (new \App\Models\ShopSettingsModel())->visibleTransactionTypes($shopSettings);
$initialTransactionType = in_array((string)$defaultTransactionType, $posVisibleTypes, true)
    ? (string)$defaultTransactionType
    : (string)($posVisibleTypes[0] ?? 'gas_sale');
?>

<div class="pos-statusbar">
  <?php if($cashSession): ?>
    <span class="status status-success">Cash session open — <?=esc($cashSession["register_code"])?></span>
  <?php else: ?>
    <span class="status status-warning">Cash session not open — cash sales need an open session<?php if (\App\Services\PermissionService::allows('CASH_MANAGE')): ?> · <a href="<?= site_url('cash') ?>">Open Counter Cash</a><?php endif; ?></span>
  <?php endif; ?>
  <div class="pos-keys" aria-label="Keyboard shortcuts">
    <span><kbd>F2</kbd> Customer</span><span><kbd>F4</kbd> Add line</span><span><kbd>F7</kbd> Payment</span><span><kbd>F9</kbd> Post</span>
  </div>
</div>
<div id="posValidationAlert" class="alert alert-danger d-none mb-3" role="alert" tabindex="-1"></div>
<div id="posSuccessAlert" class="alert alert-success mb-3" role="status">
  <div id="posSuccessMessage" class="fw-semibold"></div>
  <div id="posReceiptActions">
    <button type="button" class="btn btn-outline-success btn-sm" id="printPreviewBtn">Print Preview</button>
    <button type="button" class="btn btn-success btn-sm" id="printReceiptBtn">Print Receipt</button>
  </div>
</div>

<div id="receiptPreviewOverlay" aria-hidden="true">
  <div id="receiptPreviewDialog" role="dialog" aria-modal="true" aria-label="Receipt Print Preview">
    <div class="receipt-preview-head">
      <span>Receipt Print Preview</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="closeReceiptPreview">Close</button>
    </div>
    <iframe id="receiptPreviewFrame" title="Receipt Print Preview"></iframe>
  </div>
</div>

<form method="post" action="<?=site_url('sales/save')?>" id="saleForm"><?=csrf_field()?>
<input type="hidden" name="lines_json" id="lines_json">
<input type="hidden" name="payments_json" id="payments_json">
<input type="hidden" name="stock_override_confirmed" id="stock_override_confirmed" value="0">

<div class="row g-3 pos-workspace mode-<?= esc($initialTransactionType) ?>" id="posWorkspace">
<div class="col-lg-9 pos-lines-panel"><div class="card"><div class="card-body">

<div class="row g-2 pos-header-row mb-2">
  <div class="col-md-3">
    <label class="form-label fw-semibold">Transaction Type</label>
    <select name="transaction_type" id="transactionType" class="form-select">
      <?php foreach(\App\Models\ShopSettingsModel::TRANSACTION_TYPE_LABELS as $value=>$label): ?>
        <?php if (in_array($value, (new \App\Models\ShopSettingsModel())->visibleTransactionTypes($shopSettings), true)): ?>
          <option value="<?= esc($value) ?>" <?= $value === $initialTransactionType ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3" id="gasEntryModeWrap" style="display:<?= $initialTransactionType === 'gas_sale' ? 'block' : 'none' ?>">
    <label class="form-label fw-semibold">Gas Entry</label>
    <select id="gasEntryMode" class="form-select"><option value="quantity" <?= ($defaultGasEntryMode ?? 'quantity') === 'quantity' ? 'selected' : '' ?>>KG</option><option value="amount" <?= ($defaultGasEntryMode ?? 'quantity') === 'amount' ? 'selected' : '' ?>>Amount</option><option value="cylinders" <?= ($defaultGasEntryMode ?? 'quantity') === 'cylinders' ? 'selected' : '' ?>>Cylinders</option></select>
  </div>
  <div class="col-md-3">
    <label class="form-label" for="customer_id">Customer</label>
    <div class="pos-customer-search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="customerSearch" class="form-control form-control-sm" placeholder="Find by code, name or phone" autocomplete="off" aria-label="Find customer by code, name or phone" aria-describedby="customerSearchHint">
    </div>
    <select name="customer_id" id="customer_id" class="form-select">
      <option value="" data-walkin-option="1">Walk-in / Cash</option>
      <?php foreach($customers as $c): ?><option value="<?=$c['id']?>" data-search="<?= esc(strtolower(trim(($c['code'] ?? '') . ' ' . $c['name'] . ' ' . ($c['phone'] ?? '')))) ?>"><?=esc(($c['code']?$c['code'].' — ':'').$c['name'])?></option><?php endforeach; ?>
    </select>
    <div id="customerSearchHint" class="visually-hidden" aria-live="polite"></div>
  </div>
  <div class="col-md-3">
    <?php $posDateRules=(new \App\Services\TransactionDatePolicy())->rules((int)session()->get('location_id')); ?>
    <label class="form-label">Transaction Time</label>
    <input name="transaction_at" type="datetime-local" class="form-control" value="<?=date('Y-m-d\TH:i')?>" min="<?=esc($posDateRules['earliest'])?>T00:00" <?= $posDateRules['allows_backdate'] ? '' : 'readonly' ?> title="<?=esc($posDateRules['description'])?>">
    <?php if($posDateRules['allows_backdate']): ?><div class="form-text small"><?=esc($posDateRules['description'])?></div><?php endif; ?>
  </div>
  <div class="col-12">
    <div id="customerCreditStatus" class="small mt-1 text-muted">Walk-in / Cash: credit sale not allowed.</div>
  </div>
</div>

<div id="standardTransaction" style="display:<?= in_array($initialTransactionType,['gas_sale','cylinder_sale'],true) ? 'block' : 'none' ?>">
  <div class="table-responsive">
    <table class="table table-sm align-middle" id="lines">
      <thead id="lineHead"></thead>
      <tbody></tbody>
    </table>
  </div>
  <button type="button" class="btn btn-outline-primary" id="addLine">Add Line</button>
</div>

<div id="securityTransaction" style="display:<?= $initialTransactionType === 'security_deposit' ? 'block' : 'none' ?>">
  <div class="card border-warning">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Security Deposit / Issue Cylinder</h6><button type="button" class="btn btn-sm btn-primary" id="openIssuePicker">Add Line / Select Cylinders</button></div>
      <div class="small text-muted mb-2">Select physical cylinders by type and Filled / Empty. Filled includes partially-filled cylinders. The same Type + condition cannot be added twice.</div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Type / Status</th><th>Physical Cylinder(s)</th><th class="text-end">Gas KG</th><th class="text-end">Gas Rate</th><th class="text-end">Cylinder Rate</th><th class="text-end">Amount</th><th></th></tr></thead><tbody id="issueLinesBody"></tbody></table></div>
      <div id="issueEmpty" class="text-muted small py-3">No cylinders selected. Click Add Line / Select Cylinders.</div>
    </div>
  </div>
</div>

<div id="returnTransaction" style="display:<?= $initialTransactionType === 'cylinder_return' ? 'block' : 'none' ?>">
  <div class="card border-info">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Cylinder Return / Refund Deposit</h6><button type="button" class="btn btn-sm btn-primary" id="openReturnPicker">Select Cylinders</button></div>
      <div class="small text-muted mb-2">Only the selected customer's pending cylinders are available. Return gas is entered separately per cylinder.</div>
      <?php if((int)($shopSettings['allow_return_gas_qty']??0)===1): ?>
      <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-4 col-lg-3"><label class="form-label mb-1 fw-semibold">Overall Return Gas Rate</label><input id="returnOverallRate" type="number" min="0" step="0.01" class="form-control form-control-sm"></div>
        <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-primary" id="applyReturnRate">Apply to All</button></div>
      </div>
      <?php endif; ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Issued KG</th><?php if((int)($shopSettings['allow_return_gas_qty']??0)===1): ?><th>Return KG</th><th>Return Rate</th><th>Consumed KG</th><?php endif; ?><th>Status</th><th></th></tr></thead><tbody id="returnLinesBody"></tbody></table></div>
      <div id="returnEmpty" class="text-muted small py-3">No cylinders selected. Click Select Cylinders.</div>
    </div>
  </div>
</div>

<div class="modal fade" id="issuePickerModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Select Cylinders to Issue</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label">Cylinder Type</label><select id="issuePickerType" class="form-select"><option value="">Select</option><?php foreach($types as $t): $unitsForType=array_values(array_filter($availableCustodyUnits ?? [], static fn($u)=>(int)($u['cylinder_type_id']??0)===(int)$t['id'])); $gasForType=(float)($filledStock[$t['id']]??0); $qtyForType=count($unitsForType); ?><option value="<?= (int)$t['id'] ?>"><?= esc($t['code'].' — '.$t['name'].' — Available Stock: '.number_format($gasForType,2).' KG ('.$qtyForType.' cylinder'.($qtyForType===1?'':'s').')') ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Filled / Empty</label><select id="issuePickerStatus" class="form-select"><option value="filled">Filled / Partially Filled</option><option value="empty">Empty</option></select></div><div class="col-md-3 d-flex align-items-end"><span class="small text-muted" id="issuePickerCount"></span></div></div>
    <div id="issuePickerCards" class="d-flex flex-wrap gap-2"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmIssuePicker">OK / Add Line</button></div>
</div></div></div>

<div class="modal fade" id="returnPickerModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Select Customer Cylinders to Return</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label">Cylinder Type</label><select id="returnPickerType" class="form-select"><option value="">All Types</option><?php foreach($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= esc($t['code'].' — '.$t['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Filled / Empty</label><select id="returnPickerStatus" class="form-select"><option value="">All</option><option value="filled">Filled / Partially Filled</option><option value="empty">Empty</option></select></div><div class="col-md-3 d-flex align-items-end"><span class="small text-muted" id="returnPickerCount"></span></div></div>
    <div id="returnPickerCards" class="d-flex flex-wrap gap-2"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmReturnPicker">OK / Add Selected</button></div>
</div></div></div></div>

</div></div>

<div class="col-lg-3 pos-summary-panel"><div class="card pos-summary-card"><div class="card-body">
<div class="pos-summary-metrics">
  <div class="pos-summary-metric"><label class="form-label fw-semibold">Current Sale</label><div class="form-control bg-light fw-semibold"><span id="saleTotal">0.00</span></div></div>
  <div class="pos-summary-metric"><label class="form-label">OS Balance</label><div class="form-control bg-light"><span id="previousOs">0.00</span></div></div>
  <div class="pos-summary-metric"><label class="form-label">Discount</label><input name="discount_amount" id="discount" type="number" min="0" step="any" value="0" class="form-control"></div>
</div>
<div class="pos-summary-metrics" id="securityDepositSummary" style="display:<?= $initialTransactionType === 'security_deposit' ? 'grid' : 'none' ?>; margin-top:-.35rem;">
  <div class="pos-summary-metric">
    <label class="form-label fw-semibold">Security Deposit Amount</label>
    <input name="security_deposit_amount" id="securityDeposit" type="number" min="0" step="0.01" value="0" class="form-control">
  </div>
  <div class="pos-summary-metric">
    <label class="form-label fw-semibold">Gas / Cylinder Balance</label>
    <div class="form-control bg-light fw-semibold"><span id="combinedGasDue">0.00</span></div>
  </div>
  <div class="pos-summary-metric"></div>
</div>
<div class="mb-3" id="netReceivableBox">
  <label class="form-label" id="netReceivableLabel">Net Receivable Amount</label>
  <div class="form-control bg-light fw-bold"><span id="netPayable">0.00</span></div>
</div>
<div class="mb-3" id="receiptAmountBox"><label class="form-label" id="receiptAmountLabel">Receipt Amount</label><div class="form-control bg-light fw-bold"><span id="receiptAmountValue">0.00</span></div></div>
<div class="mb-3" id="balanceBox"><label class="form-label fw-semibold" id="balanceLabel">OS Balance</label><div class="form-control bg-light fw-bold text-primary"><span id="customerOsBalanceValue">0.00</span></div></div>
<div class="mb-3" id="refundBox" style="display:<?= $initialTransactionType === 'cylinder_return' ? 'block' : 'none' ?>"><label class="form-label fw-semibold">Security Deposit Refund</label><input name="security_deposit_refund_amount" id="refundAmount" type="number" min="0" step="0.01" value="0" class="form-control"><div class="form-text">Refundable deposit balance: Rs. <span id="refundAvailable">0.00</span></div></div>

<div id="combinedPaymentSection" class="border rounded p-2 mb-2" style="display:<?= $initialTransactionType === 'security_deposit' ? 'block' : 'none' ?>">
  <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" id="amountToCollectHelp">Amount to collect <span class="text-muted">(help)</span></button>
  <div id="amountToCollectHelpMessage" class="alert alert-info py-2 px-2 mt-2 mb-2 small d-none">
    The payment rows below are the actual amounts received. You can split one receipt across multiple methods, for example Rs. 500 Online + Rs. 500 Cash. The configured allocation rule keeps Gas / Cylinder and Security Deposit separate in the accounts.
  </div>
  <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
    <span class="fw-semibold">Amount Received</span><strong>Rs. <span id="combinedAmountReceivedTotal">0.00</span></strong>
  </div>
  <div class="d-flex justify-content-between align-items-center mt-1">
    <span class="fw-semibold">OS After Payment</span><strong class="text-primary">Rs. <span id="combinedOsAfterPayment">0.00</span></strong>
  </div>
</div>
<div id="paymentSection" style="display:block">
  <div id="paymentPurposeLabel" class="paymentCollectionTitle">Payment Collection</div>
  <div id="payments"></div>
  <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="addPayment">Add Payment</button><div class="mt-1"><label class="form-label small fw-semibold mb-1">Reference / Note</label><textarea id="paymentReferenceNote" class="form-control payment-reference" rows="2" placeholder="Reference / Note (optional)"></textarea></div>
</div>


<button class="btn btn-primary w-100" id="saveBtn">Post Transaction</button>
<div class="text-center small text-muted mt-1 d-none d-md-block">Press <kbd>F9</kbd> or <kbd>Ctrl</kbd>+<kbd>Enter</kbd> to post</div>
</div></div></div>
</div>
</form>

<script>
const types=<?=json_encode(array_values($types),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
const rates=<?=json_encode($cylinderRates)?>,kgRate=<?=json_encode($kgRate)?>;
const filledStock=<?=json_encode($filledStock)?>,filledUnits=<?=json_encode($filledUnits)?>,emptyStock=<?=json_encode($emptyStock)?>,gasStock=<?=json_encode($gasStock)?>;
const balances=<?=json_encode($balances)?>,creditLimits=<?=json_encode($creditLimits)?>,creditSaleAllowed=<?=json_encode(array_reduce($customers,static function($out,$customer){$out[(string)$customer['id']]=(int)($customer['allow_credit_sale']??0);return $out;},[]))?>;
const creditLimitMode=<?=json_encode($creditLimitMode??'none')?>,shopCreditLimit=<?=json_encode((float)($shopCreditLimit??0))?>,shopOutstanding=<?=json_encode((float)($shopOutstanding??0))?>;
const availableCustodyUnits=<?=json_encode($availableCustodyUnits)?>,allCustomerCustody=<?=json_encode($custodyUnits)?>;
const allowPosSourceCylinderSelection=<?=json_encode((int)($allowPosSourceCylinderSelection??0))?>===1;
const defaultTransactionType=<?=json_encode($defaultTransactionType??'gas_sale')?>,defaultPaymentMode=<?=json_encode($shopSettings['default_payment_mode']??'cash')?>;
const depositPaymentAllocationRule=<?=json_encode($shopSettings['deposit_payment_allocation_rule']??'deposit_first')?>;
const allowReturnGasQty=<?=json_encode((int)($shopSettings['allow_return_gas_qty']??0))?>===1;
const tbody=document.querySelector('#lines tbody'),lineHead=document.getElementById('lineHead'),payments=document.getElementById('payments');

function transactionType(){return document.getElementById('transactionType').value;}
function selectedCustomerId(){return document.getElementById('customer_id').value;}
function selectedCustomer(){const id=selectedCustomerId();return id?{balance:Number(balances[id]||0),limit:Number(creditLimits[id]||0),allowCredit:String(creditSaleAllowed[String(id)]??'0')==='1'}:null;}
function refreshWalkInCustomerOption(resetSelection=false){
 const select=document.getElementById('customer_id'),t=transactionType();
 if(!select)return;
 let walkIn=select.querySelector('option[data-walkin-option="1"]');
 let placeholder=select.querySelector('option[data-customer-placeholder="1"]');
 const customerOnly=t==='security_deposit'||t==='cylinder_return';
 if(customerOnly){
   if(walkIn)walkIn.remove();
   if(!placeholder){
     placeholder=document.createElement('option');
     placeholder.value='';
     placeholder.textContent='Select customer';
     placeholder.dataset.customerPlaceholder='1';
     select.insertBefore(placeholder,select.firstChild);
   }
   if(resetSelection)select.value='';
 }else{
   if(placeholder)placeholder.remove();
   if(!walkIn){
     walkIn=document.createElement('option');
     walkIn.value='';
     walkIn.textContent='Walk-in / Cash';
     walkIn.dataset.walkinOption='1';
     select.insertBefore(walkIn,select.firstChild);
   }
   if(resetSelection)select.value='';
 }
}
function refreshCustomer(){
 const c=selectedCustomer(),box=document.getElementById('customerCreditStatus'),osBox=document.getElementById('customerOsBalanceValue');
 if(osBox)osBox.textContent=(c?c.balance:0).toFixed(2);
 if(!box)return c;
 if(!c){box.className='small mt-1 text-danger';box.textContent='Walk-in / Cash: credit sale not allowed.';return c;}
 const depositBalance=Math.max(0,Number((window.depositBalances||{})[String(selectedCustomerId())]||0));
 const appendDeposit=(base)=>base+' | Deposit Balance: Rs. '+depositBalance.toFixed(2);
 if(!c.allowCredit){box.className='small mt-1 text-danger';box.textContent=appendDeposit('Credit Sale: Not Allowed — Enable Allow Credit Sale on the customer record.');return c;}
 if(creditLimitMode==='none'){
   if(c.limit>0){
     const available=Math.max(0,c.limit-Math.max(0,c.balance));
     if(available<=0){box.className='small mt-1 text-danger';box.textContent=appendDeposit('Credit Sale: Not Allowed — Customer credit limit reached.');return c;}
     box.className='small mt-1 text-success';box.textContent=appendDeposit('Credit Sale: Allowed — Credit Limit: Rs. '+c.limit.toFixed(2)+' | Available: Rs. '+available.toFixed(2));return c;
   }
   box.className='small mt-1 text-success';box.textContent=appendDeposit('Credit Sale: Allowed — Credit Limit: Unlimited');return c;
 }
 if(creditLimitMode==='shop'){
   const available=Math.max(0,shopCreditLimit-shopOutstanding);
   if(available<=0){box.className='small mt-1 text-danger';box.textContent=appendDeposit('Credit Sale: Not Allowed — Shop credit limit reached.');return c;}
   box.className='small mt-1 text-success';box.textContent=appendDeposit('Credit Sale: Allowed — Credit Limit: Rs. '+shopCreditLimit.toFixed(2)+' | Available: Rs. '+available.toFixed(2));return c;
 }
 const available=Math.max(0,c.limit-Math.max(0,c.balance));
 if(available<=0){box.className='small mt-1 text-danger';box.textContent=appendDeposit('Credit Sale: Not Allowed — Customer credit limit reached.');return c;}
 box.className='small mt-1 text-success';box.textContent=appendDeposit('Credit Sale: Allowed — Credit Limit: Rs. '+c.limit.toFixed(2)+' | Available: Rs. '+available.toFixed(2));return c;
}
let balanceRefreshInProgress=false;
async function refreshSelectedCustomerBalance(){
 const id=selectedCustomerId();
 if(!id||balanceRefreshInProgress)return;
 balanceRefreshInProgress=true;
 try{
  const response=await fetch(<?=json_encode(site_url('sales/customer-balance'))?>+'/'+encodeURIComponent(id),{headers:{'Accept':'application/json'},credentials:'same-origin',cache:'no-store'});
  if(!response.ok)return;
  const data=await response.json();
  if(String(data.customer_id)!==String(id)||!Number.isFinite(Number(data.balance)))return;
  balances[id]=Number(data.balance);
  if(String(selectedCustomerId())!==String(id))return;
  document.getElementById('previousOs').textContent=Number(data.balance).toFixed(2);
  refreshCustomer();
 }catch(error){
  console.warn('Unable to refresh POS customer OS balance.',error);
 }finally{
  balanceRefreshInProgress=false;
 }
}
window.addEventListener('focus',refreshSelectedCustomerBalance);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshSelectedCustomerBalance();});
function companyUnitsFor(status,typeId){
  return availableCustodyUnits.filter(u=>(!status||u.status===status)&&(!typeId||String(u.cylinder_type_id)===String(typeId)));
}
function custodyOptions(){
  return '<option value="">Select customer cylinder (optional)</option>'+allCustomerCustody.filter(u=>String(u.customer_id)===String(selectedCustomerId())).map(u=>'<option value="'+u.unit_id+'">'+u.unit_code+' — '+u.cylinder_code+' — '+u.cylinder_name+' — '+Number(u.gas_weight_kg||0).toFixed(2)+' KG — Deposit '+Number(u.deposit_amount||0).toFixed(2)+'</option>').join('');
}
function refillLineOptions(typeId){
  return '<option value="">Select customer cylinder (optional)</option>'+allCustomerCustody.filter(u=>String(u.customer_id)===String(selectedCustomerId())&&(!typeId||String(u.cylinder_type_id)===String(typeId))).map(u=>'<option value="'+u.unit_id+'">'+u.unit_code+' — '+u.cylinder_code+' — '+Number(u.gas_weight_kg||0).toFixed(2)+' KG</option>').join('');
}
function cylinderTypeOptions(){
  return '<option value="">Select</option>'+types.map(t=>{
    const units=filledUnits[t.id]||[];
    const gas=units.reduce((s,u)=>s+Number(u.gas_weight_kg||0),0);
    const stock=units.length;
    return '<option value="'+t.id+'">'+t.code+' — '+t.name+' — Available Stock: '+gas.toFixed(2)+' KG ('+stock+' cylinder'+(stock===1?'':'s')+')</option>';
  }).join('');
}
function clearLines(){tbody.innerHTML='';}
function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function filledSourceOptions(typeId){
  const units=(filledUnits[typeId]||[]).filter(u=>Number(u.gas_weight_kg||0)>0.00001);
  return '<option value="">Select source filled cylinder</option>'+units.map(u=>'<option value="'+u.id+'">'+escapeHtml(u.unit_code)+' — '+escapeHtml(u.cylinder_code)+' — '+escapeHtml(u.cylinder_name)+' — '+Number(u.gas_weight_kg||0).toFixed(2)+' KG</option>').join('');
}
function refreshGasSourceLine(tr, resetSource=true){
  const typeId=tr.querySelector('.cyl').value,source=tr.querySelector('.sourceCyl'),qty=tr.querySelector('.qty');
  const previous=resetSource?'':source.value;
  source.innerHTML=filledSourceOptions(typeId);
  source.value=previous;
  source.disabled=!typeId;
  const unit=(filledUnits[typeId]||[]).find(u=>String(u.id)===String(source.value));
  if(unit){
    const max=Number(unit.gas_weight_kg||0);
    qty.max=max;
    if(tr.dataset.entryMode!=='amount' && Number(qty.value||0)>max)qty.value=max;
  }else{
    qty.removeAttribute('max');
  }
}
function refreshDuplicateTypeOptions(){
 const selected=[...tbody.querySelectorAll('.cyl')].map(s=>s.value).filter(Boolean);
 tbody.querySelectorAll('.cyl').forEach(select=>{const own=select.value;[...select.options].forEach(o=>{o.disabled=!!o.value&&o.value!==own&&selected.includes(o.value);});});
}
function addGasLine(){
 const tr=document.createElement('tr');
 tr.innerHTML='<td><select class="form-select cyl">'+cylinderTypeOptions()+'</select></td><td class="sourceCell" style="display:'+(allowPosSourceCylinderSelection?'table-cell':'none')+'"><select class="form-select sourceCyl" disabled><option value="">Select source filled cylinder</option></select></td><td><input class="form-control entryValue" type="number" min="0" step="any" value="0" placeholder="KG"><input class="qty" type="hidden" value="0"><input class="cylinderQty" type="hidden" value="0"><input class="enteredAmount" type="hidden"></td><td><input class="form-control form-control-sm gasRate" type="number" min="0" step="any" aria-label="Gas Rate"></td><td><select class="form-select targetCyl" disabled></select></td><td class="lineTotal">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger remove">Delete</button></td>';
 const mode=()=>document.getElementById('gasEntryMode').value;
 tr.dataset.entryMode=mode();tr.querySelector('.gasRate').value=kgRate!==null?Number(kgRate).toFixed(2):'';tr.querySelector('.gasRate').disabled=false;
 tr.querySelector('.cyl').onchange=()=>{refreshDuplicateTypeOptions();refreshGasSourceLine(tr,true);const t=tr.querySelector('.targetCyl');t.disabled=!selectedCustomerId();t.innerHTML=refillLineOptions(tr.querySelector('.cyl').value);recalc();};
 tr.querySelector('.entryValue').oninput=()=>{const m=mode(),v=Number(tr.querySelector('.entryValue').value||0),rate=Number(tr.querySelector('.gasRate').value||0);tr.dataset.entryMode=m;if(m==='amount'){tr.querySelector('.enteredAmount').value=v>0?v.toFixed(2):'';tr.querySelector('.qty').value=rate>0&&v>0?(v/rate).toFixed(2):'0';tr.querySelector('.cylinderQty').value='0';}else if(m==='cylinders'){const type=types.find(x=>String(x.id)===String(tr.querySelector('.cyl').value));const kg=Math.max(0,Number(type?.capacity_kg||0))*v;tr.querySelector('.cylinderQty').value=v;tr.querySelector('.qty').value=kg.toFixed(2);tr.querySelector('.enteredAmount').value=rate>0&&kg>0?(kg*rate).toFixed(2):'';}else{tr.querySelector('.qty').value=v;tr.querySelector('.cylinderQty').value='0';tr.querySelector('.enteredAmount').value=rate>0&&v>0?(v*rate).toFixed(2):'';}refreshGasSourceLine(tr,false);recalc();};
 tr.querySelector('.gasRate').oninput=()=>{const rate=Number(tr.querySelector('.gasRate').value||0),q=Number(tr.querySelector('.qty').value||0),entryMode=mode();if(entryMode==='amount'){const amount=Number(tr.querySelector('.enteredAmount').value||0);tr.querySelector('.qty').value=rate>0&&amount>0?(amount/rate).toFixed(2):'0';}else{tr.querySelector('.enteredAmount').value=rate>0&&q>0?(q*rate).toFixed(2):'';}recalc();};
 tr.querySelector('.targetCyl').innerHTML=refillLineOptions('');tr.querySelector('.targetCyl').disabled=!selectedCustomerId();tr.querySelector('.sourceCyl').onchange=()=>recalc();tr.querySelector('.remove').onclick=()=>{tr.remove();recalc();};tbody.appendChild(tr);
 tr.querySelector('.sourceCell').style.display=allowPosSourceCylinderSelection?'':'none';refreshGasSourceLine(tr,true);refreshDuplicateTypeOptions();
}
function rebuildLines(){
 clearLines();const t=transactionType();lineHead.innerHTML='';document.getElementById('lines').classList.remove('gas-sale-mode','cylinder-sale-mode','source-enabled');
 if(t==='gas_sale'){document.getElementById('lines').classList.add('gas-sale-mode');if(allowPosSourceCylinderSelection)document.getElementById('lines').classList.add('source-enabled');lineHead.innerHTML='<tr><th>Cylinder Type</th><th class="sourceHead" style="display:'+(allowPosSourceCylinderSelection?'table-cell':'none')+'">Source Filled Cylinder</th><th id="gasQtyHeader">Qty / KG</th><th>Gas Rate</th><th><span class="gas-sale-header-wrap">Customer<br>Cylinder</span></th><th>Amount</th><th>Action</th></tr>';addGasLine();document.getElementById('gasEntryMode').onchange?.();document.getElementById('addLine').style.display='inline-block';}
 else if(t==='cylinder_sale'){document.getElementById('lines').classList.add('cylinder-sale-mode');lineHead.innerHTML='<tr><th>Cylinder Type</th><th>Status</th><th>Qty</th><th>Gas KG</th><th>Gas Rate</th><th>Cylinder Rate</th><th>Amount</th><th></th></tr>';addCylinderSaleLine();document.getElementById('addLine').style.display='inline-block';}
 else {document.getElementById('addLine').style.display='none';}
}
function refreshPaymentModes(){const walkIn=!selectedCustomerId(),customer=selectedCustomer();payments.querySelectorAll('.payment').forEach(p=>{const m=p.querySelector('.mode');[...m.options].forEach(o=>o.disabled=walkIn&&o.value!=='cash'||(!walkIn&&!customer?.allowCredit&&o.value==='credit'));if(walkIn||(!customer?.allowCredit&&m.value==='credit'))m.value='cash';});}
function paymentTypeOptions(){
 const t=transactionType();
 if(t==='security_deposit'){
   if(depositPaymentAllocationRule==='manual')return '<option value="security_deposit">Security Deposit</option><option value="sale">Gas / Cylinder</option>';
   return '<option value="combined">Combined</option>';
 }
 if(t==='cylinder_return')return '<option value="security_deposit_refund">Security Deposit Refund</option>';
 return '<option value="sale">Sale</option>';
}
function addPayment(paymentType){
 const div=document.createElement('div');div.className='payment';
 div.innerHTML='<select class="form-select paymentType"></select><select class="form-select mode" aria-label="Payment method"><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="online">Online</option><option value="credit">Credit</option></select><input class="form-control amount payment-amount" type="number" min="0" step="0.01" placeholder="Amount (optional)"><button type="button" class="btn btn-outline-danger remove" aria-label="Remove payment">×</button>';
 payments.appendChild(div);
 const t=transactionType();
 const type=paymentType||((t==='security_deposit')?'combined':(t==='cylinder_return'?'security_deposit_refund':'sale'));
 const typeSelect=div.querySelector('.paymentType');
 typeSelect.innerHTML=paymentTypeOptions();
 typeSelect.value=type;
 typeSelect.classList.toggle('is-hidden', t!=='security_deposit' || depositPaymentAllocationRule!=='manual');
 div.querySelector('.mode').value=defaultPaymentMode;
 div.querySelector('.mode').onchange=()=>{refreshPaymentModes();recalc();};
 div.querySelector('.amount').oninput=recalc;
 typeSelect.onchange=recalc;
 div.querySelector('.remove').onclick=()=>{div.remove();if(!payments.querySelector('.payment')&&transactionType()!=='cylinder_return')addPayment();recalc();};
 refreshPaymentModes();recalc();
}
function paymentTotal(type=null){return [...payments.querySelectorAll('.payment')].reduce((s,p)=>{const pt=p.querySelector('.paymentType')?.value||'sale';return s+(type===null||pt===type?Number(p.querySelector('.amount').value||0):0);},0);}
function statusForCylinderSale(tr){return tr.querySelector('.cylStatus')?.value||'empty';}
function cylinderTypeById(typeId){return types.find(t=>String(t.id)===String(typeId))||null;}
function selectedFilledUnits(tr){const ids=Array.isArray(tr._selectedUnitIds)?tr._selectedUnitIds:[],typeId=tr.querySelector('.cyl').value,available=filledUnits[typeId]||[];return ids.map(id=>available.find(u=>String(u.id)===String(id))).filter(Boolean);}
function renderFilledCylinderPicker(tr){const typeId=tr.querySelector('.cyl').value,picker=tr._pickerRow.querySelector('.cylinderPicker'),status=tr.querySelector('.cylStatus').value;if(status!=='filled'||!typeId){picker.innerHTML='<div class="text-muted small">Select a cylinder type to view available physical cylinders.</div>';return;}const units=(filledUnits[typeId]||[]).filter(u=>Number(u.gas_weight_kg||0)>0.00001);if(!units.length){picker.innerHTML='<div class="alert alert-warning py-2 mb-0">No filled/partially filled cylinders are currently available for this cylinder type.</div>';tr._selectedUnitIds=[];return;}const selected=new Set((tr._selectedUnitIds||[]).map(String));picker.innerHTML='<div class="d-flex justify-content-between align-items-center cylinder-picker-title"><strong>Select physical cylinder(s)</strong><span class="small text-muted">'+units.length+' available</span></div><div class="d-flex flex-wrap cylinder-options">'+units.map(u=>{const checked=selected.has(String(u.id))?' checked':'';const gas=Number(u.gas_weight_kg||0),cap=Number(u.capacity_kg||cylinderTypeById(typeId)?.capacity_kg||0);const statusLabel=cap>0&&gas>=cap-0.00001?'Filled':'Partially Filled';const statusClass=statusLabel==='Filled'?'status-filled':'status-partial';return '<label class="btn btn-sm btn-outline-secondary text-start cylinder-option position-relative"><input class="filledUnitCheck" type="checkbox" value="'+u.id+'"'+checked+'><svg class="cylinder-art" viewBox="0 0 40 50" aria-hidden="true"><rect x="9" y="6" width="22" height="38" rx="7" fill="currentColor" opacity=".12"></rect><rect x="11" y="8" width="18" height="34" rx="6" fill="none" stroke="currentColor" stroke-width="2"></rect><path d="M15 8V4h10v4M17 4V1h6v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M14 14h12M14 18h12" stroke="currentColor" stroke-width="1.5" opacity=".65"></path></svg><span class="cylinder-copy"><span class="cylinder-code">'+escapeHtml(u.cylinder_code||u.unit_code||'Cylinder')+'</span><span class="cylinder-name">'+escapeHtml(u.cylinder_name||'')+'</span><span class="cylinder-gas"><strong>'+gas.toFixed(2)+' / '+cap.toFixed(2)+' KG</strong> <span class="cylinder-status '+statusClass+'">'+statusLabel+'</span></span></span></label>';}).join('')+'</div>';picker.querySelectorAll('.filledUnitCheck').forEach(cb=>cb.onchange=()=>{tr._selectedUnitIds=[...picker.querySelectorAll('.filledUnitCheck:checked')].map(x=>Number(x.value));syncFilledCylinderLine(tr);});}
function syncFilledCylinderLine(tr){if(statusForCylinderSale(tr)!=='filled'){recalc();return;}const selected=selectedFilledUnits(tr),qty=tr.querySelector('.qty'),gas=tr.querySelector('.gasQty');qty.value=selected.length;gas.value=selected.reduce((s,u)=>s+Number(u.gas_weight_kg||0),0).toFixed(2);recalc();}
function addCylinderSaleLine(){const tr=document.createElement('tr');tr._selectedUnitIds=[];tr.innerHTML='<td><select class="form-select cyl">'+cylinderTypeOptions()+'</select></td><td><select class="form-select cylStatus"><option value="filled">Filled / Partially Filled</option><option value="empty">Empty</option></select></td><td><input class="form-control qty" type="number" min="0" step="1" value="0"></td><td><input class="form-control gasQty" type="number" value="0.000" readonly></td><td><div class="input-group input-group-sm gasWrap"><span class="input-group-text">Rs/KG</span><input class="form-control gasRate" type="number" min="0" step="any"></div></td><td><div class="input-group input-group-sm"><span class="input-group-text">Rs</span><input class="form-control cylRate" type="number" min="0" step="any"></div></td><td class="lineTotal">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger remove">×</button></td>';const pickerRow=document.createElement('tr');pickerRow.className='cylinderPickerRow';pickerRow.innerHTML='<td colspan="8"><div class="bg-light border rounded p-1 cylinderPicker"><div class="text-muted small">Select a cylinder type to view available physical cylinders.</div></div></td>';tr.querySelector('.cyl').onchange=()=>{tr._selectedUnitIds=[];refreshCylinderSaleLine.call(tr);};tr.querySelector('.cylStatus').onchange=()=>{tr._selectedUnitIds=[];refreshCylinderSaleLine.call(tr);};tr.querySelector('.qty').oninput=recalc;tr.querySelector('.gasRate').oninput=recalc;tr.querySelector('.cylRate').oninput=recalc;tr.querySelector('.remove').onclick=()=>{tr.remove();pickerRow.remove();refreshDuplicateTypeOptions();recalc();};tbody.appendChild(tr);tbody.appendChild(pickerRow);tr._pickerRow=pickerRow;refreshCylinderSaleLine.call(tr);}
function refreshCylinderSaleLine(){const tr=this.tagName==='TR'?this:tbody.querySelector('tr:not(.cylinderPickerRow):last-child'),typeId=tr.querySelector('.cyl').value,status=tr.querySelector('.cylStatus').value,type=cylinderTypeById(typeId),pickerRow=tr._pickerRow,picker=pickerRow.querySelector('.cylinderPicker');pickerRow.classList.toggle('is-visible',!!typeId);tr.querySelector('.gasWrap').style.display=status==='filled'?'flex':'none';tr.querySelector('.gasRate').disabled=status!=='filled';tr.querySelector('.gasRate').value=status==='filled'&&kgRate!==null?Number(kgRate).toFixed(2):'';const configuredCylinderPrice=Number(rates[typeId]??0);const masterCylinderPrice=Number(type?.empty_cylinder_price??0);const defaultCylinderPrice=configuredCylinderPrice>0?configuredCylinderPrice:(masterCylinderPrice>0?masterCylinderPrice:0);tr.querySelector('.cylRate').value=defaultCylinderPrice.toFixed(2);if(status==='filled'){tr.querySelector('.qty').readOnly=true;tr.querySelector('.gasQty').title='Calculated from selected physical cylinder(s).';renderFilledCylinderPicker(tr);}else{tr.querySelector('.qty').readOnly=false;tr.querySelector('.qty').max=Number(emptyStock[typeId]||0);if(Number(tr.querySelector('.qty').value||0)>Number(emptyStock[typeId]||0))tr.querySelector('.qty').value=Number(emptyStock[typeId]||0);tr.querySelector('.gasQty').value='0.000';picker.innerHTML='<div class="text-muted small">Empty cylinder sale: enter quantity only. Gas stock is not affected.</div>';}syncFilledCylinderLine(tr);}
function gasForCylinderSale(tr){
 const status=statusForCylinderSale(tr),typeId=tr.querySelector('.cyl').value;
 if(status!=='filled'||!typeId)return 0;
 return selectedFilledUnits(tr).reduce((sum,u)=>sum+Number(u.gas_weight_kg||0),0);
}
function saleTotalForPayment(){return Number(document.getElementById('saleTotal').textContent||0);}
function recalc(){
 let saleTotal=0,gasRequired=0;
 const t=transactionType();
 const usedByType={};
 tbody.querySelectorAll('tr:not(.cylinderPickerRow)').forEach(tr=>{
   const qty=Math.max(0,Number(tr.querySelector('.qty')?.value||0));
   let amount=0,gas=0;
   if(t==='gas_sale'){
     gas=qty;
     const typeId=tr.querySelector('.cyl').value;
     const mode=document.getElementById('gasEntryMode')?.value||'quantity';
     if(mode==='amount'){
       const amountEntered=Number(tr.querySelector('.enteredAmount')?.value||0);
       const rate=Number(tr.querySelector('.gasRate')?.value||0);
       gas=rate>0&&amountEntered>0?amountEntered/rate:0;
       tr.querySelector('.qty').value=gas>0?gas.toFixed(2):'0';
     }else if(mode==='cylinders'){
       const type=types.find(x=>String(x.id)===String(typeId)),capacity=Math.max(0,Number(type?.capacity_kg||0));
       const count=Math.max(0,Number(tr.querySelector('.cylinderQty')?.value||0));
       gas=capacity*count;
       tr.querySelector('.qty').value=gas.toFixed(2);
       tr.querySelector('.enteredAmount').value=gas>0&&Number(tr.querySelector('.gasRate').value||0)>0?(gas*Number(tr.querySelector('.gasRate').value)).toFixed(2):'';
     }else{
       tr.querySelector('.enteredAmount').value=Number(tr.querySelector('.qty').value||0)>0&&Number(tr.querySelector('.gasRate').value||0)>0
         ?(Number(tr.querySelector('.qty').value)*Number(tr.querySelector('.gasRate').value)).toFixed(2):'';
     }
     const available=(filledUnits[typeId]||[]).reduce((sum,u)=>sum+Number(u.gas_weight_kg||0),0);
     const prior=Number(usedByType[typeId]||0),remaining=Math.max(0,available-prior);
     tr.querySelector('.availableGas')?.replaceChildren(document.createTextNode(remaining.toFixed(2)+' KG'));
     tr.querySelector('.qty').max=Math.max(0,remaining);
     if(gas>remaining && tr.dataset.entryMode==='cylinders'){
       const type=types.find(x=>String(x.id)===String(typeId)),capacity=Math.max(0,Number(type?.capacity_kg||0));
       const allowedCount=capacity>0?Math.floor((remaining+0.00001)/capacity):0;
       tr.querySelector('.cylinderQty').value=allowedCount;
       tr.querySelector('.entryValue').value=allowedCount;
       gas=allowedCount*capacity;
       tr.querySelector('.qty').value=gas.toFixed(2);
       tr.querySelector('.enteredAmount').value=(gas*Number(tr.querySelector('.gasRate').value||0)).toFixed(2);
     }else if(gas>remaining && tr.dataset.entryMode!=='amount'){
       tr.querySelector('.qty').value=remaining;gas=remaining;
       tr.querySelector('.enteredAmount').value=(remaining*Number(tr.querySelector('.gasRate').value||0)).toFixed(2);
     }
     if(gas>remaining && tr.dataset.entryMode==='amount')tr.querySelector('.entryValue').setCustomValidity('Amount exceeds available gas stock.');
     else tr.querySelector('.entryValue').setCustomValidity('');
     usedByType[typeId]=prior+gas;
     amount=mode==='amount'?Number(tr.querySelector('.enteredAmount').value||0):gas*Number(tr.querySelector('.gasRate').value||0);
   }else if(t==='cylinder_sale'){
     gas=gasForCylinderSale(tr);
     const gr=Number(tr.querySelector('.gasRate').value||0),cr=Number(tr.querySelector('.cylRate').value||0),q=Math.floor(qty);
     amount=statusForCylinderSale(tr)==='filled'?gas*gr+q*cr:q*cr;
   }
   gasRequired+=gas;saleTotal+=amount;
   tr.querySelector('.lineTotal').textContent=''+amount.toFixed(2);
 });
 // Show the total KG that will leave gas stock when Amount or cylinder-count entry is used.
 const gasQtyHeader=document.getElementById('gasQtyHeader');
 const gasEntryMode=document.getElementById('gasEntryMode')?.value||'quantity';
 if(gasQtyHeader){
   if(t==='gas_sale'&&gasEntryMode==='amount')gasQtyHeader.textContent='Qty / KG ('+gasRequired.toFixed(2)+' KG)';
   else if(t==='gas_sale'&&gasEntryMode==='cylinders')gasQtyHeader.textContent='Cylinder Qty ('+gasRequired.toFixed(2)+' KG gas)';
   else gasQtyHeader.textContent='Qty / KG';
 }
 if(t==='security_deposit'){
   issueLines.forEach(l=>{
     const gas=Number(l.gas_weight_kg||0),q=(l.selected_cylinder_unit_ids||[]).length;
     saleTotal+=l.status==='filled'?(gas*Number(l.gas_rate||0)+q*Number(l.cylinder_rate||0)):q*Number(l.cylinder_rate||0);
     gasRequired+=gas;
   });
 }else if(t==='cylinder_return'){
   returnLines.forEach(l=>{gasRequired+=Number(l.return_gas_kg||0);});
 }
 const discount=Math.max(0,Number(document.getElementById('discount').value||0));
 saleTotal=Math.max(0,saleTotal-discount);
 const customer=selectedCustomer(),previousOs=customer?Number(customer.balance||0):0;
 const deposit=t==='security_deposit'?Math.max(0,Number(document.getElementById('securityDeposit').value||0)):0;
 const netReceivable=t==='security_deposit'?deposit:(t==='cylinder_return'?0:Math.max(0,saleTotal+previousOs));
 document.getElementById('saleTotal').textContent=saleTotal.toFixed(2);
 document.getElementById('previousOs').textContent=previousOs.toFixed(2);
 document.getElementById('netPayable').textContent=netReceivable.toFixed(2);

 let paid=0,balanceAfter=0;
 if(t==='security_deposit'){
   const totalDue=deposit+Math.max(0,saleTotal+previousOs),received=paymentTotal();
   paid=received;
   balanceAfter=Math.max(0,totalDue-received);
   document.getElementById('combinedGasDue').textContent=Math.max(0,saleTotal+previousOs).toFixed(2);
   document.getElementById('combinedAmountReceivedTotal').textContent=received.toFixed(2);
   document.getElementById('combinedOsAfterPayment').textContent=balanceAfter.toFixed(2);
 }else{
   const refundPaid=t==='cylinder_return'?paymentTotal('security_deposit_refund'):paymentTotal();
   paid=refundPaid;
   const collected=[...payments.querySelectorAll('.payment')].reduce((sum,p)=>sum+((p.querySelector('.mode')?.value||'')==='credit'?0:Number(p.querySelector('.amount').value||0)),0);balanceAfter=t==='cylinder_return'?previousOs:previousOs+saleTotal-collected;
 }
 document.getElementById('receiptAmountValue').textContent=paid.toFixed(2);
 document.getElementById('customerOsBalanceValue').textContent=balanceAfter.toFixed(2);
 document.getElementById('paymentPurposeLabel').textContent=t==='security_deposit'?'Payment Collection':(t==='cylinder_return'?'Security Deposit Refund Payment':'Sale Payment');
 document.getElementById('combinedPaymentSection').style.display=t==='security_deposit'?'block':'none';
 document.getElementById('securityDepositSummary').style.display=t==='security_deposit'?'grid':'none';
 document.getElementById('paymentSection').style.display=t==='cylinder_return'?(Number(document.getElementById('refundAmount').value||0)>0?'block':'none'):'block';
 document.getElementById('netReceivableLabel').textContent=t==='security_deposit'?'Gas / Cylinder Due':'Net Receivable Amount';
 document.getElementById('balanceLabel').textContent=t==='security_deposit'?'Gas / Cylinder Balance':'OS Balance';
 document.getElementById('netReceivableBox').style.display=t==='security_deposit'?'none':'block';
 document.getElementById('receiptAmountBox').style.display=t==='security_deposit'?'none':'block';
 document.getElementById('balanceBox').style.display=t==='security_deposit'?'none':'block';
 updateRefund();
}
let resettingTransactionType=false;
function resetTransactionFormState(){
  // Switching transaction type starts a completely clean transaction context.
  // Keep the newly selected transaction type itself, but reset every other
  // input/select/line/picker/payment state so nothing leaks between workflows.
  clearLines();
  lineHead.innerHTML='';
  issueLines=[];
  returnLines=[];

  const resetValue=(id,value)=>{
    const el=document.getElementById(id);
    if(el)el.value=value;
  };
  resetValue('customer_id','');
  resetValue('discount','0');
  resetValue('securityDeposit','0');
  resetValue('refundAmount','0');
  resetValue('gasEntryMode','quantity');
  resetValue('returnOverallRate','');
  resetValue('issuePickerType','');
  resetValue('issuePickerStatus','filled');
  resetValue('returnPickerType','');
  resetValue('returnPickerStatus','');
  resetValue('paymentReferenceNote','');
  resetValue('stock_override_confirmed','0');

  // Reset transaction time to the current local time for the new transaction.
  const timeInput=document.querySelector('input[name="transaction_at"]');
  if(timeInput){
    const now=new Date();
    const pad=n=>String(n).padStart(2,'0');
    timeInput.value=now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate())+'T'+pad(now.getHours())+':'+pad(now.getMinutes());
  }

  // Close any open cylinder picker so old selections cannot remain visible.
  ['issuePickerModal','returnPickerModal'].forEach(id=>{
    const modal=document.getElementById(id);
    if(modal && window.bootstrap?.Modal){
      const instance=window.bootstrap.Modal.getInstance(modal);
      if(instance)instance.hide();
    }
  });

  // Clear picker result areas/counts too; the filters above will be restored
  // to their workflow defaults when the picker is opened again.
  const issueCards=document.getElementById('issuePickerCards');
  const issueCount=document.getElementById('issuePickerCount');
  const returnCards=document.getElementById('returnPickerCards');
  const returnCount=document.getElementById('returnPickerCount');
  if(issueCards)issueCards.innerHTML='';
  if(issueCount)issueCount.textContent='';
  if(returnCards)returnCards.innerHTML='';
  if(returnCount)returnCount.textContent='';

  payments.innerHTML='';
  document.getElementById('posValidationAlert').classList.add('d-none');
  document.getElementById('posSuccessAlert').style.display='none';
  window.lastPostedReceiptUrl='';

  const previewFrame=document.getElementById('receiptPreviewFrame');
  if(previewFrame)previewFrame.src='about:blank';
  const receiptOverlay=document.getElementById('receiptPreviewOverlay');
  if(receiptOverlay){
    receiptOverlay.classList.remove('is-visible');
    receiptOverlay.setAttribute('aria-hidden','true');
  }

  const help=document.getElementById('amountToCollectHelpMessage');
  if(help)help.classList.add('d-none');

  refreshWalkInCustomerOption(true);
  addPayment();
}
function refreshForm(){
 const t=transactionType(),standard=['gas_sale','cylinder_sale'].includes(t),workspace=document.getElementById('posWorkspace');
 refreshWalkInCustomerOption();
 if(workspace)workspace.className='row g-3 pos-workspace mode-'+t;
 payments.querySelectorAll('.payment').forEach(p=>{const pt=p.querySelector('.paymentType');if(!pt)return;pt.innerHTML=paymentTypeOptions();pt.value=t==='security_deposit'?(depositPaymentAllocationRule==='manual'?'sale':'combined'):(t==='cylinder_return'?'security_deposit_refund':'sale');pt.classList.toggle('is-hidden',t!=='security_deposit'||depositPaymentAllocationRule!=='manual');});
 document.getElementById('standardTransaction').style.display=standard?'block':'none';document.getElementById('gasEntryModeWrap').style.display=t==='gas_sale'?'block':'none';
 document.getElementById('securityTransaction').style.display=t==='security_deposit'?'block':'none';
 document.getElementById('returnTransaction').style.display=t==='cylinder_return'?'block':'none';
 document.getElementById('securityDeposit').disabled=t!=='security_deposit'; document.getElementById('securityDepositSummary').style.display=t==='security_deposit'?'grid':'none'; document.getElementById('discount').disabled=!standard; if(!standard)document.getElementById('discount').value='0';
 if(t!=='security_deposit')document.getElementById('securityDeposit').value='0';
 document.getElementById('refundBox').style.display=t==='cylinder_return'?'block':'none';
 document.getElementById('paymentSection').style.display=t==='cylinder_return'?(Number(document.getElementById('refundAmount').value||0)>0?'block':'none'):'block';document.getElementById('combinedPaymentSection').style.display=t==='security_deposit'?'block':'none';document.getElementById('paymentPurposeLabel').textContent=t==='security_deposit'?'Payment Collection':(t==='cylinder_return'?'Security Deposit Refund Payment':'Sale Payment');
 document.getElementById('saveBtn').textContent=t==='cylinder_return'?'Return Cylinder / Refund Deposit':t==='security_deposit'?'Receive Deposit / Issue Cylinder':'Post Transaction';
 if(standard)rebuildLines();else{clearLines();lineHead.innerHTML='';}
 setCustodyLists();refreshPaymentModes();refreshCustomer();recalc();
}
function handleTransactionTypeChange(){
 if(resettingTransactionType)return;
 resettingTransactionType=true;
 try{
   resetTransactionFormState();
   refreshForm();
   rebuildLines();
   const c=selectedCustomer();
   const previousOs=c?c.balance:0;
   document.getElementById('previousOs').textContent=previousOs.toFixed(2);
   document.getElementById('customerOsBalanceValue').textContent=previousOs.toFixed(2);
   setCustodyLists();
   refreshCustomer();
   recalc();
 }finally{
   resettingTransactionType=false;
 }
}

let issueLines=[],returnLines=[];
window.depositBalances=<?=json_encode($depositBalances??[],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

function issueSelectedIds(){return issueLines.flatMap(x=>x.selected_cylinder_unit_ids||[]).map(Number);}
function returnSelectedIds(){return returnLines.map(x=>Number(x.unit_id));}
function issueRateDefaults(typeId){const t=cylinderTypeById(typeId);const cylCfg=Number(rates[typeId]??0);const cylMaster=Number(t?.empty_cylinder_price??0);return {gas:kgRate!==null?Number(kgRate):0,cyl:cylCfg>0?cylCfg:cylMaster};}
function issuePickerRender(){
 const typeSelect=document.getElementById('issuePickerType'),typeId=typeSelect.value,status=document.getElementById('issuePickerStatus').value,box=document.getElementById('issuePickerCards'),used=new Set(issueSelectedIds());
 const selectedType=typeSelect.options[typeSelect.selectedIndex];
 if(selectedType&&typeId){const typeObj=cylinderTypeById(typeId),unitsForType=availableCustodyUnits.filter(u=>String(u.cylinder_type_id)===String(typeId)),gasForType=(filledUnits[typeId]||[]).reduce((s,u)=>s+Number(u.gas_weight_kg||0),0);selectedType.textContent=(typeObj?.code||'')+' — '+(typeObj?.name||'')+' — Available Stock: '+gasForType.toFixed(2)+' KG ('+unitsForType.length+' cylinder'+(unitsForType.length===1?'':'s')+')';}
 let units=availableCustodyUnits.filter(u=>String(u.cylinder_type_id)===String(typeId)&&!used.has(Number(u.id)));
 units=units.filter(u=>status==='filled'?Number(u.gas_weight_kg||0)>0.00001:u.status==='empty');
 document.getElementById('issuePickerCount').textContent=units.length+' available';
 if(!typeId){box.innerHTML='<div class="text-muted">Select a cylinder type.</div>';return;}
 if(!units.length){box.innerHTML='<div class="alert alert-warning w-100">No available cylinders match this filter.</div>';return;}
 box.innerHTML=units.map(u=>{const gas=Number(u.gas_weight_kg||0),cap=Number(u.capacity_kg||0),isEmpty=gas<=0.00001,isFull=!isEmpty&&gas>=cap-0.00001,label=isEmpty?'Empty':(isFull?'Filled':'Partially Filled'),statusClass=isEmpty?'issue-status-empty':(isFull?'issue-status-filled':'issue-status-partial');return '<label class="btn btn-sm btn-outline-secondary text-start cylinder-option position-relative '+statusClass+'"><input type="checkbox" class="issuePickCheck" value="'+u.id+'" style="position:absolute;opacity:0"><span class="cylinder-art" aria-hidden="true"></span><span class="cylinder-copy"><span class="cylinder-code">'+escapeHtml(u.unit_code)+'</span><span class="cylinder-name">'+escapeHtml(u.cylinder_name||u.cylinder_code||'')+'</span><span class="cylinder-gas"><strong>'+gas.toFixed(2)+' KG</strong> <span class="cylinder-status">'+label+'</span></span></span></label>';}).join('');
}
function renderIssueLines(){
 const body=document.getElementById('issueLinesBody'),empty=document.getElementById('issueEmpty');
 if(!body||!empty)return;
 body.innerHTML=issueLines.map((l,i)=>{
   const gas=Number(l.gas_weight_kg||0);
   const amount=l.status==='filled'
     ?(gas*Number(l.gas_rate||0)+Number(l.cylinder_rate||0)*l.selected_cylinder_unit_ids.length)
     :(Number(l.cylinder_rate||0)*l.selected_cylinder_unit_ids.length);
   const physical=(l.selected_cylinder_unit_ids||[]).map(id=>{
     const u=availableCustodyUnits.find(x=>Number(x.id)===Number(id));
     return '<span class="badge text-bg-light border me-1">'+escapeHtml(u?.unit_code||id)+'</span>';
   }).join('');
   const gasRate=l.status==='filled'
     ?'<input class="form-control form-control-sm issueGasRateInput" data-index="'+i+'" type="number" min="0" step="0.01" value="'+Number(l.gas_rate||0).toFixed(2)+'">'
     :'';
   return '<tr>'
     +'<td><strong>'+escapeHtml(l.type_label)+'</strong><br><span class="badge text-bg-'+(l.status==='filled'?'success':'secondary')+'">'+(l.status==='filled'?'Filled / Partial':'Empty')+'</span></td>'
     +'<td>'+physical+'</td>'
     +'<td class="text-end">'+gas.toFixed(2)+'</td>'
     +'<td class="text-end">'+gasRate+'</td>'
     +'<td class="text-end">'+Number(l.cylinder_rate||0).toFixed(2)+'</td>'
     +'<td class="text-end fw-semibold">'+amount.toFixed(2)+'</td>'
     +'<td><button type="button" class="btn btn-sm btn-outline-danger issue-line-remove" data-index="'+i+'">×</button></td>'
     +'</tr>';
 }).join('');
 empty.style.display=issueLines.length?'none':'block';
 body.querySelectorAll('.issueGasRateInput').forEach(el=>el.oninput=()=>{
   const i=Number(el.dataset.index);
   if(!issueLines[i])return;
   issueLines[i].gas_rate=Math.max(0,Number(el.value||0));
   recalc();
 });
 body.querySelectorAll('.issue-line-remove').forEach(btn=>btn.onclick=()=>{
   const i=Number(btn.dataset.index);
   issueLines.splice(i,1);
   renderIssueLines();
   recalc();
 });
}
function openIssuePicker(){
 if(!selectedCustomerId()){alert('Select a customer first.');return;}
 document.getElementById('issuePickerType').value='';
 document.getElementById('issuePickerStatus').value='filled';
 issuePickerRender();
 bootstrap.Modal.getOrCreateInstance(document.getElementById('issuePickerModal')).show();
}
function addIssueSelection(){
 const typeId=Number(document.getElementById('issuePickerType').value||0),status=document.getElementById('issuePickerStatus').value,ids=[...document.querySelectorAll('.issuePickCheck:checked')].map(x=>Number(x.value));
 if(!typeId||!ids.length){alert('Select a cylinder type and at least one physical cylinder.');return;}
 const combo=typeId+'|'+status;if(issueLines.some(x=>x.combo===combo)){alert('This Cylinder Type + Filled/Empty combination is already in the issue transaction. Remove it before adding another line.');return;}
 const units=ids.map(id=>availableCustodyUnits.find(u=>Number(u.id)===id)).filter(Boolean),def=issueRateDefaults(typeId),type=cylinderTypeById(typeId);
 const gas=units.reduce((s,u)=>s+Number(u.gas_weight_kg||0),0);
 issueLines.push({combo,type_id:typeId,type_label:(type?.code||'')+' — '+(type?.name||''),status,selected_cylinder_unit_ids:ids,gas_weight_kg:gas,gas_rate:status==='filled'?def.gas:0,cylinder_rate:def.cyl});
 renderIssueLines();recalc();bootstrap.Modal.getInstance(document.getElementById('issuePickerModal')).hide();
}
function returnPickerRender(){
 const typeId=document.getElementById('returnPickerType').value,status=document.getElementById('returnPickerStatus').value,box=document.getElementById('returnPickerCards'),used=new Set(returnSelectedIds()),cid=selectedCustomerId();
 let units=allCustomerCustody.filter(u=>String(u.customer_id)===String(cid)&&!used.has(Number(u.unit_id)));
 if(typeId)units=units.filter(u=>String(u.cylinder_type_id)===String(typeId));
 if(status)units=units.filter(u=>status==='filled'?Number(u.gas_weight_kg||0)>0.00001:Number(u.gas_weight_kg||0)<=0.00001);
 document.getElementById('returnPickerCount').textContent=units.length+' available';
 if(!cid){box.innerHTML='<div class="alert alert-warning w-100">Select a customer first.</div>';return;}
 if(!units.length){box.innerHTML='<div class="alert alert-warning w-100">No pending cylinders match this filter.</div>';return;}
 box.innerHTML=units.map(u=>'<label class="btn btn-sm btn-outline-secondary text-start cylinder-option position-relative"><input type="checkbox" class="returnPickCheck" value="'+u.unit_id+'" style="position:absolute;opacity:0"><span class="cylinder-copy"><span class="cylinder-code">'+escapeHtml(u.unit_code)+'</span><span class="cylinder-name">'+escapeHtml(u.cylinder_code||u.cylinder_name||'')+'</span><span class="cylinder-gas"><strong>Issued '+Number(u.issued_gas_weight_kg??u.gas_weight_kg??0).toFixed(2)+' KG</strong> · '+(Number(u.gas_weight_kg||0)>0?'Filled/Partial':'Empty')+'</span></span></label>').join('');
}
function renderReturnLines(){
 const body=document.getElementById('returnLinesBody'),empty=document.getElementById('returnEmpty');
 body.innerHTML=returnLines.map((l,i)=>{
   const issued=Number(l.issued_gas_weight_kg||0),ret=Number(l.return_gas_kg||0),cons=Math.max(0,issued-ret);
   const gasCells=allowReturnGasQty
     ? '<td><input class="form-control form-control-sm returnGasInput" data-index="'+i+'" type="number" min="0" step="0.001" value="'+ret.toFixed(2)+'"></td><td><input class="form-control form-control-sm returnRateInput" data-index="'+i+'" type="number" min="0" step="0.01" value="'+Number(l.return_gas_rate||0).toFixed(2)+'" '+(ret>0?'':'disabled')+'></td><td class="consumedCell">'+cons.toFixed(2)+'</td>'
     : '';
   return '<tr><td><strong>'+escapeHtml(l.unit_code)+'</strong><br><span class="small text-muted">'+escapeHtml(l.cylinder_code||'')+'</span></td><td>'+issued.toFixed(2)+'</td>'+gasCells+'<td class="statusCell">'+(ret>0?'Partially Filled':'Empty')+'</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="returnLines.splice('+i+',1);renderReturnLines();recalc();">×</button></td></tr>';
 }).join('');
 empty.style.display=returnLines.length?'none':'block';
 body.querySelectorAll('.returnGasInput').forEach(el=>el.oninput=()=>{
   const i=Number(el.dataset.index);
   returnLines[i].return_gas_kg=Math.max(0,Number(el.value||0));
   if(returnLines[i].return_gas_kg>0&&Number(returnLines[i].return_gas_rate||0)<=0)returnLines[i].return_gas_rate=Number(returnLines[i].issued_gas_rate||0);
   renderReturnLines();recalc();
 });
 body.querySelectorAll('.returnRateInput').forEach(el=>el.oninput=()=>{
   returnLines[Number(el.dataset.index)].return_gas_rate=Math.max(0,Number(el.value||0));recalc();
 });
}
function openReturnPicker(){if(!selectedCustomerId()){alert('Select a customer first.');return;}document.getElementById('returnPickerType').value='';document.getElementById('returnPickerStatus').value='';returnPickerRender();bootstrap.Modal.getOrCreateInstance(document.getElementById('returnPickerModal')).show();}
function addReturnSelection(){const ids=[...document.querySelectorAll('.returnPickCheck:checked')].map(x=>Number(x.value));if(!ids.length){alert('Select at least one cylinder.');return;}ids.forEach(id=>{const u=allCustomerCustody.find(x=>Number(x.unit_id)===id);if(u)returnLines.push({unit_id:id,unit_code:u.unit_code,cylinder_code:u.cylinder_code,issued_gas_weight_kg:Number(u.issued_gas_weight_kg??u.gas_weight_kg??0),issued_gas_rate:Number(u.issued_gas_rate||0),return_gas_kg:0,return_gas_rate:Number(u.issued_gas_rate||0)});});renderReturnLines();recalc();bootstrap.Modal.getInstance(document.getElementById('returnPickerModal')).hide();}
function setCustodyLists(){renderIssueLines();renderReturnLines();updateRefund();}
function updateRefund(){const available=Number((window.depositBalances||{})[selectedCustomerId()]||0);const el=document.getElementById('refundAvailable');el.textContent=available.toFixed(2);el.dataset.value=available.toFixed(2);const refund=document.getElementById('refundAmount');if(Number(refund.value||0)>available)refund.value=available.toFixed(2);}


document.getElementById('issuePickerType').onchange=issuePickerRender;document.getElementById('issuePickerStatus').onchange=issuePickerRender;document.getElementById('openIssuePicker').onclick=openIssuePicker;document.getElementById('confirmIssuePicker').onclick=addIssueSelection;
document.getElementById('returnPickerType').onchange=returnPickerRender;document.getElementById('returnPickerStatus').onchange=returnPickerRender;document.getElementById('openReturnPicker').onclick=openReturnPicker;document.getElementById('confirmReturnPicker').onclick=addReturnSelection;
document.getElementById('refundAmount').oninput=recalc;
const applyReturnRate=document.getElementById('applyReturnRate');
if(applyReturnRate)applyReturnRate.onclick=()=>{const rate=Math.max(0,Number(document.getElementById('returnOverallRate').value||0));returnLines.forEach(l=>l.return_gas_rate=rate);renderReturnLines();recalc();};
document.getElementById('gasEntryMode').onchange=()=>{
 const mode=document.getElementById('gasEntryMode').value,header=document.getElementById('gasQtyHeader');
 if(header)header.textContent=mode==='cylinders'?'Cylinder Qty (0.000 KG gas)':(mode==='amount'?'Qty / KG (0.000 KG)':'Qty / KG');
 const sourceHead=document.querySelector('#lines .sourceHead');
 if(sourceHead)sourceHead.style.display=mode==='cylinders'?'none':(allowPosSourceCylinderSelection?'table-cell':'none');
 tbody.querySelectorAll('tr').forEach(tr=>{if(!tr.querySelector('.entryValue'))return;
   const sourceCell=tr.querySelector('.sourceCell');
   if(sourceCell)sourceCell.style.display=mode==='cylinders'?'none':(allowPosSourceCylinderSelection?'table-cell':'none');const rate=Number(tr.querySelector('.gasRate').value||0),q=Number(tr.querySelector('.qty').value||0),a=Number(tr.querySelector('.enteredAmount').value||0),input=tr.querySelector('.entryValue');tr.dataset.entryMode=mode;tr.querySelector('.gasRate').disabled=false;if(mode==='amount'){const v=a>0?a:(rate>0?q*rate:0);tr.querySelector('.enteredAmount').value=v>0?v.toFixed(2):'';input.value=v>0?v.toFixed(2):'';input.placeholder='Amount';input.step='0.01';}else if(mode==='cylinders'){const type=types.find(x=>String(x.id)===String(tr.querySelector('.cyl').value)),cap=Math.max(0,Number(type?.capacity_kg||0)),count=Number(tr.querySelector('.cylinderQty')?.value||0),v=count>0?count:(cap>0&&q>0?Math.floor(q/cap):0),kg=cap*v;tr.querySelector('.cylinderQty').value=v;tr.querySelector('.qty').value=kg.toFixed(2);input.value=v>0?String(v):'';input.placeholder='Cylinder Qty';input.step='1';tr.querySelector('.enteredAmount').value=rate>0&&kg>0?(kg*rate).toFixed(2):'';}else{const v=q>0?q:(rate>0&&a>0?a/rate:0);tr.querySelector('.qty').value=v>0?v.toFixed(2):'0';tr.querySelector('.cylinderQty').value='0';input.value=v>0?v.toFixed(2):'';input.placeholder='KG';input.step='any';tr.querySelector('.enteredAmount').value=rate>0&&v>0?(v*rate).toFixed(2):'';}refreshGasSourceLine(tr,false);});recalc();
};
document.getElementById('transactionType').onchange=handleTransactionTypeChange;
document.getElementById('customer_id').onchange=()=>{
  const selected=selectedCustomerId();
  // A customer change starts a clean transaction context so no values from the
  // previous customer can remain accidentally selected.
  clearLines();
  lineHead.innerHTML='';
  document.getElementById('discount').value='0';
  document.getElementById('securityDeposit').value='0';
    payments.innerHTML='';
  addPayment();
  refreshCustomer();
  setCustodyLists();
  refreshForm();
  if(selected){
    const c=selectedCustomer();
    document.getElementById('previousOs').textContent=(c?c.balance:0).toFixed(2);
    document.getElementById('customerOsBalanceValue').textContent=(c?c.balance:0).toFixed(2);
  }else{
    document.getElementById('previousOs').textContent='0.00';
    document.getElementById('customerOsBalanceValue').textContent='0.00';
  }
  recalc();
};
document.getElementById('addLine').onclick=()=>{if(transactionType()==='gas_sale')addGasLine();else if(transactionType()==='cylinder_sale')addCylinderSaleLine();};
document.getElementById('discount').oninput=recalc;document.getElementById('securityDeposit').oninput=recalc;const returnUnitsEl=document.getElementById('returnUnits');if(returnUnitsEl)returnUnitsEl.onchange=updateRefund;document.getElementById('addPayment').onclick=()=>addPayment();document.getElementById('amountToCollectHelp').onclick=()=>document.getElementById('amountToCollectHelpMessage').classList.toggle('d-none');

window.lastPostedReceiptUrl='';
const receiptOverlay=document.getElementById('receiptPreviewOverlay');
const receiptFrame=document.getElementById('receiptPreviewFrame');
const openReceiptPreview=()=>{
  if(!window.lastPostedReceiptUrl)return;
  receiptFrame.src=window.lastPostedReceiptUrl;
  receiptOverlay.classList.add('is-visible');
  receiptOverlay.setAttribute('aria-hidden','false');
};
const closeReceiptPreview=()=>{
  receiptOverlay.classList.remove('is-visible');
  receiptOverlay.setAttribute('aria-hidden','true');
};
const printReceiptDirect=()=>{
  if(!window.lastPostedReceiptUrl)return;
  const frame=document.createElement('iframe');
  frame.style.position='fixed';frame.style.width='1px';frame.style.height='1px';frame.style.border='0';frame.style.opacity='0';frame.style.pointerEvents='none';
  document.body.appendChild(frame);
  frame.onload=()=>{
    try{frame.contentWindow.focus();frame.contentWindow.print();}finally{setTimeout(()=>frame.remove(),1500);}
  };
  frame.src=window.lastPostedReceiptUrl;
};
document.getElementById('printPreviewBtn').onclick=openReceiptPreview;
document.getElementById('printReceiptBtn').onclick=printReceiptDirect;
document.getElementById('closeReceiptPreview').onclick=closeReceiptPreview;
receiptOverlay.addEventListener('click',e=>{if(e.target===receiptOverlay)closeReceiptPreview();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeReceiptPreview();});

document.getElementById('saleForm').onsubmit=async(e)=>{
 e.preventDefault();const errorBox=document.getElementById('posValidationAlert'),saveBtn=document.getElementById('saveBtn');
 const fail=msg=>{errorBox.textContent=String(msg);errorBox.classList.remove('d-none');errorBox.scrollIntoView({behavior:'smooth',block:'nearest'});return false;};errorBox.classList.add('d-none');
 const t=transactionType(),customerId=selectedCustomerId();
 let lines=[];
 if(t==='security_deposit'){
   if(!customerId)return fail('Select a customer for Security Deposit / Issue Cylinder.');
   if(!issueLines.length)return fail('Select at least one cylinder to issue.');
   lines=issueLines.map(l=>({cylinder_type_id:l.type_id,cylinder_status:l.status,selected_cylinder_unit_ids:l.selected_cylinder_unit_ids,gas_weight_kg:l.gas_weight_kg,gas_rate:l.gas_rate,cylinder_rate:l.cylinder_rate}));
 }else if(t==='cylinder_return'){
   if(!customerId)return fail('Select a customer for Cylinder Return.');
   if(!returnLines.length)return fail('Select at least one customer custody cylinder to return.');
   const allowGas=<?=json_encode((int)($shopSettings['allow_return_gas_qty']??0))?>===1;
   for(const l of returnLines){if(!allowGas&&Number(l.return_gas_kg||0)>0)return fail('Return gas quantity is disabled in Shop Settings.');if(Number(l.return_gas_kg||0)>Number(l.issued_gas_weight_kg||0)+0.00001&&!<?=json_encode((int)($shopSettings['allow_return_gas_over_issued']??0))?>)return fail('Return gas exceeds issued gas for '+l.unit_code+'.');}
   const refund=Math.max(0,Number(document.getElementById('refundAmount').value||0));
   const depositBalance=Number(document.getElementById('refundAvailable').dataset.value||0);
   if(refund>depositBalance+0.01)return fail('Refund exceeds the customer refundable security deposit balance.');
   lines=returnLines.map(l=>({unit_id:l.unit_id,return_gas_kg:Number(l.return_gas_kg||0),return_gas_rate:Number(l.return_gas_rate||0)}));
 }else if(t==='gas_sale'){
   lines=[...tbody.querySelectorAll('tr')].map(tr=>({cylinder_type_id:tr.querySelector('.cyl').value,source_cylinder_unit_id:tr.querySelector('.sourceCyl').value,quantity:tr.querySelector('.qty').value,cylinder_quantity:tr.querySelector('.cylinderQty')?.value||'0',gas_entry_mode:document.getElementById('gasEntryMode').value,gas_rate:tr.querySelector('.gasRate').value,entered_amount:document.getElementById('gasEntryMode').value==='amount'?tr.querySelector('.enteredAmount').value:'',customer_cylinder_unit_id:tr.querySelector('.targetCyl').value}));
   if(!lines.length)return fail('Add at least one gas line.');
 }else if(t==='cylinder_sale'){
   lines=[...tbody.querySelectorAll('tr:not(.cylinderPickerRow)')].map(tr=>({cylinder_type_id:tr.querySelector('.cyl').value,cylinder_status:tr.querySelector('.cylStatus').value,quantity:tr.querySelector('.qty').value,gas_weight_kg:tr.querySelector('.gasQty').value,gas_rate:tr.querySelector('.gasRate').value,cylinder_rate:tr.querySelector('.cylRate').value,selected_cylinder_unit_ids:tr._selectedUnitIds||[]}));if(!lines.length)return fail('Add at least one cylinder sale line.');
 }
 let pays=[];
 if(t==='security_deposit'){
   const received=paymentTotal(),gasDue=Math.max(0,saleTotalForPayment()+Number(document.getElementById('previousOs').textContent||0)),depositDue=Number(document.getElementById('securityDeposit').value||0);
   if(received>gasDue+depositDue+0.01)return fail('Amount received cannot exceed the total amount due.');
   pays=[...payments.querySelectorAll('.payment')].map(p=>({payment_type:depositPaymentAllocationRule==='manual'?(p.querySelector('.paymentType')?.value||'sale'):'combined',payment_mode:p.querySelector('.mode').value,amount:p.querySelector('.amount').value,reference_no:(document.getElementById('paymentReferenceNote')?.value||'')}));
   if(depositPaymentAllocationRule==='manual'){
     const dp=pays.filter(p=>p.payment_type==='security_deposit').reduce((s,p)=>s+Number(p.amount||0),0),sp=pays.filter(p=>p.payment_type==='sale').reduce((s,p)=>s+Number(p.amount||0),0);
     if(sp>gasDue+0.01)return fail('Gas / Cylinder payment allocation cannot exceed Rs. '+gasDue.toFixed(2)+'.');
   }
 }else{
   pays=[...payments.querySelectorAll('.payment')].map(p=>({payment_type:p.querySelector('.paymentType')?.value||'sale',payment_mode:p.querySelector('.mode').value,amount:p.querySelector('.amount').value,reference_no:(document.getElementById('paymentReferenceNote')?.value||'')}));
 }
 document.getElementById('lines_json').value=JSON.stringify(lines);document.getElementById('payments_json').value=JSON.stringify(pays);
 const saleTotal=Number(document.getElementById('saleTotal').textContent||0),deposit=t==='security_deposit'?Number(document.getElementById('securityDeposit').value||0):0,refund=t==='cylinder_return'?Number(document.getElementById('refundAmount').value||0):0;
 if(t==='security_deposit'&&deposit<0)return fail('Security Deposit Amount cannot be negative.');
 if(t==='cylinder_return'&&refund>0){const refundTotal=paymentTotal('security_deposit_refund');if(!pays.length){addPayment('security_deposit_refund');return fail('Add a payment method for the security deposit refund.');}if(Math.abs(refundTotal-refund)>0.01)return fail('Refund payment total must equal the refund amount.');if(pays.some(p=>p.payment_type!=='security_deposit_refund'))return fail('Cylinder Return payments must be classified as Security Deposit Refund.');}
 if(t==='security_deposit'){const expectedGas=Math.max(0,saleTotal+Number(document.getElementById('previousOs').textContent||0)),totalDue=expectedGas+deposit,received=paymentTotal();if(received>totalDue+0.01)return fail('Amount Received cannot exceed the Total Due of Rs. '+totalDue.toFixed(2)+'.');if(!pays.length)return fail('Add a payment row or leave the default zero/blank payment row when the gas amount is going to customer credit.');if(depositPaymentAllocationRule==='manual'){const dp=pays.filter(p=>p.payment_type==='security_deposit').reduce((s,p)=>s+Number(p.amount||0),0),sp=pays.filter(p=>p.payment_type==='sale').reduce((s,p)=>s+Number(p.amount||0),0);if(sp>expectedGas+0.01)return fail('Gas / Cylinder allocation cannot exceed Rs. '+expectedGas.toFixed(2)+'.');}else if(pays.some(p=>p.payment_type!=='combined'))return fail('Invalid payment allocation.');}
 if(['gas_sale','cylinder_sale'].includes(t)){const expected=Math.max(0,saleTotal+Number(document.getElementById('previousOs').textContent||0));if(!pays.length)return fail('Add at least one payment.');if(paymentTotal()>expected+0.01)return fail('Payment cannot exceed the net receivable.');}
 document.getElementById('stock_override_confirmed').value='0';saveBtn.disabled=true;const old=saveBtn.textContent;saveBtn.textContent='Posting...';
 try{const response=await fetch(document.getElementById('saleForm').action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:new FormData(document.getElementById('saleForm'))});const raw=await response.text();let data={};try{data=raw?JSON.parse(raw):{};}catch(_e){}if(!response.ok||data.success!==true){const serverMessage=String(data.error||'').trim();if(serverMessage)throw new Error(serverMessage);if(raw&&raw.trim()&&!/^\s*</.test(raw))throw new Error(raw.trim().slice(0,500));throw new Error('Sale request failed (HTTP '+response.status+'). The server returned no readable JSON error. Check Railway deployment logs for the POS sale posting error at this time.');}
 if(data.receipt_url){
   window.lastPostedReceiptUrl=data.receipt_url;
   document.getElementById('posSuccessMessage').textContent='Transaction '+String(data.sale_no||'')+' posted successfully.';
   document.getElementById('posValidationAlert').classList.add('d-none');
   document.getElementById('posSuccessAlert').style.display='block';
   document.getElementById('posSuccessAlert').scrollIntoView({behavior:'smooth',block:'start'});
   saveBtn.disabled=true;
   saveBtn.textContent='Transaction Posted';
   // Reload POS data after posting to reset inputs and refresh dropdown options.
   window.location.assign(data.redirect || window.location.href);
   return false;
 }else{
   throw new Error('Transaction posted, but the receipt URL was not returned.');
 }}catch(err){saveBtn.disabled=false;saveBtn.textContent=old;return fail(err.message);}
 return false;
};
(() => {
  const tx = document.getElementById('transactionType');
  const allowed = [...tx.options].map(o => o.value);
  const initial = allowed.includes(defaultTransactionType) ? defaultTransactionType : (allowed[0] || 'gas_sale');
  tx.value = initial;
  addPayment();
  refreshCustomer();
  handleTransactionTypeChange();
})();
</script>
<script>
// Phase 11.4 counter ergonomics: customer quick search, keyboard shortcuts and an early
// overpayment warning. These only drive the existing controls; posting rules are unchanged.
(() => {
  const form = document.getElementById('saleForm');
  const select = document.getElementById('customer_id');
  const search = document.getElementById('customerSearch');
  const hint = document.getElementById('customerSearchHint');
  const saveBtn = document.getElementById('saveBtn');

  const focusFirstLineField = () => {
    const field = document.querySelector('#lines tbody tr:not(.cylinderPickerRow) .cyl');
    if (field && field.offsetParent !== null) field.focus();
  };

  // Customer quick search: filters the customer list by code, name or phone. Selecting a
  // customer resets the lines (existing behaviour), so only Enter applies the first match.
  if (search && select) {
    const apply = () => {
      const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      let count = 0, first = null;
      [...select.options].forEach(option => {
        if (option.value === '') { option.hidden = false; return; }
        const haystack = option.dataset.search || option.textContent.toLowerCase();
        const show = terms.every(term => haystack.includes(term));
        option.hidden = !show;
        if (show) { count++; first = first || option; }
      });
      search.classList.toggle('is-invalid', terms.length > 0 && count === 0);
      hint.textContent = !terms.length ? '' : (count ? count + ' customer(s) match. Press Enter for ' + first.textContent.trim() + '.' : 'No customer matches.');
      return first;
    };
    search.addEventListener('input', apply);
    search.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault(); // never submit the sale from the search box
        const first = apply();
        if (!first) return;
        if (select.value !== first.value) {
          select.value = first.value;
          select.dispatchEvent(new Event('change'));
        }
        search.value = '';
        apply();
        focusFirstLineField();
      } else if (e.key === 'Escape') {
        search.value = '';
        apply();
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        select.focus();
      }
    });
    select.addEventListener('change', () => { if (search.value) { search.value = ''; apply(); } });
  }

  // Keyboard shortcuts (ignored while a dialog or the receipt preview is open).
  document.addEventListener('keydown', e => {
    if (document.querySelector('.modal.show') || document.getElementById('receiptPreviewOverlay')?.classList.contains('is-visible')) return;
    const type = document.getElementById('transactionType').value;
    if (e.key === 'F2') {
      e.preventDefault();
      search?.focus(); search?.select();
    } else if (e.key === 'F4') {
      e.preventDefault();
      if (type === 'gas_sale' || type === 'cylinder_sale') {
        document.getElementById('addLine').click();
        const rows = document.querySelectorAll('#lines tbody tr:not(.cylinderPickerRow) .cyl');
        rows[rows.length - 1]?.focus();
      } else if (type === 'security_deposit') {
        document.getElementById('openIssuePicker').click();
      } else if (type === 'cylinder_return') {
        document.getElementById('openReturnPicker').click();
      }
    } else if (e.key === 'F7') {
      e.preventDefault();
      const amount = document.querySelector('#payments .payment .amount');
      amount?.focus(); amount?.select();
    } else if (e.key === 'F9' || (e.key === 'Enter' && (e.ctrlKey || e.metaKey))) {
      e.preventDefault();
      if (!saveBtn.disabled) form.requestSubmit(saveBtn);
    }
  });

  // Early warning: the same "payment cannot exceed the net receivable" rule the submit check
  // applies to gas and cylinder sales, shown while typing instead of only after Post.
  const payments = document.getElementById('payments');
  const warning = document.createElement('div');
  warning.className = 'small text-danger fw-semibold mb-2 d-none';
  warning.setAttribute('role', 'status');
  payments.after(warning);
  const checkPayments = () => {
    const type = document.getElementById('transactionType').value;
    if (!['gas_sale', 'cylinder_sale'].includes(type)) { warning.classList.add('d-none'); return; }
    const expected = Math.max(0, Number(document.getElementById('saleTotal').textContent || 0) + Number(document.getElementById('previousOs').textContent || 0));
    const paid = [...payments.querySelectorAll('.payment .amount')].reduce((sum, input) => sum + Number(input.value || 0), 0);
    const over = paid > expected + 0.01;
    warning.textContent = over ? 'Payment Rs. ' + paid.toFixed(2) + ' is more than the net receivable Rs. ' + expected.toFixed(2) + '.' : '';
    warning.classList.toggle('d-none', !over);
  };
  ['input', 'change'].forEach(evt => form.addEventListener(evt, () => setTimeout(checkPayments, 0)));
  form.addEventListener('click', () => setTimeout(checkPayments, 0));
})();
</script>
<?= $this->endSection() ?>