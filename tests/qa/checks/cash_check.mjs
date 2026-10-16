// Phase 11 Counter Cash check against SQL.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/cash';
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
p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await p.goto(B + '/login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

const sessionId = sql("SELECT cs.id FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.register_id JOIN locations l ON l.id=cr.location_id WHERE l.code='MAIN' AND cs.status='open'");
check(!!sessionId, 'fixture has an open MAIN session', sessionId);
const expectedSql = () => Number(sql(`SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) FROM cash_transactions WHERE cash_session_id=${sessionId}`));

await p.goto(B + '/cash', { waitUntil: 'networkidle' });
const kpi = async (label) => num(await p.locator('.kpi').filter({ has: p.locator('.kpi-label', { hasText: new RegExp('^\\s*' + label + '\\s*$') }) }).locator('.kpi-value').innerText());
check(Math.abs((await kpi('Expected in drawer')) - expectedSql()) < 0.005, 'Expected in drawer matches SQL', String(expectedSql()));
const opening = await kpi('Opening float');
const cashIn = await kpi('Cash in');
const cashOut = await kpi('Cash out');
check(Math.abs(opening + cashIn - cashOut - expectedSql()) < 0.005, 'float + in − out = expected');

// Manual cash in 50
await p.fill('#moveAmount', '50');
await p.fill('#moveNotes', 'QA change from bank');
await Promise.all([p.waitForNavigation(), p.click('form[action$="cash/move"] button')]);
check(/Cash movement posted/.test(await p.locator('.alert-success').innerText()), 'manual cash in posted');
check(await p.locator('tbody tr', { hasText: 'QA change from bank' }).count() === 1, 'movement listed in this session');
const expected = expectedSql();
check(Math.abs((await kpi('Expected in drawer')) - expected) < 0.005, 'expected updated after cash in', String(expected));

// Live difference preview
await p.fill('#countedCash', String((expected - 10).toFixed(2)));
check(/Short by Rs\. 10\.00/.test(await p.locator('#cashDifference').innerText()), 'short by 10 shown live');
await p.fill('#countedCash', String((expected + 5).toFixed(2)));
check(/Over by Rs\. 5\.00/.test(await p.locator('#cashDifference').innerText()), 'over by 5 shown live');
await p.fill('#countedCash', String(expected.toFixed(2)));
check(/balances/.test(await p.locator('#cashDifference').innerText()), 'balanced shown live');
await p.fill('#countedCash', String((expected - 10).toFixed(2)));
await p.screenshot({ path: `${OUT}/open.png` });

// Close via confirm dialog: cancel first, then confirm
await p.click('#closeCashForm button.btn-warning');
await p.waitForSelector('#confirmModal.show');
await p.screenshot({ path: `${OUT}/confirm.png` });
await p.click('#confirmModal [data-bs-dismiss="modal"].btn-outline-secondary');
await p.waitForTimeout(400);
check(sql(`SELECT status FROM cash_sessions WHERE id=${sessionId}`) === 'open', 'cancel keeps the session open');
await p.click('#closeCashForm button.btn-warning');
await p.waitForSelector('#confirmModal.show');
await Promise.all([p.waitForNavigation(), p.click('#confirmModal [data-confirm-ok]')]);
check(sql(`SELECT CONCAT(status,'|',counted_cash) FROM cash_sessions WHERE id=${sessionId}`) === 'closed|' + (expected - 10).toFixed(2), 'session closed with counted cash');
check(/Difference: Rs\. -10\.00/.test(await p.locator('.alert-success').innerText()), 'close message shows the -10 difference', await p.locator('.alert-success').innerText());

// Reopen with a float
await p.fill('#openingCash', '500');
await Promise.all([p.waitForNavigation(), p.click('form[action$="cash/open"] button')]);
check(await kpi('Opening float') === 500 && await kpi('Cash in') === 0 && await kpi('Expected in drawer') === 500, 'reopened: float 500, cash in 0, expected 500');

// History totals
await p.goto(B + '/cash/history', { waitUntil: 'networkidle' });
const from = await p.inputValue('#cashFrom'), to = await p.inputValue('#cashTo');
const sums = sql(`SELECT CONCAT(COALESCE(SUM(CASE WHEN ct.direction='in' THEN ct.amount END),0),'|',COALESCE(SUM(CASE WHEN ct.direction='out' THEN ct.amount END),0)) FROM cash_transactions ct JOIN cash_sessions cs ON cs.id=ct.cash_session_id JOIN cash_registers cr ON cr.id=cs.register_id JOIN locations l ON l.id=cr.location_id WHERE l.code='MAIN' AND ct.transaction_at BETWEEN '${from} 00:00:00' AND '${to} 23:59:59'`).split('|').map(Number);
check(Math.abs((await kpi('Cash in')) - sums[0]) < 0.005 && Math.abs((await kpi('Cash out')) - sums[1]) < 0.005, 'history totals match SQL', JSON.stringify(sums));
await p.screenshot({ path: `${OUT}/history.png` });

await p.evaluate(() => localStorage.setItem('lpgTheme', 'dark'));
await p.goto(B + '/cash', { waitUntil: 'networkidle' });
await p.screenshot({ path: `${OUT}/dark.png` });
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
await p.setViewportSize({ width: 390, height: 844 });
for (const path of ['/cash', '/cash/history']) {
  await p.goto(B + path, { waitUntil: 'networkidle' });
  check((await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)) <= 2, 'no horizontal scroll at 390 px ' + path);
}
check(errors.filter((e) => !e.includes('debugbar')).length === 0, 'no console/page errors', errors.join(' | '));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
