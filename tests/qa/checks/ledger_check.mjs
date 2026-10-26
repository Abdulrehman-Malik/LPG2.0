// BL-030: docs/sync-policy.md classifies every table, and ledger tables are only updated or
// deleted at the sites it allows. Static check (no database): tables come from
// database/schema.sql and the CI4 migrations; mutation sites from app/**/*.php.
//   node ledger_check.mjs      Prints PASS/FAIL lines and "N passed, M failed" last.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
let pass = 0, fail = 0;
const ok = (m) => { pass++; console.log('PASS  ' + m); };
const bad = (m, d) => { fail++; console.log('FAIL  ' + m + ' :: ' + d); };
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const walk = (dir) => fs.readdirSync(path.join(root, dir), { withFileTypes: true })
  .flatMap((e) => e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name).replace(/\\/g, '/')]);

const policy = read('docs/sync-policy.md');
const classes = new Map();
for (const m of policy.matchAll(/^\| `([a-z_]+)` \| (master|ledger|state|system) \|/gm)) classes.set(m[1], m[2]);
const allowed = new Set();
const allowedSection = policy.split('## Allowed ledger mutations')[1] || '';
for (const m of allowedSection.matchAll(/^\| `([a-z_]+)` \| `([^`]+)` \|/gm)) allowed.add(m[2] + ' ' + m[1]);

// Tables: schema.sql CREATE TABLE plus tables created by CI4 migrations.
const tables = new Set();
for (const m of read('database/schema.sql').matchAll(/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?/gi)) tables.add(m[1].toLowerCase());
for (const f of walk('app/Database/Migrations').filter((f) => f.endsWith('.php'))) {
  for (const m of read(f).matchAll(/createTable\(\s*'([a-z_]+)'|CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?/g)) tables.add(m[1] || m[2]);
}
for (const f of walk('database/migrations').filter((f) => f.endsWith('.sql'))) {
  for (const m of read(f).matchAll(/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?/gi)) tables.add(m[1].toLowerCase());
}
for (const f of walk('app').filter((f) => f.endsWith('.php') && !f.includes('/Database/Migrations/'))) {
  for (const m of read(f).matchAll(/CREATE TABLE IF NOT EXISTS `?([a-z_]+)`?/g)) tables.add(m[1]);
}
tables.add('migrations'); // created by CodeIgniter
const unclassified = [...tables].filter((t) => !classes.has(t)).sort();
unclassified.length === 0 ? ok(tables.size + ' tables, all classified in docs/sync-policy.md') : bad('tables missing from docs/sync-policy.md', unclassified.join(', '));
const stale = [...classes.keys()].filter((t) => !tables.has(t));
stale.length === 0 ? ok('every classified table exists') : bad('classified tables that do not exist', stale.join(', '));

// Mutation sites on ledger tables.
const ledger = [...classes].filter(([, c]) => c === 'ledger').map(([t]) => t);
const ledgerRe = ledger.join('|');
const siteRe = new RegExp(`table\\(\\s*['"](${ledgerRe})(?: [a-z]+)?['"]\\s*\\)[^;]*->(?:update|delete|replace|updateBatch)\\(|\\b(?:UPDATE|DELETE\\s+FROM)\\s+\`?(${ledgerRe})\`?[\\s;]`, 'g');
const found = new Map(); // "file table" => "file:line: matched text"
for (const f of walk('app').filter((f) => f.endsWith('.php') && !f.includes('/Database/Migrations/') && !f.endsWith('TransactionalDataRefreshService.php'))) {
  const src = read(f);
  for (const m of src.matchAll(siteRe)) {
    const key = f + ' ' + (m[1] || m[2]);
    if (!found.has(key)) found.set(key, f + ':' + src.slice(0, m.index).split('\n').length + ': ' + m[0].replace(/\s+/g, ' ').slice(0, 160));
  }
}
const unlisted = [...found.keys()].filter((s) => !allowed.has(s)).sort();
unlisted.length === 0 ? ok(found.size + ' ledger mutation sites, all allowed by the policy') : bad('ledger tables changed at sites the policy does not allow', unlisted.map((s) => found.get(s)).join(' | '));
const unused = [...allowed].filter((s) => !found.has(s)).sort();
unused.length === 0 ? ok('every allowed mutation site still exists') : bad('allowed sites no longer in the code', unused.join('; '));

// BL-033: every stock movement records the cylinder's state before and after it.
const insertCall = "table('inventory_movements')->insert(";
const stateless = [];
let inserts = 0;
for (const f of walk('app').filter((f) => f.endsWith('.php') && !f.includes('/Database/Migrations/'))) {
  const src = read(f);
  for (let at = src.indexOf(insertCall); at >= 0; at = src.indexOf(insertCall, at + 1)) {
    inserts++;
    let depth = 0, end = at + insertCall.length - 1;
    for (; end < src.length; end++) { if (src[end] === '(') depth++; else if (src[end] === ')' && --depth === 0) break; }
    const args = src.slice(at, end);
    if (!/from_state|MovementStates::reverse|\$states/.test(args) || !/to_state|MovementStates::reverse|\$states/.test(args)) {
      stateless.push(f + ':' + src.slice(0, at).split('\n').length);
    }
  }
}
stateless.length === 0 ? ok(inserts + ' inventory_movements inserts, all with from_state and to_state') : bad('inventory_movements inserts without from_state/to_state', stateless.join(', '));

console.log(pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
