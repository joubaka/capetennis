// Run with Playwright in NODE_PATH. Uses the actual Blade card markup and mocked
// responses; no application database or publication endpoint is contacted.
const fs = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

test('closed team draw cards show publication failures, recovery and success on phone and desktop', async () => {
  const template = fs.readFileSync('resources/views/backend/headOffice/team-event-show.blade.php', 'utf8');
  const start = template.indexOf('<details id="publication-draw-');
  const end = template.indexOf('<div class="event-draw-card-content">', start);
  const card = template.slice(start, end)
    .replace(/@php[\s\S]*?@endphp/g, '')
    .replace(/@include\([^\n]+/g, '')
    .replace(/\{\{[\s\S]*?\}\}/g, '1')
    .replace(/@disabled\([^\n]+?\)/g, '');
  const browser = await chromium.launch({ headless: true, ...(process.env.CT_BROWSER_EXECUTABLE ? { executablePath: process.env.CT_BROWSER_EXECUTABLE } : {}) });
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`<style>.d-none {display:none!important} [hidden]{display:none!important}</style>
        <meta name="csrf-token" content="test">${card}</details>
        <div data-event-draw-publication data-event-id="1" data-url="/mock" data-status-url="/status"><span data-draw-publication-summary></span></div>`);
      await page.evaluate(() => {
        document.querySelector('[data-quick-draw-publication]').dataset.published = 'false';
        window.replies = [];
        window.requests = [];
        window.fetch = async (url, options) => {
          window.requests.push({ url, options });
          const reply = window.replies.shift();
          if (reply === 'disconnect') throw new Error('Disconnected');
          return { ok: true, json: async () => reply };
        };
      });
      await page.addScriptTag({ path: 'public/js/head-office-draw-publication.js' });
      const state = published => ({ success: true, event_id: 1, draw_states: [{ id: 1, published, locked: false, oop_published: false }], draw_summary: {}, failed: [] });
      const failed = state(false);
      failed.success = false;
      failed.failed = [{ id: 1, message: 'Draw is not ready to publish: review team ties.' }];
      await page.evaluate(result => window.replies.push(result), failed);
      await page.locator('[data-quick-draw-publication]').click();
      const feedback = page.locator('[data-quick-draw-feedback]');
      await feedback.filter({ hasText: 'review team ties' }).waitFor({ state: 'visible' });
      assert.equal(await page.locator('[data-quick-publication-card]').getAttribute('open'), null);
      assert.equal(await page.locator('[data-quick-draw-publication]').textContent(), 'Publish');
      await page.evaluate(() => window.replies.push('disconnect', 'disconnect'));
      await page.locator('[data-quick-draw-publication]').click();
      await page.locator('[data-quick-draw-status-retry]').waitFor({ state: 'visible' });
      await page.evaluate(result => window.replies.push(result), state(false));
      await page.locator('[data-quick-draw-status-retry]').click();
      await feedback.filter({ hasText: 'statuses confirmed' }).waitFor({ state: 'visible' });
      await page.evaluate(result => window.replies.push(result), state(true));
      await page.locator('[data-quick-draw-publication]').click();
      await feedback.filter({ hasText: 'Draw published.' }).waitFor({ state: 'visible' });
      assert.equal(await page.locator('[data-quick-draw-publication]').textContent(), 'Unpublish');
      const requests = await page.evaluate(() => window.requests);
      assert.deepEqual(JSON.parse(requests.at(-1).options.body), { operation: 'draws', action: 'publish', draw_ids: [1] });
      assert.equal(requests.filter(request => request.options.method === 'POST').length, 3);
      assert.equal(await page.locator('[data-quick-publication-card]').getAttribute('open'), null);
      await page.close();
    }
  } finally {
    await browser.close();
  }
});
