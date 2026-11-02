/* Super User endpoint snapshots with actual linked CSS; all requests served locally.
 * Guest/non-Super User discovery follows the sales switch in HTTP feature tests.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const read = name => fs.readFileSync(path.join(root, `storage/framework/testing/ctr-marketing-${name}.html`), 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const homeHtml = read('home');
const introHtml = read('intro');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const styles = [];
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname === 'localhost' && url.pathname === '/ctr') return route.fulfill({ contentType: 'text/html', body: introHtml });
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (url.hostname !== 'localhost' || !target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) styles.push(url.pathname);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(homeHtml, { waitUntil: 'networkidle' });
      const banner = page.locator('[data-home-ctr]');
      assert.equal(await banner.isVisible(), true);
      assert.equal(await banner.locator('h2').innerText(), 'Discover CTR Rankings');
      const link = banner.getByRole('link', { name: 'Explore CTR Rankings' });
      assert.equal(new URL(await link.getAttribute('href')).pathname, '/ctr');
      await banner.screenshot({ path: path.join(root, `storage/framework/testing/ctr-marketing-home-${width}.png`) });
      const linkHeight = await link.evaluate(el => el.getBoundingClientRect().height);
      assert.ok(linkHeight >= 43.9, `home link ${width}px height ${linkHeight}`);
      assert.equal(await banner.locator('h2').evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
      assert.equal(await banner.evaluate(el => {
        const rect = el.getBoundingClientRect();
        return el.scrollWidth <= el.clientWidth + 1 && rect.left >= -1 && rect.right <= innerWidth + 1;
      }), true, `home ${width}px`);
      await link.click();
      await page.waitForLoadState('networkidle');
      assert.equal(new URL(page.url()).pathname, '/ctr');
      const intro = page.locator('[data-ctr-intro]');
      assert.equal(await intro.locator('h1').innerText(), 'Discover CTR Rankings');
      assert.equal(await intro.locator('h1').evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
      assert.match(await intro.innerText(), /Your CTR access is active/);
      assert.match(await intro.innerText(), /1 January to 31 December/);
      assert.equal(await intro.locator('[data-ctr-rating], [data-ctr-top-player]').count(), 0);
      assert.equal(await intro.getByRole('link', { name: 'Open CTR ratings' }).isVisible(), true);
      assert.equal(await intro.getByRole('link', { name: 'View series rankings' }).isVisible(), true);
      assert.deepEqual(await intro.evaluate(el => {
        const errors = [];
        if (el.scrollWidth > el.clientWidth + 1) errors.push('intro overflow');
        for (const node of el.querySelectorAll('section, a')) {
          const rect = node.getBoundingClientRect();
          if (rect.left < -1 || rect.right > innerWidth + 1) errors.push('clipped ' + node.tagName);
        }
        return errors;
      }), [], `intro ${width}px`);
      await intro.screenshot({ path: path.join(root, `storage/framework/testing/ctr-marketing-intro-${width}.png`) });
      console.log(`PASS ${width}px: Super User home banner, CTR navigation, protected directory link, containment`);
    }
    const guestHome = read('guest-home');
    const guestIntro = read('guest-intro');
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(guestHome, { waitUntil: 'networkidle' });
      assert.equal(await page.locator('[data-home-ctr]').isVisible(), true);
      await page.setContent(guestIntro, { waitUntil: 'networkidle' });
      const intro = page.locator('[data-ctr-intro]');
      assert.equal(await intro.getByRole('link', { name: 'Open CTR ratings' }).count(), 0);
      assert.equal(await intro.getByRole('link', { name: 'Explore Player Plus' }).isVisible(), true);
      assert.equal(await intro.locator('[data-ctr-rating], [data-ctr-top-player]').count(), 0);
      assert.ok(await intro.evaluate(el => el.scrollWidth <= el.clientWidth + 1), 'guest intro fits');
      await intro.screenshot({ path: path.join(root, 'storage/framework/testing/ctr-marketing-guest-' + width + '.png') });
      console.log('PASS guest ' + width + 'px: marketing visible, admin and premium data absent');
    }
    assert.ok(styles.length, 'Actual linked stylesheets must load');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
