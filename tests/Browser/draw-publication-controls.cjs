// Render fixtures with CT_DRAW_PUBLICATION_QA=1 and DrawPublicationToolbarTest first.
// Uses real Blade output/local CSS and JS, with publication requests doubled in memory.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('draw and scheduler publication controls fit narrow screens and keep publication actions separate', async () => {
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const css = ['public/assets/vendor/css/core.css', 'public/assets/vendor/css/theme-default.css',
      'public/css/backend-workspace.css', 'public/css/mobile-workspace.css', 'public/css/draw-workspace-navigation.css'].map(read).join('\n');
    for (const surface of ['header', 'scheduler']) {
      const markup = read(`storage/app/testing/draw-publication-${surface}.html`).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
      for (const width of [320, 390, 430, 768, 1280]) {
        await page.setViewportSize({width, height: 900});
        await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="testing">
          <style>${css} body {font-family:system-ui,sans-serif}</style><body class="ct-backend"><main class="container-xxl p-2">${markup}</main>
          <button id="fm-publish" style="display:none">Editor publication action</button>`);
        await page.evaluate(() => {
          window.confirm = () => true;
          window.requests = [];
          window.editorClicks = 0;
          window.failPublication = false;
          document.getElementById('fm-publish').addEventListener('click', () => {editorClicks++;});
          document.addEventListener('click', event => {if (event.target.closest('a')) event.preventDefault();}, true);
          window.fetch = async (url, options) => {
            requests.push({url, body: options.body ? JSON.parse(options.body) : null});
            if (failPublication) return {ok: false, json: async () => ({message: 'Publication test failure. Please retry.'})};
            return {ok: true, json: async () => ({published: true, revision: 3, oop_published: true})};
          };
        });
        await page.addScriptTag({path: path.join(root, 'public/assets/vendor/libs/popper/popper.js')});
        await page.addScriptTag({path: path.join(root, 'public/assets/vendor/js/bootstrap.js')});
        await page.addScriptTag({path: path.join(root, 'public/js/draw-workspace-navigation.js')});
        if (surface === 'header') {
          await page.getByRole('button', {name: 'Publish', exact: true}).click();
          assert.equal(await page.getByRole('button', {name: 'Switch draw', exact: true}).count(), 1);
          assert.equal(await page.getByRole('link', {name: 'Previous draw', exact: true}).count(), 0);
          assert.equal(await page.getByRole('link', {name: 'Next draw', exact: true}).count(), 0);
        } else {
          assert.equal(await page.getByRole('link', {name: 'Previous draw', exact: true}).count(), 1);
          assert.equal(await page.getByRole('link', {name: 'Next draw', exact: true}).count(), 1);
        }
        assert.equal(await page.locator('[data-draw-publication]').count(), 1);
        assert.equal(await page.getByRole('button', {name: 'Publish draw', exact: true}).count(), 1);
        assert.equal(await page.getByRole('button', {name: 'Publish schedule', exact: true}).count(), 1);
        assert.deepEqual(await page.evaluate(() => {
          const failures = [];
          if (document.documentElement.scrollWidth > innerWidth) failures.push('page overflow');
          for (const element of document.querySelectorAll('[data-draw-publication] button, nav[aria-label] a')) {
            if (!element.getClientRects().length) continue;
            const rect = element.getBoundingClientRect();
            if (rect.left < -1 || rect.right > innerWidth + 1) failures.push('clipped publication or navigation control');
          }
          return failures;
        }), [], `${surface} layout at ${width}px`);
        if (width === 320) await page.screenshot({path: path.join(root, `storage/app/testing/draw-publication-${surface}-320.png`), fullPage: true});
        const draw = page.getByRole('button', {name: 'Publish draw', exact: true});
        const schedule = page.getByRole('button', {name: 'Publish schedule', exact: true});
        await draw.click();
        if (surface === 'header') {
          assert.equal(await page.evaluate(() => editorClicks), 1, 'draw toolbar delegates to the revision-aware editor');
          assert.equal(await page.evaluate(() => requests.length), 0);
        } else {
          await page.getByRole('button', {name: 'Unpublish draw', exact: true}).waitFor();
          const body = await page.evaluate(() => requests[0].body);
          assert.deepEqual(body, {revision: 2, published: true});
          assert.equal(await schedule.getAttribute('aria-pressed'), 'false');
        }
        if (surface === 'header' && !(await schedule.isVisible())) await page.getByRole('button', {name: 'Publish', exact: true}).click();
        await schedule.click();
        const publishedSchedule = page.getByRole('button', {name: 'Unpublish schedule', exact: true});
        await publishedSchedule.waitFor();
        const requestsBeforeFailure = await page.evaluate(() => requests.length);
        await page.evaluate(() => {window.failPublication = true;});
        await publishedSchedule.click();
        await page.getByText('Publication test failure. Please retry.', {exact: true}).first().waitFor();
        assert.equal(await publishedSchedule.getAttribute('aria-pressed'), 'true');
        assert.equal(await publishedSchedule.isEnabled(), true);
        assert.equal(await page.evaluate(() => requests.length), requestsBeforeFailure + 1, 'failure does not silently retry');
        if (surface === 'header') {
          await page.locator('summary').filter({hasText: 'More tools'}).click();
          const alternate = page.getByRole('link', {name: 'Replace with alternate', exact: true});
          await alternate.waitFor();
          const bounds = await alternate.boundingBox();
          assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, `tools fit at ${width}px`);
          assert.equal(await page.locator('[data-workspace-draw-status]').textContent(), 'Draw: unpublished');
          assert.equal(await page.locator('[data-workspace-schedule-status]').textContent(), 'Schedule: published');
        }
      }
    }
    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
  }
});
