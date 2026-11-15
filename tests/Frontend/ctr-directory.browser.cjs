// Run with CT_DIRECTORY_FILTER_HTML and CT_DIRECTORY_OVERALL_HTML exported by PlayerRatingDirectoryFiltersTest.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');

for (const width of [390, 1440]) {
  test(`rating filters, pagination and history at ${width}px`, async () => {
    assert.ok(process.env.CT_DIRECTORY_FILTER_HTML, 'Export the fictional feature-test HTML first');
    const html = fs.readFileSync(process.env.CT_DIRECTORY_FILTER_HTML, 'utf8');
    assert.ok(process.env.CT_DIRECTORY_OVERALL_HTML, 'Export the fictional Overall HTML first');
    const overallHtml = fs.readFileSync(process.env.CT_DIRECTORY_OVERALL_HTML, 'utf8');
    const browser = await chromium.launch({headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
    try {
      const page = await browser.newPage({viewport: {width, height: 950}});
      const requests = [];
      await page.route('**/*', async route => {
        const requestUrl = new URL(route.request().url());
        if (['stylesheet', 'font', 'image'].includes(route.request().resourceType()) && ['localhost', 'ct.test'].includes(requestUrl.hostname)) {
          const publicRoot = path.resolve('public') + path.sep;
          const asset = path.resolve('public', '.' + decodeURIComponent(requestUrl.pathname));
          if (asset.startsWith(publicRoot) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({path: asset});
        }
        if (route.request().resourceType() !== 'document' && route.request().headers()['x-ctr-directory'] !== '1') return route.abort();
        requests.push(new URL(route.request().url()));
        return route.fulfill({contentType: 'text/html', body: new URL(route.request().url()).searchParams.get('ball_type') === 'overall' ? overallHtml : html});
      });
      await page.goto('http://localhost/backend/player-performance/ratings?ball_type=standard&age_group=u11&page=3');
      await page.addScriptTag({path: 'public/js/ctr-directory.js'});
      await page.locator('#ball-type').selectOption('overall');
      await page.locator('#ctr-apply-filters').click();
      await page.waitForFunction(() => document.querySelector('[data-ctr-status]').textContent === 'Player results updated.');
      const submitted = requests.at(-1);
      assert.equal(submitted.searchParams.get('ball_type'), 'overall');
      assert.equal(submitted.searchParams.has('page'), false);
      assert.equal(submitted.searchParams.has('u11_boys_page'), false);
      assert.equal(await page.locator('[data-ctr-directory]').getAttribute('aria-busy'), null);
      const firstUrl = page.url();
      assert.equal(new URL(firstUrl).searchParams.has('age_group'), false);
      assert.equal(new URL(firstUrl).searchParams.get('sort'), 'shared');
      assert.equal(await page.locator('#age-group').count(), 0);
      assert.equal(await page.locator('#ball-type').inputValue(), 'overall');
      const nextPage = page.locator('.pagination a[href*="page=2"]:visible').first();
      await nextPage.click();
      await page.waitForURL(/page=2/);
      assert.equal(requests.at(-1).searchParams.get('ball_type'), 'overall');
      assert.equal(requests.at(-1).searchParams.get('age_group') || '', '');
      assert.equal(await page.locator('#ctr-results').evaluate(element => element === document.activeElement), true);
      await page.locator('#search').fill('Synthetic');
      await page.locator('#ctr-apply-filters').click();
      await page.waitForURL(/search=Synthetic/);
      assert.equal(requests.at(-1).searchParams.get('search'), 'Synthetic');
      await page.goBack();
      await page.waitForURL(/page=2/);
      await page.waitForFunction(() => document.querySelector('[data-ctr-directory]').getAttribute('aria-busy') === null);
      assert.ok(await page.locator('#ctr-apply-filters').isVisible());
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow');
      if (process.env.CT_DIRECTORY_SCREENSHOT_DIR) {
        await page.locator('[data-ctr-directory]').screenshot({path: path.join(process.env.CT_DIRECTORY_SCREENSHOT_DIR, `ctr-directory-${width}.png`)});
      }
    } finally {
      await browser.close();
    }
  });
}
