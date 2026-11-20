/* Fictional form + mocked AJAX only; never submits proposals or contacts a live endpoint. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixture = fs.readFileSync(path.join(root, 'storage/framework/testing/host-event-form.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') + '<script src="/assets/js/host-court-capacity.js"></script>';
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
      const originalFetch = window.fetch;
      window.hostSuggestionCalls = [];
      window.hostCompletedRed = [];
      window.hostAjaxMode = 'normal';
      window.fetch = async (url, options) => {
        if (!String(url).includes('/host-an-event/suggestions')) return originalFetch(url, options);
        const courts = Number(options.body.get('courts'));
        const assigned = [0, 1, 2, 3].map(i => Number(options.body.get(`assigned_courts[${i}]`)));
        window.hostSuggestionCalls.push({ courts, assigned, auto: options.body.get('use_suggested_limits'), csrf: options.body.get('_token'), token: options.body.get('submission_token'), method: options.method });
        // Deliberately ignore AbortSignal so revision handling must reject an older response.
        const delay = window.hostAjaxMode === 'race' && assigned[0] === 2 ? 700 : 30;
        await new Promise(resolve => setTimeout(resolve, delay));
        window.hostCompletedRed.push(assigned[0]);
        const total = assigned.reduce((sum, value) => sum + value, 0);
        if (window.hostAjaxMode === 'failure' || total > courts || total < 1) {
          return { ok: false, json: async () => ({ errors: { assigned_courts: ['Assign at least one court, without exceeding your total available courts.'] } }) };
        }
        const limits = assigned.map((count, i) => count * (i === 0 ? 16 : 4));
        const cents = value => { const [whole, fraction = ''] = String(value).split('.'); return Number(whole) * 100 + Number(fraction.padEnd(2, '0')); };
        const auto = options.body.get('use_suggested_limits') === '1';
        const entries = auto ? limits : [0, 1, 2, 3].map(i => Number(options.body.get('limits[' + i + ']')));
        const rows = entries.map((count, i) => {
          const fee = cents(options.body.get('fees[' + i + ']'));
          const gross = count * fee;
          const processing = Math.floor((fee * 500 + 5000) / 10000) * count;
          const platform = count * 1500;
          return { entries: count, fee, gross, processing, platform, retained: gross - processing - platform };
        });
        const totals = Object.fromEntries(['entries', 'gross', 'processing', 'platform', 'retained'].map(key => [key, rows.reduce((sum, row) => sum + row[key], 0)]));
        const hours = options.body.get('rental_hours');
        const rate = options.body.get('rental_rate');
        totals.convenor = cents(options.body.get('convenor_cost'));
        totals.rental = Math.floor((total * cents(hours) * cents(rate) + 50) / 100);
        totals.other_costs = cents(options.body.get('other_costs'));
        totals.remaining = totals.retained - totals.convenor - totals.rental - totals.other_costs;
        const financial_projection = { rows, totals, assumptions: { courts: total, hours, rental: rate } };
        return { ok: true, json: async () => ({ model: 'court-ratio-v2', inputs: { courts, assigned_courts: assigned }, limits,
          assigned_total: total, suggested_entries: limits.reduce((sum, value) => sum + value, 0), financial_projection }) };
      };
    });
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      assert.equal(route.request().method(), 'GET', 'QA must never submit to a real endpoint');
      if (url.hostname !== 'localhost') return route.abort();
      if (url.pathname === '/host-capacity-qa') return route.fulfill({ contentType: 'text/html', body: fixture });
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname).replace(/^\/ct\/public/, ''));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [320, 360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://localhost/host-capacity-qa', { waitUntil: 'networkidle' });
      assert.equal(await page.locator('[data-entry-limit]').count(), 4);
      assert.equal(await page.locator('[data-host-submit]').isEnabled(), true);
      assert.equal(await page.locator('[data-projection-total="remaining"]').innerText(), 'R8,690.00');
      assert.equal(await page.getByRole('button', { name: /Calculate/ }).count(), 0);
      assert.equal(await page.locator('[name="playing_hours"], [name="slot_minutes"], [name="contingency_percent"]').count(), 0);
      assert.equal(await page.locator('[data-court-suggestion="0"]').innerText(), '16');
      await page.locator('#event_name').fill('Keep this organiser edit');
      await page.locator('[name="fees[0]"]').fill('225');
      await page.locator('[name="assigned_courts[0]"]').fill('2');
      assert.equal(await page.locator('[data-host-submit]').isDisabled(), true);
      await page.waitForFunction(() => document.querySelector('[data-capacity-status]').textContent.includes('without exceeding'));
      assert.equal(await page.locator('[data-court-suggestion="0"]').innerText(), '—');
      await page.locator('#courts').fill('11');
      await page.waitForFunction(() => document.querySelector('[data-court-suggestion="0"]').textContent === '32');
      assert.equal(await page.locator('[data-entry-limit="0"]').inputValue(), '32');
      assert.equal(await page.locator('[data-host-submit]').isEnabled(), true);
      assert.equal(await page.locator('#event_name').inputValue(), 'Keep this organiser edit');
      assert.equal(await page.locator('[name="fees[0]"]').inputValue(), '225');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `ratio form ${width}px`);
      await page.locator('[data-host-proposal]').screenshot({ path: path.join(root, `storage/framework/testing/host-court-capacity-${width}.png`) });
      console.log(`PASS ${width}px: always-visible categories, automatic Red32, allocation error/recovery, preserved details`);
    }
    await page.goto('http://localhost/host-capacity-qa', { waitUntil: 'networkidle' });
    await page.evaluate(() => { window.hostAjaxMode = 'race'; });
    await page.locator('#courts').fill('20');
    await page.locator('[name="assigned_courts[0]"]').fill('2');
    await page.waitForFunction(() => window.hostSuggestionCalls.some(call => call.assigned[0] === 2));
    await page.locator('[name="assigned_courts[0]"]').fill('3');
    await page.waitForFunction(() => document.querySelector('[data-court-suggestion="0"]').textContent === '48');
    await page.waitForFunction(() => window.hostCompletedRed.includes(2));
    assert.equal(await page.locator('[data-entry-limit="0"]').inputValue(), '48', 'Older successful response cannot overwrite newer suggestions');
    await page.locator('[data-entry-limit="0"]').fill('8');
    await page.waitForFunction(() => document.querySelector('[data-projection-total="entries"]').textContent === '44');
    await page.locator('[name="fees[0]"]').fill('250');
    assert.equal(await page.locator('[name="fees[0]"]').isEnabled(), true, 'Typing is never disabled');
    await page.waitForFunction(() => document.querySelector('[data-projection-cell="0-fee"]').textContent === 'R250.00');
    assert.equal(await page.locator('[data-entry-limit="0"]').inputValue(), '8', 'Fee edit preserves reduced entry limit');
    await page.locator('#convenor_cost').fill('750');
    await page.waitForFunction(() => document.querySelector('[data-projection-total="convenor"]').textContent === 'R750.00');
    assert.equal(await page.locator('[data-entry-limit="0"]').inputValue(), '8', 'Expense edit preserves reduced entry limit');
    await page.evaluate(() => { window.hostAjaxMode = 'failure'; });
    await page.locator('[name="assigned_courts[1]"]').fill('3');
    await page.waitForFunction(() => document.querySelector('[data-capacity-status]').textContent.includes('without exceeding'));
    assert.equal(await page.locator('[data-host-submit]').isDisabled(), true);
    assert.equal(await page.locator('[data-court-suggestion="0"]').innerText(), '—');
    const calls = await page.evaluate(() => window.hostSuggestionCalls);
    assert.ok(calls.every(call => call.method === 'POST' && call.csrf && call.token), 'Mocked AJAX includes CSRF and actor token');
    assert.equal(await page.locator('[data-projection-values]').isVisible(), false, 'Failed AJAX hides stale finances');
    await page.goto('http://localhost/host-capacity-qa', { waitUntil: 'networkidle' });
    await page.locator('#courts').fill('11');
    await page.locator('[name="assigned_courts[0]"]').fill('2');
    await page.locator('[name="fees[0]"]').fill('240');
    await page.waitForFunction(() => document.querySelector('[data-projection-cell="0-fee"]').textContent === 'R240.00' && document.querySelector('[data-entry-limit="0"]').value === '32');
    assert.equal(await page.locator('[name="fees[0]"]').inputValue(), '240', 'Mixed assignment and fee edit preserved');
    await page.locator('[data-entry-limit="0"]').fill('0');
    await page.locator('[data-entry-limit="1"]').fill('0');
    await page.locator('[data-entry-limit="2"]').fill('0');
    await page.locator('[data-entry-limit="3"]').fill('0');
    await page.waitForFunction(() => document.querySelector('[data-projection-result-label]').textContent === 'Projected loss');
    assert.equal(await page.locator('[data-projection-total="remaining"]').innerText(), 'R-2,700.00');
    assert.equal(await page.locator('[data-host-submit]').isDisabled(), true, 'Zero-entry projection cannot submit');
    assert.deepEqual(errors, []);
    console.log('PASS stale/out-of-order responses, failed AJAX, disabled submit, CSRF payload; no real POSTs');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
