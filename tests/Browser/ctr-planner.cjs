/* Actual fictional Blade exports; all HTTP requests are fulfilled locally. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'storage/app/testing/ctr-planner');
const publicRoot = path.join(root, 'public');
const mime = file => ({ '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.png': 'image/png' }[path.extname(file)] || 'application/octet-stream');
const fixture = name => path.join(output, (name === 'review' ? 'preview' : name) + '.html');
async function main() {
  for (const state of ['initial', 'review', 'blocked', 'created']) assert.ok(fs.existsSync(fixture(state)), `Missing fictional export ${state}.html`);
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const report = [], submitted = [], missing = new Set(), errors = [];
  try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const request = route.request(), url = new URL(request.url());
      if (!['localhost', '127.0.0.1', 'ct.test'].includes(url.hostname)) return route.abort();
      if (request.method() !== 'GET') {
        if (/\/ctr-planner\//.test(url.pathname)) submitted.push({ path: url.pathname, method: request.method(), body: request.postData() });
        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Fictional submission captured</title><p>Captured locally; no application endpoint called.</p>' });
      }
      const state = url.pathname.match(/^\/ctr-qa\/(initial|review|blocked|created)$/)?.[1];
      if (state) return route.fulfill({ path: fixture(state), contentType: 'text/html' });
      const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname).replace(/^\/ct\/public\//, '/'));
      if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ path: asset, contentType: mime(asset) });
      if (['stylesheet', 'script', 'font'].includes(request.resourceType())) missing.add(url.pathname);
      return route.abort();
    });
    for (const width of [360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const state of ['initial', 'review', 'blocked', 'created']) {
        await page.goto(`http://localhost/ctr-qa/${state}`, { waitUntil: 'networkidle' });
        const planner = page.locator('#ctr-planner');
        assert.equal(await planner.count(), 1);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${state} ${width}: document containment`);
        assert.match(await planner.innerText(), /CTR Certified/);
        if (state === 'initial') {
          assert.equal(await page.locator('#ctr-target').inputValue(), '4');
          assert.equal(await page.locator('#ctr-plan-all').isEnabled(), true);
        }
        if (state === 'review') {
          assert.equal(await page.locator('#ctr-review input[type=number]').count(), 11);
          assert.equal(await page.locator('#ctr-proposal article').count(), 3);
          const sizes = await page.locator('#ctr-proposal article li').evaluateAll(nodes => nodes.reduce((counts, node) => { const article = node.closest('article'); const index = [...article.parentElement.querySelectorAll('article')].indexOf(article); counts[index] = (counts[index] || 0) + 1; return counts; }, []));
          assert.deepEqual(sizes, [4, 4, 3]);
          assert.match(await page.locator('#ctr-prize-total').innerText(), /3 winner prizes \+ 3 sportsmanship prizes = 6 prizes/);
          assert.equal(await page.locator('#ctr-confirm').isEnabled(), true);
          for (const selector of ['#ctr-confirm', '#ctr-review-changes', '#ctr-review input[type=number]']) {
            const box = await page.locator(selector).first().boundingBox();
            assert.ok(box && box.height >= 40, `${selector} reachable touch target`);
          }
        }
        if (state === 'blocked') assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
        if (state === 'created') {
          assert.equal(await page.locator('#ctr-created').count(), 1);
          assert.equal(await page.locator('#ctr-confirm').count(), 0);
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(output, `${state}-${width}.png`), fullPage: true });
        report.push({ state, width, containment: true });
      }
    }
    await page.goto('http://localhost/ctr-qa/review', { waitUntil: 'networkidle' });
    const input = page.locator('#ctr-review input[type=number]').first();
    await input.fill('2');
    assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Unsaved placement edits must disable confirmation');
    await page.locator('#ctr-review-changes').click();
    await page.waitForLoadState('networkidle');
    assert.equal(submitted.length, 1);
    assert.match(submitted[0].body, /placements/);
    assert.match(submitted[0].body, /revision/);
    await page.goto('http://localhost/ctr-qa/review', { waitUntil: 'networkidle' });
    await page.locator('#ctr-confirm').click();
    await page.waitForLoadState('networkidle');
    assert.equal(submitted.length, 2);
    const confirmed = new URLSearchParams(submitted[1].body);
    assert.ok(confirmed.get('token'));
    assert.ok(confirmed.get('revision'));
    assert.equal([...confirmed.keys()].some(key => key.startsWith('placements')), false, 'Confirmation submits saved proposal identity only');
    assert.deepEqual([...missing], [], 'No missing local execution/style assets');
    assert.deepEqual(errors, [], 'No browser runtime errors');
    fs.writeFileSync(path.join(output, 'browser-report.json'), JSON.stringify({ fictional: true, report, submissionsCaptured: submitted.length, errors, missingAssets: [...missing] }, null, 2));
    console.log(`PASS: ${report.length} fictional page/viewport checks; dirty edit guard; review and confirm captured locally.`);
  } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
