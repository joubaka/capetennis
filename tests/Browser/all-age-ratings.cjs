/* Presentation QA of synthetic Laravel-rendered ratings and actual linked styles.
 * Generate fixtures with CT_ALL_AGE_QA_HTML=1 and the integration/group directory tests.
 * No application, database, or external network requests are made here.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const unavailableHtml = fs.readFileSync(path.join(root, 'storage/framework/testing/all-age-ratings-unavailable.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const scoreHtml = fs.readFileSync(path.join(root, 'storage/framework/testing/all-age-ratings-score.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const ageHtml = fs.readFileSync(path.join(root, 'storage/framework/testing/all-age-ratings-age.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const html = fs.readFileSync(path.join(root, 'storage/framework/testing/all-age-ratings.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

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
      assert.equal(await panel.locator('section > h2').count(), 1);
      assert.equal(await panel.locator('section > h2').innerText(), 'All players');
      assert.equal(await panel.locator('[data-rating-position]').count(), 2);
      assert.match(await panel.innerText(), /No recent played-match connection/);
      assert.match(await panel.innerText(), /Low confidence — historical connection/);
      assert.match(await panel.locator('#age-group').innerText(), /U11 · born 2015/);
      assert.match(await panel.innerText(), /1 January to 31 December/);
      assert.equal(await panel.locator('#rating-sort').isVisible(), false);
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
      const options = panel.locator('details').filter({ has: page.locator('summary', { hasText: /^More options$/ }) });
      await options.locator(':scope > summary').click();
      assert.equal(await panel.locator('#rating-sort').isVisible(), true);
      const explanation = panel.locator('details').filter({ has: page.locator('summary', { hasText: /^Rating details$/ }) });
      await explanation.locator(':scope > summary').click();
      assert.equal(await explanation.getAttribute('open'), '');
      const connectionPanel = panel.locator('[data-rating-connections]');
      for (const connection of await connectionPanel.all()) {
        if (await connection.getAttribute('open') === null) await connection.locator(':scope > summary').click();
        assert.match(await connection.innerText(), /not scheduled matches or confirmed links/);
        assert.equal(await connection.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      }
      await panel.screenshot({ path: path.join(root, `storage/framework/testing/all-age-ratings-${width}.png`) });
      console.log(`PASS ${width}px: shared/withheld rows, filters, explanation and containment`);
      await page.setContent(unavailableHtml, { waitUntil: 'networkidle' });
      const unavailable = page.locator('.operational-page').last();
      assert.equal(await unavailable.locator('section > h2').count(), 0);
      assert.equal(await unavailable.locator('.list-group-item').count(), 0);
      assert.equal(await unavailable.locator('[data-rating-position]').count(), 0);
      assert.match(await unavailable.innerText(), /Ratings are currently unavailable/);
      assert.match(await unavailable.innerText(), /No matching player profiles/);
      assert.equal(await unavailable.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, `unavailable ${width}px`);
      await unavailable.screenshot({ path: path.join(root, `storage/framework/testing/all-age-ratings-unavailable-${width}.png`) });
      console.log(`PASS ${width}px: unavailable snapshot excludes profiles without calculated groups`);
      await page.setContent(scoreHtml, { waitUntil: 'networkidle' });
      const scores = page.locator('.operational-page').last();
      assert.equal(await scores.locator('section > h2').count(), 1);
      assert.equal(await scores.locator('#rating-sort').inputValue(), 'score');
      assert.equal(await scores.locator('[data-rating-position]').count(), 0);
      assert.ok(await scores.locator('[data-score-list-position]').count() >= 3);
      assert.match(await scores.innerText(), /uncalibrated/i);
      assert.equal(await scores.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, `score order ${width}px`);
      await scores.screenshot({ path: path.join(root, `storage/framework/testing/all-age-ratings-score-${width}.png`) });
      console.log(`PASS ${width}px: optional all-score list, warning and list numbers`);
      await page.setContent(ageHtml, { waitUntil: 'networkidle' });
      const ages = page.locator('.operational-page').last();
      assert.ok(await ages.locator('section > h2').count() >= 2);
      assert.equal(await ages.locator('#rating-sort').inputValue(), 'age');
      assert.equal(await ages.locator('[data-rating-position]').count(), 0);
      assert.ok(await ages.locator('[data-score-list-position]').count() >= 3);
      assert.match(await ages.innerText(), /uncalibrated/i);
      assert.equal(await ages.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, `age order ${width}px`);
      await ages.screenshot({ path: path.join(root, `storage/framework/testing/all-age-ratings-age-${width}.png`) });
      console.log(`PASS ${width}px: age-group score order and list numbers`);
    }
    assert.ok(loadedStyles.length > 0, 'Actual rendered stylesheet links must load');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
