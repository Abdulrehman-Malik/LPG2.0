// Customers / Suppliers / Ledgers check.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/parties';
fs.mkdirSync(OUT, { recursive: true });
const MYSQL = `${process.env.QA_MYSQL || 'mysql'} -h ${process.env.QA_DB_HOST || '127.0.0.1'} -P ${process.env.QA_DB_PORT || '3306'} -u ${process.env.QA_DB_USER || 'root'}${process.env.QA_DB_PASS ? ' -p' + process.env.QA_DB_PASS : ''} -N ${process.env.QA_DB || 'lpg_qa'}`;
const sql = (q) => { for (let i = 0; i < 5; i++) { const r = execSync(`${MYSQL} -e "${q}"`, { shell: 'bash' }).toString().split(String.fromCharCode(13)).join('').trim(); if (r) return r; } return ''; };
const num = (s) => Number(String(s).replace('Rs.', '').replace(/[^0-9.\-]/g, ''));
let pass = 0, fail = 0;
const check = (c, m, d = '') => { if (c) { pass++; console.log('PASS  ' + m); } else { fail++; console.log('FAIL  ' + m + (d ? ' :: ' + d : '')); } };

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
const errors = [];
p.on('pageerror', (e) => errors.push(e.message));
p.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('debugbar')) errors.push(m.text()); });
await p.goto(B + '/login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

// Customers: OS column vs independent SQL (gas balance; deposit setting is off)
await p.goto(B + '/customers', { waitUntil: 'networkidle' });
const expected = Object.fromEntries(sql(`SELECT c.code, c.opening_balance + COALESCE((SELECT SUM(credit_amount)+SUM(return_gas_ledger_amount) FROM sales s WHERE s.customer_id=c.id AND s.status='posted' AND s.location_id=1),0) - COALESCE((SELECT SUM(amount) FROM customer_receipts r WHERE r.customer_id=c.id AND r.status='posted' AND r.location_id=1),0) FROM customers c WHERE c.location_id=1`).split('\n').map((l) => l.split('\t')).map(([k, v]) => [k, Number(v)]));
const shown = await p.$$eval('table.datatable tbody tr', (rows) => rows.map((r) => [r.cells[0].textContent.trim(), r.cells[7].textContent.trim()]));
for (const [code, os] of shown) check(Math.abs(Number(os.replace(/,/g, '')) - (expected[code] ?? NaN)) < 0.005, `customer ${code} OS ${os} matches SQL`, String(expected[code]));
const kpiOs = num(await p.locator('.kpi', { hasText: 'Outstanding' }).locator('.kpi-value').innerText());
check(Math.abs(kpiOs - Object.values(expected).filter((v) => v > 0.005).reduce((a, b) => a + b, 0)) < 0.005, 'Outstanding KPI equals sum of positive balances', String(kpiOs));
await p.screenshot({ path: `${OUT}/customers.png` });

// Failed save keeps input and reopens the form
await p.click('button[data-bs-target="#form"]');
await p.fill('#cName', 'QA Keep Input');
await p.fill('#cPhone', '0300-1234567');
await p.fill('#cLimit', '-5');
await p.evaluate(() => document.getElementById('cLimit').removeAttribute('min'));
await Promise.all([p.waitForNavigation(), p.click('#form button.btn-primary')]);
await p.waitForSelector('#form.show');
check(/cannot be negative/.test(await p.locator('.alert-danger').innerText()), 'negative credit limit refused');
check(await p.inputValue('#cName') === 'QA Keep Input' && await p.inputValue('#cPhone') === '0300-1234567', 'form reopened with the typed values');
await p.fill('#cLimit', '1000');
await Promise.all([p.waitForNavigation(), p.click('#form button.btn-primary')]);
check(/Customer created/.test(await p.locator('.alert-success').innerText()), 'customer saved after correction');
check(sql("SELECT CONCAT(phone,'|',credit_limit) FROM customers WHERE name='QA Keep Input'") === '0300-1234567|1000.00', 'saved values correct');

// Every customer ledger: statement closing balance = current balance (no warning)
for (const id of sql('SELECT id FROM customers WHERE location_id=1').split('\n')) {
  await p.goto(B + '/customers/ledger/' + id, { waitUntil: 'networkidle' });
  const warn = await p.locator('#customerStatement .alert-warning').count();
  const closing = num(await p.locator('#customerStatement tfoot td').last().innerText());
  check(warn === 0, `customer ${id} statement closing ${closing} matches balance`);
}
await p.goto(B + '/customers/ledger/' + sql("SELECT id FROM customers WHERE code='QA-BAL-A'"), { waitUntil: 'networkidle' });
check(num(await p.locator('#customerStatement tfoot td').last().innerText()) === 100.5, 'QA-BAL-A closing 100.50 (150.50 − 50; voided receipt ignored)');
await p.screenshot({ path: `${OUT}/customer-ledger.png` });

// Suppliers: payable column vs SQL
await p.goto(B + '/suppliers', { waitUntil: 'networkidle' });
const pay = Object.fromEntries((await p.$$eval('table.datatable tbody tr', (rows) => rows.map((r) => [r.cells[0].textContent.trim(), r.cells[7].textContent.trim()]))).map(([k, v]) => [k, Number(v.replace(/,/g, ''))]));
check(pay.QS1 === 2800 && pay.QB1 === 400, 'supplier payables QS1 2800, QB1 400', JSON.stringify(pay));
await p.screenshot({ path: `${OUT}/suppliers.png` });
for (const id of sql('SELECT id FROM suppliers').split('\n')) {
  const r = await p.goto(B + '/suppliers/ledger/' + id, { waitUntil: 'networkidle' });
  if (r.status() !== 200) continue;
  check(await p.locator('#supplierStatement .alert-warning').count() === 0, `supplier ${id} statement matches payable`);
}
await p.goto(B + '/suppliers/ledger/1', { waitUntil: 'networkidle' });
check(/recorded at this branch/.test(await p.locator('.kpi', { hasText: 'Opening balance' }).innerText()), 'separate balances: opening counted at its own branch only (D9-4)');
await p.screenshot({ path: `${OUT}/supplier-ledger.png` });

// D9-4 single balance for the business: QS1 = 1000 opening + 3000 (MAIN) + 2000 (QA-B2) − 1200 = 4800;
// the branch-only supplier QB1 keeps its own balance (400).
sql(`INSERT INTO business_settings(business_id,setting_key,setting_value) VALUES (1,'supplier_balance_mode',JSON_QUOTE('business')) ON DUPLICATE KEY UPDATE setting_value=JSON_QUOTE('business')`);
await p.goto(B + '/suppliers', { waitUntil: 'networkidle' });
const pooled = Object.fromEntries((await p.$$eval('table.datatable tbody tr', (rows) => rows.map((r) => [r.cells[0].textContent.trim(), r.cells[7].textContent.trim()]))).map(([k, v]) => [k, Number(v.replace(/,/g, ''))]));
check(pooled.QS1 === 4800 && pooled.QB1 === 400, 'single business balance: QS1 4800 (all branches), QB1 400', JSON.stringify(pooled));
await p.goto(B + '/suppliers/ledger/1', { waitUntil: 'networkidle' });
check(await p.locator('#supplierStatement .alert-warning').count() === 0, 'single balance: statement matches payable');
check(/QA Branch Two/.test(await p.locator('#supplierStatement').innerText()), 'single balance: statement lists the other branch purchase');
check(num(await p.locator('#supplierStatement tfoot td').last().innerText()) === 4800, 'single balance: statement closes at 4800');
await p.screenshot({ path: `${OUT}/supplier-ledger-business.png` });
sql(`DELETE FROM business_settings WHERE business_id=1 AND setting_key='supplier_balance_mode'`);

// Supplier failed save keeps input
await p.goto(B + '/suppliers', { waitUntil: 'networkidle' });
await p.click('button[data-bs-target="#form"]');
await p.fill('#sName', 'QA Supplier Keep');
await p.evaluate(() => { const i = document.getElementById('sOpening'); i.removeAttribute('min'); i.value = '-1'; });
await Promise.all([p.waitForNavigation(), p.click('#form button.btn-primary')]);
await p.waitForSelector('#form.show');
check(await p.inputValue('#sName') === 'QA Supplier Keep', 'supplier form reopened with typed name');

// Dark + phone
await p.evaluate(() => localStorage.setItem('lpgTheme', 'dark'));
await p.goto(B + '/customers/ledger/' + sql("SELECT id FROM customers WHERE code='QA-BAL-A'"), { waitUntil: 'networkidle' });
await p.screenshot({ path: `${OUT}/ledger-dark.png` });
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
await p.setViewportSize({ width: 390, height: 844 });
for (const path of ['/customers', '/suppliers', '/customers/ledger/1', '/suppliers/ledger/1']) {
  await p.goto(B + path, { waitUntil: 'networkidle' });
  check((await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)) <= 2, 'no horizontal scroll at 390 px ' + path);
}
check(errors.length === 0, 'no console/page errors', errors.join(' | '));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
