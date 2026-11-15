/* Rendered fictional Results page and real local assets; no live app/database reads. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = path.join(root, 'storage/framework/testing');
const html = fs.readFileSync(path.join(fixtureRoot, 'results-overview-qa.html'), 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const navigation = fs.readFileSync(path.join(publicRoot, 'assets/js/my-tennis-navigation.js'), 'utf8');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    let liveMarkup = '';
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname !== 'localhost') return route.abort();
      if (url.pathname === '/results-qa') {
        const variant = url.searchParams.get('variant');
        const variants = ['all', 'extreme', 'zero', 'unavailable'];
        const body = variants.includes(variant)
          ? fs.readFileSync(path.join(fixtureRoot, `results-overview-${variant}-qa.html`), 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') : html;
        return route.fulfill({ contentType: 'text/html', body });
      }
      if (url.pathname.endsWith('/live-results')) return route.fulfill({ contentType: 'text/html', body: liveMarkup });
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://localhost/results-qa', { waitUntil: 'networkidle' });
      const overview = page.locator('[data-results-overview]');
      const chart = overview.locator('[data-match-activity]');
      assert.equal(await chart.count(), 1);
      assert.equal(await page.locator('[data-match-activity]').count(), 1);
      assert.equal(await overview.locator('[data-results-stat]').count(), 4);
      for (const [key, expected] of Object.entries({ played: '124', wins: '93', losses: '31', rate: '75%' })) {
        assert.equal(await overview.locator(`[data-results-stat="${key}"]`).innerText(), expected);
      }
      assert.ok(await overview.locator('[data-results-stat="played"]').evaluate(el => parseFloat(getComputedStyle(el).fontSize) >= 28));
      assert.equal(await overview.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(20, 45, 80)');
      assert.equal(await overview.evaluate(el => !!(el.compareDocumentPosition(document.querySelector('[data-my-tennis-live-results]')) & Node.DOCUMENT_POSITION_FOLLOWING)), true);
      const metrics = await overview.locator('.overview-metrics').boundingBox();
      const plot = await chart.boundingBox();
      if (width >= 992) assert.ok(Math.abs(metrics.y - plot.y) < 50, 'desktop chart alongside metrics');
      else assert.ok(plot.y > metrics.y, 'phone chart below metrics');
      const selected = overview.locator('#record-year');
      assert.ok(await selected.evaluate(el => el.getBoundingClientRect().height >= 44));
      const monthLink = chart.locator('a.record-month-link').first();
      const target = new URL(await monthLink.getAttribute('href'), 'http://localhost');
      assert.ok(target.searchParams.get('record_month'));
      assert.equal(target.searchParams.get('year'), '2026');
      assert.equal(target.hash, '#match-results');
      const recent = page.locator('[data-my-tennis-live-results]');
      const more = recent.locator('[data-recent-results-more]');
      assert.equal(await recent.locator('.record-row:visible').count(), 3);
      await more.locator('summary').focus();
      await page.keyboard.press('Space');
      assert.equal(await more.evaluate(el => el.open), true);
      assert.ok(await recent.locator('.record-row:visible').count() > 3);
      const problems = await overview.evaluate(el => {
        const issues = [];
        if (el.scrollWidth > el.clientWidth + 1) issues.push('overview overflow');
        for (const node of el.querySelectorAll('select, button, .overview-metric, [data-match-activity]')) {
          const box = node.getBoundingClientRect();
          if (box.width && (box.left < -1 || box.right > innerWidth + 1)) issues.push('clipped ' + node.tagName);
        }
        return issues;
      });
      assert.deepEqual(problems, [], `${width}px`);
      await overview.scrollIntoViewIfNeeded();
      await page.screenshot({ path: path.join(fixtureRoot, `results-overview-${width}.png`), fullPage: true });
    }
    for (const variant of ['all', 'extreme', 'zero', 'unavailable']) {
      await page.setViewportSize({ width: 360, height: 1000 });
      await page.goto(`http://localhost/results-qa?variant=${variant}`, { waitUntil: 'networkidle' });
      const overview = page.locator('[data-results-overview]');
      if (variant === 'all') {
        assert.equal(await overview.locator('[data-results-stat="played"]').innerText(), '125');
        assert.match(await overview.locator('[data-match-activity]').innerText(), /Latest 12 saved months/);
      } else if (variant === 'extreme') {
        assert.equal(await overview.locator('[data-results-stat="rate"]').innerText(), '100%');
        assert.equal(await overview.locator('[data-results-stat="rate"]').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      } else if (variant === 'zero') {
        assert.equal(await overview.locator('[data-results-stat="played"]').innerText(), '0');
        assert.equal(await overview.locator('[data-results-stat="rate"]').innerText(), '—');
        assert.match(await overview.locator('[data-match-activity]').innerText(), /No saved match activity/);
      } else {
        assert.equal(await overview.locator('[data-results-stat]').count(), 0);
        assert.equal(await overview.locator('[data-match-activity]').count(), 0);
        assert.ok(await overview.locator('[role="status"]').count());
      }
      assert.equal(await overview.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, variant);
    }
    await page.goto('http://localhost/results-qa', { waitUntil: 'networkidle' });
    await page.locator('[data-recent-results-more] summary').click();
    // Exercise the actual refresh script with a changed response while the disclosure is open.
    await page.clock.install();
    const recent = page.locator('[data-my-tennis-live-results]');
    liveMarkup = await recent.evaluate(el => {
      const next = el.cloneNode(true);
      next.querySelector('[data-recent-results-more]').removeAttribute('open');
      next.querySelector('.record-score').textContent = 'Updated score';
      return next.outerHTML;
    });
    await recent.locator('[data-recent-results-more] summary').focus();
    await page.addScriptTag({ content: navigation });
    const refresh = page.waitForResponse(response => response.url().endsWith('/live-results'));
    await page.clock.runFor(15100);
    await refresh;
    await page.waitForFunction(() => document.querySelector('[data-my-tennis-live-results] .record-score')?.textContent === 'Updated score');
    assert.equal(await page.locator('[data-recent-results-more]').evaluate(el => el.open), true);
    assert.equal(await page.locator('[data-recent-results-more] summary').evaluate(el => document.activeElement === el), true);
    console.log('PASS: Results overview/chart ordering, responsive containment, month links, native disclosure and polling state/focus at 360/390/768/1440px.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
