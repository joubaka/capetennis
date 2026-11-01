/* Synthetic Laravel endpoint HTML with its actual stylesheet links; no live requests. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const html = fs.readFileSync(path.join(root, 'storage/framework/testing/ctr-top-ten.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const loadedStyles = [];
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (url.hostname !== 'localhost' || !target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) loadedStyles.push(url.pathname);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      const panel = page.locator('[data-ctr-top-ten]');
      assert.equal(await panel.count(), 1);
      assert.equal(await panel.locator('h2').innerText(), 'Top 10 CTR');
      assert.match(await panel.innerText(), /Provisional ratings do not change the official ranking/);
      assert.match(await panel.innerText(), /Within-group-only scores cannot establish a shared Top 10/);
      const group = panel.locator('details').first();
      assert.equal(await group.getAttribute('open'), null);
      assert.match(await group.locator('summary').innerText(), /U11 · born 2015 · Boys/);
      await group.locator('summary').click();
      assert.equal(await group.getAttribute('open'), '');
      assert.equal(await group.locator('[data-ctr-top-player]').count(), 1);
      assert.match(await group.innerText(), /72\.4\/100/);
      const badge = page.locator('[data-ctr-rating]').first();
      await badge.scrollIntoViewIfNeeded();
      assert.equal(await badge.isVisible(), true);
      assert.equal(await badge.innerText(), 'CTR 72.4');
      assert.deepEqual(await panel.evaluate(element => {
        const issues = [];
        if (element.scrollWidth > element.clientWidth + 1) issues.push('panel overflow');
        for (const node of element.querySelectorAll('summary, li, a')) {
          const box = node.getBoundingClientRect();
          if (box.left < -1 || box.right > innerWidth + 1) issues.push('clipped ' + node.tagName);
        }
        return issues;
      }), [], `${width}px`);
      assert.equal(await page.locator('.public-ranking-shell').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, `shell ${width}px`);
      await page.locator('.public-ranking-shell').screenshot({ path: path.join(root, `storage/framework/testing/ctr-top-ten-${width}.png`) });
      console.log(`PASS ${width}px: CTR group expands, badge visible, provisional warning and containment`);
    }
    assert.ok(loadedStyles.length > 0, 'Actual linked stylesheets must load');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
