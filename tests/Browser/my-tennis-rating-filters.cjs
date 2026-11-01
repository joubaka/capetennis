/* Synthetic rating data, actual My Tennis AJAX script and native browser forms. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'resources/views/frontend/my-tennis/index.blade.php'), 'utf8');
const script = source.match(/<script>\s*(document.addEventListener\('DOMContentLoaded',[\s\S]*?)<\/script>/)[1];
const options = (values, selected) => values.map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`).join('');
function partial(params = new URLSearchParams()) {
  const gender = params.has('rating_gender') ? params.get('rating_gender') : 'girls';
  const level = params.get('rating_level') || 'overall';
  const month = params.get('month') || '0';
  return `<button class="active" data-rating-section="explore">Explore ratings</button>
    <form action="http://ct-rating.test/rating" data-rating-filters>
      <input type="hidden" name="month" value="${month}"><input type="hidden" name="scale" value="focused">
      <label>Gender <select id="ctr-rating-gender" name="rating_gender">${options([['', 'All genders'], ['girls', 'Girls'], ['boys', 'Boys']], gender)}</select></label>
      <label>Rating level <select id="ctr-rating-level" name="rating_level">${options([['overall', 'Overall CTR'], ['red', 'Red']], level)}</select></label>
    </form>
    <form action="http://ct-rating.test/rating" data-rating-filters>
      <input type="hidden" name="rating_gender" value="${gender}"><input type="hidden" name="rating_level" value="${level}">
      <label>Month <select id="rating-month" name="month">${options([['0', 'January'], ['3', 'April']], month)}</select></label>
    </form><p data-saved-result>Saved ${level} ratings; gender=${gender}; month=${month}</p>`;
}
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: {width, height: 900} });
      const errors = [], requests = [];
      let hold = false, release, fail = false, navigations = 0;
      page.on('pageerror', error => errors.push(error.message));
      page.on('framenavigated', () => navigations++);
      await page.route('http://ct-rating.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/fixture') return route.fulfill({contentType: 'text/html', body: `<!doctype html><html><body style="padding:16px">
          <button id="my-tennis-rating-tab">Rating</button><section id="my-tennis-rating" data-rating-url="http://ct-rating.test/rating"><p data-rating-placeholder>Open rating</p></section>
          <script>window.PlayerPathwayUI = class {}; window.PlayerRatingHistoryRefreshUI = class {sync() {}};
          window.bootstrap = {Tab: {getOrCreateInstance(element) {return {show() {element.classList.add('active'); element.dispatchEvent(new Event('shown.bs.tab'));}};}}};</script><script>${script}</script></body></html>`});
        assert.equal(route.request().headers()['x-requested-with'], 'XMLHttpRequest');
        requests.push(url);
        if (hold) {hold = false; await new Promise(resolve => {release = resolve;});}
        if (fail) {fail = false; return route.fulfill({status: 503, body: 'Unavailable'});}
        return route.fulfill({contentType: 'text/html', body: partial(url.searchParams)});
      });
      await page.goto('http://ct-rating.test/fixture');
      await page.locator('#my-tennis-rating-tab').dispatchEvent('shown.bs.tab');
      await page.locator('[data-saved-result]').waitFor();
      assert.equal(await page.locator('#ctr-rating-gender').inputValue(), 'girls');
      hold = true;
      await page.locator('#ctr-rating-level').selectOption('red');
      await page.waitForFunction(() => document.querySelector('#my-tennis-rating').getAttribute('aria-busy') === 'true');
      while (!release) await new Promise(resolve => setTimeout(resolve, 10));
      await page.locator('#rating-month').selectOption('3');
      await page.locator('#ctr-rating-gender').focus();
      await page.locator('#ctr-rating-gender').selectOption('');
      release();
      await page.waitForFunction(() => document.querySelector('[data-saved-result]').textContent === 'Saved red ratings; gender=; month=3');
      assert.equal(requests.length, 3, 'Rapid changes collapse into one follow-up request');
      assert.equal(await page.evaluate(() => document.activeElement.id), 'ctr-rating-gender');
      assert.equal(await page.locator('[data-rating-section="explore"].active').count(), 1);
      fail = true;
      await page.locator('#ctr-rating-gender').selectOption('boys');
      await page.locator('[data-rating-retry]').waitFor();
      assert.match(await page.locator('[data-saved-result]').textContent(), /Saved red ratings/);
      await page.locator('[data-rating-retry]').click();
      await page.waitForFunction(() => document.querySelector('[data-saved-result]').textContent.includes('gender=boys'));
      assert.equal(navigations, 1, 'Filters update without navigating');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('PASS: Rating AJAX updates, cross-form rapid changes, focus/section, retry and no navigation at 390/1440px.');
  } finally {await browser.close();}
})().catch(error => {console.error(error); process.exitCode = 1;});
