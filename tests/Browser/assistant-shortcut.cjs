// Rendered assistant markup and real assets; no provider or application data calls.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');

test('assistant shortcut instructions, clipboard fallback and direct opening', async () => {
  const html = fs.readFileSync(path.join(root, 'storage/framework/testing/event-assistant.html'), 'utf8');
  assert.match(html, /data-ea-shortcut-copy/);
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  try {
    for (const width of [320, 390, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [], requests = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(() => {
        window.microphoneCalls = 0;
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: async value => { window.copiedAddress = value; } }, configurable: true });
        Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia: () => { window.microphoneCalls++; throw Error('Unexpected recording'); } }, configurable: true });
      });
      await page.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: url.search ? html.replace('data-auto-open="false"', 'data-auto-open="true"') : html });
        if (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/')) return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(path.join(root, 'public', url.pathname), 'utf8') });
        requests.push(url.pathname);
        return route.fulfill({ json: { available: false } });
      });
      await page.goto('http://assistant.test/');
      await page.locator('[data-event-assistant-open]').click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('database setup'));
      // Shortcut controls should remain useful even while assistant setup is unavailable.
      await page.locator('[data-ea-shortcut]').click();
      await page.locator('#ea-shortcut-help').waitFor({ state: 'visible' });
      await page.locator('[data-ea-shortcut-copy]').click();
      assert.match(await page.evaluate(() => window.copiedAddress), /\/backend\/events\/801\/assistant\?home-screen=1$/);
      await page.evaluate(() => { navigator.clipboard.writeText = async () => { throw Error('Denied'); }; });
      await page.locator('[data-ea-shortcut-copy]').click();
      await page.getByText('Press and hold the selected address, then choose Copy.', { exact: true }).waitFor();
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.goto('http://assistant.test/?home-screen=1');
      await page.getByRole('dialog').waitFor({ state: 'visible' });
      await page.locator('#ea-shortcut-help').waitFor({ state: 'visible' });
      assert.equal(await page.evaluate(() => window.microphoneCalls), 0);
      assert.equal(requests.every(url => url.endsWith('/context')), true);
      assert.deepEqual(errors, []);
      await page.close();
    }
  } finally { await browser.close(); }
});
