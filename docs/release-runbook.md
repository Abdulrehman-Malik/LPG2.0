# Release Runbook — Back Office (Phase 12.4)

How to take a back-office release from `main` to a live shop safely. This builds on `docs/railway-deployment.md`, which covers platform variables, and `docs/first-install-setup.md`, which covers new installations.

## 1. Before the release

| # | Step | Owner |
|---|---|---|
| 1 | GitHub Actions on the release commit: **LPG CI** (schema, migrations, PHPUnit) and **LPG Runtime QA** (Playwright) both green. | Developer |
| 2 | Full QA run on a disposable database: `bash tests/qa/run_all.sh` (see `tests/qa/README.md`). Record the summary in `SQA.md`. | Developer / SQA |
| 3 | List the new migrations since the last release (`database/migrations/README.md`). Every migration is idempotent and safe to re-run. | Developer |
| 4 | Agree a time outside shop hours. Tell branch staff that cash sessions must be **closed** before the release. | Owner |
| 5 | Confirm that the production `.env` / platform variables are unchanged, and that `CI_ENVIRONMENT=production` is set. | Owner |

## 2. Backup (mandatory)

1. Sign in as a System Administrator and open **Configuration → Database Backup & Restore**.
2. Create a backup and **download** it. The file saved on the server is not an independent copy, because Railway service files can be lost on redeploy.
3. Store the downloaded `.sql` somewhere outside the server, such as a secure drive or Google Drive (the backup page can upload there when configured).
4. Note the file name and its size in the release log.

## 3. Deploy

1. Deploy the release commit. On Railway, push or redeploy `main`; on XAMPP, `git pull` in the application folder.
2. Open the login page. If migrations are pending, the page lists them and blocks sign-in.
3. Run the pending migrations from the login page with System Administrator credentials (the migration form), or with `php spark migrations:run` on a server with shell access.
4. The login page must report that all migrations are up to date. Sign in.

## 4. Smoke test (about 10 minutes)

| Check | Expected |
|---|---|
| Dashboard | Loads; figures for today; no "Migration Required" banner |
| Counter Cash | Open a session with the counted float |
| POS | Post a small walk-in cash gas sale; print the receipt (80 mm) |
| Sale History | The sale is listed; open its details |
| Customer ledger | Open one credit customer; the statement's closing balance equals the current balance |
| Inventory | Gas KG reduced by the sale |
| Branch switch | A Head Office user can switch branches; the Business Hierarchy screen shows all branches |
| Audit Log | The sale and cash entries are recorded |

Void the smoke-test sale from Sale History, with the reason "release smoke test", so it doesn't count.

## 5. Rollback

Roll back only if the smoke test fails in a way that blocks trading.

1. **Code only, no migrations in the release:** redeploy the previous commit (`git revert` the release, or redeploy the previous Railway deployment). No data step is needed.
2. **Release included migrations:** the migrations only **add** tables, columns or rows, and older code ignores them. Redeploying the previous commit is usually enough, so leave the database as it is.
3. **Data damaged:** open Database Backup & Restore and restore the backup downloaded in step 2. The restore first takes its own safety backup. Then redeploy the previous commit. Transactions posted after the backup are lost and must be re-entered from paper or receipts, so restoring is the last resort.
4. Record what happened in `SQA.md` and open a defect.

## 6. Backup and restore drill

A restore that was never tested is not a backup. Repeat this drill on a **non-production** database every quarter and after any change to the backup feature:

1. Create test data, or use the QA fixtures: `bash tests/qa/reset_db.sh` then `bash tests/qa/verify_hq.sh`.
2. Record row counts and table checksums.
3. Create and download a backup in the app.
4. Change data: delete some sales and edit a customer.
5. Restore the downloaded file in the app.
6. Compare row counts and checksums with step 2. They must match.

The latest drill result is recorded in `SQA.md` (P12-01).

## 7. After the release

- Watch the first trading hour: Dashboard alerts, cash session, no error reports from counters.
- Keep the pre-release backup for at least 30 days.
- Update `DEVELOPMENT_PLAN.md` and `SQA.md` with the release commit and smoke-test result.
