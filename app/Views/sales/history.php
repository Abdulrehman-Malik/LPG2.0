<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.history-scroll { max-height: 68vh; overflow: auto; }
.history-scroll thead th { position: sticky; top: 0; z-index: 2; }
.sale-history-row { cursor: pointer; }
.sale-history-row td { white-space: nowrap; }
.sale-history-row.is-voided td { color: var(--lpg-muted); }
.sale-history-row.is-voided td:first-child { text-decoration: line-through; }
.history-totals { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; font-size: .88rem; color: var(--lpg-muted); }
.history-totals strong { color: var(--lpg-text); font-variant-numeric: tabular-nums; }
.history-presets .btn { --bs-btn-padding-y: .25rem; --bs-btn-font-size: .82rem; }
.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem; margin-bottom: 1rem; }
.detail-grid > div { border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); padding: .55rem .7rem; background: var(--lpg-surface-2); }
.detail-grid > div > span:first-child { display: block; font-size: .75rem; color: var(--lpg-muted); }
.detail-grid strong { font-variant-numeric: tabular-nums; }
.detail-section-title { font-size: .95rem; font-weight: 650; margin: 1rem 0 .4rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sale History</h1>
    <p class="page-subtitle">Search, review, print and void posted sales. Voided sales stay on record with their reversal.</p>
  </div>
  <div class="page-actions">
    <a href="<?= site_url('sales') ?>" class="btn btn-primary"><i class="bi bi-cart-plus me-1"></i>New Sale</a>
  </div>
</div>

<div id="saleHistoryPanel">
  <div class="card mb-3"><div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2"><label class="form-label" for="historyFrom">From</label><input type="date" id="historyFrom" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="historyTo">To</label><input type="date" id="historyTo" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="historySaleNo">Sale No.</label><input type="text" id="historySaleNo" class="form-control" placeholder="Sale number"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="historyCustomer">Customer</label><input type="text" id="historyCustomer" class="form-control" placeholder="Name / code"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="historyType">Transaction Type</label><select id="historyType" class="form-select"><option value="">All Types</option><option value="gas_sale">Gas Sale</option><option value="cylinder_sale">Cylinder Sale</option><option value="security_deposit">Security Deposit</option><option value="cylinder_return">Cylinder Return</option><option value="refill_service">Refill Service</option><option value="cylinder_exchange">Cylinder Exchange</option><option value="filled_cylinder">Filled Cylinder</option><option value="empty_sale">Empty Cylinder</option><option value="mixed">Mixed</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label" for="historyStatus">Status</label><select id="historyStatus" class="form-select"><option value="">All</option><option value="posted">Posted</option><option value="voided">Void</option></select></div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary" id="historySearch"><i class="bi bi-search me-1"></i>Search</button>
        <div class="btn-group history-presets" role="group" aria-label="Date range">
          <button type="button" class="btn btn-outline-secondary" data-preset="today">Today</button>
          <button type="button" class="btn btn-outline-secondary" data-preset="yesterday">Yesterday</button>
          <button type="button" class="btn btn-outline-secondary" data-preset="week">Last 7 days</button>
          <button type="button" class="btn btn-outline-secondary" data-preset="month">This month</button>
          <button type="button" class="btn btn-outline-secondary" id="historyAll">All History</button>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="historyExport"><i class="bi bi-download me-1"></i>Export CSV</button>
    </div>
  </div></div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span id="historyCount" aria-live="polite"></span>
      <div class="history-totals" id="historyTotals"></div>
    </div>
    <div class="table-responsive history-scroll">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead><tr>
          <th>Sale No.</th><th>Date / Time</th><th>Customer</th><th>Type</th><th class="text-end">Subtotal</th><th class="text-end">Discount</th><th class="text-end">Current Sale</th><th class="text-end">Previous OS</th><th class="text-end">Receipt</th><th class="text-end">Net Receivable</th><th class="text-end">OS Balance</th><th>Status</th><th>User</th><th class="text-end">Action</th>
        </tr></thead>
        <tbody id="saleHistoryBody"><tr><td colspan="14" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-labelledby="saleDetailsTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header gap-2">
      <h2 class="modal-title fs-5 me-auto" id="saleDetailsTitle">Sale Details</h2>
      <div class="d-flex gap-2" id="saleDetailsActions"></div>
      <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body" id="saleDetailsBody"></div>
  </div></div>
</div>

<?php if ($canVoidSales ?? false): ?>
<div class="modal fade" id="voidSaleModal" tabindex="-1" aria-labelledby="voidSaleTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" id="voidSaleForm">
    <?= csrf_field() ?>
    <div class="modal-header"><h2 class="modal-title fs-5" id="voidSaleTitle">Void sale <span id="voidSaleNo"></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="alert alert-warning small mb-3">Stock, cash and the customer ledger are reversed. The sale stays on record marked VOID; it is not deleted.</div>
      <label class="form-label required" for="voidReason">Reason</label>
      <textarea class="form-control" id="voidReason" name="void_reason" rows="3" maxlength="500" required placeholder="e.g. Wrong customer selected"></textarea>
      <div class="invalid-feedback">Enter the reason for voiding this sale.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep sale</button><button type="submit" class="btn btn-danger">Void sale</button></div>
  </form></div>
</div>
<?php endif; ?>

<script>
const canVoidSales = <?= json_encode($canVoidSales ?? false) ?>;
const historyBody = document.getElementById('saleHistoryBody');
const receiptBase = <?= json_encode(site_url('sales/receipt')) ?>;
const voidBase = <?= json_encode(site_url('sales/void')) ?>;
function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
const money = v => Number(v || 0).toFixed(2);
const qty = v => Number(v || 0).toFixed(3);
const localDate = (d) => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
const historyTypeLabel=t=>({gas_sale:'Gas Sale',cylinder_sale:'Cylinder Sale',security_deposit:'Security Deposit',cylinder_return:'Cylinder Return',refill_service:'Refill Service',cylinder_exchange:'Cylinder Exchange',filled_cylinder:'Filled Cylinder',empty_sale:'Empty Cylinder',mixed:'Mixed'}[t]||t||'-');
const received = s => Number(s.receipt_amount ?? (Number(s.total_amount || 0) - Number(s.credit_amount || 0)));

function historyQuery(){const p=new URLSearchParams({from:document.getElementById('historyFrom').value,to:document.getElementById('historyTo').value,sale_no:document.getElementById('historySaleNo').value,customer:document.getElementById('historyCustomer').value,transaction_type:document.getElementById('historyType').value,status:document.getElementById('historyStatus').value});return p.toString();}
function csvCell(v){const s=String(v??'');return '"'+s.replace(/"/g,'""')+'"';}
function exportSaleHistory(){
  const rows=[...historyBody.querySelectorAll('tr.sale-history-row')];
  if(!rows.length){window.LPG?.toast('There are no sales to export for the current filters.','warning');return;}
  const headers=['Sale No.','Date / Time','Customer','Type','Subtotal','Discount','Current Sale','Previous OS','Receipt','Net Receivable','OS Balance','Status','User'];
  const data=rows.map(row=>[...row.querySelectorAll('td')].slice(0,13).map(td=>td.innerText.replace(/\s+/g,' ').trim()));
  const csv=[headers,...data].map(r=>r.map(csvCell).join(',')).join('\r\n');
  const blob=new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);const a=document.createElement('a');
  a.href=url;a.download='sale-history-'+(document.getElementById('historyFrom').value||'all')+'-to-'+(document.getElementById('historyTo').value||'all')+'.csv';
  document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(url);
}

function statusBadge(s){return s.status==='voided'?'<span class="status status-danger">VOID</span>':'<span class="status status-success">POSTED</span>';}
function renderRow(s){
  const action=canVoidSales&&s.status==='posted'?'<button type="button" class="btn btn-sm btn-outline-danger void-sale" data-id="'+s.id+'" data-no="'+escapeHtml(s.sale_no)+'"><i class="bi bi-x-circle me-1"></i>Void</button>':'';
  return '<tr class="sale-history-row'+(s.status==='voided'?' is-voided':'')+'" data-id="'+s.id+'" tabindex="0" aria-label="Open sale '+escapeHtml(s.sale_no)+'">'
    +'<td class="fw-semibold">'+escapeHtml(s.sale_no)+'</td><td class="text-nowrap">'+escapeHtml(s.transaction_at||'')+'</td><td>'+escapeHtml(s.customer_name||'Walk-in / Cash')+'</td><td>'+escapeHtml(historyTypeLabel(s.transaction_type))+'</td>'
    +'<td class="text-end">'+money(s.subtotal)+'</td><td class="text-end">'+money(s.discount_amount)+'</td><td class="text-end fw-semibold">'+money(s.total_amount)+'</td><td class="text-end">'+money(s.previous_os_balance)+'</td>'
    +'<td class="text-end">'+money(received(s))+'</td><td class="text-end">'+money(s.net_receivable_amount??s.total_amount)+'</td><td class="text-end">'+money(s.os_balance??s.credit_amount)+'</td>'
    +'<td>'+statusBadge(s)+'</td><td>'+escapeHtml(s.created_by_name||'-')+'</td><td class="text-end">'+action+'</td></tr>';
}
async function loadSaleHistory(){
  historyBody.innerHTML='<tr><td colspan="14" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…</td></tr>';
  document.getElementById('historyTotals').innerHTML='';
  try{
    const res=await fetch(<?= json_encode(site_url('sales/history/data')) ?>+'?'+historyQuery(),{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!res.ok)throw new Error(d.error||'Unable to load sale history.');
    const rows=d.sales||[];
    document.getElementById('historyCount').textContent=rows.length+' sale(s)';
    if(!rows.length){historyBody.innerHTML='<tr><td colspan="14"><div class="empty-state"><i class="bi bi-receipt"></i><div class="empty-state-title">No sales found</div>Change the dates or filters, or use All History.</div></td></tr>';return;}
    historyBody.innerHTML=rows.map(renderRow).join('');
    const posted=rows.filter(s=>s.status==='posted');
    const voided=rows.length-posted.length;
    document.getElementById('historyTotals').innerHTML='<span>Posted sales <strong>Rs. '+money(posted.reduce((t,s)=>t+Number(s.total_amount||0),0))+'</strong></span><span>Received <strong>Rs. '+money(posted.reduce((t,s)=>t+received(s),0))+'</strong></span>'+(voided?'<span>Voided <strong>'+voided+'</strong></span>':'');
    historyBody.querySelectorAll('.sale-history-row').forEach(row=>{
      row.addEventListener('click',e=>{if(!e.target.closest('.void-sale'))showSaleDetails(Number(row.dataset.id));});
      row.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target===row)showSaleDetails(Number(row.dataset.id));});
    });
    historyBody.querySelectorAll('.void-sale').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();voidSale(Number(b.dataset.id),b.dataset.no);}));
  }catch(e){historyBody.innerHTML='<tr><td colspan="14" class="text-center text-danger py-4">'+escapeHtml(e.message)+'</td></tr>';}
}

function table(headers, rows){
  return '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr>'+headers.map(h=>'<th'+(h.endsWith(' ')?' class="text-end"':'')+'>'+h.trim()+'</th>').join('')+'</tr></thead><tbody>'
    +(rows||'<tr><td colspan="'+headers.length+'" class="text-muted text-center py-3">None</td></tr>')+'</tbody></table></div>';
}
async function showSaleDetails(id){
  const body=document.getElementById('saleDetailsBody'),actions=document.getElementById('saleDetailsActions');
  body.innerHTML='<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…</div>';actions.innerHTML='';
  document.getElementById('saleDetailsTitle').textContent='Sale Details';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('saleDetailsModal')).show();
  try{
    const res=await fetch(<?= json_encode(site_url('sales/details')) ?>+'/'+id,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!res.ok)throw new Error(d.error||'Unable to load sale details.');
    const s=d.sale,ms=d.movements||[];
    document.getElementById('saleDetailsTitle').textContent='Sale '+s.sale_no;
    actions.innerHTML='<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'+receiptBase+'/'+s.id+'"><i class="bi bi-printer me-1"></i>Receipt 80 mm</a><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'+receiptBase+'/'+s.id+'?format=a4">A4</a>';
    const items=(d.items||[]).map(i=>{
      const src=ms.find(m=>Number(m.source_line_id)===Number(i.id)&&m.inventory_type==='gas_kg'&&m.direction==='out');
      const rateLabel=s.transaction_type==='cylinder_sale'?(i.line_type==='filled_cylinder'?'Gas '+money(i.gas_rate)+' / Cyl '+money(i.cylinder_price):'Cyl '+money(i.cylinder_price)):money(i.applied_rate);
      return '<tr><td>'+i.line_no+'</td><td>'+escapeHtml(i.cylinder_code||'-')+' — '+escapeHtml(i.cylinder_name||'-')+'</td><td>'+escapeHtml(i.line_type||'-')+'</td><td>'+escapeHtml(src?.unit_code||i.customer_unit_code||'-')+'</td><td class="text-end">'+qty(i.quantity)+'</td><td class="text-end">'+qty(i.gas_weight_kg)+'</td><td class="text-end">'+rateLabel+'</td><td class="text-end fw-semibold">'+money(i.line_total)+'</td></tr>';
    }).join('');
    const paymentTypeLabel=t=>({sale:'Sale',security_deposit:'Security Deposit',security_deposit_refund:'Security Deposit Refund'}[t]||t||'-');
    const pays=(d.payments||[]).map(p=>'<tr><td>'+escapeHtml(paymentTypeLabel(p.payment_type))+'</td><td>'+escapeHtml(p.payment_mode||'')+'</td><td class="text-end">'+money(p.amount)+'</td><td>'+escapeHtml(p.reference_no||'-')+'</td></tr>').join('');
    const movs=ms.map(m=>'<tr><td>'+escapeHtml(m.unit_code||'-')+'</td><td>'+escapeHtml(m.inventory_type||'')+'</td><td>'+escapeHtml(m.direction||'')+'</td><td class="text-end">'+qty(m.quantity)+'</td><td>'+escapeHtml(m.source_type||'')+'</td><td>'+escapeHtml(m.notes||'')+'</td></tr>').join('');
    const voidInfo=s.status==='voided'?'<div class="alert alert-danger"><strong>VOID</strong> — '+escapeHtml(s.voided_at||'')+' by '+escapeHtml(s.voided_by_name||'-')+'<br>Reason: '+escapeHtml(s.void_reason||'-')+'</div>':'';
    const cell=(label,value)=>'<div><span>'+label+'</span><strong>'+value+'</strong></div>';
    body.innerHTML=voidInfo
      +'<div class="detail-grid">'+cell('Sale No.',escapeHtml(s.sale_no))+cell('Date / Time',escapeHtml(s.transaction_at))+cell('Customer',escapeHtml(s.customer_name||'Walk-in / Cash'))+cell('Type',escapeHtml(historyTypeLabel(s.transaction_type)))+cell('Status',statusBadge(s))+'</div>'
      +'<div class="detail-grid">'+cell('Previous OS Balance',money(s.previous_os_balance))+cell('Subtotal',money(s.subtotal))+cell('Discount',money(s.discount_amount))+cell('Current Sale',money(s.total_amount))+cell('Net Receivable',money(s.net_receivable_amount??s.total_amount))+cell('Receipt Amount',money(received(s)))+cell('OS Balance',money(s.os_balance??s.credit_amount))+cell('Credit / OS (legacy)',money(s.credit_amount))+'</div>'
      +'<h3 class="detail-section-title">Sale Lines</h3>'+table(['#','Cylinder Type','Line Type','Physical / Customer Cylinder','Qty ','Gas KG ','Rate ','Amount '],items)
      +'<h3 class="detail-section-title">Payments</h3>'+table(['Payment For','Mode','Amount ','Reference'],pays)
      +'<h3 class="detail-section-title">Inventory / Reversal Movements</h3>'+table(['Physical Cylinder','Inventory','Direction','Qty ','Source','Notes'],movs);
  }catch(e){body.innerHTML='<div class="alert alert-danger">'+escapeHtml(e.message)+'</div>';}
}

function voidSale(id,no){
  if(!canVoidSales)return;
  const form=document.getElementById('voidSaleForm');
  form.action=voidBase+'/'+id;
  document.getElementById('voidSaleNo').textContent=no;
  const reason=document.getElementById('voidReason');
  reason.value='';reason.classList.remove('is-invalid');
  const modalEl=document.getElementById('voidSaleModal');
  modalEl.addEventListener('shown.bs.modal',()=>reason.focus(),{once:true});
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}
document.getElementById('voidSaleForm')?.addEventListener('submit',e=>{
  const reason=document.getElementById('voidReason');
  reason.value=reason.value.trim();
  if(!reason.value){e.preventDefault();reason.classList.add('is-invalid');reason.focus();}
});

// Filters: Enter in any field searches; date presets fill From/To and search.
document.querySelectorAll('#saleHistoryPanel .card-body input, #saleHistoryPanel .card-body select').forEach(el=>el.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();loadSaleHistory();}}));
document.querySelectorAll('[data-preset]').forEach(btn=>btn.addEventListener('click',()=>{
  const now=new Date(),from=new Date(now),to=new Date(now);
  if(btn.dataset.preset==='yesterday'){from.setDate(now.getDate()-1);to.setDate(now.getDate()-1);}
  else if(btn.dataset.preset==='week'){from.setDate(now.getDate()-6);}
  else if(btn.dataset.preset==='month'){from.setDate(1);}
  document.getElementById('historyFrom').value=localDate(from);document.getElementById('historyTo').value=localDate(to);loadSaleHistory();
}));
document.getElementById('historySearch').onclick=loadSaleHistory;
document.getElementById('historyAll').onclick=()=>{document.getElementById('historyFrom').value='2000-01-01';document.getElementById('historyTo').value=localDate(new Date());loadSaleHistory();};
document.getElementById('historyExport').onclick=exportSaleHistory;
loadSaleHistory();
</script>
<?= $this->endSection() ?>
