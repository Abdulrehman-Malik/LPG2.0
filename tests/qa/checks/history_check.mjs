// Phase 11 Sale History check: post two sales, search, details + receipt links, void dialog.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/history';
fs.mkdirSync(OUT, { recursive: true });
const MYSQL = `${process.env.QA_MYSQL || 'mysql'} -h ${process.env.QA_DB_HOST || '127.0.0.1'} -P ${process.env.QA_DB_PORT || '3306'} -u ${process.env.QA_DB_USER || 'root'}${process.env.QA_DB_PASS ? ' -p' + process.env.QA_DB_PASS : ''} -N ${process.env.QA_DB || 'lpg_qa'}`;
const sql = (q) => { for (let i = 0; i < 5; i++) { const r = execSync(`${MYSQL} -e "${q}"`, { shell: 'bash' }).toString().split(String.fromCharCode(13)).join('').trim(); if (r) return r; } return ''; };
let pass = 0, fail = 0;
const check = (c, m, d = '') => { if (c) { pass++; console.log('PASS  ' + m); } else { fail++; console.log('FAIL  ' + m + (d ? ' :: ' + d : '')); } };

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
const errors = [];
p.on('pageerror', (e) => errors.push(e.message));
p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await p.goto(B + '/login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

// Post two cash sales through the POS (1 kg each).
async function sell() {
  await p.goto(B + '/sales', { waitUntil: 'networkidle' });
  const line = p.locator('#lines tbody tr').first();
  const c6 = await line.locator('.cyl option').evaluateAll((os) => os.find((o) => o.textContent.trim().startsWith('C6'))?.value || '');
  await line.locator('.cyl').selectOption(c6);
  if (await line.locator('.sourceCyl').isVisible()) {
    const src = await line.locator('.sourceCyl option').evaluateAll((os) => os.find((o) => o.textContent.trim().startsWith('QA-E2E-C6-001'))?.value || '');
    await line.locator('.sourceCyl').selectOption(src);
  }
  await line.locator('.entryValue').fill('1');
  await p.waitForTimeout(150);
  await p.locator('#payments .payment .amount').first().fill(await p.locator('#saleTotal').innerText());
  await Promise.all([p.waitForResponse((r) => r.url().endsWith('/sales/save')), p.keyboard.press('F9')]);
  await p.waitForEvent('load');
  await p.waitForLoadState('networkidle');
}
await sell(); await sell();
const sales = sql("SELECT CONCAT(id,':',sale_no) FROM sales ORDER BY id").split('\n');
check(sales.length === 2, 'two sales posted', sales.join(','));
const [id2, no2] = sales[1].split(':');

await p.goto(B + '/sales/history', { waitUntil: 'networkidle' });
await p.waitForSelector('tr.sale-history-row');
check((await p.locator('tr.sale-history-row').count()) === 2, 'history lists both sales');
check(/Posted sales Rs\. 200\.00/.test(await p.locator('#historyTotals').innerText()), 'totals line shows Rs. 200 posted', await p.locator('#historyTotals').innerText());
check((await p.locator('.void-sale').first().innerText()).includes('Void'), 'void button is labelled Void (not Delete)');
await p.screenshot({ path: `${OUT}/list.png` });

// Enter in Sale No. searches
await p.fill('#historySaleNo', no2);
await p.press('#historySaleNo', 'Enter');
await p.waitForFunction(() => document.querySelectorAll('tr.sale-history-row').length === 1, null, { timeout: 8000 }).catch(() => {});
check((await p.locator('tr.sale-history-row').count()) === 1, 'Enter in Sale No. filters to one sale');

// Details modal + receipt links
await p.locator('tr.sale-history-row').first().click();
await p.waitForSelector('#saleDetailsBody .detail-section-title');
const detailText = await p.locator('#saleDetailsBody').innerText();
check(/Inventory \/ Reversal Movements/.test(detailText) && /gas_kg/.test(detailText), 'details show lines, payments and movements');
const links = await p.$$eval('#saleDetailsActions a', (as) => as.map((a) => a.getAttribute('href')));
check(links.length === 2 && links[0].endsWith('/sales/receipt/' + id2) && links[1].endsWith('?format=a4'), 'receipt links 80 mm and A4', JSON.stringify(links));
await p.screenshot({ path: `${OUT}/details.png` });
await p.click('#saleDetailsModal .btn-close');
await p.waitForTimeout(400);

// Void with empty reason is blocked; with reason it voids and returns to history
await p.locator('.void-sale').first().click();
await p.waitForSelector('#voidSaleModal.show');
await p.click('#voidSaleForm button[type=submit]');
await p.waitForTimeout(300);
check(await p.locator('#voidSaleModal.show').isVisible(), 'empty reason keeps the dialog open');
check(sql(`SELECT status FROM sales WHERE id=${id2}`) === 'posted', 'nothing voided without a reason');
await p.screenshot({ path: `${OUT}/void-dialog.png` });
await p.fill('#voidReason', 'QA history void dialog');
await Promise.all([p.waitForNavigation(), p.click('#voidSaleForm button[type=submit]')]);
check(p.url().endsWith('/sales/history'), 'void returns to Sale History', p.url());
check(sql(`SELECT CONCAT(status,'|',void_reason) FROM sales WHERE id=${id2}`) === 'voided|QA history void dialog', 'sale voided with reason');
check(/voided successfully/.test(await p.locator('.alert-success').innerText()), 'success message shown');
await p.waitForSelector('tr.sale-history-row.is-voided');
check(/VOID/.test(await p.locator('tr.sale-history-row.is-voided').innerText()), 'voided row shows VOID');

// Presets, dark, phone
await p.click('[data-preset="week"]');
await p.waitForTimeout(500);
const from = await p.inputValue('#historyFrom');
const d = new Date(); d.setDate(d.getDate() - 6);
const localIso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
check(from === localIso, 'Last 7 days preset sets From', from + ' vs ' + localIso);
await p.evaluate(() => localStorage.setItem('lpgTheme', 'dark'));
await p.reload({ waitUntil: 'networkidle' });
await p.screenshot({ path: `${OUT}/dark.png` });
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
await p.setViewportSize({ width: 390, height: 844 });
await p.reload({ waitUntil: 'networkidle' });
check((await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)) <= 2, 'no horizontal page scroll at 390 px');
await p.screenshot({ path: `${OUT}/phone.png`, fullPage: true });
check(errors.filter((e) => !e.includes('debugbar')).length === 0, 'no console/page errors', errors.join(' | '));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
