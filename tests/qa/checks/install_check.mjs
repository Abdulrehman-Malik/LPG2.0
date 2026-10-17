// Installation wizard (docs/first-install-setup.md) end to end in a browser.
// Needs an app served with FRESH_DATABASE_SETUP=true against a DISPOSABLE database that does
// not exist yet (QA_INSTALL_DB), e.g. tests/qa/install_check.sh.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const B = process.env.QA_INSTALL_URL || 'http://localhost:8081';
const DB = process.env.QA_INSTALL_DB || 'lpg_install_test';
const MYSQL = process.env.QA_MYSQL_CMD || 'mysql -u root -N';
const OUT = (process.env.QA_TMP || 'qa-output') + '/shots/install';
if (/^(perfect_lpg|pak_gas|production)/.test(DB)) throw new Error('Refusing to run against ' + DB);
fs.mkdirSync(OUT, { recursive: true });
let pass = 0, fail = 0;
const check = (c, m, d = '') => { c ? pass++ : fail++; console.log((c ? 'PASS  ' : 'FAIL  ') + m + (c || !d ? '' : ' :: ' + d)); };
const sql = (q) => execSync(`${MYSQL} -e "${q}"`, { shell: 'bash' }).toString().split(String.fromCharCode(13)).join('').trim();

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('requestfailed', (r) => errors.push('requestfailed ' + r.url()));
page.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('favicon')) errors.push(r.status() + ' ' + r.url()); });
page.on('dialog', (d) => d.accept());
const body = async () => (await page.locator('body').innerText()).replace(/\s+/g, ' ');

console.log('--- fresh install');
await page.goto(B + '/login');
check(page.url().endsWith('/installation'), 'login redirects to Installation Setup while setup is incomplete', page.url());
let t = await body();
check(t.includes(DB) && /Database exists No/.test(t), 'shows the configured database and that it will be created', t.slice(0, 300));
check(!/alert-danger|could not be inspected/i.test(await page.content()), 'no error on first load');
await page.screenshot({ path: `${OUT}/1-fresh.png`, fullPage: true });

await page.fill('#confirm', 'install database');
await page.check('#understand');
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check(/INSTALL DATABASE/.test(await body()) && page.url().endsWith('/installation'), 'wrong confirmation text is refused');
check(sql(`SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB}'`) === '0', 'nothing created after a refused confirmation');

await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
const started = Date.now();
await Promise.all([page.waitForURL(/\/login/, { timeout: 180000 }), page.click('button[type=submit]')]);
check(/Database setup completed/.test(await body()), `install completes and returns to login with a success message (${Math.round((Date.now() - started) / 1000)} s)`, (await body()).slice(0, 300));
await page.screenshot({ path: `${OUT}/2-installed-login.png`, fullPage: true });
const tables = Number(sql(`SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='${DB}'`));
check(tables > 40, `schema applied (${tables} tables)`);
check(sql(`SELECT state FROM ${DB}.lpg_installation_state WHERE id=1`) === 'complete', 'completion recorded');
const files = fs.readdirSync('../../../database/migrations').filter((f) => /^\d{3}_.*\.sql$/.test(f) && !f.startsWith('011_')).length;
const done = Number(sql(`SELECT COUNT(DISTINCT migration_seq) FROM ${DB}.lpg_migration_history WHERE status IN ('success','skipped')`));
check(done >= files, `every migration applied or already contained in the schema (${done}/${files})`);
check(sql(`SELECT COUNT(*) FROM ${DB}.lpg_migration_history WHERE migration_seq=11 AND status='success' AND executed_statements>0`) === '0', 'destructive refresh migration 011 not executed');

console.log('--- first login');
await page.fill('#login', 'admin');
await page.fill('#password', 'admin123');
await Promise.all([page.waitForURL(/\/dashboard/, { timeout: 60000 }).catch(() => {}), page.click('button[type=submit]')]);
check(/\/dashboard/.test(page.url()), 'admin / admin123 signs in to the dashboard', page.url() + ' :: ' + (await body()).slice(0, 200));
await page.goto(B + '/logout');
await page.goto(B + '/login');
check(page.url().endsWith('/login'), 'login no longer redirects to setup after completion');

console.log('--- interrupted installation can be rerun');
sql(`UPDATE ${DB}.lpg_installation_state SET state='installing', completed_at=NULL WHERE id=1`);
await page.goto(B + '/installation');
t = await body();
check(/did not finish/i.test(t) && (await page.locator('#admin_password').count()) === 0, 'unfinished install: explained, no administrator password needed', t.slice(0, 300));
await page.screenshot({ path: `${OUT}/2b-interrupted.png`, fullPage: true });
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await Promise.all([page.waitForURL(/\/login/, { timeout: 180000 }), page.click('button[type=submit]')]);
check(/Database setup completed/.test(await body()) && sql(`SELECT state FROM ${DB}.lpg_installation_state WHERE id=1`) === 'complete', 'unfinished install rebuilt and completed');
sql(`INSERT INTO ${DB}.cash_registers(location_id,code,name) VALUES (1,'QAI','QA')`);
sql(`INSERT INTO ${DB}.cash_sessions(register_id,opened_by,opening_cash) SELECT MAX(id),1,0 FROM ${DB}.cash_registers`);
sql(`UPDATE ${DB}.lpg_installation_state SET state='installing' WHERE id=1`);
await page.goto(B + '/installation');
check(/Destructive database replacement/i.test(await body()), 'a database with business records is never treated as an unfinished install');
sql(`UPDATE ${DB}.lpg_installation_state SET state='complete' WHERE id=1`);
console.log('--- replacement of an existing database');
await page.goto(B + '/installation');
t = await body();
check(/Destructive database replacement/i.test(t) && (await page.locator('#admin_password').isEnabled()), 'existing data: replacement warning and administrator password field shown');
await page.screenshot({ path: `${OUT}/3-replace.png`, fullPage: true });
sql(`INSERT INTO ${DB}.expense_categories(business_id,code,name) VALUES (1,'QAINST','Install marker')`);
await page.fill('#admin_password', 'wrong-password');
await page.fill('#replace_confirm', 'REPLACE DATABASE');
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
check(/password verification failed/i.test(await body()), 'wrong administrator password refused');
check(sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '1', 'database unchanged after a refused replacement');
await page.fill('#admin_password', 'admin123');
await page.fill('#replace_confirm', 'replace');
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
check(/REPLACE DATABASE/.test(await body()) && sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '1', 'missing REPLACE DATABASE confirmation refused, data kept');
await page.fill('#admin_password', 'admin123');
await page.fill('#replace_confirm', 'REPLACE DATABASE');
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await Promise.all([page.waitForURL(/\/login/, { timeout: 180000 }), page.click('button[type=submit]')]);
check(/Database setup completed/.test(await body()), 'authorised replacement completes');
check(sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '0', 'replacement rebuilt the database (marker row gone)');

check(errors.length === 0, 'no browser console errors, failed requests or HTTP errors', errors.join(' | ').slice(0, 500));
await browser.close();
console.log(`\n${pass} passed, ${fail} failed`);
