# Back-Office UI Design System (Phase 11)

Reference for redesigning screens. Read this instead of re-auditing the layout.

## Where things live

| Concern | File |
|---|---|
| Layout (sidebar, top bar, flash messages, confirm modal, toast container) | `app/Views/layouts/app.php` |
| Menu items, permissions, active entry, breadcrumb | `app/Libraries/Navigation.php` |
| Branch appearance settings → CSS variables (font, size, brand/accent colour, readable text colours) | `app/Libraries/AppTheme.php` |
| Tokens, Bootstrap overrides, components, shell | `public/assets/css/app.css` |
| Shell behaviour, `LPG.toast()`, `LPG.confirm()`, `data-confirm`, double-submit guard | `public/assets/js/app.js` |
| Libraries (no CDN) | `public/assets/vendor/` — Bootstrap 5.3.3, Bootstrap Icons 1.11.3, jQuery 3.7.1, DataTables 1.13.8, Inter (variable), Chart.js 4.4.1 |

Assets are linked with `?v=<filemtime>`; `public/.htaccess` lets Apache cache them for 30 days.

## Theming
- Branch settings (Shop Settings → Appearance) set the defaults: theme, font, font size, primary (sidebar) colour and accent colour. "Inter (default)" is the stored value `system`.
- Each user can switch Light / Dark / Match device from the account menu. The choice is stored in the browser (`localStorage.lpgTheme`).
- Dark mode is Bootstrap's `data-bs-theme="dark"`. Use the tokens, not fixed colours, so both themes work: `--lpg-surface`, `--lpg-surface-2`, `--lpg-border`, `--lpg-text`, `--lpg-muted`, `--lpg-accent`, `--lpg-link`, and `--lpg-success|warning|danger|info` with their `-soft` backgrounds.
- `btn-primary` is the accent colour. Text on it is chosen automatically for contrast (`--lpg-accent-contrast`). Links use a darker accent shade that meets 4.5:1 on white.

## Components

```html
<!-- Page header -->
<div class="page-header">
  <div><h1 class="page-title">Expenses</h1><p class="page-subtitle">One line on what this page is for.</p></div>
  <div class="page-actions"><a class="btn btn-primary" href="…"><i class="bi bi-plus-lg me-1"></i>Add expense</a></div>
</div>

<!-- KPI tile (variants: is-success, is-warning, is-danger, is-info) -->
<div class="kpi is-success"><div class="kpi-label">Sales today <i class="bi bi-cart-check"></i></div>
  <div class="kpi-value">Rs. 12,500</div><div class="kpi-hint">14 sales</div></div>

<!-- Status badge: status-success | status-warning | status-danger | status-info | (neutral) -->
<span class="status status-success">Active</span>

<!-- Form section inside a long form -->
<div class="form-section"><h2 class="form-section-title">Payment</h2><p class="form-section-hint">…</p> …fields… </div>

<!-- Required field marker -->
<label class="form-label required" for="amount">Amount</label>

<!-- Empty state -->
<div class="empty-state"><i class="bi bi-inbox"></i><div class="empty-state-title">No expenses yet</div>Add the first one with “Add expense”.</div>

<!-- Confirm before submitting (replaces onsubmit="return confirm(...)") -->
<form method="post" data-confirm="Void purchase P-0001? Stock and ledgers will be reversed." data-confirm-ok="Void purchase">…</form>
```

- Toast from JavaScript: `LPG.toast('Saved', 'success')`. The types are success, danger, warning and info.
- Confirm from JavaScript: `await LPG.confirm('Message', {title, okLabel, danger: true})` resolves to true or false.
- Tables: inside a `.card`, use `<div class="card-body table-responsive">` (the padding is handled). Add the class `datatable` for search and paging. Numeric columns get `text-end`. Numbers use tabular figures automatically.
- POST forms show a spinner on the submit button and block double submission. Forms that post with `fetch` (e.g. POS) are unaffected. Opt out with `data-no-loading`.

## Page conventions
- The top bar already shows the page `title` and the breadcrumb. The page header in the content adds the subtitle and the actions.
- Keep the logic in services and controllers. A redesign changes views (and view-only JS) unless a defect is found.
- Every page must work at 390 px width without horizontal page scroll. Wide tables scroll inside their container.
- After each screen: run the Playwright suite (`tests/e2e`), and check the page in light and dark mode at 1440 px and 390 px.
