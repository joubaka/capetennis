/* Presentation QA of synthetic Laravel-rendered ratings and actual linked styles.
 * Generate the fixture with CT_ALL_AGE_QA_HTML=1 and AllAgeRatingIntegrationTest.
 * No application, database, or external network requests are made here.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
let html = fs.readFileSync(path.join(root, 'storage/framework/testing/all-age-ratings.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const connections = path.join(root, 'storage/framework/testing/all-age-connections-fragment.html');
if (fs.existsSync(connections)) html = html.replace('<form method="get"', fs.readFileSync(connections, 'utf8') + '<form method="get"');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const loadedStyles = [];
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (url.hostname !== 'localhost' || !target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) {
        return route.abort();
      }
      if (target.endsWith('.css')) loadedStyles.push(url.pathname);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      const panel = page.locator('.operational-page').last();
      await assert.equal(await panel.locator('h1').innerText(), 'Cape Tennis all-age ratings');
      assert.equal(await panel.locator('.list-group-item').count(), 3);
      assert.match(await panel.innerText(), /No recent played-match connection/);
      assert.match(await panel.innerText(), /Low confidence — historical connection/);
      const layout = await panel.evaluate(element => {
        const errors = [];
        if (element.scrollWidth > element.clientWidth + 1) errors.push('ratings panel overflow');
        for (const control of element.querySelectorAll('select, input, button')) {
          const box = control.getBoundingClientRect();
          if (box.width && box.height < 43) errors.push('small control: ' + (control.id || control.textContent));
          if (box.left < -1 || box.right > innerWidth + 1) errors.push('clipped control: ' + control.id);
        }
        return errors;
      });
      assert.deepEqual(layout, [], `${width}px`);
      await panel.locator('#age-group').selectOption('u18');
      assert.equal(await panel.locator('#age-group').inputValue(), 'u18');
      await panel.locator('#search').fill('Synthetic test');
      const explanation = panel.locator('details').first();
      await explanation.locator(':scope > summary').click();
      assert.equal(await explanation.getAttribute('open'), '');
      const connectionPanel = panel.locator('[data-rating-connections]');
      if (await connectionPanel.count()) {
        assert.match(await connectionPanel.innerText(), /not scheduled matches or confirmed links/);
        assert.equal(await connectionPanel.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      }
      await panel.screenshot({ path: path.join(root, `storage/framework/testing/all-age-ratings-${width}.png`) });
      console.log(`PASS ${width}px: shared/withheld rows, filters, explanation and containment`);
    }
    assert.ok(loadedStyles.length > 0, 'Actual rendered stylesheet links must load');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
