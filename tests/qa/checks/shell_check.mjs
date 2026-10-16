// Phase 11 shell check: every menu page at desktop + phone width; JS errors, failed/external
// requests, horizontal overflow; sidebar collapse, search, theme switch, off-canvas.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const BASE = process.env.QA_BASE_URL || 'http://localhost:8080';
const OUT = process.argv[2] || (process.env.QA_TMP || 'qa-output') + '/shots/shell';
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
fs.mkdirSync(OUT, { recursive: true });
let pass = 0, fail = 0;
const ok = (m) => { pass++; console.log('PASS  ' + m); };
const bad = (m, d = '') => { fail++; console.log('FAIL  ' + m + (d ? ' :: ' + d : '')); };

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const errors = [];
const external = new Set();
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('request', (r) => { const u = r.url(); if (!u.startsWith(BASE) && !u.startsWith('data:') && !u.startsWith('http://localhost:8080')) external.add(u); });
page.on('requestfailed', (r) => { if (!r.url().includes('debugbar')) errors.push('requestfailed ' + r.url()); });

await page.goto(BASE + '/login');
await page.fill('input[name=login]', 'admin');
await page.fill('input[name=password]', 'admin123');
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
page.url().includes('dashboard') ? ok('login') : bad('login', page.url());

const links = await page.$$eval('#appNav a.app-nav-link', (as) => as.map((a) => ({ href: a.href, label: a.textContent.trim() })));
links.length > 20 ? ok(`menu has ${links.length} links`) : bad('menu links', String(links.length));

for (const width of [1440, 390]) {
  await page.setViewportSize({ width, height: width > 500 ? 900 : 844 });
  for (const { href, label } of links) {
    const path = href.replace(BASE + '/', '');
    if (ONLY && !ONLY.includes(path)) continue;
    errors.length = 0;
    const resp = await page.goto(href);
    await page.waitForLoadState('networkidle').catch(() => {});
    const status = resp ? resp.status() : 0;
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const active = await page.$$eval('.app-nav-link.active', (as) => as.map((a) => a.textContent.trim()));
    const issues = [];
    if (status !== 200) issues.push('HTTP ' + status);
    if (errors.length) issues.push(errors.join(' | ').slice(0, 300));
    if (overflow > 2) issues.push('page overflows by ' + overflow + 'px');
    if (width > 500 && !(active.length === 1 && active[0] === label)) issues.push('active=' + JSON.stringify(active));
    issues.length ? bad(`${width}px ${path}`, issues.join('; ')) : ok(`${width}px ${path}`);
    await page.screenshot({ path: `${OUT}/${width}-${path.replace(/[\/?=]/g, '_')}.png`, fullPage: false });
  }
}
external.size ? bad('external requests', [...external].join(', ')) : ok('no external (CDN) requests');

// Desktop shell behaviour
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(BASE + '/expenses/categories');
const crumbs = await page.$$eval('.app-breadcrumb li', (l) => l.map((x) => x.textContent.trim()));
JSON.stringify(crumbs) === JSON.stringify(['Cash & Expenses', 'Expense Categories']) ? ok('breadcrumb ' + crumbs.join(' / ')) : bad('breadcrumb', JSON.stringify(crumbs));
await page.fill('#navSearch', 'transfer');
const visible = await page.$$eval('#appNav [data-nav-item]:not([hidden])', (l) => l.map((x) => x.textContent.trim()));
JSON.stringify(visible) === JSON.stringify(['Branch Stock Transfers']) ? ok('menu search filters to Branch Stock Transfers') : bad('menu search', JSON.stringify(visible));
await Promise.all([page.waitForNavigation(), page.press('#navSearch', 'Enter')]);
page.url().endsWith('/inventory/transfers') ? ok('menu search Enter opens first match') : bad('search enter', page.url());
await page.click('#sidebarToggle');
await page.waitForTimeout(400);
const w = await page.$eval('#sidebar', (e) => e.getBoundingClientRect().width);
w < 100 ? ok('sidebar collapses to ' + w + 'px') : bad('collapse', String(w));
await page.reload();
(await page.evaluate(() => document.body.classList.contains('sidebar-collapsed'))) ? ok('collapsed state remembered') : bad('collapse persist');
await page.screenshot({ path: `${OUT}/desktop-collapsed.png` });
await page.click('#sidebarToggle');
await page.click('.app-user-btn');
await page.click('[data-theme-choice="dark"]');
(await page.getAttribute('html', 'data-bs-theme')) === 'dark' ? ok('dark theme applied') : bad('dark theme');
await page.goto(BASE + '/dashboard');
(await page.getAttribute('html', 'data-bs-theme')) === 'dark' ? ok('dark theme persists across pages') : bad('dark persist');
await page.screenshot({ path: `${OUT}/desktop-dark-dashboard.png` });
await page.goto(BASE + '/reports/branches');
await page.screenshot({ path: `${OUT}/desktop-dark-branches.png` });
await page.click('.app-user-btn');
await page.click('[data-theme-choice="light"]');
(await page.getAttribute('html', 'data-bs-theme')) === 'light' ? ok('light theme restored') : bad('light theme');

// Phone off-canvas
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(BASE + '/dashboard');
const hiddenX = await page.$eval('#sidebar', (e) => e.getBoundingClientRect().right);
hiddenX <= 0 ? ok('phone: menu hidden off-canvas') : bad('phone hidden', String(hiddenX));
await page.click('#sidebarToggle');
await page.waitForTimeout(350);
const shownX = await page.$eval('#sidebar', (e) => e.getBoundingClientRect().left);
shownX >= 0 ? ok('phone: menu slides in') : bad('phone open', String(shownX));
await page.screenshot({ path: `${OUT}/phone-menu-open.png` });
await page.keyboard.press('Escape');
await page.waitForTimeout(350);
(await page.$eval('#sidebar', (e) => e.getBoundingClientRect().right)) <= 0 ? ok('phone: Escape closes menu') : bad('phone escape');

console.log(`\n${pass} passed, ${fail} failed`);
await browser.close();
process.exit(fail ? 1 : 0);
