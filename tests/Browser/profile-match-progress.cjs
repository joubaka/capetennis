/* Fictional authorized Profile rendered by Laravel; local assets, no live data. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtures = path.join(root, 'storage/framework/testing');
const read = name => fs.readFileSync(path.join(fixtures, name), 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname !== 'localhost') return route.abort();
      if (url.pathname === '/profile-chart-qa') {
        const pending = url.searchParams.get('state') === 'pending';
        return route.fulfill({ contentType: 'text/html', body: read(pending ? 'profile-match-progress-pending-qa.html' : 'profile-match-progress-qa.html') });
      }
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://localhost/profile-chart-qa', { waitUntil: 'networkidle' });
      const card = page.locator('[data-profile-match-progress]');
      assert.equal(await card.count(), 1);
      assert.equal(await card.evaluate(el => {
        const header = document.querySelector('[data-player-profile-header]');
        const overview = document.querySelector('[data-player-overview]');
        return !!(header.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)
          && !!(el.compareDocumentPosition(overview) & Node.DOCUMENT_POSITION_FOLLOWING);
      }), true, 'chart immediately precedes existing profile overview');
      assert.match(await card.innerText(), /Reconstructed using scheduled match order/);
      const explanation = new URL(await card.getByRole('link', { name: /Explore match explanations/ }).getAttribute('href'), 'http://localhost');
      assert.equal(explanation.hash, '#ctr-match-progress-heading');
      const pairs = await card.locator('[data-profile-match-pair]').evaluateAll(nodes => nodes.map(el => ({
        before: Number(el.dataset.before), after: Number(el.dataset.after), delta: Number(el.dataset.delta),
      })));
      assert.ok(pairs.length >= 3 && pairs.length <= 5);
      assert.deepEqual(pairs, [
        { before: 70, after: 71.2, delta: 1.2 },
        { before: 62, after: 60.5, delta: -1.5 },
        { before: 55, after: 55, delta: 0 },
        { before: 0, after: 100, delta: 100 },
      ], 'chronological saved pairs remain exact; unavailable match has no numeric arrow');
      for (const pair of pairs) {
        assert.ok(pair.before >= 0 && pair.before <= 100 && pair.after >= 0 && pair.after <= 100);
        assert.ok(Math.abs(Math.round((pair.after - pair.before) * 10) / 10 - pair.delta) < .001);
      }
      assert.ok(pairs.some(pair => pair.delta > 0));
      assert.ok(pairs.some(pair => pair.delta < 0));
      assert.ok(pairs.some(pair => pair.delta === 0));
      const scroll = card.locator('[data-profile-match-chart]');
      await scroll.focus();
      assert.equal(await scroll.evaluate(el => document.activeElement === el), true);
      if (width < 650) {
        assert.ok(await scroll.evaluate(el => el.scrollWidth > el.clientWidth));
        await page.keyboard.press('ArrowRight');
        await page.waitForFunction(() => document.querySelector('[data-profile-match-chart]').scrollLeft > 0);
      }
      const values = card.locator('.pm-values');
      await values.locator('summary').focus();
      await page.keyboard.press('Space');
      assert.equal(await values.evaluate(el => el.open), true);
      assert.equal(await card.locator('[data-profile-match-row]').count(), 5);
      assert.match(await values.innerText(), /Before[\s\S]*After[\s\S]*Change/);
      assert.match(await values.innerText(), /Unavailable|unavailable|uncertain/);
      const issues = await card.evaluate(el => {
        const problems = [];
        if (el.scrollWidth > el.clientWidth + 1) problems.push('card overflow');
        for (const node of el.querySelectorAll('a, summary, [data-profile-match-chart], [data-profile-match-row]')) {
          const box = node.getBoundingClientRect();
          if (box.width && (box.left < -1 || box.right > innerWidth + 1)) problems.push('clipped ' + node.tagName);
        }
        return problems;
      });
      assert.deepEqual(issues, [], `${width}px`);
      await card.screenshot({ path: path.join(fixtures, `profile-match-progress-${width}.png`) });
      await page.goto('http://localhost/profile-chart-qa?state=pending', { waitUntil: 'networkidle' });
      assert.equal(await page.locator('[data-profile-match-progress]').count(), 1);
      assert.equal(await page.locator('[data-profile-match-pair]').count(), 0);
      assert.equal(await page.locator('[data-profile-match-chart]').count(), 0);
      assert.ok(await page.locator('[data-profile-match-pending]').isVisible());
    }
    console.log('PASS: prominent Profile match-rating graph, signed before/after pairs, unavailable/pending states and keyboard/scroll containment at 360/390/768/1440px.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
