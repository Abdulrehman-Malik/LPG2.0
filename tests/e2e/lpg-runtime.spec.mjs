import { test, expect } from '@playwright/test';

test.use({ baseURL: process.env.LPG_QA_BASE_URL || 'http://localhost:8080' });
async function login(page) {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.locator('input[name="login"], #login').first().fill('admin');
  await page.locator('input[name="password"], #password').first().fill('admin123');
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/dashboard/);
}
test.describe.configure({ mode: 'serial' });

test('E2E-001 Login succeeds', async ({ page }) => {
  await login(page); await expect(page.locator('body')).toContainText('Dashboard');
});
test('E2E-002 Dashboard business summary loads', async ({ page }) => {
  await login(page);
  const body = await page.locator('body').innerText();
  for (const label of ['Available Gas KG','Empty Gas Cylinders in Shop','Issued Cylinders','Sales','Cash Counter','Credit Sales / Accounts Receivable','Expenses']) expect(body.toLowerCase()).toContain(label.toLowerCase());
});
test('E2E-003 POS screen exposes configured gas-sale transaction', async ({ page }) => {
  await login(page); await page.goto('/sales',{waitUntil:'networkidle'});
  await expect(page.locator('#transactionType')).toHaveValue('gas_sale');
  await expect(page.locator('#lines tbody .cyl')).toHaveCount(1);
  await expect(page.locator('#saveBtn')).toContainText('Post Transaction');
});


test('E2E-009 Inventory adjustment screen is usable and enforces shop-available cylinder choices', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/inventory/adjustments',{waitUntil:'networkidle'});
  await expect(page.locator('h4')).toContainText('Stock Adjustment');

  // New Adjustment and How It Works tabs.
  await expect(page.locator('[data-adjust-panel="new"]')).toBeVisible();
  await page.locator('[data-adjust-tab="guide"]').click();
  await expect(page.locator('[data-adjust-panel="guide"]')).toBeVisible();
  await page.locator('[data-adjust-tab="new"]').click();
  await expect(page.locator('[data-adjust-panel="new"]')).toBeVisible();

  // Color legend is present for gas, filled and empty stock.
  await expect(page.locator('.stock-color-box.gas')).toHaveCount(1);
  await expect(page.locator('.stock-color-box.filled')).toHaveCount(1);
  await expect(page.locator('.stock-color-box.empty')).toHaveCount(1);

  // Visible stock numbers use two decimal places.
  const summaryNumbers = await page.locator('.row.g-2.mb-3 .fs-5').allInnerTexts();
  for (const value of summaryNumbers) expect(value).toMatch(/\d+\.\d{2}(?: KG)?$/);

  // Every cylinder type offered for adjustment has shop stock.
  const typeOptions = await page.locator('#adjustCylinderType option').evaluateAll(opts =>
    opts.filter(o => o.value).map(o => ({ text: o.textContent || '', shop: (o.textContent || '').match(/· (\d+) in shop/)?.[1] }))
  );
  for (const option of typeOptions) expect(Number(option.shop || 0)).toBeGreaterThan(0);

  // Physical-cylinder selection contains only cylinders currently in the shop.
  await page.locator('#adjustType').selectOption('filled_cylinder');
  const typeValue = await page.locator('#adjustCylinderType option[value]').first().getAttribute('value');
  if (typeValue) {
    await page.locator('#adjustCylinderType').selectOption(typeValue);
    const visibleUnits = await page.locator('#sourceCylinder option').evaluateAll(opts =>
      opts.filter(o => o.value && !o.hidden).map(o => o.textContent || '')
    );
    for (const text of visibleUnits) expect(text).not.toMatch(/\bcustody\b|\bissued\b/i);
  }

  // Stock Visibility tab is available and readable.
  await page.locator('[data-history-tab="visibility"]').click();
  await expect(page.locator('[data-history-panel="visibility"]')).toBeVisible();
  await expect(page.locator('[data-history-panel="visibility"] table')).toBeVisible();

  expect(pageErrors).toEqual([]);
});

test('E2E-010 Dashboard summary cards drill down and preserve stock tab', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/dashboard',{waitUntil:'networkidle'});

  for (const label of [
    'Available Gas KG','Empty Gas Cylinders in Shop','Issued Cylinders','Sales',
    'Cash Counter','Credit Sales / Accounts Receivable','Expenses'
  ]) await expect(page.locator('.dashboard-card').filter({hasText:label}).first()).toBeVisible();

  const checks = [
    ['Available Gas KG','Available Gas KG — Cylinder Type & Individual Cylinders'],
    ['Empty Gas Cylinders in Shop','Empty Gas Cylinders in Shop — Type & Individual Cylinders'],
    ['Issued Cylinders','Issued Cylinders — Customers & Cylinder Details'],
    ['Sales','Sales — Payment & Transaction Details'],
    ['Cash Counter','Cash Counter — Register In / Out Movements'],
    ['Credit Sales / Accounts Receivable','Credit Sales / Accounts Receivable — Customer OS'],
    ['Expenses','Expenses — Category & Transaction Details']
  ];
  for (const [card,title] of checks) {
    await page.locator('.dashboard-card').filter({hasText:card}).first().click();
    await expect(page.locator('#dashboardDetailModal')).toBeVisible();
    await expect(page.locator('#dashboardDetailTitle')).toHaveText(title);
    await page.locator('#dashboardDetailModal .btn-close').click();
  }

  // Date filter is hidden by default; open it and exercise the actual From/To + Apply workflow.
  await page.getByRole('button', {name:'Show Date Filter'}).click();
  await expect(page.locator('#dashboardDateFilter')).toBeVisible();

  const toDate = new Date();
  const fromDate = new Date(toDate);
  fromDate.setDate(fromDate.getDate() - 6);
  const isoDate = (date) => date.toISOString().slice(0, 10);
  await page.locator('#dashboardDateFilter input[name="from"]').fill(isoDate(fromDate));
  await page.locator('#dashboardDateFilter input[name="to"]').fill(isoDate(toDate));
  await page.getByRole('button', {name:'Apply Filter'}).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('.dashboard-card').filter({hasText:'Sales'}).first()).toBeVisible();

  await page.locator('#stock-tab').click();
  await expect(page.locator('#stock-pane')).toBeVisible();
  await expect(page.locator('#stock-pane')).toContainText('Current Stock Details');

  expect(pageErrors).toEqual([]);
});

test('E2E-003a POS transaction type switch rebuilds isolated detail UI without page errors', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') pageErrors.push(message.text());
  });

  await login(page);
  await page.goto('/sales',{waitUntil:'networkidle'});

  await expect(page.locator('#transactionType')).toHaveValue('gas_sale');
  await expect(page.locator('#lines thead')).toContainText('Qty / KG');
  await expect(page.locator('#newSaleTab')).toHaveCount(0);
  await expect(page.locator('#saleHistoryTab')).toHaveCount(0);
  await expect(page.locator('#saleHistoryPanel')).toHaveCount(0);
  await expect(page.locator('#lines thead')).toContainText('Gas Rate');

  await page.locator('#transactionType').selectOption('cylinder_sale');
  await expect(page.locator('#lines thead')).toContainText('Status');
  await expect(page.locator('#lines thead')).toContainText('Cylinder Rate');
  await expect(page.locator('#lines .cylStatus')).toHaveCount(1);
  await expect(page.locator('#gasEntryModeWrap')).toBeHidden();

  await page.locator('#transactionType').selectOption('security_deposit');
  await expect(page.locator('#securityTransaction')).toBeVisible();
  await expect(page.locator('#standardTransaction')).toBeHidden();
  await expect(page.locator('#securityDeposit')).toBeEnabled();

  await page.locator('#transactionType').selectOption('cylinder_return');
  await expect(page.locator('#returnTransaction')).toBeVisible();
  await expect(page.locator('#securityTransaction')).toBeHidden();
  await expect(page.locator('#standardTransaction')).toBeHidden();

  await page.locator('#transactionType').selectOption('gas_sale');
  await expect(page.locator('#lines thead')).toContainText('Qty / KG');
  await expect(page.locator('#lines tbody .cyl')).toHaveCount(1);

  await page.getByRole('link', { name: 'Sale History' }).click();
  await expect(page).toHaveURL(/\/sales\/history/);
  await expect(page.locator('#saleHistoryPanel')).toBeVisible();
  await expect(page.locator('#historySearch')).toBeVisible();

  expect(pageErrors).toEqual([]);
});
test('E2E-004 Actual gas sale posts successfully', async ({ page }) => {
  await login(page); await page.goto('/sales',{waitUntil:'networkidle'});
  await page.locator('#transactionType').selectOption('gas_sale');
  await page.locator('#customer_id').selectOption({label:'QA-E2E-CUST — QA E2E Customer'});
  const line=page.locator('#lines tbody tr').first();
  await line.locator('.cyl').selectOption({label:'C6 — 6 kg'});
  await expect(line.locator('.sourceCyl')).toBeEnabled();
  await line.locator('.sourceCyl').selectOption({label:/QA-E2E-C6-001/});
  await line.locator('.entryValue').fill('2');
  const total=await page.locator('#saleTotal').innerText();
  expect(Number(total)).toBeGreaterThan(0);
  await page.locator('.payment .amount').first().fill(total);
  await page.locator('#saveBtn').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(/posted successfully/i);
});
test('E2E-005 Posted gas sale appears in Sale History with inventory movement', async ({ page }) => {
  await login(page); await page.goto('/sales/history',{waitUntil:'networkidle'});
  await expect(page.locator('#saleHistoryPanel')).toBeVisible();
  await page.locator('#historyType').selectOption('gas_sale');
  await page.locator('#historyCustomer').fill('QA-E2E-CUST');
  await page.locator('#historySearch').click(); await page.waitForTimeout(500);
  const row=page.locator('#saleHistoryBody tr.sale-history-row').first();
  await expect(row).toBeVisible(); await expect(row).toContainText('QA-E2E-CUST');
  await expect(row).toContainText(/Gas Sale/i); await expect(row).toContainText(/POSTED/i);
  await row.click();
  await expect(page.locator('#saleDetailsBody')).toContainText(/Inventory \/ Reversal Movements/i);
  await expect(page.locator('#saleDetailsBody')).toContainText(/gas_kg/i);
  await expect(page.locator('#saleDetailsBody')).toContainText(/out/i);
});
test('E2E-006 Gas stock is reduced after the actual sale', async ({ page }) => {
  await login(page); await page.goto('/inventory',{waitUntil:'networkidle'});
  const row=page.locator('table tbody tr').filter({hasText:'C6'}).first();
  await expect(row).toBeVisible(); await expect(row).toContainText('4.000 KG');
});
test('E2E-007 POS rejects an invalid zero-quantity gas sale without posting', async ({ page }) => {
  await login(page); await page.goto('/sales',{waitUntil:'networkidle'});
  const line=page.locator('#lines tbody tr').first();
  await line.locator('.cyl').selectOption({label:'C6 — 6 kg'}); await line.locator('.entryValue').fill('0');
  await page.locator('.payment .amount').first().fill('0'); await page.locator('#saveBtn').click();
  await expect(page.locator('#posValidationAlert')).toBeVisible();
  await expect(page.locator('#posValidationAlert')).toContainText(/valid quantity|invalid/i);
  await expect(page).toHaveURL(/\/sales$/);
});
test('E2E-008 Configuration navigation remains available after POS regression', async ({ page }) => {
  await login(page); await page.getByText('Configuration',{exact:false}).first().click();
  await page.waitForTimeout(300); await expect(page.locator('body')).toContainText(/configuration/i);
});


test('E2E-011 All application screens load without HTTP, PHP, or browser errors', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });
  await login(page);

  const screens = [
    '/dashboard', '/shop-settings', '/sales', '/sales/history', '/cash', '/cash/history',
    '/purchases', '/inventory', '/inventory/adjustments', '/inventory/controls', '/inventory/wastage',
    '/expenses', '/receipts', '/supplier-payments', '/reports', '/reports/inventory-detail',
    '/reports/custody', '/audit', '/customers', '/suppliers', '/cylinder-types', '/rates',
    '/users', '/inventory/opening'
  ];

  for (const path of screens) {
    const response = await page.goto(path, { waitUntil: 'networkidle' });
    console.log('SCREEN_QA:', path, response?.status());
    expect(response, path).not.toBeNull();
    const bodyText = await page.locator('body').innerText();
    if (response.status() >= 400) throw new Error('SCREEN_QA_HTTP_FAILED ' + path + ' status=' + response.status() + '\n' + bodyText.slice(0, 5000));
    const errorMatch = bodyText.match(/(Whoops!|Exception|Fatal error|Undefined variable|Call to undefined|Database Error)/i);
    if (errorMatch) throw new Error('SCREEN_QA_FAILED ' + path + ': ' + errorMatch[0] + '\n' + bodyText.slice(0, 1200));
  }

  expect(pageErrors).toEqual([]);
});

test('E2E-012 Receipt History shows POS-generated payment with its source', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/receipts', { waitUntil: 'networkidle' });
  await expect(page.locator('h4')).toContainText('Customer Receipts');
  const history = page.locator('table.datatable').last();
  await expect(history.locator('thead')).toContainText('Source');
  await expect(history.locator('thead')).toContainText('Status');
  const posRow = history.locator('tbody tr').filter({ hasText: /POS — gas sale \/ Sale Payment/i }).first();
  await expect(posRow).toBeVisible();
  await expect(posRow).toContainText(/QA-E2E-CUST|QA E2E Customer/i);
  await expect(posRow).toContainText(/CASH|CHEQUE|ONLINE/i);
  await expect(posRow).toContainText(/POSTED/i);
  expect(pageErrors).toEqual([]);
});

test('E2E-013 Customer Ledger receipt history shows POS source and payment details', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/customers', { waitUntil: 'networkidle' });
  const customerRow = page.locator('tr').filter({ hasText: 'QA-E2E-CUST' }).first();
  await expect(customerRow).toBeVisible();
  await customerRow.getByRole('link', { name: 'Ledger' }).click();
  await expect(page).toHaveURL(/\/customers\/ledger\/\d+/);
  await page.locator('[data-bs-target="#customerReceipts"]').click();
  const history = page.locator('#customerReceipts table.datatable');
  await expect(history.locator('thead')).toContainText('Source');
  await expect(history.locator('thead')).toContainText('Reference');
  await expect(history.locator('thead')).toContainText('Details');
  const posRow = history.locator('tbody tr').filter({ hasText: /POS — gas sale \/ Sale Payment/i }).first();
  await expect(posRow).toBeVisible();
  await expect(posRow).toContainText(/CASH|CHEQUE|ONLINE/i);
  await expect(posRow).toContainText(/POSTED/i);
  expect(pageErrors).toEqual([]);
});
