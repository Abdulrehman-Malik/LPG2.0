# Back-office QA suites (`tests/qa`)

These are the runtime checks behind the SQA evidence in `SQA.md`. They drive the running application over HTTP and in a real browser, and compare what it shows against independent SQL. Together with `tests/e2e/lpg-runtime.spec.mjs` and PHPUnit, they are the regression gate for back-office changes. This is plan item 8.3.6.

> **They write to the database.** Run them only against a disposable QA database. `env.sh` refuses to run against `perfect_lpg`, `pak_gas` or `production*`.

## Setup

1. Create an empty database for QA, e.g. `lpg_qa`.
2. Serve the app against it. For example:
   ```bash
   LPG_DB_NAME=lpg_qa LPG_DB_HOST=127.0.0.1 LPG_DB_USER=root LPG_DB_PASS= CI_ENVIRONMENT=development \
     php spark serve --host 127.0.0.1 --port 8080
   ```
   Set `app.baseURL` to the same address. Logins and cookies need the host to match.
3. In `tests/e2e`, run `npm install` and `npx playwright install chromium`. This installs Playwright and axe-core.
4. Configure the suites through environment variables; the defaults are in `env.sh`:

   | Variable | Default | Meaning |
   |---|---|---|
   | `QA_BASE_URL` | `http://localhost:8080` | App URL |
   | `QA_DB` | `lpg_qa` | Disposable QA database |
   | `QA_DB_HOST` / `QA_DB_PORT` / `QA_DB_USER` / `QA_DB_PASS` | `127.0.0.1` / `3306` / `root` / empty | MySQL connection |
   | `QA_MYSQL`, `QA_PHP` | `mysql`, `php` | Binaries. On XAMPP use `/c/xampp/mysql/bin/mysql.exe` and `/c/xampp/php/php.exe`. |
   | `QA_TMP` | system temp + `/lpg-qa` | Cookies, downloads and screenshots |

## Run

```bash
bash tests/qa/run_all.sh            # every suite, resetting the database between suites (about 25 minutes)
bash tests/qa/run_all.sh --no-e2e   # skip the Playwright runtime spec
bash tests/qa/run_all.sh --a11y     # also write an accessibility report to $QA_TMP/a11y-report.txt
bash tests/qa/reset_db.sh           # rebuild the QA database only: schema, migrations, fixtures
```

The run ends with a summary line per suite. A single suite can be run on its own after `reset_db.sh`, e.g. `bash tests/qa/verify_hq.sh` or `cd tests/qa/checks && node pos_check.mjs`.

## Suites

| Suite | What it proves | SQA |
|---|---|---|
| `verify_phase8.sh` | Migration endpoint protected; disabled users signed out; login throttling; voids blocked when stock moved; cash close and deposits locked correctly; credit limits use the posting branch with no session. It installs `commands/QaPhase8Credit.php` for one check and removes it again. | P8-01…P8-11 |
| `verify_phase9.sh` | Business settings, back-dating policy, business-owned master data, supplier scope | P9-01…P9-12 |
| `verify_transfers.sh` | Inter-branch stock transfers (one-step and two-step), isolation, reconciliation | P9-13…P9-20 |
| `verify_hq.sh` | Branch Overview figures against SQL, branch scope | P9-21…P9-22 |
| `verify_branch_admin.sh` | Branch access on the Users screen, cash-close and ledger scope fixes, sidebar | P9-23…P9-30 |
| `verify_business_flow.sh` | Opening stock, credit and part-paid purchases, supplier payable/payments, purchase void, Cylinder Sale, partial/full wastage, cylinder-type delete rule, both money-rounding rules (D10-2), separate vs single supplier balance (D9-4), §6 invariants | P12-02, P12-05 |
| `verify_pages.sh` | Every menu page and the drill-down/data pages return 200 with no PHP or database exception (run after `verify_hq.sh`); catches SQL that MySQL 8 rejects but MariaDB accepts | P12-04 |
| `checks/pos_check.mjs` | POS customer search, shortcuts, overpayment warning, F9 posting, 80 mm / A4 receipts | P11-11…P11-14 |
| `checks/history_check.mjs` | Sale History filters, details, void dialog | P11-15…P11-18 |
| `checks/cash_check.mjs` | Counter Cash figures against SQL; open, count and close | P11-19…P11-21 |
| `checks/hier_check.mjs` | Business Hierarchy tree, adding a shop, scope | P9-33…P9-37 |
| `checks/parties_check.mjs` | Customer and supplier balances and ledger statements against SQL (data: `fixtures/parties.sql`) | P11-22…P11-26 |
| `checks/shell_check.mjs` | Every menu page at 1440 and 390 px: HTTP 200, no console errors, no sideways scroll, one active menu entry; theme and phone menu | P11-01…P11-06 |
| `checks/a11y_check.mjs` | axe-core WCAG 2.1 A/AA audit of every menu page in light and dark mode (report only) | P11-32 |
