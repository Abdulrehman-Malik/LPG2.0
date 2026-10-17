// Rates and customer pricing on screen: Rates page, and the POS defaults for Issue Cylinder and
// Cylinder Sale (filled cylinder rate, pro-rata, customer rates). Run after verify_rates.sh, which
// leaves: C6 filled rate 900 (6 KG), QR-KG special 120 / KG, QR-FILL empty cylinder price 200.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/rates';
fs.mkdirSync(OUT, { recursive: true });
let pass = 0, fail = 0;
const check = (c, m, d = '') => { c ? pass++ : fail++; console.log((c ? 'PASS  ' : 'FAIL  ') + m + (c || !d ? '' : ' :: ' + d)); };

const browser = await chromium.launch();
const p = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
const errors = [];
p.on('pageerror', (e) => errors.push(e.message));
p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await p.goto(B + '/login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

await p.goto(B + '/rates', { waitUntil: 'networkidle' });
const c6 = (await p.locator('tr', { hasText: 'C6 —' }).first().innerText()).replace(/\s+/g, ' ');
check(/Rs\. 900\.00/.test(c6) && /Rs\. 250\.00/.test(c6), 'Rates page: C6 full cylinder Rs. 900, empty cylinder Rs. 250', c6);
check(/from the rate per KG/.test((await p.locator('tr', { hasText: 'C11_8' }).first().innerText())), 'types without a filled rate say they come from the rate per KG');
await p.screenshot({ path: `${OUT}/rates.png`, fullPage: true });
await p.goto(B + '/rates?tab=customers', { waitUntil: 'networkidle' });
check(await p.locator('#customerRates.active tr', { hasText: 'Rates Special KG' }).count() > 0, 'Customer rates tab opens from ?tab=customers and lists special rates');
await p.screenshot({ path: `${OUT}/customer-rates.png`, fullPage: true });

await p.goto(B + '/sales', { waitUntil: 'networkidle' });
const pick = async (code) => p.evaluate((code) => {
  const s = document.getElementById('customer_id');
  const o = [...s.options].find((x) => x.textContent.trim().startsWith(code));
  s.value = o.value; s.dispatchEvent(new Event('change'));
}, code);
const c6id = await p.evaluate(() => String(types.find((t) => t.code === 'C6').id));
const issueFor = async (code, gas) => {
  // Changing the transaction type clears the customer (existing POS rule), so choose the type first.
  await p.selectOption('#transactionType', 'security_deposit');
  await pick(code);
  return p.evaluate(([typeId, gas]) => {
    const d = issueRateDefaults(typeId);
    issueLines.length = 0;
    issueLines.push({ combo: typeId + '|filled', type_id: Number(typeId), type_label: 'C6', status: 'filled', selected_cylinder_unit_ids: gas.map((_, i) => 900000 + i), unit_gas: gas, gas_weight_kg: gas.reduce((a, b) => a + b, 0), gas_rate: d.gas, cylinder_rate: d.cyl });
    renderIssueLines(); recalc();
    return { input: document.querySelector('.issueGasRateInput')?.value, total: document.getElementById('saleTotal').textContent.trim(), cylCols: document.querySelectorAll('#issueLinesBody tr:first-child td').length };
  }, [c6id, gas]);
};
let r = await issueFor('QR-STD', [6]);
check(r.input === '900.00' && r.total === '900.00', 'Issue Cylinder: rate per full cylinder 900, total 900 (standard customer)', JSON.stringify(r));
check(r.cylCols === 6, 'no cylinder price column while the business charges gas only', JSON.stringify(r));
r = await issueFor('QR-STD', [6, 4.7]);
check(r.total === '1605.00', 'one full + one 4.7 KG cylinder: 900 + 705 = 1,605', JSON.stringify(r));
r = await issueFor('QR-KG', [6]);
check(r.input === '720.00' && r.total === '720.00', 'customer with special 120 / KG: 720 per cylinder', JSON.stringify(r));
await p.fill('.issueGasRateInput', '870');
r = await p.evaluate(() => ({ total: document.getElementById('saleTotal').textContent.trim(), line: document.querySelector('.issueAmount').textContent.trim(), perKg: issueLines[0].gas_rate }));
check(r.total === '870.00' && r.line === '870.00' && Math.abs(r.perKg - 145) < 1e-9, 'cashier edits the rate per cylinder to 870: total 870, posts 145 / KG', JSON.stringify(r));
await p.screenshot({ path: `${OUT}/pos-issue.png`, fullPage: false });

await p.selectOption('#transactionType', 'cylinder_sale');
await pick('QR-FILL');
const head = await p.locator('#lines thead').innerText();
check(/Gas rate \/ cylinder/i.test(head), 'Cylinder Sale shows "Gas rate / cylinder"', head);
const cs = await p.evaluate((typeId) => {
  const tr = document.querySelector('#lines tbody tr'); const sel = tr.querySelector('.cyl'); sel.value = typeId; sel.dispatchEvent(new Event('change'));
  const st = tr.querySelector('.cylStatus'); st.value = 'filled'; st.dispatchEvent(new Event('change'));
  return { gas: tr.querySelector('.gasRate').value, cyl: tr.querySelector('.cylRate').value };
}, c6id);
check(cs.gas === '900.00' && cs.cyl === '200.00', 'Cylinder Sale defaults: gas 900 per cylinder, customer empty cylinder price 200', JSON.stringify(cs));

check(errors.length === 0, 'no browser errors', errors.join(' | '));
await browser.close();
console.log(`\n${pass} passed, ${fail} failed`);
