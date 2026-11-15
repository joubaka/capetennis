/* Uses fictional HTTP-rendered fixtures exported by MyTennisOverviewTest. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = process.env.CT_OVERVIEW_QA_DIR || path.join(root, 'storage/framework/testing/overview-qa');
const screenshotRoot = process.env.CT_OVERVIEW_SCREENSHOT_DIR || path.join(fixtureRoot, 'screenshots');

async function run() {
  fs.mkdirSync(screenshotRoot, { recursive: true });
  const browser = await chromium.launch(process.env.CT_CHROMIUM_PATH
    ? { executablePath: process.env.CT_CHROMIUM_PATH } : { channel: 'msedge' });
  try {
    for (const width of [320, 390, 768, 1440]) {
      for (const name of ['member', 'non-plus', 'preview']) {
        const html = fs.readFileSync(path.join(fixtureRoot, `${name}.html`), 'utf8');
        const page = await browser.newPage({ viewport: { width, height: 950 } });
        await page.route('**/*', async route => {
          const request = route.request();
          const url = new URL(request.url());
          if (!['localhost', 'ct.test'].includes(url.hostname)) return route.abort();
          if (request.isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: html });
          const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
          if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) {
            return route.fulfill({ path: asset });
          }
          return route.abort();
        });
        await page.goto('http://localhost/my-tennis?player=1&tab=profile');
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} overflow at ${width}`);
        if (name === 'member') {
          const overview = page.locator('[data-player-overview]');
          await overview.waitFor();
          const report = overview.locator('[data-monthly-report]');
          await report.waitFor();
          const monthLink = report.getByRole('link', { name: /Explore this month/ });
          const url = new URL(await monthLink.getAttribute('href'), 'http://localhost');
          assert.match(url.searchParams.get('record_month') || '', /^\d{4}-\d{2}$/);
          assert.equal(url.searchParams.get('year'), url.searchParams.get('record_month').slice(0, 4));
          const edit = page.getByRole('link', { name: 'Edit profile', exact: true });
          await edit.scrollIntoViewIfNeeded();
          assert.ok(await edit.isVisible(), 'Contact editing remains reachable');
          await monthLink.focus();
          assert.equal(await monthLink.evaluate(node => node === document.activeElement), true);
          await page.screenshot({ path: path.join(screenshotRoot, `overview-member-${width}.png`), fullPage: true });
        } else {
          const sample = page.locator('[data-plus-sample]');
          await sample.waitFor();
          assert.match(await sample.innerText(), /Fictional example/);
          assert.equal(await sample.locator('dd').evaluateAll(nodes => nodes.every(node => getComputedStyle(node).filter === 'none')), true, 'Examples remain readable');
          assert.equal(await page.locator('[data-monthly-report]').count(), 0, 'No personal report in a marketing preview');
          const cta = page.getByRole('link', { name: /View membership and availability/ }).first();
          await cta.scrollIntoViewIfNeeded();
          assert.ok(await cta.isVisible());
          if (width === 390 || width === 1440) await page.screenshot({ path: path.join(screenshotRoot, `overview-${name}-${width}.png`), fullPage: true });
        }
        console.log(`PASS ${name} at ${width}px`);
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
}

run().catch(error => { console.error(error); process.exitCode = 1; });
