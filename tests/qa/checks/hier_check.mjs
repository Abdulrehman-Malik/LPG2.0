// Business Hierarchy check (run after verify_branch_admin.sh on a fresh QA DB).
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/hier';
fs.mkdirSync(OUT, { recursive: true });
const MYSQL = `${process.env.QA_MYSQL || 'mysql'} -h ${process.env.QA_DB_HOST || '127.0.0.1'} -P ${process.env.QA_DB_PORT || '3306'} -u ${process.env.QA_DB_USER || 'root'}${process.env.QA_DB_PASS ? ' -p' + process.env.QA_DB_PASS : ''} -N ${process.env.QA_DB || 'lpg_qa'}`;
const sql = (q) => { for (let i = 0; i < 5; i++) { const r = execSync(`${MYSQL} -e "${q}"`, { shell: 'bash' }).toString().split(String.fromCharCode(13)).join('').trim(); if (r) return r; } return ''; };
let pass = 0, fail = 0;
const check = (c, m, d = '') => { if (c) { pass++; console.log('PASS  ' + m); } else { fail++; console.log('FAIL  ' + m + (d ? ' :: ' + d : '')); } };

const browser = await chromium.launch();
async function session(user, pass, viewport = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport });
  const p = await ctx.newPage();
  p.errors = [];
  p.on('pageerror', (e) => p.errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('debugbar')) p.errors.push(m.text()); });
  await p.goto(B + '/login');
  await p.fill('input[name=login]', user); await p.fill('input[name=password]', pass);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  return p;
}
const MAIN = sql("SELECT id FROM locations WHERE code='MAIN'"), B2 = sql("SELECT id FROM locations WHERE code='QA-B2'"), OTHER = sql("SELECT id FROM locations WHERE code='QA-OTHER'");

const p = await session('admin', 'admin123');
const resp = await p.goto(B + '/business-hierarchy', { waitUntil: 'networkidle' });
check(resp.status() === 200, 'admin opens Business Hierarchy');
check(await p.locator('#appNav a.active', { hasText: 'Business Hierarchy' }).count() === 1, 'menu entry active');
for (const id of [MAIN, B2, OTHER]) check(await p.locator(`.hier-row[data-select="shop-${id}"]`).count() === 1, `tree lists shop ${id}`);
check((await p.locator('#hierDetail h2').innerText()) === 'Main LPG Shop', 'current shop selected initially');

// Live figures for MAIN vs SQL
const gas = Number(sql(`SELECT COALESCE(SUM(gas_weight_kg),0) FROM cylinder_units WHERE location_id=${MAIN} AND status='filled'`)).toFixed(2);
check((await p.locator('#hierDetail').innerText()).includes(gas + ' KG'), 'MAIN gas KG matches SQL', gas);

// Select QA-B2: staff list matches user_shop_access
await p.click(`.hier-row[data-select="shop-${B2}"]`);
check((await p.locator("#hierDetail").innerText()).includes("No staff assigned") === (sql(`SELECT COUNT(*) FROM user_shop_access WHERE location_id=${B2}`) === "0"), "QA-B2 staff state matches SQL");
await p.click(`.hier-row[data-select="shop-${MAIN}"]`);
const staff = sql(`SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR '|') FROM user_shop_access usa JOIN users u ON u.id=usa.user_id WHERE usa.location_id=${MAIN}`).split('|').filter(Boolean);
const shown = await p.$$eval('#hierDetail .hier-people li .fw-semibold', (els) => els.map((e) => e.textContent.trim()));
check(staff.length > 0 && staff.every((n) => shown.some((s) => s.startsWith(n))), 'MAIN staff match user_shop_access', JSON.stringify({ staff, shown }));
check(p.url().includes("node=shop-" + MAIN), "selection reflected in URL");
await p.screenshot({ path: `${OUT}/shop.png` });

// Jump to a user from the detail panel
await p.locator('#hierDetail [data-goto^="user-"]').first().click();
check((await p.locator('#hierDetail .hier-section', { hasText: 'Access' }).count()) === 1, 'user detail shows access list');
await p.screenshot({ path: `${OUT}/user.png` });

// Expand / collapse all
await p.click('#hierCollapse');
check(await p.locator(`[data-node="shop-${MAIN}"]`).getAttribute("aria-expanded") === "false", 'collapse all');
await p.click('#hierExpand');
check(await p.locator(`[data-node="shop-${MAIN}"]`).getAttribute("aria-expanded") === "true", 'expand all');

// Keyboard navigation
await p.click('#hierCollapse');
await p.locator('.hier-row').first().focus();
await p.keyboard.press('ArrowDown');
const focused = await p.evaluate(() => document.activeElement?.dataset.select || document.activeElement?.textContent.trim());
check(!!focused, 'ArrowDown moves focus', focused);
await p.keyboard.press('Enter');
check((await p.locator('#hierDetail h2').count()) === 1, 'Enter selects the focused node');

// Search
await p.fill('#hierSearch', 'branch two');
const visibleShops = await p.$$eval('[data-node^="shop-"]:not(.is-hidden)', (els) => els.map((e) => e.dataset.node));
check(JSON.stringify(visibleShops) === JSON.stringify(['shop-' + B2]), 'search narrows to QA Branch Two', JSON.stringify(visibleShops));
check(await p.locator('#hierTree mark').count() >= 1, 'match highlighted');
await p.fill('#hierSearch', 'zzz-nothing');
check(await p.locator('#hierNoMatch').isVisible(), 'no-match message');
await p.fill('#hierSearch', '');

// Add a shop from the tree
await p.locator('.hier-add').first().click();
await p.waitForSelector('#addShopModal.show');
await p.fill('#shopCode', 'qa-b3');
await p.fill('#shopName', 'QA Branch Three');
await p.fill('#shopCity', 'Lahore');
await p.screenshot({ path: `${OUT}/add-shop.png` });
await Promise.all([p.waitForNavigation(), p.click('#addShopModal button[type=submit]')]);
const B3 = sql("SELECT id FROM locations WHERE code='QA-B3'");
check(!!B3, 'shop QA-B3 created (code upper-cased)');
check(sql(`SELECT CONCAT((SELECT COUNT(*) FROM shop_settings WHERE location_id=${B3}),'/',(SELECT COUNT(*) FROM cash_registers WHERE location_id=${B3}),'/',(SELECT COUNT(*) FROM inventory_policies WHERE location_id=${B3}))`) === '1/1/1', 'default settings, counter and inventory policy provisioned');
check(p.url().includes('business-hierarchy?node=shop-' + B3), 'returns to the tree with the new shop selected', p.url());
check((await p.locator('#hierDetail h2').innerText()) === 'QA Branch Three', 'new shop shown in details');
check(/Shop created/.test(await p.locator('.alert-success').innerText()), 'success message');
check(sql(`SELECT COUNT(*) FROM audit_logs WHERE action='SHOP_CREATED' AND entity_id=${B3}`) === '1', 'audit entry written');

// Dark and phone
await p.evaluate(() => localStorage.setItem('lpgTheme', 'dark'));
await p.reload({ waitUntil: 'networkidle' });
await p.click('#hierExpand');
await p.screenshot({ path: `${OUT}/dark.png` });
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
await p.setViewportSize({ width: 390, height: 844 });
await p.reload({ waitUntil: 'networkidle' });
check((await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)) <= 2, 'no horizontal scroll at 390 px');
await p.screenshot({ path: `${OUT}/phone.png`, fullPage: true });
check(p.errors.length === 0, 'no console/page errors (admin)', p.errors.join(' | '));

// Scope: MAIN-only manager sees only MAIN; cashier is refused
const m = await session('qa_scoped_manager', 'admin123');
await m.goto(B + '/business-hierarchy', { waitUntil: 'networkidle' });
const mShops = await m.$$eval('[data-node^="shop-"]', (els) => els.map((e) => e.dataset.node));
check(JSON.stringify(mShops) === JSON.stringify(['shop-' + MAIN]), 'scoped manager sees only MAIN', JSON.stringify(mShops));
const html = await m.content();
check(!html.includes('QA Branch Two') && !html.includes('QA Other Shop'), 'no other-branch names in the page source');
const c = await session('qa_two', 'Secret12345');
const cr = await c.goto(B + '/business-hierarchy');
check(cr.status() === 403, 'cashier gets 403', String(cr.status()));
check(await c.locator('#appNav a', { hasText: 'Business Hierarchy' }).count() === 0, 'cashier has no menu entry');

console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
