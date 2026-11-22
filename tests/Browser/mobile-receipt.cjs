// Built Vue/Inertia integration tests with deterministic API fixtures, not a live AI provider.
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const publicDir = path.join(root, 'public');
const manifest = JSON.parse(fs.readFileSync(path.join(publicDir, 'build/.vite/manifest.json')));
const entry = manifest['resources/js/app.ts'];
const tenant = { id: 'tenant-a', name: 'Keluarga Uji', slug: 'uji', role: 'owner', status: 'active' };
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jazkAAAAASUVORK5CYII=', 'base64');
const transaction = { id: 'transaction-a', description: 'Belanja Uji', type: 'expense', amount_minor: 45000,
  subtotal_minor: 48500, receipt_discount_minor: 3500, tax_minor: 0, service_charge_minor: 0,
  transaction_date: '2026-11-03', created_at: '2026-11-19T05:53:00Z', currency: 'IDR',
  items: [{ item_name: 'Belanja', quantity: 1, price_minor: 48500, discount_minor: 0, total_minor: 48500 }],
  images: [{ id: 'proof', name: 'struk.png', url: '/proof.png' }] };
const props = { tenant, tenants: [tenant], user: { id: 'user-a', name: 'Pengguna Uji', email: 'test@example.test' },
  auth: { user: { id: 'user-a', name: 'Pengguna Uji' } }, apps: [], csrf_token: 'test-only',
  accounts: [{ id: 'cash', name: 'Kas', account_type: 'cash' }], categories: [], transaction, transactions: [transaction],
  summary: { incomeMinor: 0, expenseMinor: 45000, netMinor: -45000, savingsMinor: 0 },
  activeBudgets: [], activeSavings: [], recentTransactions: [transaction],
  ai_engine: { is_configured: true, provider_name: 'Fixture', model: 'test' } };
let submittedMultipart = '';
const routes = { '/workspaces': 'WorkspaceSelector', '/t/uji/apps/pekan/transactions/create': 'Pekan/Transactions/Create',
  '/t/uji/apps/pekan/transactions/transaction-a/edit': 'Pekan/Transactions/Edit',
  '/t/uji/apps/pekan/receipt-scan': 'Pekan/ReceiptScan/Index', '/t/uji/apps/pekan/transactions': 'Pekan/Transactions/Index',
  '/t/uji/apps/pekan/transactions/transaction-a': 'Pekan/Transactions/Detail',
  '/t/uji/apps/pekan/dashboard': 'Pekan/Dashboard', '/t/uji/apps/app1': 'App1/Index', '/t/uji/apps/app2': 'App2/Index' };
const server = http.createServer(async (request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (url.pathname === '/proof.png') { response.setHeader('Content-Type', 'image/png'); return response.end(image); }
  if (url.pathname.startsWith('/build/')) {
    const file = path.resolve(publicDir, '.' + url.pathname);
    if (!file.startsWith(publicDir + path.sep) || !fs.existsSync(file)) { response.writeHead(404); return response.end(); }
    response.setHeader('Content-Type', file.endsWith('.js') ? 'application/javascript' : 'text/css');
    return response.end(fs.readFileSync(file));
  }
  if (request.method === 'POST') {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    submittedMultipart = Buffer.concat(chunks).toString();
    response.writeHead(303, { Location: '/t/uji/apps/pekan/transactions' });
    return response.end();
  }
  const component = routes[url.pathname] || 'App1/Index';
  const page = { component, props, url: url.pathname, version: null };
  if (request.headers['x-inertia']) {
    response.writeHead(200, { 'Content-Type': 'application/json', 'X-Inertia': 'true' });
    return response.end(JSON.stringify(page));
  }
  response.setHeader('Content-Type', 'text/html');
  response.end(`<!doctype html><html lang="id"><head><meta name="viewport" content="width=device-width,initial-scale=1">
    ${entry.css.map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head>
    <body><div id="app" data-page="${JSON.stringify(page).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></div>
    <script type="module" src="/build/${entry.file}"></script></body></html>`);
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || undefined });
  const evidence = process.env.RUDIMAH_SCREENSHOTS || path.join(os.tmpdir(), 'opencode', 'rudimah-mobile-evidence');
  fs.mkdirSync(evidence, { recursive: true });
  try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true,
      hasTouch: true, timezoneId: 'America/Los_Angeles' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/t/uji/apps/pekan/transactions/create');
    const chooserEvent = page.waitForEvent('filechooser');
    await page.locator('[role=button]').filter({ has: page.locator('input[type=file]') }).click();
    const chooser = await chooserEvent;
    await chooser.setFiles({ name: 'kamera.png', mimeType: 'image/png', buffer: image });
    await page.locator('#description').fill('Lampiran mobile');
    await page.getByRole('button', { name: 'Simpan Transaksi', exact: true }).click();
    await page.waitForURL('**/transactions');
    assert.match(submittedMultipart, /name="images\[0\]"; filename="kamera.png"/);
    console.log('PASS mobile multipart attachment');

    await page.route('**/receipt-scan/upload', route => route.fulfill({ json: {
      success: true, scan_id: 'scan-a', images: transaction.images,
      extracted: { merchant_name: 'Toko Uji', transaction_date: '2026-11-03', transaction_date_raw: '03/11/26',
        subtotal_minor: 48500, discount_minor: 3500, tax_minor: 0, service_charge_minor: 0, total_minor: 45000,
        items: transaction.items },
    } }));
    await page.goto(base + '/t/uji/apps/pekan/receipt-scan');
    await page.locator('input[type=file]').setInputFiles({ name: 'struk.png', mimeType: 'image/png', buffer: image });
    await page.locator('#ocr_date').waitFor();
    assert.equal(await page.locator('#ocr_date').inputValue(), '2026-11-03');
    assert.equal(await page.getByLabel('Total tercetak (Rp)').inputValue(), '45000');
    await page.locator('#ocr_discount').fill('4000');
    assert.equal(await page.getByLabel('Total tercetak (Rp)').inputValue(), '44500');
    await page.locator('#ocr_discount').fill('3500');
    assert.equal(await page.getByLabel('Total tercetak (Rp)').inputValue(), '45000');
    assert.match(await page.getByText('Tanggal transaksi yang akan disimpan:').innerText(), /3 Nov 2026/);
    assert.equal(await page.locator('img[src="/proof.png"]').count() > 0, true);
    await page.screenshot({ path: path.join(evidence, 'receipt-review-mobile.png'), fullPage: true });
    let confirmed;
    await page.route('**/receipt-scan/confirm', async route => {
      confirmed = route.request().postDataJSON();
      await route.fulfill({ json: { success: true, redirect_url: '/t/uji/apps/pekan/transactions' } });
    });
    await page.getByRole('button', { name: 'Simpan Sebagai Transaksi Final' }).click();
    await page.waitForURL('**/transactions');
    assert.equal(confirmed.total_minor, 45000);
    assert.equal(confirmed.discount_minor, 3500);
    assert.equal(confirmed.transaction_date, '2026-11-03');
    console.log('PASS OCR preview, confirmed total, discount, calendar date and attachment');

    await page.unroute('**/receipt-scan/upload');
    await page.route('**/receipt-scan/upload', route => route.fulfill({ json: {
      success: true, scan_id: 'scan-b', images: transaction.images,
      extracted: { merchant_name: 'Toko Uji', transaction_date: null, subtotal_minor: 48500,
        discount_minor: 3500, total_minor: 48500, items: transaction.items },
    } }));
    await page.goto(base + '/t/uji/apps/pekan/receipt-scan');
    await page.locator('input[type=file]').setInputFiles({ name: 'struk.png', mimeType: 'image/png', buffer: image });
    await page.locator('#ocr_date').waitFor();
    assert.equal(await page.locator('#ocr_date').inputValue(), '');
    assert.equal(await page.getByRole('button', { name: 'Simpan Sebagai Transaksi Final' }).isDisabled(), true);
    await page.locator('#ocr_date').fill('2026-11-03');
    assert.equal(await page.getByRole('button', { name: 'Simpan Sebagai Transaksi Final' }).isDisabled(), true);
    await page.getByLabel('Total tercetak (Rp)').fill('45000');
    assert.equal(await page.getByRole('button', { name: 'Simpan Sebagai Transaksi Final' }).isEnabled(), true);
    console.log('PASS unreadable date and inconsistent total block saving until corrected');

    await page.goto(base + '/t/uji/apps/pekan/transactions/transaction-a/edit');
    assert.match(await page.getByText('Total Mutasi:').locator('..').innerText(), /45\.000/);
    assert.equal(await page.locator('input[type=date]').inputValue(), '2026-11-03');
    await page.getByLabel('Pajak (Rp)').fill('3000');
    assert.match(await page.getByText('Total Mutasi:').locator('..').innerText(), /48\.000/);
    await page.getByLabel('Pajak (Rp)').fill('0');
    await page.getByRole('button', { name: 'Simpan Perubahan', exact: true }).click();
    await page.waitForURL('**/transactions');
    assert.match(submittedMultipart, /name="amount_minor"\r\n\r\n45000/);
    assert.match(submittedMultipart, /name="receipt_discount_minor"\r\n\r\n3500/);
    console.log('PASS edit preserves receipt discount and date');

    await page.goto(base + '/t/uji/apps/pekan/transactions/transaction-a');
    assert.match(await page.locator('time').first().innerText(), /3 Nov 2026/);
    console.log('PASS calendar date unchanged in negative UTC timezone');

    for (const width of [360, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [url, component] of Object.entries(routes)) {
        await page.goto(base + url);
        await page.locator('h1').waitFor();
        const header = page.locator('h1');
        if (!component.startsWith('Workspace')) {
          const order = await header.evaluate(el => Boolean(el.previousElementSibling?.querySelector('button')));
          assert.equal(order, true, `information icon before title: ${component}`);
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `no page overflow: ${width}/${component}`);
        for (const theme of ['light', 'dark']) {
          await page.evaluate(theme => {
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.setAttribute('data-theme', theme);
          }, theme);
          await page.screenshot({ path: path.join(evidence, `${component.replaceAll('/', '-')}-${width}-${theme}.png`), fullPage: true });
        }
      }
    }
    assert.deepEqual(errors, []);
    console.log(`PASS shared shell across modules, icon order and responsive screenshots: ${evidence}`);
  } finally {
    await browser.close();
    server.close();
  }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
