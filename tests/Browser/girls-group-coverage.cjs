/* Offline responsive QA of the synthetic, Laravel-rendered selected-group page. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const html = fs.readFileSync(path.join(root, 'storage/framework/testing/girls-group-coverage.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    let styles = 0;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (url.hostname !== 'localhost' || !target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) styles++;
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      const panel = page.locator('.operational-page').last();
      assert.match(await panel.innerText(), /Within-group ratings only/);
      assert.match(await panel.innerText(), /matching number in another group does not mean equal ability/);
      assert.match(await panel.innerText(), /Shared badges and profile ratings remain withheld/);
      const rows = panel.locator('.list-group-item');
      assert.equal(await rows.count(), 2);
      assert.match(await rows.first().innerText(), /Girls First/);
      assert.match(await rows.first().innerText(), /within-group only/);
      assert.doesNotMatch(await panel.innerText(), /Reference One/);
      const errors = await panel.evaluate(element => {
        const errors = [];
        if (element.scrollWidth > element.clientWidth + 1) errors.push('panel overflow');
        for (const control of element.querySelectorAll('select,input:not([type=hidden]),button')) {
          const box = control.getBoundingClientRect();
          if (box.width && box.height < 43) errors.push('small control');
          if (box.left < -1 || box.right > innerWidth + 1) errors.push('clipped control');
        }
        return errors;
      });
      assert.deepEqual(errors, [], `${width}px`);
      await panel.locator('#gender').selectOption('girls');
      assert.equal(await panel.locator('#gender').inputValue(), 'girls');
      assert.ok(await panel.locator('input[name=comparison_group]').inputValue());
      await panel.screenshot({ path: path.join(root, `storage/framework/testing/girls-group-coverage-${width}.png`) });
      console.log(`PASS ${width}px: selected girls group, scope labels, filters and containment`);
    }
    assert.ok(styles > 0, 'Actual linked styles loaded');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
