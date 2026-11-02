/* Synthetic authorized guidance response, real shared loader and pathway JS. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const loading = fs.readFileSync(path.join(root, 'public/assets/js/site-loading.js'), 'utf8');
const pathway = fs.readFileSync(path.join(root, 'public/assets/js/player-pathway.js'), 'utf8');
const style = fs.readFileSync(path.join(root, 'resources/views/layouts/sections/loading.blade.php'), 'utf8').match(/<style>[\s\S]*?<\/style>/)[0];
(async () => {
  const browser = await chromium.launch({channel: 'msedge', headless: true});
  try {
    for (const width of [390, 1440]) {
      const page = await browser.newPage({viewport: {width, height: 900}});
      let release, fail = true;
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('http://ct-pathway.test/**', async route => {
        if (new URL(route.request().url()).pathname === '/fixture') return route.fulfill({contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8">${style}</head><body style="font-family:Arial;padding:16px"><main id="panel"><ol><li><h2>Wilson A</h2><button id="more" data-pathway-info="/details" aria-expanded="false">More info</button><div data-pathway-info-content hidden></div></li></ol></main><script>${loading}</script><script>${pathway}</script><script>new PlayerPathwayUI(document.getElementById('panel'),()=>{});</script></body></html>`});
        await new Promise(resolve => {release = resolve;});
        return route.fulfill({status: fail ? 503 : 200, contentType: 'text/html', body: fail ? 'Unavailable' : '<div data-pathway-stage-details="wilson-a">Wilson A guidance</div>'});
      });
      await page.goto('http://ct-pathway.test/fixture');
      for (const failed of [true, false]) {
        fail = failed; release = null;
        await page.locator('#more').click();
        await page.locator('#ct-loading-dialog[open]').waitFor();
        assert.equal(await page.locator('#more').isDisabled(), true);
        assert.equal(await page.locator('#ct-loading-title').textContent(), 'Loading pathway details');
        assert.equal(await page.locator('#ct-loading-dialog').evaluate(el => el.getBoundingClientRect().width <= innerWidth), true);
        assert.equal(await page.locator('.ct-loading-spinner').evaluate(el => getComputedStyle(el).borderTopColor), 'rgb(0, 65, 119)');
        if (failed) await page.screenshot({path: path.join(root, 'storage/app/private', `pathway-loading-${width}.png`)});
        while (!release) await new Promise(resolve => setTimeout(resolve, 10));
        release();
        await page.locator('#ct-loading-dialog[open]').waitFor({state: 'hidden'});
        assert.equal(await page.locator('#more').isEnabled(), true);
        assert.equal(await page.evaluate(() => document.activeElement.id), 'more');
        assert.match(await page.locator('[data-pathway-info-content]').textContent(), failed ? /try again/ : /Wilson A guidance/);
      }
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('PASS: Shared More info modal appears immediately, stays blue/responsive, closes on error/success, restores focus and retries at 390/1440px.');
  } finally {await browser.close();}
})().catch(error => {console.error(error); process.exitCode = 1;});
