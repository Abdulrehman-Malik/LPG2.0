// Phase 11.4 POS check: search, shortcuts, warning, F9 posting, receipts, themes, phone width.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/pos';
fs.mkdirSync(OUT, { recursive: true });
let pass = 0, fail = 0;
const ok = (m) => { pass++; console.log('PASS  ' + m); };
const bad = (m, d = '') => { fail++; console.log('FAIL  ' + m + (d ? ' :: ' + d : '')); };
const check = (cond, m, d) => (cond ? ok(m) : bad(m, d));

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
const errors = [];
p.on('pageerror', (e) => errors.push(e.message));
p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

await p.goto(B + '/login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
await p.goto(B + '/sales', { waitUntil: 'networkidle' });
await p.screenshot({ path: `${OUT}/light-1440.png` });

// F2 focuses the customer search
await p.keyboard.press('F2');
check(await p.evaluate(() => document.activeElement?.id === 'customerSearch'), 'F2 focuses customer search');

// Search filters the list; Enter selects the first match and resets lines (existing change handler)
await p.keyboard.type('credit');
const visible = await p.$$eval('#customer_id option', (os) => os.filter((o) => !o.hidden && o.value).map((o) => o.textContent.trim()));
check(visible.length === 1 && visible[0].startsWith('QA-E2E-CREDIT'), 'search "credit" leaves only QA-E2E-CREDIT', JSON.stringify(visible));
await p.keyboard.press('Enter');
const selected = await p.$eval('#customer_id', (s) => s.options[s.selectedIndex].textContent.trim());
check(selected.startsWith('QA-E2E-CREDIT'), 'Enter selects the matching customer', selected);
check(await p.$eval('#customerSearch', (i) => i.value === ''), 'search box clears after selection');
check(await p.evaluate(() => [...document.querySelectorAll('#customer_id option')].every((o) => !o.hidden)), 'full customer list restored');
check(!(await p.$eval('#customerCreditStatus', (e) => e.textContent)).startsWith('Walk-in'), 'credit status updated for the customer');
check(p.url().endsWith('/sales'), 'Enter in search did not submit the form', p.url());
await p.fill('#customerSearch', 'zzzz-nobody');
check(await p.$eval('#customerSearch', (i) => i.classList.contains('is-invalid')), 'no-match search is flagged');
await p.press('#customerSearch', 'Escape');

// F4 adds a line
await p.click('body', { position: { x: 5, y: 300 } });
const before = await p.locator('#lines tbody tr:not(.cylinderPickerRow)').count();
await p.keyboard.press('F4');
const after = await p.locator('#lines tbody tr:not(.cylinderPickerRow)').count();
check(after === before + 1, `F4 adds a line (${before} -> ${after})`);

// Back to walk-in, one clean line
await p.selectOption('#customer_id', '');
const line = p.locator('#lines tbody tr').first();
const c6 = await line.locator('.cyl option').evaluateAll((os) => os.find((o) => o.textContent.trim().startsWith('C6'))?.value || '');
await line.locator('.cyl').selectOption(c6);
if (await line.locator('.sourceCyl').isVisible()) { const src = await line.locator('.sourceCyl option').evaluateAll((os) => os.find((o) => o.textContent.trim().startsWith('QA-E2E-C6-001'))?.value || ''); await line.locator('.sourceCyl').selectOption(src); }
await line.locator('.entryValue').fill('1');
await p.waitForTimeout(200);
const total = await p.locator('#saleTotal').innerText();
check(Number(total) > 0, 'line total computed', total);

// F7 focuses the payment amount; overpayment warning appears while typing
await p.keyboard.press('F7');
check(await p.evaluate(() => document.activeElement?.classList.contains('amount')), 'F7 focuses payment amount');
await p.keyboard.type('99999');
await p.waitForTimeout(100);
const warn = p.locator('#payments + .text-danger');
check(await warn.isVisible(), 'overpayment warning shown before posting', await warn.innerText().catch(() => ''));
await p.screenshot({ path: `${OUT}/overpay-warning.png` });

// F9 with overpayment -> submit validation error, nothing posted
const salesBefore = await p.evaluate(async () => 0);
await p.keyboard.press('F9');
await p.waitForTimeout(300);
check(await p.locator('#posValidationAlert').isVisible(), 'F9 runs validation (overpayment refused)', await p.locator('#posValidationAlert').innerText());
await p.screenshot({ path: `${OUT}/validation-error.png` });

// Correct amount and post with F9
await p.locator('#payments .payment .amount').first().fill(total);
await p.waitForTimeout(100);
check(!(await warn.isVisible()), 'warning clears when payment is valid');
let resolvePost; const posted = new Promise((r) => { resolvePost = r; });
await p.route('**/sales/save', async (route) => { const response = await route.fetch(); const text = await response.text(); resolvePost(text); await route.fulfill({ response, body: text }); });
await p.keyboard.press('F9');
const body = JSON.parse(await posted || '{}');
await p.unroute('**/sales/save');
check(body.success === true && body.sale_no, 'F9 posts the sale', JSON.stringify(body).slice(0, 200));
await p.waitForLoadState('networkidle');
const receiptUrl = body.receipt_url;

// Receipt formats
if (receiptUrl) {
  const r = await ctx.newPage();
  await r.goto(receiptUrl);
  check((await r.evaluate(() => document.body.getBoundingClientRect().width)) < 330, '80 mm receipt is narrow by default');
  await r.screenshot({ path: `${OUT}/receipt-80mm.png`, fullPage: true });
  await r.click('text=A4');
  await r.waitForLoadState();
  check(r.url().includes('format=a4') && (await r.evaluate(() => document.body.getBoundingClientRect().width)) > 500, 'A4 receipt layout');
  await r.screenshot({ path: `${OUT}/receipt-a4.png`, fullPage: true });
  await r.close();
}

// Dark and phone
await p.evaluate(() => localStorage.setItem('lpgTheme', 'dark'));
await p.goto(B + '/sales', { waitUntil: 'networkidle' });
await p.screenshot({ path: `${OUT}/dark-1440.png` });
await p.selectOption('#transactionType', 'security_deposit');
await p.waitForTimeout(200);
await p.screenshot({ path: `${OUT}/dark-deposit.png` });
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
await p.setViewportSize({ width: 390, height: 844 });
await p.goto(B + '/sales', { waitUntil: 'networkidle' });
const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
check(overflow <= 2, 'no horizontal page scroll at 390 px', String(overflow));
await p.screenshot({ path: `${OUT}/phone.png`, fullPage: true });

check(errors.filter((e) => !e.includes('debugbar')).length === 0, 'no console/page errors', errors.join(' | '));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
