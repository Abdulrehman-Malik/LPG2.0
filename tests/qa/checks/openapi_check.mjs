// BL-020: docs/api/openapi.yaml is a valid OpenAPI document and lists every /api/v1 route.
//   node openapi_check.mjs            runs `php spark routes` (QA_PHP or php on PATH)
//   node openapi_check.mjs routes.txt reads the `spark routes` table from a file
// Prints PASS/FAIL lines and "N passed, M failed" last.
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const require = createRequire(path.join(root, 'tests/e2e/package.json'));
const YAML = require('yaml');
const { Validator } = await import(pathToFileURL(path.join(root, 'tests/e2e/node_modules/@seriousme/openapi-schema-validator/index.js')).href);

let pass = 0, fail = 0;
const ok = (msg) => { pass++; console.log('PASS  ' + msg); };
const bad = (msg, detail) => { fail++; console.log('FAIL  ' + msg + ' :: ' + detail); };

const specText = fs.readFileSync(path.join(root, 'docs/api/openapi.yaml'), 'utf8');
const spec = YAML.parse(specText);
const result = await new Validator().validate(spec);
result.valid ? ok('openapi.yaml validates against the OpenAPI ' + spec.openapi + ' schema')
  : bad('openapi.yaml schema', JSON.stringify(result.errors).slice(0, 800));

const table = process.argv[2]
  ? fs.readFileSync(process.argv[2], 'utf8')
  : execFileSync(process.env.QA_PHP || 'php', ['spark', 'routes'], { cwd: root, encoding: 'utf8' });
// Rows look like: | GET | api/v1/staff/orders/([0-9]+) | » | handler | filters | filters |
const routes = [];
for (const line of table.split(/\r?\n/)) {
  const cells = line.split('|').map((c) => c.trim());
  if (cells.length > 3 && /^api\/v1\//.test(cells[2])) {
    routes.push(cells[1].toLowerCase() + ' ' + cells[2].slice('api/v1'.length).replace(/\([^)]*\)/g, '{}'));
  }
}
routes.length > 0 ? ok(routes.length + ' api/v1 routes found in spark routes') : bad('spark routes', 'no api/v1 routes parsed');

const documented = new Set();
for (const [p, item] of Object.entries(spec.paths || {})) {
  for (const method of Object.keys(item)) {
    if (['get', 'post', 'put', 'patch', 'delete'].includes(method)) documented.add(method + ' ' + p.replace(/\{[^}]+\}/g, '{}'));
  }
}
const missing = routes.filter((r) => !documented.has(r));
missing.length === 0 ? ok('every route is documented') : bad('routes missing from openapi.yaml', missing.join(', '));
const routeSet = new Set(routes);
const stale = [...documented].filter((d) => !routeSet.has(d));
stale.length === 0 ? ok('every documented operation exists as a route') : bad('operations without a route', stale.join(', '));

console.log(pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
