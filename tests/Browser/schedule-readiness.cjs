// Render ScheduleReadinessRenderingTest with CT_SCHEDULE_READINESS_HTML=1 first.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');

test('saved calendar clearly shows missing playoffs at phone and desktop widths', async () => {
  const html = fs.readFileSync(path.join(root, 'storage/framework/testing/schedule-readiness.html'), 'utf8')
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    for (const width of [320, 390, 768, 1440]) {
      const page = await browser.newPage({viewport: {width, height: 1000}});
      await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (url.hostname === 'localhost' && file.startsWith(publicRoot + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
          await route.fulfill({path: file});
        } else await route.abort();
      });
      await page.setContent(html, {waitUntil: 'load'});
      const warning = page.locator('[role="status"]').filter({hasText: 'Playoff preparation is still needed.'});
      assert.equal(await warning.count(), 1);
      assert.match(await warning.innerText(), /2 configured playoff stage\(s\) have no fixtures/);
      const link = warning.getByRole('link', {name: 'Prepare playoffs in scheduling'});
      assert.match(await link.getAttribute('href'), /draw_ids%5B0%5D=/);
      assert.equal(await warning.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      await warning.scrollIntoViewIfNeeded();
      await page.screenshot({path: path.join(root, `storage/framework/testing/schedule-readiness-${width}.png`)});
      await page.close();
    }
  } finally { await browser.close(); }
});
