// Installation wizard (docs/first-install-setup.md) end to end in a browser.
// Needs an app served with FRESH_DATABASE_SETUP=true against a DISPOSABLE database (QA_INSTALL_DB)
// that does not exist yet or has no tables, e.g. tests/qa/install_check.sh. QA_MYSQL_CMD must be
// able to read and write that database.
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
const tableCount = () => Number(sql(`SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='${DB}'`));

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/status of 4\d\d/.test(m.text())) errors.push(m.text()); });
page.on('requestfailed', (r) => { if (!/ERR_ABORTED/.test(r.failure()?.errorText || '')) errors.push('requestfailed ' + r.url() + ' ' + (r.failure()?.errorText || '')); });
page.on('response', (r) => { if (r.status() >= 500) errors.push(r.status() + ' ' + r.url()); });
page.on('dialog', (d) => d.accept());
const body = async () => (await page.locator('body').innerText()).replace(/\s+/g, ' ');
const submit = async () => Promise.all([page.waitForLoadState('networkidle'), page.click('form button.btn-primary')]);

const unique = Date.now().toString(36).slice(-5).toUpperCase();
const owner = { name: 'QA Owner', user: 'qaowner' + unique.toLowerCase(), pass: 'Qa-Install-' + unique + '-2026' };

/** Walks the wizard from the checks step to the review step with the given answers. */
async function fillWizard(o) {
  await page.goto(B + '/installation');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('a.btn-primary')]);
  await page.fill('#bName', 'QA Gas Traders ' + unique);
  await page.fill('#bCode', 'QAGT' + unique);
  await page.fill('#bCity', 'Lahore');
  await submit();
  await page.fill('#lName', 'QA Model Town Shop');
  await page.fill('#lCode', 'MT1');
  await submit();
  await page.fill('#oName', o.name);
  await page.fill('#oUser', o.user);
  await page.fill('#oPass', o.pass);
  await page.fill('#oPass2', o.pass);
  await submit();
}
async function install() {
  await page.fill('#confirm', 'INSTALL DATABASE');
  await page.check('#understand');
  const started = Date.now();
  await Promise.all([page.waitForURL(/\/login/, { timeout: 300000 }), page.click('form button.btn-primary')]);
  return Math.round((Date.now() - started) / 1000);
}

console.log('--- fresh install through the wizard');
await page.goto(B + '/login');
check(page.url().endsWith('/installation'), 'login redirects to the installation wizard while setup is incomplete', page.url());
let t = await body();
check(t.includes(DB) && /Server and database checks/.test(t) && /Database server reachable/.test(t), 'step 1 shows the environment checks and the configured database', t.slice(0, 300));
await page.screenshot({ path: `${OUT}/1-checks.png`, fullPage: true });
await Promise.all([page.waitForLoadState('networkidle'), page.click('a.btn-primary')]);
await page.evaluate(() => document.querySelectorAll('[required]').forEach((e) => e.removeAttribute('required')));
await submit();
check(/Business name is required/.test(await body()) && page.url().includes('step=business'), 'step 2 refuses a missing business name');
await page.goto(B + '/installation?step=review');
check(page.url().includes('step=business'), 'later steps cannot be skipped');

await fillWizard({ ...owner, pass: 'short' });
check(/password|characters/i.test(await body()) && page.url().includes('step=owner'), 'step 4 refuses a weak owner password', (await body()).slice(0, 200));
await page.fill('#oName', owner.name);
await page.fill('#oUser', owner.user);
await page.fill('#oPass', owner.pass);
await page.fill('#oPass2', owner.pass);
await submit();
t = await body();
check(page.url().includes('step=review') && t.includes('QA Gas Traders ' + unique) && t.includes(owner.user), 'step 5 reviews the business, branch and owner');
await page.screenshot({ path: `${OUT}/2-review.png`, fullPage: true });
await page.fill('#confirm', 'install database');
await page.check('#understand');
await submit();
check(/INSTALL DATABASE/.test(await body()) && tableCount() === 0, 'wrong confirmation text is refused and nothing is created');

const seconds = await install();
check(/Installation complete/.test(await body()), `installation runs step by step and returns to sign in (${seconds} s)`, (await body()).slice(0, 300));
await page.screenshot({ path: `${OUT}/3-installed-login.png`, fullPage: true });
check(tableCount() > 40, `schema applied (${tableCount()} tables)`);
check(sql(`SELECT state FROM ${DB}.lpg_installation_state WHERE id=1`) === 'complete', 'completion recorded');
const biz = sql(`SELECT CONCAT(code,'|',name) FROM ${DB}.businesses`);
check(biz === `QAGT${unique}|QA Gas Traders ${unique}`, 'the business is saved as entered', biz);
check(sql(`SELECT CONCAT(code,'|',name) FROM ${DB}.locations`) === 'MT1|QA Model Town Shop', 'the first branch is saved as entered');
check(sql(`SELECT COUNT(*) FROM ${DB}.users WHERE username='admin'`) === '0', 'no default admin account is left behind');
check(sql(`SELECT GROUP_CONCAT(username) FROM ${DB}.users WHERE is_active=1`) === owner.user, 'only the owner account is active (seeded accounts such as pak are disabled)', sql(`SELECT GROUP_CONCAT(username) FROM ${DB}.users WHERE is_active=1`));
check(sql(`SELECT COUNT(*) FROM ${DB}.lpg_migration_history WHERE migration_seq=11 AND status='success' AND executed_statements>0`) === '0', 'destructive refresh migration 011 not executed');

console.log('--- first sign-in');
await page.fill('#login', 'admin');
await page.fill('#password', 'admin123');
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
check(!/\/dashboard/.test(page.url()), 'admin / admin123 does not sign in');
await page.fill('#login', owner.user);
await page.fill('#password', owner.pass);
await Promise.all([page.waitForURL(/\/dashboard/, { timeout: 60000 }).catch(() => {}), page.click('button[type=submit]')]);
check(/\/dashboard/.test(page.url()), 'the owner signs in to the dashboard', page.url() + ' :: ' + (await body()).slice(0, 200));
check((await body()).includes('QA Model Town Shop'), 'the dashboard shows the new branch');
await page.goto(B + '/logout');
await page.goto(B + '/login');
check(page.url().endsWith('/login'), 'login no longer redirects to setup after completion');

console.log('--- unfinished installation can be rerun');
sql(`UPDATE ${DB}.lpg_installation_state SET state='installing', completed_at=NULL WHERE id=1`);
await page.goto(B + '/installation');
check(/did not finish/i.test(await body()), 'unfinished install is explained on the checks step');
await fillWizard(owner);
check((await page.locator('#admin_password').count()) === 0, 'no administrator password needed to rebuild an unfinished install');
await install();
check(/Installation complete/.test(await body()) && sql(`SELECT state FROM ${DB}.lpg_installation_state WHERE id=1`) === 'complete', 'unfinished install rebuilt and completed', page.url() + ' :: ' + (await body()).slice(0, 200));

sql(`INSERT INTO ${DB}.cash_registers(location_id,business_id,code,name) SELECT id,business_id,'QAI','QA' FROM ${DB}.locations LIMIT 1`);
sql(`INSERT INTO ${DB}.cash_sessions(register_id,business_id,opened_by,opening_cash) SELECT r.id,r.business_id,(SELECT MIN(id) FROM ${DB}.users),0 FROM ${DB}.cash_registers r WHERE r.code='QAI'`);
sql(`UPDATE ${DB}.lpg_installation_state SET state='installing' WHERE id=1`);
await page.goto(B + '/installation');
check(/already has data/i.test(await body()) && !/did not finish/i.test(await body()), 'a database with business records is never treated as an unfinished install');
sql(`UPDATE ${DB}.lpg_installation_state SET state='complete' WHERE id=1`);

console.log('--- replacement of an installed database');
sql(`INSERT INTO ${DB}.expense_categories(business_id,code,name) SELECT id,'QAINST','Install marker' FROM ${DB}.businesses LIMIT 1`);
await fillWizard(owner);
check((await page.locator('#admin_password').isEnabled()), 'existing data: the review asks for an administrator password');
await page.screenshot({ path: `${OUT}/4-replace.png`, fullPage: true });
await page.fill('#admin_password', 'wrong-password');
await page.fill('#replace_confirm', 'REPLACE DATABASE');
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await submit();
check(/password verification failed/i.test(await body()), 'wrong administrator password refused');
check(sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '1', 'database unchanged after a refused replacement');
await page.fill('#admin_password', owner.pass);
await page.fill('#replace_confirm', 'replace');
await page.fill('#confirm', 'INSTALL DATABASE');
await page.check('#understand');
await submit();
check(/REPLACE DATABASE/.test(await body()) && sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '1', 'missing REPLACE DATABASE confirmation refused, data kept');
await page.fill('#admin_password', owner.pass);
await page.fill('#replace_confirm', 'REPLACE DATABASE');
await install();
check(/Installation complete/.test(await body()), 'authorised replacement completes');
check(sql(`SELECT COUNT(*) FROM ${DB}.expense_categories WHERE code='QAINST'`) === '0', 'replacement rebuilt the database (marker row gone)');

check(errors.length === 0, 'no browser errors, failed requests or server errors', errors.join(' | ').slice(0, 500));
await browser.close();
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
