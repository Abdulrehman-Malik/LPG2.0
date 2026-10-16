// Phase 11.6 accessibility audit: axe-core WCAG 2.1 A/AA on every menu page (light + dark) and login.
import { chromium } from '../../e2e/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const B = process.env.QA_BASE_URL || 'http://localhost:8080';
const axeSrc = fs.readFileSync(new URL('../../e2e/node_modules/axe-core/axe.min.js', import.meta.url), 'utf8');
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
const byRule = new Map();
async function audit(label) {
  await p.addScriptTag({ content: axeSrc });
  const res = await p.evaluate(async () => (await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } })).violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 4), count: v.nodes.length })));
  for (const v of res) {
    const r = byRule.get(v.id) || { impact: v.impact, help: v.help, pages: [], count: 0 };
    r.pages.push(label + ' (' + v.count + '): ' + v.nodes.join(' | '));
    r.count += v.count;
    byRule.set(v.id, r);
  }
  return res.length;
}
await p.goto(B + '/login', { waitUntil: 'networkidle' });
await audit('login');
await p.fill('input[name=login]', 'admin'); await p.fill('input[name=password]', 'admin123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
const links = await p.$$eval('#appNav a.app-nav-link', (as) => as.map((a) => a.href));
for (const theme of ['light', 'dark']) {
  await p.evaluate((t) => localStorage.setItem('lpgTheme', t), theme);
  for (const href of links) {
    const path = href.replace(B + '/', '');
    if (ONLY && !ONLY.includes(path)) continue;
    await p.goto(href, { waitUntil: 'networkidle' });
    await audit(theme + ' ' + path);
  }
}
await p.evaluate(() => localStorage.setItem('lpgTheme', 'light'));
const rows = [...byRule.entries()].sort((a, b) => b[1].count - a[1].count);
console.log(`RULES WITH VIOLATIONS: ${rows.length}`);
for (const [id, r] of rows) {
  console.log(`\n[${r.impact}] ${id} — ${r.help} — ${r.count} node(s) on ${r.pages.length} page(s)`);
  r.pages.slice(0, 5).forEach((x) => console.log('   ' + x.slice(0, 300)));
}
await browser.close();
