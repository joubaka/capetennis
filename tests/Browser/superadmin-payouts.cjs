/* Synthetic Laravel render and local assets only. Never submits a financial form. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const outputs = path.join(root, 'storage/framework/testing');
const capture = path.join(outputs, 'dashboard-payout-form.html');

(async () => {
  assert.ok(fs.existsSync(capture), 'Generate a current capture with CT_DASHBOARD_PAYOUT_QA_HTML=1');
  assert.ok(fs.statSync(capture).mtimeMs >= fs.statSync(path.join(root, 'resources/views/backend/superadmin/event-finances.blade.php')).mtimeMs, 'Payout capture must be current');
  const html = fs.readFileSync(capture, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  assert.ok(html.includes('Record accounting correction') && html.includes('payoutRecipientUserId'));
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!['localhost', '127.0.0.1'].includes(url.hostname) || !file.startsWith(publicRoot + path.sep)
        || !fs.existsSync(file) || !fs.statSync(file).isFile()) return route.abort();
      return route.fulfill({ path: file });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      await page.evaluate(() => {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active', 'show'));
        document.getElementById('pane-payouts').classList.add('active', 'show');
        document.getElementById('payoutFormCollapse').classList.add('show');
        document.querySelectorAll('#pane-payouts details').forEach(el => { el.open = true; });
      });
      assert.equal(await page.locator('#payoutRecipient').isVisible(), true);
      assert.match(await page.locator('#pane-payouts input[name=idempotency_key]').inputValue(), /^[a-f0-9-]{36}$/i);
      const reason = page.locator('#pane-payouts textarea[name=reason]');
      assert.equal(await reason.count(), 1);
      assert.equal(await reason.getAttribute('required'), '');
      assert.equal(await reason.getAttribute('minlength'), '5');
      assert.equal(await page.locator('#pane-payouts input[name=confirm]').getAttribute('required'), '');
      assert.ok((await page.locator('#pane-payouts').textContent()).includes('does not return money or reverse a bank transfer'));
      const correction = await page.locator('.payout-correction').boundingBox();
      assert.ok(correction.width >= 280 && correction.height < 600, `${width}px: correction remains readable without an excessively tall row`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      assert.ok(overflow <= 1, `${width}px: page has no horizontal overflow (${overflow})`);
      if (width === 390 || width === 1440) {
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(outputs, `dashboard-payout-form-${width}.png`), fullPage: true });
      }
      console.log(`PASS ${width}px: payout form, immutable-correction controls, contained page`);
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
