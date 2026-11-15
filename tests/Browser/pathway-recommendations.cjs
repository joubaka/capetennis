/* Actual fictional Laravel route/Blade fixture; local assets only, no live data. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixture = path.join(root, 'storage/framework/testing/pathway-events-qa.html');
const html = fs.readFileSync(fixture, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname !== 'localhost') return route.abort();
      if (url.pathname === '/pathway-qa') return route.fulfill({ contentType: 'text/html', body: html });
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.goto('http://localhost/pathway-qa', { waitUntil: 'networkidle' });
      const panel = page.locator('#player-development-pathway');
      assert.equal(await panel.locator('[data-pathway-applicable-stage]').innerText(), 'TSA tournaments');
      assert.equal(await panel.locator('[data-pathway-stage-state]').getAttribute('data-pathway-stage-state'), 'update_pending');
      assert.equal(await panel.locator('[data-pathway-snapshot] [aria-current="step"]').count(), 1);
      assert.equal(await panel.locator('[data-pathway-event]').count(), 1);
      const action = panel.getByRole('link', { name: 'View matching events', exact: true });
      assert.equal(await action.getAttribute('href'), '#pathway-upcoming-events');
      assert.ok(await action.evaluate(el => el.getBoundingClientRect().height >= 44));
      await action.click();
      await panel.locator('#pathway-upcoming-events').scrollIntoViewIfNeeded();
      assert.ok(await panel.getByRole('link', { name: 'Check entry requirements', exact: true }).isVisible());
      const errors = await panel.evaluate(el => {
        const issues = [];
        if (el.scrollWidth > el.clientWidth + 1) issues.push('panel overflow');
        for (const item of el.querySelectorAll('article, button, .pathway-browse')) {
          const box = item.getBoundingClientRect();
          if (box.width && (box.left < -1 || box.right > innerWidth + 1)) issues.push('clipped ' + item.tagName);
        }
        return issues;
      });
      assert.deepEqual(errors, [], `${width}px`);
      await page.screenshot({ path: path.join(root, `storage/framework/testing/pathway-events-${width}.png`), fullPage: true });
    }
    console.log('PASS: rendered saved-stage summary, selected card, event anchor, public event action and containment at 360/390/768/1440px.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
