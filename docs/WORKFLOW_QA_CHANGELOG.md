# Workflow QA Change Log

## 2026-10-04 — Dedicated QA branch

Branch: `qa/workflow-cleanup`

### Latest failure reviewed
- Runtime QA run: `37197973474`
- Browser job: `111423662863`
- Database bootstrap, PHP setup, web startup, and HTTP smoke tests passed.
- Browser QA failed with `chrome-error://chromewebdata/`.
- Chromium installation succeeded.
- Server log showed PHP remained healthy and accepted connections.

### Changes made
1. Added this dedicated branch so workflow/runtime fixes remain isolated from `main`.
2. Added a minimal Chromium-to-localhost probe using Python's static HTTP server on port 8090.
3. Changed the PHP development server bind address from `127.0.0.1` to `0.0.0.0` for runner/browser compatibility.
4. Changed browser target from `127.0.0.1` to `localhost`.
5. Explicitly disabled Chromium proxy resolution and removed proxy environment variables.
6. Added browser screenshots and probe logs to the workflow artifact.
7. Updated `actions/checkout` from v4 to v5 to remove the GitHub Actions Node.js 20 warning.
8. Added the new branch to the workflow push trigger.

### Application-code policy
No application PHP, JavaScript, CSS, database schema, controller, model, view, or business-logic files were changed. All changes are QA/workflow configuration only.

### Next verification
Run the complete Runtime QA workflow on this branch. The loopback probe will distinguish a Chromium/runner networking problem from a PHP/CodeIgniter browser-request problem.

## Follow-up: Probe setup correction

The first isolated probe attempt exposed a QA-script issue: the probe ran in a separate workflow step before the temporary Playwright directory was created. The probe step now installs Playwright/Chromium itself before testing loopback, making the diagnostic self-contained.


## Root cause found

The Chromium loopback probe passed, proving the runner and Chromium could reach localhost. The application login page then failed because the repository `env` file contains an active legacy `app.baseURL` value (`http://localhost:180/LPG/public/`). Appending a second value in the QA workflow did not override the first value. The rendered login form therefore posted to the legacy port instead of the test server.

### Fix
The QA workflow now replaces the active `app.baseURL` and `app_baseURL` values in the copied `.env` before adding database/test settings. This is QA configuration only; the repository application configuration files remain unchanged.


## Application behavior fix

The browser test reached the dashboard successfully after the base URL fix, but the dashboard opened on **Current Stock** by default. Review of `app/Views/dashboard/index.php` showed the default `stockTab` was being forced to `filled` when no `stock_tab` query parameter was supplied. This made the requested Summary tab inaccessible as the default view.

### Fix on QA branch
- Changed the default dashboard tab to Summary (`stock_tab = ''` when no valid stock tab is requested).
- Strengthened browser QA to verify the five Summary metrics, switch to Current Stock, verify Filled/Empty/Issued cards, return to Summary, then continue Configuration and scrollbar checks.
- This is the only application file changed for this behavior fix: `app/Views/dashboard/index.php`.


## Follow-up: summary-tab render exception

After changing the default to Summary, the browser test exposed a second view-layer issue: the stock pane still indexed `$stock['']` while it was hidden, producing a CodeIgniter `ErrorException: Undefined array key ""`.

### Fix
Added a separate safe stock display key that falls back to `filled` for the hidden stock pane while keeping the active dashboard tab as Summary. This preserves the requested default without changing stock data behavior.


## Follow-up: remaining fallback-key typo

The next browser run found one remaining reference to the old empty stock key in the hidden-pane empty-state message. It was corrected to use the same safe display stock key. No additional application files were changed.


## Final warning cleanup

The green runtime run still showed non-fatal dependency/action warnings, so these were cleaned up on the QA branch:
- Added `**/ThirdParty/**` to Composer's classmap exclusions to stop Composer scanning CodeIgniter's bundled third-party classes under the framework's `CodeIgniter\\` PSR-4 rule.
- Refreshed `composer.lock`'s content hash to match the current dependency definition.
- Updated `actions/checkout` to v5 in CI (Node 24).
- Updated `actions/upload-artifact` to v7 in Runtime QA (Node 24).

MySQL image startup warnings are container-image diagnostics (deprecated MySQL startup flag, self-signed test CA, insecure test pid path, and missing optional timezone files); they do not indicate an application failure and are not application configuration defects.

## Final green validation target

The branch must finish with: HTTP runtime pass, Chromium loopback pass, browser UI pass, PHPUnit pass, and no actionable Composer/action warnings.
