import { test, expect } from '@playwright/test';

test.use({ baseURL: process.env.LPG_QA_BASE_URL || 'http://localhost:8080' });
async function login(page) {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.locator('input[name="login"], #login').first().fill('admin');
  await page.locator('input[name="password"], #password').first().fill('admin123');
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');

  if (!/\/dashboard(?:[/?#]|$)/.test(page.url())) {
    const bodyText = (await page.locator('body').innerText().catch(() => '')).slice(0, 2000);
    throw new Error('QA login did not reach dashboard. URL=' + page.url() + '\\nPage text:\\n' + bodyText);
  }
  await expect(page).toHaveURL(/\/dashboard/);
}

async function selectOptionByTextPrefix(select, prefix) {
  const value = await select.locator('option').evaluateAll((options, textPrefix) => {
    const option = options.find(item => item.textContent.trim().startsWith(textPrefix));
    return option ? option.value : '';
  }, prefix);
  expect(value, 'No option begins with: ' + prefix).not.toBe('');
  await select.selectOption(value, { timeout: 5000 });
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
  await expect(page.locator('.page-title, h4').first()).toContainText('Stock Adjustment');

  // New Adjustment and How It Works tabs.
  await expect(page.locator('[data-adjust-panel="new"]')).toBeVisible();
  await page.locator('[data-adjust-tab="guide"]').click();
  await expect(page.locator('[data-adjust-panel="guide"]')).toBeVisible();

  // The guide panel displays all three explanatory color markers.
  await expect(page.locator('[data-adjust-panel="guide"] .stock-color-box.gas:visible')).toHaveCount(1);
  await expect(page.locator('[data-adjust-panel="guide"] .stock-color-box.filled:visible')).toHaveCount(1);
  await expect(page.locator('[data-adjust-panel="guide"] .stock-color-box.empty:visible')).toHaveCount(1);

  await page.locator('[data-adjust-tab="new"]').click();
  await expect(page.locator('[data-adjust-panel="new"]')).toBeVisible();

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
test('E2E-014 Credit limit is enforced without changing stock', async ({ page }) => {
  await login(page);
  await page.goto('/sales', { waitUntil: 'networkidle' });
  await page.locator('#transactionType').selectOption('gas_sale', { timeout: 5000 });
  // The source-cylinder selector is intentionally hidden in cylinder-count mode.
  await page.locator('#gasEntryMode').selectOption('quantity', { timeout: 5000 });
  await expect(page.locator('#customer_id')).toContainText('QA-E2E-CREDIT');
  await page.locator('#customer_id').selectOption({ label: 'QA-E2E-CREDIT — QA E2E Credit Customer' }, { timeout: 5000 });
  const line = page.locator('#lines tbody tr').first();
  const cylinderType = await line.locator('.cyl option').evaluateAll(options => {
    const option = options.find(item => item.textContent.trim().startsWith('C6 — 6 kg'));
    return option ? { value: option.value, label: option.textContent.trim() } : { value: '', label: '', available: options.map(item => item.textContent.trim()) };
  });
  expect(cylinderType.value, 'C6 cylinder type option was not available: ' + JSON.stringify(cylinderType.available || cylinderType.label)).not.toBe('');
  await line.locator('.cyl').selectOption(cylinderType.value, { timeout: 5000 });
  await expect(line.locator('.sourceCyl')).toBeEnabled({ timeout: 5000 });
  const sourceCylinderOption = await line.locator('.sourceCyl option').evaluateAll(options => {
    const option = options.find(item => item.textContent.includes('QA-E2E-C6-001'));
    return option
      ? { value: option.value, label: option.textContent.trim() }
      : { value: '', available: options.map(item => item.textContent.trim()) };
  });
  expect(sourceCylinderOption.value, 'QA-E2E-C6-001 source cylinder was not available: ' + JSON.stringify(sourceCylinderOption.available || sourceCylinderOption.label)).not.toBe('');
  await line.locator('.sourceCyl').selectOption(sourceCylinderOption.value, { timeout: 5000 });
  await line.locator('.entryValue').fill('1');
  const total = Number(await page.locator('#saleTotal').innerText());
  expect(total).toBeGreaterThan(50);
  const payment = page.locator('#payments .payment').first();
  await payment.locator('.mode').selectOption('credit');
  await payment.locator('.amount').fill(total.toFixed(2));
  await page.locator('#saveBtn').click();
  await expect(page.locator('#posValidationAlert')).toBeVisible();
  await expect(page.locator('#posValidationAlert')).toContainText(/customer credit limit exceeded/i);
  // Reload so the assertion reads persisted stock from the server, not the pre-submit DOM.
  await page.reload({ waitUntil: 'networkidle' });
  const refreshedLine = page.locator('#lines tbody tr').first();
  await selectOptionByTextPrefix(refreshedLine.locator('.cyl'), 'C6 — 6 kg');
  await expect(refreshedLine.locator('.sourceCyl')).toContainText('QA-E2E-C6-001');
  await expect(refreshedLine.locator('.sourceCyl')).toContainText('6.00 KG');
});

test('E2E-004 Actual gas sale posts successfully', async ({ page }) => {
  await login(page); await page.goto('/sales',{waitUntil:'networkidle'});
  await page.locator('#transactionType').selectOption('gas_sale');
  await page.locator('#customer_id').selectOption({label:'QA-E2E-CUST — QA E2E Customer'});
  const line=page.locator('#lines tbody tr').first();
  await selectOptionByTextPrefix(line.locator('.cyl'), 'C6 — 6 kg');
  await expect(line.locator('.sourceCyl')).toBeEnabled();
  await selectOptionByTextPrefix(line.locator('.sourceCyl'), 'QA-E2E-C6-001');
  await line.locator('.entryValue').fill('2');
  const total=await page.locator('#saleTotal').innerText();
  expect(Number(total)).toBeGreaterThan(0);
  await page.locator('.payment .amount').first().fill(total);
  // The POS submits asynchronously, then redirects to reset the form. The browser may
  // discard the response body once that navigation starts, so capture the authoritative
  // JSON response in a route handler before it is handed to the page.
  let resolvePost;
  const postCaptured = new Promise(resolve => { resolvePost = resolve; });
  await page.route('**/sales/save', async route => {
    const response = await route.fetch();
    const body = await response.text();
    resolvePost({ status: response.status(), body });
    await route.fulfill({ response, body });
  });
  await page.locator('#saveBtn').click();
  const postResponse = await postCaptured;
  await page.unroute('**/sales/save');
  expect(postResponse.status, 'POS save endpoint returned HTTP ' + postResponse.status).toBeLessThan(400);
  const postResult = JSON.parse(postResponse.body);
  expect(postResult.success, JSON.stringify(postResult)).toBe(true);
  expect(postResult.sale_no).toBeTruthy();
  expect(String(postResult.receipt_url)).toContain('/sales/receipt/');
  await expect(page).toHaveURL(/\/sales(?:[/?#]|$)/);
});
test('E2E-005 Posted gas sale appears in Sale History with inventory movement', async ({ page }) => {
  await login(page); await page.goto('/sales/history',{waitUntil:'networkidle'});
  await expect(page.locator('#saleHistoryPanel')).toBeVisible();
  await page.locator('#historyType').selectOption('gas_sale');
  await page.locator('#historyCustomer').fill('QA-E2E-CUST');
  await page.locator('#historySearch').click(); await page.waitForTimeout(500);
  const row=page.locator('#saleHistoryBody tr.sale-history-row').first();
  await expect(row).toBeVisible(); await expect(row).toContainText('QA E2E Customer');
  await expect(row).toContainText(/Gas Sale/i); await expect(row).toContainText(/POSTED/i);
  await row.click();
  await expect(page.locator('#saleDetailsBody')).toContainText(/Inventory \/ Reversal Movements/i);
  await expect(page.locator('#saleDetailsBody')).toContainText(/gas_kg/i);
  await expect(page.locator('#saleDetailsBody')).toContainText(/out/i);
});
test('E2E-006 The selected source cylinder gas is reduced after the actual sale', async ({ page }) => {
  await login(page); await page.goto('/inventory',{waitUntil:'networkidle'});
  const typeRow=page.locator('table tbody tr').filter({hasText:'C6'}).first();
  await expect(typeRow).toBeVisible();
  await typeRow.getByRole('link',{name:/View Cylinders/i}).click();
  await expect(page).toHaveURL(/inventory\/cylinders\/\d+/);
  const sourceRow=page.locator('table tbody tr').filter({hasText:'QA-E2E-C6-001'}).first();
  await expect(sourceRow).toBeVisible();
  await expect(sourceRow).toContainText('4.00 KG');
  await expect(sourceRow).toContainText('Partially Filled');
});
test('E2E-007 POS rejects an invalid zero-quantity gas sale without posting', async ({ page }) => {
  await login(page);
  await page.goto('/sales', { waitUntil: 'networkidle' });
  await page.locator('#gasEntryMode').selectOption('quantity', { timeout: 5000 });
  const line = page.locator('#lines tbody tr').first();
  await selectOptionByTextPrefix(line.locator('.cyl'), 'C6 — 6 kg');
  const sourceCylinder = line.locator('.sourceCyl');
  await expect(sourceCylinder).toBeEnabled({ timeout: 5000 });
  await selectOptionByTextPrefix(sourceCylinder, 'QA-E2E-C6-001');
  await line.locator('.entryValue').fill('0');
  await page.locator('.payment .amount').first().fill('0');
  await page.locator('#saveBtn').click();
  await expect(page.locator('#posValidationAlert')).toBeVisible();
  await expect(page.locator('#posValidationAlert')).toContainText(/valid quantity|invalid|greater than zero/i);
  await expect(page).toHaveURL(/sales$/);
});
test('E2E-008 Configuration navigation remains available after POS regression', async ({ page }) => {
  await login(page); await page.getByText('Configuration',{exact:false}).first().click();
  await page.waitForTimeout(300); await expect(page.locator('body')).toContainText(/configuration/i);
});


test('E2E-011 All application screens load without HTTP, PHP, or browser errors', async ({ page }) => {
  // 24 screens in one test; the single-threaded dev server serves every page and its local assets serially.
  test.setTimeout(150000);
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
  await expect(page.locator('.page-title, h4').first()).toContainText('Customer Receipts');
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
  // The URL changes when navigation commits; wait for the page scripts (Bootstrap tabs) first.
  await page.waitForLoadState('networkidle');
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


test('E2E-020 Business and shop administration renders for System Administrator', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/business-management', { waitUntil: 'networkidle' });
  await expect(page.locator('.page-title, h4').first()).toContainText('Businesses & Shops');
  await expect(page.locator('body')).toContainText('Shop locations');
  await expect(page.locator('form[action*="business-management/business/save"]')).toHaveCount(1);
  expect(pageErrors).toEqual([]);
});

test('E2E-021 Customer ordering catalog renders with separate business/shop controls', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/mobile-catalog', { waitUntil: 'networkidle' });
  await expect(page.locator('.page-title, h4').first()).toContainText('Customer Ordering Catalog');
  await expect(page.locator('body')).toContainText('Shop availability');
  await expect(page.locator('#product-sale-mode')).toBeVisible();
  expect(pageErrors).toEqual([]);
});

test('E2E-022 Customer App account review page renders safely', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/customer-app-accounts', { waitUntil: 'networkidle' });
  await expect(page.locator('.page-title, h4').first()).toContainText('Customer App Accounts');
  await expect(page.locator('body')).toContainText('Pending registration requests');
  await expect(page.locator('body')).toContainText('Customer App accounts');
  expect(pageErrors).toEqual([]);
});

test('E2E-023 Browser customer order queue renders without bypassing login or page permissions', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });

  await login(page);
  await page.goto('/customer-orders', { waitUntil: 'networkidle' });
  await expect(page.locator('.page-title, h4').first()).toContainText('Customer Orders');
  await expect(page.locator('#order-status')).toBeVisible();
  await expect(page.locator('body')).toContainText('No customer orders match this filter.');
  expect(pageErrors).toEqual([]);
});


test('E2E-024 Mobile API health, public registration discovery, and auth guards', async ({ request }) => {
  const health = await request.get('/api/v1/health');
  expect(health.status()).toBe(200);
  expect((await health.json()).status).toBe('ok');

  const shops = await request.get('/api/v1/customer/auth/shops');
  expect(shops.status()).toBe(200);
  const shopPayload = await shops.json();
  expect(Array.isArray(shopPayload.data?.shops)).toBe(true);

  const customerMe = await request.get('/api/v1/customer/me');
  expect(customerMe.status()).toBe(401);
  const staffMe = await request.get('/api/v1/staff/me');
  expect(staffMe.status()).toBe(401);

  const invalidLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'definitely-not-the-password' }
  });
  expect(invalidLogin.status()).toBe(401);
});

test('E2E-025 Staff API token refresh rotation and logout revocation', async ({ request }) => {
  const login = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(login.status()).toBe(200);
  const loginPayload = await login.json();
  const originalTokens = loginPayload.data?.tokens;
  expect(originalTokens?.access_token).toMatch(/^[a-f0-9]{64}$/);
  expect(originalTokens?.refresh_token).toBeTruthy();

  const auth = token => ({ Authorization: 'Bearer ' + token });
  const me = await request.get('/api/v1/staff/me', { headers: auth(originalTokens.access_token) });
  expect(me.status()).toBe(200);

  const refreshed = await request.post('/api/v1/staff/auth/refresh', {
    data: { refresh_token: originalTokens.refresh_token }
  });
  expect(refreshed.status()).toBe(200);
  const rotatedTokens = (await refreshed.json()).data?.tokens;
  expect(rotatedTokens?.access_token).toMatch(/^[a-f0-9]{64}$/);
  expect(rotatedTokens.access_token).not.toBe(originalTokens.access_token);

  const rotatedMe = await request.get('/api/v1/staff/me', {
    headers: auth(rotatedTokens.access_token)
  });
  expect(rotatedMe.status()).toBe(200);

  const logout = await request.post('/api/v1/staff/auth/logout', {
    headers: auth(rotatedTokens.access_token)
  });
  expect(logout.status()).toBe(200);

  const afterLogout = await request.get('/api/v1/staff/me', {
    headers: auth(rotatedTokens.access_token)
  });
  expect(afterLogout.status()).toBe(401);

  // Reusing a consumed refresh token must revoke that token family.
  const secondLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(secondLogin.status()).toBe(200);
  const secondTokens = (await secondLogin.json()).data?.tokens;
  const secondRefresh = await request.post('/api/v1/staff/auth/refresh', {
    data: { refresh_token: secondTokens.refresh_token }
  });
  expect(secondRefresh.status()).toBe(200);
  const secondRotated = (await secondRefresh.json()).data?.tokens;
  const replayedRefresh = await request.post('/api/v1/staff/auth/refresh', {
    data: { refresh_token: secondTokens.refresh_token }
  });
  expect(replayedRefresh.status()).toBe(401);
  const replayRevokedSession = await request.get('/api/v1/staff/me', {
    headers: auth(secondRotated.access_token)
  });
  expect(replayRevokedSession.status()).toBe(401);
});

test('E2E-026 Customer signup approval, shop-scoped catalog, and idempotent order lifecycle', async ({ request }) => {
  const staffLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(staffLogin.status()).toBe(200);
  const staffData = (await staffLogin.json()).data;
  const staffToken = staffData.tokens.access_token;
  const shopId = Number(staffData.current_shop_id);
  expect(shopId).toBeGreaterThan(0);
  const staffHeaders = {
    Authorization: 'Bearer ' + staffToken,
    'X-Shop-Id': String(shopId)
  };

  const publicShopsResponse = await request.get('/api/v1/customer/auth/shops');
  expect(publicShopsResponse.status()).toBe(200);
  const publicShops = (await publicShopsResponse.json()).data?.shops || [];
  expect(publicShops.some(shop => Number(shop.id) === shopId)).toBe(true);

  const unique = Date.now().toString();
  const username = 'qa_mobile_' + unique;
  const password = 'QA-Mobile-Strong-123!';
  const signup = await request.post('/api/v1/customer/auth/signup', {
    data: {
      shop_id: shopId,
      username,
      password,
      customer_name: 'QA Mobile Customer',
      phone: '03000000000',
      city: 'Lahore',
      address: '1 QA Test Street'
    }
  });
  expect(signup.status()).toBe(202);
  const signupData = await signup.json();
  expect(signupData.status).toBe('pending_approval');
  expect(signupData.request_id).toBeTruthy();

  const approval = await request.post('/api/v1/staff/customer-signups/' + signupData.request_id + '/approve', {
    headers: staffHeaders,
    data: {}
  });
  expect(approval.status()).toBe(201);

  const customerLogin = await request.post('/api/v1/customer/auth/login', {
    data: { username, password }
  });
  expect(customerLogin.status()).toBe(200);
  const customerTokens = (await customerLogin.json()).data?.tokens;
  expect(customerTokens?.access_token).toMatch(/^[a-f0-9]{64}$/);
  const customerHeaders = { Authorization: 'Bearer ' + customerTokens.access_token };

  const me = await request.get('/api/v1/customer/me', { headers: customerHeaders });
  expect(me.status()).toBe(200);
  const customer = (await me.json()).data?.customer;
  expect(Number(customer.shop.id)).toBe(shopId);
  expect(Number(customer.business.id)).toBeGreaterThan(0);

  // Exercise the business/shop configuration and catalog services used by the staff app.
  for (const url of [
    '/api/v1/staff/configuration/application',
    '/api/v1/staff/configuration/businesses/' + customer.business.id,
    '/api/v1/staff/configuration/shops/' + shopId,
    '/api/v1/staff/businesses',
    '/api/v1/staff/businesses/' + customer.business.id + '/shops',
    '/api/v1/staff/businesses/' + customer.business.id + '/catalog',
    '/api/v1/staff/shops/' + shopId + '/products'
  ]) {
    const response = await request.get(url, { headers: staffHeaders });
    expect(response.status(), 'GET ' + url).toBe(200);
  }

  // Provision a second shop under the same business and verify default shop records are created.
  const secondShopName = 'QA API Second Shop ' + unique.slice(-4);
  const createSecondShop = await request.post('/api/v1/staff/businesses/' + customer.business.id + '/shops', {
    headers: staffHeaders,
    data: {
      code: 'QA' + unique.slice(-8).toUpperCase(),
      name: secondShopName,
      city: 'Lahore'
    }
  });
  expect(createSecondShop.status()).toBe(201);
  const secondShopId = Number((await createSecondShop.json()).data?.shop_id);
  expect(secondShopId).toBeGreaterThan(0);
  const shopsAfterCreate = await request.get('/api/v1/staff/businesses/' + customer.business.id + '/shops', { headers: staffHeaders });
  expect(shopsAfterCreate.status()).toBe(200);
  expect(((await shopsAfterCreate.json()).data?.shops || []).some(shop => Number(shop.id) === secondShopId)).toBe(true);

  const catalogResponse = await request.get('/api/v1/customer/products', { headers: customerHeaders });
  expect(catalogResponse.status()).toBe(200);
  const catalog = (await catalogResponse.json()).data;
  expect(Number(catalog.shop.id)).toBe(shopId);
  const product = (catalog.products || []).find(item => item.sale_mode === 'sell_gas_only' && item.price?.price_available === true);
  expect(product, 'CI must seed one priced gas-only catalog product').toBeTruthy();

  const ordersResponse = await request.get('/api/v1/customer/orders', { headers: customerHeaders });
  expect(ordersResponse.status()).toBe(200);
  expect(Array.isArray((await ordersResponse.json()).data?.orders)).toBe(true);

  const unassignedBusinessShop = publicShops.find(shop => shop.business_name === 'QA Isolation Business');
  expect(unassignedBusinessShop, 'CI must seed another business for customer-order tenant-isolation checks').toBeTruthy();
  const idempotencyKey = 'qa-order-' + unique;
  const orderPayload = {
    // A client-supplied shop_id must never override the customer's assigned shop.
    shop_id: Number(unassignedBusinessShop.id),
    fulfilment_method: 'delivery',
    delivery_address: '1 QA Test Street',
    delivery_city: 'Lahore',
    customer_notes: 'Automated API smoke test',
    items: [{ product_id: Number(product.id), quantity: 2 }]
  };
  const firstOrderResponse = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': idempotencyKey },
    data: orderPayload
  });
  expect(firstOrderResponse.status()).toBe(201);
  const firstOrder = (await firstOrderResponse.json()).data?.order;
  expect(firstOrder?.status).toBe('pending');
  expect(Number(firstOrder?.location_id)).toBe(shopId);
  expect(Number(firstOrder?.business_id)).toBe(Number(customer.business.id));
  expect(firstOrder?.pos_sale_id).toBeFalsy();

  const retryResponse = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': idempotencyKey },
    data: orderPayload
  });
  expect(retryResponse.status()).toBe(201);
  const retriedOrder = (await retryResponse.json()).data?.order;
  expect(Number(retriedOrder.id)).toBe(Number(firstOrder.id));

  const reusedKeyResponse = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': idempotencyKey },
    data: { ...orderPayload, items: [{ product_id: Number(product.id), quantity: 3 }] }
  });
  expect(reusedKeyResponse.status()).toBe(409);
  expect((await reusedKeyResponse.json()).error?.code).toBe('IDEMPOTENCY_KEY_REUSED');

  const staffOrdersResponse = await request.get('/api/v1/staff/orders', { headers: staffHeaders });
  expect(staffOrdersResponse.status()).toBe(200);
  const staffOrders = (await staffOrdersResponse.json()).data?.orders || [];
  expect(staffOrders.some(order => Number(order.id) === Number(firstOrder.id))).toBe(true);

  const accept = await request.post('/api/v1/staff/orders/' + firstOrder.id + '/status', {
    headers: staffHeaders,
    data: { status: 'accepted' }
  });
  expect(accept.status()).toBe(200);
  expect((await accept.json()).data?.order?.status).toBe('accepted');

  const customerOrder = await request.get('/api/v1/customer/orders/' + firstOrder.id, { headers: customerHeaders });
  expect(customerOrder.status()).toBe(200);
  const visibleOrder = (await customerOrder.json()).data?.order;
  expect(visibleOrder?.status).toBe('accepted');
  // Accepting an order is a workflow change only, never a POS/financial posting.
  expect(visibleOrder?.pos_sale_id).toBeFalsy();
  expect(Number(visibleOrder?.location_id)).toBe(shopId);
  expect(Number(visibleOrder?.business_id)).toBe(Number(customer.business.id));
  expect(visibleOrder?.items?.[0]?.selected_cylinder_unit_ids).toBeUndefined();

  // A different approved customer in the same shop must not read or cancel this order.
  const otherUsername = 'qa_mobile_other_' + unique;
  const otherSignupResponse = await request.post('/api/v1/customer/auth/signup', {
    data: {
      shop_id: shopId,
      username: otherUsername,
      password,
      customer_name: 'QA Other Mobile Customer',
      phone: '03000000002',
      city: 'Lahore',
      address: '2 QA Test Street'
    }
  });
  expect(otherSignupResponse.status()).toBe(202);
  const otherSignup = await otherSignupResponse.json();
  const otherApproval = await request.post('/api/v1/staff/customer-signups/' + otherSignup.request_id + '/approve', {
    headers: staffHeaders,
    data: {}
  });
  expect(otherApproval.status()).toBe(201);
  const otherLogin = await request.post('/api/v1/customer/auth/login', {
    data: { username: otherUsername, password }
  });
  expect(otherLogin.status()).toBe(200);
  const otherCustomerHeaders = { Authorization: 'Bearer ' + (await otherLogin.json()).data.tokens.access_token };
  const foreignOrderRead = await request.get('/api/v1/customer/orders/' + firstOrder.id, { headers: otherCustomerHeaders });
  expect(foreignOrderRead.status()).toBe(404);
  const foreignOrderCancel = await request.post('/api/v1/customer/orders/' + firstOrder.id + '/cancel', {
    headers: otherCustomerHeaders,
    data: { reason: 'Attempt to access another customer order in QA.' }
  });
  expect(foreignOrderCancel.status()).toBe(404);

  const invalidStatusFilter = await request.get('/api/v1/customer/orders?status=not-a-real-status', { headers: customerHeaders });
  expect(invalidStatusFilter.status()).toBe(422);
  expect((await invalidStatusFilter.json()).error?.code).toBe('VALIDATION_FAILED');

  const invalidQuantity = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': 'qa-invalid-qty-' + unique },
    data: { ...orderPayload, items: [{ product_id: Number(product.id), quantity: 0 }] }
  });
  expect(invalidQuantity.status()).toBe(422);
  expect((await invalidQuantity.json()).error?.code).toBe('VALIDATION_FAILED');

  const invalidIdempotencyKey = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': 'invalid key ' + unique },
    data: orderPayload
  });
  expect(invalidIdempotencyKey.status()).toBe(422);
  expect((await invalidIdempotencyKey.json()).error?.code).toBe('VALIDATION_FAILED');

  // A valid customer session must not bypass shop-level order controls.
  const settingsResponse = await request.get('/api/v1/staff/configuration/shops/' + shopId, { headers: staffHeaders });
  expect(settingsResponse.status()).toBe(200);
  const shopSettings = (await settingsResponse.json()).data?.settings || [];
  const previousOrdersSetting = shopSettings.find(setting => setting.key === 'orders_enabled');
  expect(previousOrdersSetting, 'orders_enabled must be present in the shop configuration').toBeTruthy();

  const disableOrders = await request.post('/api/v1/staff/configuration/save', {
    headers: staffHeaders,
    data: { scope: 'shop', owner_id: shopId, key: 'orders_enabled', value: false }
  });
  expect(disableOrders.status()).toBe(200);
  try {
    const blockedOrder = await request.post('/api/v1/customer/orders', {
      headers: { ...customerHeaders, 'Idempotency-Key': 'qa-disabled-' + unique },
      data: orderPayload
    });
    expect(blockedOrder.status()).toBe(403);
    expect((await blockedOrder.json()).error?.code).toBe('ORDERING_UNAVAILABLE');
  } finally {
    const restore = previousOrdersSetting.has_override
      ? await request.post('/api/v1/staff/configuration/save', {
          headers: staffHeaders,
          data: { scope: 'shop', owner_id: shopId, key: 'orders_enabled', value: Boolean(previousOrdersSetting.value) }
        })
      : await request.post('/api/v1/staff/configuration/reset', {
          headers: staffHeaders,
          data: { scope: 'shop', owner_id: shopId, key: 'orders_enabled' }
        });
    expect(restore.status(), 'shop order setting must be restored after the disabled-ordering regression test').toBe(200);
  }

  // A stale customer catalog must yield a product conflict, not a generic server error.
  const deactivateProduct = await request.post('/api/v1/staff/shops/' + shopId + '/products/' + product.id + '/availability', {
    headers: staffHeaders,
    data: { is_active: false }
  });
  expect(deactivateProduct.status()).toBe(200);
  try {
    const staleProductOrder = await request.post('/api/v1/customer/orders', {
      headers: { ...customerHeaders, 'Idempotency-Key': 'qa-stale-product-' + unique },
      data: orderPayload
    });
    expect(staleProductOrder.status()).toBe(409);
    expect((await staleProductOrder.json()).error?.code).toBe('ORDER_PRODUCT_UNAVAILABLE');
  } finally {
    const restoreProduct = await request.post('/api/v1/staff/shops/' + shopId + '/products/' + product.id + '/availability', {
      headers: staffHeaders,
      data: { is_active: true }
    });
    expect(restoreProduct.status(), 'catalog availability must be restored after the stale-catalog regression test').toBe(200);
  }

  const logout = await request.post('/api/v1/customer/auth/logout', { headers: customerHeaders });
  expect(logout.status()).toBe(200);
  const revoked = await request.get('/api/v1/customer/me', { headers: customerHeaders });
  expect(revoked.status()).toBe(401);
});


test('E2E-027 Staff API rejects cross-shop scope overrides and platform-only business creation', async ({ request }) => {
  const login = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'qa_scoped_manager', password: 'admin123' }
  });
  expect(login.status()).toBe(200);
  const staff = (await login.json()).data;
  expect(Number(staff.current_shop_id)).toBeGreaterThan(0);

  const publicShops = await request.get('/api/v1/customer/auth/shops');
  expect(publicShops.status()).toBe(200);
  const shops = (await publicShops.json()).data?.shops || [];
  const otherShop = shops.find(shop => shop.business_name === 'QA Isolation Business');
  expect(otherShop, 'CI must seed a second business and shop').toBeTruthy();
  expect(Number(otherShop.id)).not.toBe(Number(staff.current_shop_id));
  const siblingShop = shops.find(shop => shop.name.startsWith('QA API Second Shop '));
  expect(siblingShop, 'The first API test must provision a second shop under the main business').toBeTruthy();
  expect(Number(siblingShop.id)).not.toBe(Number(staff.current_shop_id));

  const headers = {
    Authorization: 'Bearer ' + staff.tokens.access_token,
    'X-Shop-Id': String(otherShop.id)
  };
  const crossShopOrders = await request.get('/api/v1/staff/orders', { headers });
  expect(crossShopOrders.status()).toBe(403);
  expect((await crossShopOrders.json()).error?.code).toBe('SHOP_ACCESS_DENIED');

  const siblingShopOrders = await request.get('/api/v1/staff/orders', {
    headers: { Authorization: 'Bearer ' + staff.tokens.access_token, 'X-Shop-Id': String(siblingShop.id) }
  });
  expect(siblingShopOrders.status()).toBe(403);
  expect((await siblingShopOrders.json()).error?.code).toBe('SHOP_ACCESS_DENIED');

  const crossBusinessSettings = await request.get('/api/v1/staff/configuration/shops/' + otherShop.id, { headers });
  expect(crossBusinessSettings.status()).toBe(403);

  const createBusiness = await request.post('/api/v1/staff/businesses', {
    headers,
    data: { code: 'UNAUTHORIZED-QA', name: 'Must Not Be Created' }
  });
  expect(createBusiness.status()).toBe(403);
});





test('E2E-028 Customer order fulfillment posts to POS once and safely replays', async ({ request }) => {
  const staffLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(staffLogin.status()).toBe(200);
  const staff = (await staffLogin.json()).data;
  const shopId = Number(staff.current_shop_id);
  const staffHeaders = {
    Authorization: 'Bearer ' + staff.tokens.access_token,
    'X-Shop-Id': String(shopId)
  };

  const unique = Date.now().toString();
  const username = 'qa_fulfill_' + unique;
  const password = 'QA-Mobile-Strong-123!';
  const signup = await request.post('/api/v1/customer/auth/signup', {
    data: {
      shop_id: shopId,
      username,
      password,
      customer_name: 'QA POS Fulfillment Customer',
      phone: '03000000003',
      city: 'Lahore',
      address: '3 QA Test Street'
    }
  });
  expect(signup.status()).toBe(202);
  const signupData = await signup.json();
  const approval = await request.post('/api/v1/staff/customer-signups/' + signupData.request_id + '/approve', {
    headers: staffHeaders,
    data: {}
  });
  expect(approval.status()).toBe(201);

  const customerLogin = await request.post('/api/v1/customer/auth/login', {
    data: { username, password }
  });
  expect(customerLogin.status()).toBe(200);
  const customerHeaders = {
    Authorization: 'Bearer ' + (await customerLogin.json()).data.tokens.access_token
  };

  const catalogResponse = await request.get('/api/v1/customer/products', { headers: customerHeaders });
  expect(catalogResponse.status()).toBe(200);
  const catalog = (await catalogResponse.json()).data;
  const product = (catalog.products || []).find(item =>
    item.sale_mode === 'sell_gas_only'
    && item.cylinder_type?.code === 'C6'
    && item.price?.price_available === true
  );
  expect(product, 'CI must seed an active, priced C6 gas-only product').toBeTruthy();

  const createOrder = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': 'qa-pos-fulfill-' + unique },
    data: {
      fulfilment_method: 'collection',
      customer_notes: 'E2E POS fulfillment and retry test',
      items: [{ product_id: Number(product.id), quantity: 1 }]
    }
  });
  expect(createOrder.status()).toBe(201);
  const order = (await createOrder.json()).data.order;
  expect(order.status).toBe('pending');
  expect(order.pos_sale_id).toBeFalsy();

  const accept = await request.post('/api/v1/staff/orders/' + order.id + '/status', {
    headers: staffHeaders,
    data: { status: 'accepted' }
  });
  expect(accept.status()).toBe(200);
  expect((await accept.json()).data.order.status).toBe('accepted');

  const optionsResponse = await request.get('/api/v1/staff/orders/' + order.id + '/fulfillment-options', {
    headers: staffHeaders
  });
  expect(optionsResponse.status()).toBe(200);
  const fulfillmentLines = (await optionsResponse.json()).data.options.lines;
  expect(fulfillmentLines).toHaveLength(1);
  const line = fulfillmentLines[0];
  expect(line.selection_field).toBe('source_cylinder_unit_id');
  const source = (line.cylinders || []).find(unit => unit.unit_code === 'QA-ORDER-C6-001');
  expect(source, 'CI must seed a separate filled source cylinder for POS fulfillment').toBeTruthy();

  const total = Number(order.total_amount);
  expect(total).toBeGreaterThan(0);
  const fulfillmentPayload = {
    fulfillment_lines: [{
      line_no: Number(line.line_no),
      source_cylinder_unit_id: Number(source.id)
    }],
    payments: [{ payment_mode: 'cash', amount: Number((total + 1).toFixed(2)) }]
  };

  // A payment mismatch must fail before any sale/inventory posting and leave the order accepted.
  const badPayment = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: fulfillmentPayload
  });
  expect(badPayment.status()).toBe(422);
  expect((await badPayment.json()).error?.code).toBe('INVALID_FULFILLMENT_REQUEST');
  const unchanged = await request.get('/api/v1/staff/orders/' + order.id, { headers: staffHeaders });
  expect(unchanged.status()).toBe(200);
  const unchangedOrder = (await unchanged.json()).data.order;
  expect(unchangedOrder.status).toBe('accepted');
  expect(unchangedOrder.pos_sale_id).toBeFalsy();
  const optionsAfterBadPayment = await request.get('/api/v1/staff/orders/' + order.id + '/fulfillment-options', {
    headers: staffHeaders
  });
  expect(optionsAfterBadPayment.status()).toBe(200);
  const sourceAfterBadPayment = (await optionsAfterBadPayment.json()).data.options.lines[0].cylinders
    .find(unit => Number(unit.id) === Number(source.id));
  expect(sourceAfterBadPayment).toBeTruthy();
  expect(Number(sourceAfterBadPayment.gas_weight_kg)).toBe(Number(source.gas_weight_kg));

  fulfillmentPayload.payments = [{ payment_mode: 'cash', amount: Number(total.toFixed(2)) }];
  const fulfilledResponse = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: fulfillmentPayload
  });
  const fulfilledBody = await fulfilledResponse.json();
  expect(fulfilledResponse.status(), 'POS fulfillment response: ' + JSON.stringify(fulfilledBody)).toBe(200);
  const fulfilled = fulfilledBody.data;
  expect(fulfilled.status).toBe('completed');
  expect(fulfilled.order.status).toBe('completed');
  const saleId = Number(fulfilled.order.pos_sale_id);
  expect(saleId).toBeGreaterThan(0);

  // Simulate the client retrying after it did not receive the successful response.
  const replayResponse = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: fulfillmentPayload
  });
  expect(replayResponse.status()).toBe(200);
  const replay = (await replayResponse.json()).data;
  expect(replay.status).toBe('completed');
  expect(Number(replay.order.pos_sale_id)).toBe(saleId);

  const customerOrder = await request.get('/api/v1/customer/orders/' + order.id, { headers: customerHeaders });
  expect(customerOrder.status()).toBe(200);
  const customerVisibleOrder = (await customerOrder.json()).data.order;
  expect(customerVisibleOrder.status).toBe('completed');
  expect(Number(customerVisibleOrder.pos_sale_id)).toBe(saleId);
  expect(customerVisibleOrder.items?.[0]?.selected_cylinder_unit_ids).toBeUndefined();
});


test('E2E-029 Mixed gas, filled-cylinder, and empty-cylinder order fulfills only with unique physical units', async ({ request }) => {
  const staffLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(staffLogin.status()).toBe(200);
  const staff = (await staffLogin.json()).data;
  const shopId = Number(staff.current_shop_id);
  const staffHeaders = {
    Authorization: 'Bearer ' + staff.tokens.access_token,
    'X-Shop-Id': String(shopId)
  };

  const unique = Date.now().toString();
  const username = 'qa_mixed_fulfill_' + unique;
  const password = 'QA-Mobile-Strong-123!';
  const signup = await request.post('/api/v1/customer/auth/signup', {
    data: {
      shop_id: shopId,
      username,
      password,
      customer_name: 'QA Mixed POS Customer',
      phone: '03000000004',
      city: 'Lahore',
      address: '4 QA Test Street'
    }
  });
  expect(signup.status()).toBe(202);
  const signupData = await signup.json();
  const approval = await request.post('/api/v1/staff/customer-signups/' + signupData.request_id + '/approve', {
    headers: staffHeaders,
    data: {}
  });
  expect(approval.status()).toBe(201);

  const customerLogin = await request.post('/api/v1/customer/auth/login', {
    data: { username, password }
  });
  expect(customerLogin.status()).toBe(200);
  const customerHeaders = {
    Authorization: 'Bearer ' + (await customerLogin.json()).data.tokens.access_token
  };

  const catalogResponse = await request.get('/api/v1/customer/products', { headers: customerHeaders });
  expect(catalogResponse.status()).toBe(200);
  const products = (await catalogResponse.json()).data.products || [];
  const gasProduct = products.find(item => item.sale_mode === 'sell_gas_only' && item.cylinder_type?.code === 'C6' && item.price?.price_available === true);
  const filledProduct = products.find(item => item.sale_mode === 'sell_filled' && item.cylinder_type?.code === 'C6' && item.price?.price_available === true);
  const emptyProduct = products.find(item => item.sale_mode === 'sell_empty' && item.cylinder_type?.code === 'C6' && item.price?.price_available === true);
  expect(gasProduct, 'A priced gas-only product must be available').toBeTruthy();
  expect(filledProduct, 'CI must seed a current C6 cylinder rate for filled-cylinder orders').toBeTruthy();
  expect(emptyProduct, 'CI must seed a current C6 cylinder rate for empty-cylinder orders').toBeTruthy();

  const createOrder = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': 'qa-mixed-pos-' + unique },
    data: {
      fulfilment_method: 'collection',
      customer_notes: 'E2E mixed sale-mode POS fulfillment test',
      items: [
        { product_id: Number(gasProduct.id), quantity: 1 },
        { product_id: Number(filledProduct.id), quantity: 1 },
        { product_id: Number(emptyProduct.id), quantity: 1 }
      ]
    }
  });
  expect(createOrder.status()).toBe(201);
  const order = (await createOrder.json()).data.order;
  expect(order.status).toBe('pending');
  expect(order.pos_sale_id).toBeFalsy();

  const accept = await request.post('/api/v1/staff/orders/' + order.id + '/status', {
    headers: staffHeaders,
    data: { status: 'accepted' }
  });
  expect(accept.status()).toBe(200);
  expect((await accept.json()).data.order.status).toBe('accepted');

  const optionsResponse = await request.get('/api/v1/staff/orders/' + order.id + '/fulfillment-options', {
    headers: staffHeaders
  });
  expect(optionsResponse.status()).toBe(200);
  const options = (await optionsResponse.json()).data.options.lines;
  expect(options).toHaveLength(3);

  const gasLine = options.find(line => line.sale_mode === 'sell_gas_only');
  const filledLine = options.find(line => line.sale_mode === 'sell_filled');
  const emptyLine = options.find(line => line.sale_mode === 'sell_empty');
  expect(gasLine).toBeTruthy();
  expect(filledLine).toBeTruthy();
  expect(emptyLine).toBeTruthy();

  const gasSource = gasLine.cylinders.find(unit => unit.unit_code === 'QA-ORDER-C6-001');
  const filledUnit = filledLine.cylinders.find(unit => unit.unit_code === 'QA-ORDER-FILLED-C6-001');
  const emptyUnit = emptyLine.cylinders.find(unit => unit.unit_code === 'QA-ORDER-EMPTY-C6-001');
  expect(gasSource, 'Dedicated gas source cylinder should be in shop stock').toBeTruthy();
  expect(filledUnit, 'Dedicated filled cylinder should be in shop stock').toBeTruthy();
  expect(emptyUnit, 'Dedicated empty cylinder should be in shop stock').toBeTruthy();

  const fulfillmentLines = [
    { line_no: Number(gasLine.line_no), source_cylinder_unit_id: Number(gasSource.id) },
    { line_no: Number(filledLine.line_no), selected_cylinder_unit_ids: [Number(filledUnit.id)] },
    { line_no: Number(emptyLine.line_no), selected_cylinder_unit_ids: [Number(emptyUnit.id)] }
  ];

  // A single physical filled cylinder cannot be both a refill source and a sold full cylinder.
  const overlapPayload = {
    fulfillment_lines: fulfillmentLines.map(line => line.line_no === Number(gasLine.line_no)
      ? { ...line, source_cylinder_unit_id: Number(filledUnit.id) }
      : line),
    payments: [{ payment_mode: 'cash', amount: Number(order.total_amount) }]
  };
  const overlappingSelection = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: overlapPayload
  });
  expect(overlappingSelection.status()).toBe(422);
  expect((await overlappingSelection.json()).error?.code).toBe('INVALID_FULFILLMENT_REQUEST');
  const afterOverlap = await request.get('/api/v1/staff/orders/' + order.id, { headers: staffHeaders });
  expect((await afterOverlap.json()).data.order.status).toBe('accepted');
  expect((await afterOverlap.json()).data.order.pos_sale_id).toBeFalsy();

  // First physical-cylinder assignment snapshots the exact selected units and requires customer review.
  const firstFulfillment = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: {
      fulfillment_lines: fulfillmentLines,
      payments: [{ payment_mode: 'cash', amount: Number(order.total_amount) }]
    }
  });
  expect(firstFulfillment.status()).toBe(200);
  const firstResult = (await firstFulfillment.json()).data;
  expect(firstResult.status).toBe('reconfirmation_required');
  expect(firstResult.order.status).toBe('pending');
  expect(firstResult.order.pos_sale_id).toBeFalsy();

  const reconfirmPrice = await request.post('/api/v1/customer/orders/' + order.id + '/reconfirm-price', {
    headers: customerHeaders
  });
  expect(reconfirmPrice.status()).toBe(200);
  expect((await reconfirmPrice.json()).data.status).toBe('price_reconfirmed');

  const reaccept = await request.post('/api/v1/staff/orders/' + order.id + '/status', {
    headers: staffHeaders,
    data: { status: 'accepted' }
  });
  expect(reaccept.status()).toBe(200);
  expect((await reaccept.json()).data.order.status).toBe('accepted');

  const confirmedOrderResponse = await request.get('/api/v1/customer/orders/' + order.id, { headers: customerHeaders });
  expect(confirmedOrderResponse.status()).toBe(200);
  const confirmedOrder = (await confirmedOrderResponse.json()).data.order;
  expect(confirmedOrder.price_reconfirmation_required).not.toBe(1);

  const total = Number(confirmedOrder.total_amount);
  const finalPayload = {
    fulfillment_lines: fulfillmentLines,
    payments: [{ payment_mode: 'cash', amount: Number(total.toFixed(2)) }]
  };
  const fulfilledResponse = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: finalPayload
  });
  expect(fulfilledResponse.status()).toBe(200);
  const fulfilled = (await fulfilledResponse.json()).data;
  expect(fulfilled.status).toBe('completed');
  expect(fulfilled.order.status).toBe('completed');
  const saleId = Number(fulfilled.order.pos_sale_id);
  expect(saleId).toBeGreaterThan(0);

  const replayResponse = await request.post('/api/v1/staff/orders/' + order.id + '/fulfill', {
    headers: staffHeaders,
    data: finalPayload
  });
  expect(replayResponse.status()).toBe(200);
  const replay = (await replayResponse.json()).data;
  expect(replay.status).toBe('completed');
  expect(Number(replay.order.pos_sale_id)).toBe(saleId);

  const customerVisible = await request.get('/api/v1/customer/orders/' + order.id, { headers: customerHeaders });
  expect(customerVisible.status()).toBe(200);
  expect((await customerVisible.json()).data.order.status).toBe('completed');
});


test('E2E-030 Concurrent POS fulfillment requests post one sale and consume stock once', async ({ request, page }) => {
  const staffLogin = await request.post('/api/v1/staff/auth/login', {
    data: { username: 'admin', password: 'admin123' }
  });
  expect(staffLogin.status()).toBe(200);
  const staff = (await staffLogin.json()).data;
  const shopId = Number(staff.current_shop_id);
  const staffHeaders = {
    Authorization: 'Bearer ' + staff.tokens.access_token,
    'X-Shop-Id': String(shopId)
  };

  const unique = Date.now().toString();
  const username = 'qa_concurrent_fulfill_' + unique;
  const password = 'QA-Mobile-Strong-123!';
  const signup = await request.post('/api/v1/customer/auth/signup', {
    data: {
      shop_id: shopId,
      username,
      password,
      customer_name: 'QA Concurrent POS Customer',
      phone: '03000000005',
      city: 'Lahore',
      address: '5 QA Test Street'
    }
  });
  expect(signup.status()).toBe(202);
  const signupData = await signup.json();
  const approval = await request.post('/api/v1/staff/customer-signups/' + signupData.request_id + '/approve', {
    headers: staffHeaders,
    data: {}
  });
  expect(approval.status()).toBe(201);

  const customerLogin = await request.post('/api/v1/customer/auth/login', {
    data: { username, password }
  });
  expect(customerLogin.status()).toBe(200);
  const customerHeaders = {
    Authorization: 'Bearer ' + (await customerLogin.json()).data.tokens.access_token
  };
  const catalogResponse = await request.get('/api/v1/customer/products', { headers: customerHeaders });
  expect(catalogResponse.status()).toBe(200);
  const product = ((await catalogResponse.json()).data.products || []).find(item =>
    item.sale_mode === 'sell_gas_only'
    && item.cylinder_type?.code === 'C6'
    && item.price?.price_available === true
  );
  expect(product).toBeTruthy();

  const orderResponse = await request.post('/api/v1/customer/orders', {
    headers: { ...customerHeaders, 'Idempotency-Key': 'qa-concurrent-pos-' + unique },
    data: {
      fulfilment_method: 'collection',
      customer_notes: 'E2E concurrent POS commit test',
      items: [{ product_id: Number(product.id), quantity: 1 }]
    }
  });
  expect(orderResponse.status()).toBe(201);
  const order = (await orderResponse.json()).data.order;
  const accept = await request.post('/api/v1/staff/orders/' + order.id + '/status', {
    headers: staffHeaders,
    data: { status: 'accepted' }
  });
  expect(accept.status()).toBe(200);

  const optionsResponse = await request.get('/api/v1/staff/orders/' + order.id + '/fulfillment-options', {
    headers: staffHeaders
  });
  expect(optionsResponse.status()).toBe(200);
  const line = (await optionsResponse.json()).data.options.lines[0];
  const source = (line.cylinders || []).find(unit => unit.unit_code === 'QA-ORDER-C6-001');
  expect(source).toBeTruthy();
  const sourceWeightBefore = Number(source.gas_weight_kg);
  expect(sourceWeightBefore).toBeGreaterThan(1);

  const payload = {
    fulfillment_lines: [{
      line_no: Number(line.line_no),
      source_cylinder_unit_id: Number(source.id)
    }],
    payments: [{ payment_mode: 'cash', amount: Number(Number(order.total_amount).toFixed(2)) }]
  };
  const concurrentResponses = await Promise.all([
    request.post('/api/v1/staff/orders/' + order.id + '/fulfill', { headers: staffHeaders, data: payload }),
    request.post('/api/v1/staff/orders/' + order.id + '/fulfill', { headers: staffHeaders, data: payload })
  ]);
  expect(concurrentResponses.map(response => response.status())).toEqual([200, 200]);
  const concurrentResults = await Promise.all(concurrentResponses.map(response => response.json()));
  for (const result of concurrentResults) {
    expect(result.data.status).toBe('completed');
    expect(Number(result.data.order.pos_sale_id)).toBeGreaterThan(0);
  }
  const linkedSaleIds = new Set(concurrentResults.map(result => Number(result.data.order.pos_sale_id)));
  expect(linkedSaleIds.size).toBe(1);

  const customerOrderResponse = await request.get('/api/v1/customer/orders/' + order.id, { headers: customerHeaders });
  expect(customerOrderResponse.status()).toBe(200);
  expect((await customerOrderResponse.json()).data.order.status).toBe('completed');

  // Independently confirm the physical source changed exactly once despite two simultaneous requests.
  await login(page);
  await page.goto('/inventory/cylinders/' + Number(line.cylinder_type_id), { waitUntil: 'networkidle' });
  const sourceRow = page.locator('table tbody tr').filter({ hasText: 'QA-ORDER-C6-001' }).first();
  await expect(sourceRow).toBeVisible();
  await expect(sourceRow).toContainText((sourceWeightBefore - 1).toFixed(2) + ' KG');
});

test('E2E-031 API lists paginate and POST creates honour Idempotency-Key', async ({ request }) => {
  const login = await request.post('/api/v1/staff/auth/login', { data: { username: 'admin', password: 'admin123' } });
  expect(login.status()).toBe(200);
  const staff = (await login.json()).data;
  const headers = { Authorization: 'Bearer ' + staff.tokens.access_token, 'X-Shop-Id': String(staff.current_shop_id) };

  // Pagination envelope: per_page is capped at 100 and meta is always present.
  const firstPage = await request.get('/api/v1/staff/businesses?per_page=1', { headers });
  expect(firstPage.status()).toBe(200);
  const firstBody = await firstPage.json();
  expect(firstBody.meta).toMatchObject({ page: 1, per_page: 1 });
  expect(firstBody.data.businesses.length).toBeLessThanOrEqual(1);
  expect(firstBody.meta.has_more).toBe(firstBody.meta.total > 1);
  const capped = await (await request.get('/api/v1/staff/shops?per_page=500', { headers })).json();
  expect(capped.meta.per_page).toBe(100);
  const pastEnd = await (await request.get('/api/v1/staff/shops?page=999', { headers })).json();
  expect(pastEnd.data.shops).toEqual([]);
  expect(pastEnd.meta.has_more).toBe(false);

  const businessId = Number(firstBody.data.businesses[0].id);
  const shopsBefore = (await (await request.get('/api/v1/staff/businesses/' + businessId + '/shops', { headers })).json()).meta.total;
  const unique = Date.now().toString(36).toUpperCase();
  const key = 'qa-shop-' + unique;
  const body = { code: 'IK' + unique.slice(-8), name: 'QA Idempotent Shop ' + unique, city: 'Lahore' };
  const url = '/api/v1/staff/businesses/' + businessId + '/shops';

  const first = await request.post(url, { headers: { ...headers, 'Idempotency-Key': key }, data: body });
  expect(first.status()).toBe(201);
  const firstJson = await first.json();
  const retry = await request.post(url, { headers: { ...headers, 'Idempotency-Key': key }, data: body });
  expect(retry.status()).toBe(201);
  expect(retry.headers()['idempotent-replay']).toBe('true');
  expect(await retry.json()).toEqual(firstJson);
  const shopsAfter = (await (await request.get(url, { headers })).json()).meta.total;
  expect(shopsAfter).toBe(shopsBefore + 1);

  const reused = await request.post(url, { headers: { ...headers, 'Idempotency-Key': key }, data: { ...body, name: body.name + ' changed' } });
  expect(reused.status()).toBe(409);
  const reusedError = (await reused.json()).error;
  expect(reusedError.code).toBe('IDEMPOTENCY_KEY_REUSED');
  expect(reusedError.request_id).toBe(reused.headers()['x-request-id']);

  const badKey = await request.post(url, { headers: { ...headers, 'Idempotency-Key': 'bad key' }, data: body });
  expect(badKey.status()).toBe(422);
  expect((await badKey.json()).error.code).toBe('INVALID_IDEMPOTENCY_KEY');
});

test('E2E-032 Staff customer API matches the web Customers screens', async ({ page, request }) => {
  const apiLogin = await request.post('/api/v1/staff/auth/login', { data: { username: 'admin', password: 'admin123' } });
  const staff = (await apiLogin.json()).data;
  const headers = { Authorization: 'Bearer ' + staff.tokens.access_token, 'X-Shop-Id': String(staff.current_shop_id) };

  const unique = Date.now().toString(36).toUpperCase();
  const created = await request.post('/api/v1/staff/customers', { headers, data: { name: 'QA API Customer ' + unique, code: 'API-' + unique, phone: '03001234567', credit_limit: 500 } });
  expect(created.status()).toBe(201);
  const customer = (await created.json()).data.customer;
  expect(Number(customer.is_active)).toBe(1);

  // A partial update keeps the other fields.
  const updated = await request.put('/api/v1/staff/customers/' + customer.id, { headers, data: { phone: '03007654321' } });
  expect(updated.status()).toBe(200);
  const after = (await updated.json()).data.customer;
  expect(after.phone).toBe('03007654321');
  expect(after.name).toBe(customer.name);
  expect(Number(after.is_active)).toBe(1);
  expect(Number(after.credit_limit)).toBe(500);
  const invalid = await request.put('/api/v1/staff/customers/' + customer.id, { headers, data: { credit_limit: -1 } });
  expect(invalid.status()).toBe(422);
  expect((await request.get('/api/v1/staff/customers/99999999', { headers })).status()).toBe(404);

  const list = await (await request.get('/api/v1/staff/customers?q=' + unique, { headers })).json();
  expect(list.meta.total).toBe(1);
  expect(list.data.customers[0].os_balance).toBe(0);
  const balance = (await (await request.get('/api/v1/staff/customers/' + customer.id + '/balance', { headers })).json()).data;
  expect(balance.os_balance).toBe(0);

  await login(page);
  await page.goto('/customers', { waitUntil: 'networkidle' });
  await expect(page.locator('body')).toContainText('QA API Customer ' + unique);

  // The seeded customer's ledger equals the web Customer Ledger (same LedgerService).
  const seeded = (await (await request.get('/api/v1/staff/customers?q=QA-E2E-CUST', { headers })).json()).data.customers[0];
  const ledger = (await (await request.get('/api/v1/staff/customers/' + seeded.id + '/ledger', { headers })).json()).data;
  await page.goto('/customers/ledger/' + seeded.id, { waitUntil: 'networkidle' });
  const webSales = await page.locator('#customerSales tbody tr:not(:has(.dataTables_empty))').count();
  const webReceipts = await page.locator('#customerReceipts tbody tr:not(:has(.dataTables_empty))').count();
  expect(ledger.sales.length).toBe(webSales);
  expect(ledger.receipts.length).toBe(webReceipts);
});

function parseMultipart(body) {
  const fields = {};
  for (const m of body.matchAll(/name="([^"]+)"\r\n\r\n([\s\S]*?)\r\n--/g)) {
    if (m[1].endsWith('[]')) (fields[m[1].slice(0, -2)] ||= []).push(m[2]); else fields[m[1]] = m[2];
  }
  return fields;
}

test('E2E-033 Staff sales API posts exactly like the POS screen', async ({ page, request }) => {
  const apiLogin = await request.post('/api/v1/staff/auth/login', { data: { username: 'admin', password: 'admin123' } });
  const staff = (await apiLogin.json()).data;
  const headers = { Authorization: 'Bearer ' + staff.tokens.access_token, 'X-Shop-Id': String(staff.current_shop_id) };
  const key = (name) => ({ ...headers, 'Idempotency-Key': 'qa-sale-' + name + '-' + Date.now() });

  // Fill a 1 KG gas sale on the POS screen and capture what it would post, without posting.
  await login(page);
  await page.goto('/sales', { waitUntil: 'networkidle' });
  await page.locator('#transactionType').selectOption('gas_sale');
  await page.locator('#customer_id').selectOption({ label: 'QA-E2E-CUST — QA E2E Customer' });
  const line = page.locator('#lines tbody tr').first();
  await selectOptionByTextPrefix(line.locator('.cyl'), 'C6 — 6 kg');
  await expect(line.locator('.sourceCyl')).toBeEnabled();
  await selectOptionByTextPrefix(line.locator('.sourceCyl'), 'QA-E2E-C6-001');
  await line.locator('.entryValue').fill('1');
  const uiTotal = Number(await page.locator('#saleTotal').innerText());
  await page.locator('.payment .amount').first().fill(String(uiTotal));
  let captured;
  await page.route('**/sales/save', async route => {
    captured = parseMultipart(route.request().postData() || '');
    await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ success: false, error: 'QA capture only' }) });
  });
  await page.locator('#saveBtn').click();
  await expect.poll(() => captured !== undefined).toBe(true);
  await page.unroute('**/sales/save');
  const payload = {
    transaction_type: captured.transaction_type, customer_id: Number(captured.customer_id), transaction_at: captured.transaction_at,
    discount_amount: captured.discount_amount, notes: captured.notes,
    lines: JSON.parse(captured.lines_json), payments: JSON.parse(captured.payments_json)
  };

  expect((await request.post('/api/v1/staff/sales', { headers, data: payload })).status()).toBe(422); // key required
  const k = key('gas');
  const posted = await request.post('/api/v1/staff/sales', { headers: k, data: payload });
  const postedBody = await posted.json();
  expect(posted.status(), JSON.stringify(postedBody)).toBe(201);
  const apiSale = postedBody.data.sale;
  expect(Number(apiSale.sale.total_amount)).toBeCloseTo(uiTotal, 2);
  const replay = await request.post('/api/v1/staff/sales', { headers: k, data: payload });
  expect(replay.headers()['idempotent-replay']).toBe('true');
  expect((await replay.json()).data.sale_no).toBe(postedBody.data.sale_no);

  // The same input posted by the POS screen itself creates the same rows.
  let resolvePost;
  const posCaptured = new Promise(resolve => { resolvePost = resolve; });
  await page.route('**/sales/save', async route => {
    const response = await route.fetch();
    const body = await response.text();
    resolvePost(JSON.parse(body));
    await route.fulfill({ response, body });
  });
  await page.locator('#saveBtn').click();
  const posResult = await posCaptured;
  await page.unroute('**/sales/save');
  expect(posResult.success, JSON.stringify(posResult)).toBe(true);
  const posSale = (await (await request.get('/api/v1/staff/sales/' + posResult.id, { headers })).json()).data;
  const pick = (d) => ({
    sale: ['transaction_type', 'customer_id', 'total_amount', 'receipt_amount', 'credit_amount', 'discount_amount', 'status'].map(f => String(d.sale[f])),
    items: d.items.map(i => [i.line_type, i.cylinder_type_id, i.quantity, i.gas_weight_kg, i.applied_rate, i.line_total].map(String)),
    payments: d.payments.map(p => [p.payment_mode, p.payment_type, p.amount].map(String)),
    movements: d.movements.map(m => [m.inventory_type, m.direction, m.cylinder_type_id, m.cylinder_unit_id, m.quantity].map(String))
  });
  expect(pick(apiSale)).toEqual(pick(posSale));

  // Business rules are the POS rules: zero quantity, walk-in credit and the credit limit.
  const zero = structuredClone(payload); zero.lines[0].quantity = '0';
  expect((await request.post('/api/v1/staff/sales', { headers: key('zero'), data: zero })).status()).toBe(422);
  const walkIn = { ...structuredClone(payload), customer_id: null, payments: [{ payment_type: 'sale', payment_mode: 'credit', amount: uiTotal }] };
  const walkInRes = await request.post('/api/v1/staff/sales', { headers: key('walkin'), data: walkIn });
  expect(walkInRes.status()).toBe(422);
  expect((await walkInRes.json()).error.code).toBe('SALE_REJECTED');
  const creditCustomer = (await (await request.get('/api/v1/staff/customers?q=QA-E2E-CREDIT', { headers })).json()).data.customers[0];
  const salesBefore = (await (await request.get('/api/v1/staff/sales?customer=QA-E2E-CREDIT', { headers })).json()).meta.total;
  const overLimit = structuredClone(payload);
  overLimit.customer_id = Number(creditCustomer.id); overLimit.lines[0].quantity = '3';
  overLimit.payments = [{ payment_type: 'sale', payment_mode: 'credit', amount: uiTotal * 3 }];
  const overRes = await request.post('/api/v1/staff/sales', { headers: key('limit'), data: overLimit });
  expect(overRes.status()).toBe(422);
  expect((await overRes.json()).error.message).toMatch(/credit limit/i);
  expect((await (await request.get('/api/v1/staff/sales?customer=QA-E2E-CREDIT', { headers })).json()).meta.total).toBe(salesBefore);

  // Void through the API reverses the sale (the latest one: an earlier sale whose cylinder was
  // used again afterwards cannot be voided).
  const voided = await request.post('/api/v1/staff/sales/' + posResult.id + '/void', { headers, data: { reason: 'QA API void test' } });
  expect(voided.status(), await voided.text()).toBe(200);
  const afterVoid = (await (await request.get('/api/v1/staff/sales/' + posResult.id, { headers })).json()).data;
  expect(afterVoid.sale.status).toBe('voided');
  const receipt = (await (await request.get('/api/v1/staff/sales/' + postedBody.data.id + '/receipt', { headers })).json()).data;
  expect(Object.keys(receipt.shopSettings).sort()).toEqual(['receipt_footer', 'receipt_title', 'show_address_on_receipt']);
});
