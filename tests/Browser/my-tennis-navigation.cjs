/* Run after exporting MyTennisDashboardIntegrationTest fixtures with CT_DASHBOARD_QA_HTML=1. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtures = path.join(root, 'storage/framework/testing/dashboard-integration-qa');
const fixture = name => fs.readFileSync(path.join(fixtures, name + '.html'), 'utf8');

async function run() {
  const browser = await chromium.launch(process.env.CT_CHROMIUM_PATH
    ? { executablePath: process.env.CT_CHROMIUM_PATH } : { channel: 'msedge' });
  try {
    for (const width of [320, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 860 } });
      const requests = [];
      const errors = [];
      let failActivity = true;
      let finishFailedActivity;
      const failedActivity = new Promise(resolve => { finishFailedActivity = resolve; });
      let finishActivityRetry;
      const activityRetry = new Promise(resolve => { finishActivityRetry = resolve; });
      let documentLoads = 0;
      let ratingFragmentLoads = 0;
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.hostname !== 'localhost') return route.abort();
        if (request.isNavigationRequest()) {
          documentLoads++;
          return route.fulfill({ contentType: 'text/html', body: fixture('ajax-entry') });
        }
        if (request.headers()['x-my-tennis-navigation'] === '1') {
          const tab = url.searchParams.get('tab') || ({
            record: 'record', opponents: 'opponents', pathway: 'pathway', 'event-ratings': 'events',
          })[url.pathname.split('/').pop()];
          assert.ok(tab, url.href);
          requests.push(tab);
          if (tab === 'activity' && failActivity) {
            failActivity = false;
            await failedActivity;
            return route.fulfill({ status: 503 });
          }
          if (tab === 'activity') await activityRetry;
          return route.fulfill({ contentType: 'text/html', body: fixture('ajax-' + tab) });
        }
        if (url.pathname.endsWith('/rating') && request.headers()['x-requested-with'] === 'XMLHttpRequest') {
          ratingFragmentLoads++;
          return route.fulfill({ contentType: 'text/html', body: fixture('rating-fragment') });
        }
        if (url.pathname.endsWith('/match-reminder')) return route.fulfill({ json: { players: [] } });
        const asset = path.resolve(publicRoot, '.' + url.pathname);
        if (!asset.startsWith(publicRoot + path.sep) || !fs.existsSync(asset) || !fs.statSync(asset).isFile()) return route.abort();
        return route.fulfill({ path: asset });
      });
      await page.goto('http://localhost/my-tennis');
      const workspace = page.locator('[data-my-tennis-workspace]');
      const nav = workspace.getByRole('navigation', { name: 'My Tennis views' });
      let navigationBounds;
      const assertAligned = async (tabs, count, rows) => {
        const bounds = await tabs.evaluateAll(elements => elements.map(element => {
          const { x, y, width, height } = element.getBoundingClientRect();
          return { x, y, width, height };
        }));
        assert.equal(bounds.length, count);
        assert.equal(new Set(bounds.map(tab => tab.y)).size, rows);
        assert.ok(bounds.every(tab => Math.abs(tab.width - bounds[0].width) < 1
          && Math.abs(tab.height - bounds[0].height) < 1 && tab.height >= 44
          && tab.x >= 0 && tab.x + tab.width <= width), JSON.stringify({ width, bounds }));
      };
      const active = async name => {
        await nav.getByRole('link', { name, exact: true }).and(nav.locator('[aria-current=page]')).waitFor();
        await assertAligned(nav.getByRole('link'), 6, width < 768 ? 2 : 1);
        const bounds = await nav.evaluate(element => {
          const { x, y, width, height } = element.getBoundingClientRect();
          return { x: x + window.scrollX, y: y + window.scrollY, width, height };
        });
        if (navigationBounds && !['x', 'y', 'width', 'height'].every(key => Math.abs(bounds[key] - navigationBounds[key]) < 1)) {
          const tabs = await nav.getByRole('link').evaluateAll(links => links.map(link => {
            const style = getComputedStyle(link);
            return [link.textContent.trim(), style.fontSize, style.lineHeight, style.padding, style.whiteSpace];
          }));
          assert.fail(JSON.stringify({ width, name, bounds, navigationBounds, tabs }));
        }
        navigationBounds = bounds;
      };
      await active('Profile');
      const activityTab = nav.getByRole('link', { name: 'Activity', exact: true });
      const activitySpinner = activityTab.locator('[data-my-tennis-tab-spinner]');
      await activityTab.click();
      assert.equal(await activityTab.getAttribute('data-my-tennis-tab-pending'), '');
      await activitySpinner.waitFor();
      assert.equal(await workspace.getAttribute('aria-busy'), 'true');
      assert.equal(await activityTab.getAttribute('aria-current'), null);
      finishFailedActivity();
      await activitySpinner.waitFor({ state: 'detached' });
      await workspace.getByRole('button', { name: 'Try again' }).click();
      await activitySpinner.waitFor();
      finishActivityRetry();
      await active('Activity');
      await activitySpinner.waitFor({ state: 'detached' });
      assert.equal(await workspace.getAttribute('aria-busy'), null);
      await workspace.getByRole('button', { name: 'Match reminders', exact: true }).click();
      const reminder = page.locator('#match-reminder');
      await reminder.getByText('No published, unfinished matches', { exact: false }).waitFor();
      await reminder.getByRole('button', { name: 'Close', exact: true }).last().click();
      await reminder.waitFor({ state: 'hidden' });
      for (const name of ['Rating', 'Results', 'Opponents', 'Tournament options']) {
        await nav.getByRole('link', { name, exact: true }).click();
        await active(name);
        if (name === 'Rating') {
          await workspace.getByRole('heading', { name: 'Player rating', exact: true }).waitFor();
          await workspace.locator('.ctr-section-tabs .nav-link').first().waitFor();
          await assertAligned(workspace.locator('.ctr-section-tabs .nav-link'), 3, 1);
          await workspace.getByRole('link', { name: 'Past-event ratings', exact: true }).last().click();
          await workspace.getByRole('heading', { name: 'Past-event ratings', exact: false }).waitFor();
        }
      }
      await page.goBack();
      await active('Opponents');
      await page.goForward();
      await active('Tournament options');
      const levels = workspace.getByText('See all levels and targets', { exact: true });
      await levels.waitFor();
      assert.equal(await levels.evaluate(summary => summary.parentElement.open), true);
      await levels.click();
      assert.equal(await levels.evaluate(summary => summary.parentElement.open), false);
      await levels.click();
      await workspace.locator('[data-player-pathway] details[open]').waitFor();
      await nav.getByRole('link', { name: 'Profile' }).click();
      await active('Profile');
      await workspace.getByRole('link', { name: 'Manage players', exact: true }).click();
      await workspace.getByRole('heading', { name: 'Manage player profiles' }).waitFor();
      await workspace.getByRole('button', { name: 'Player access' }).click();
      await workspace.locator('#link-player-panel.show').waitFor();
      assert.equal(documentLoads, 1);
      assert.equal(ratingFragmentLoads, 0);
      assert.deepEqual(requests, ['activity', 'activity', 'rating', 'events', 'record', 'opponents', 'pathway', 'opponents', 'pathway', 'profile', 'manage']);
      assert.deepEqual(errors, []);
      await page.close();
    }
  } finally {
    await browser.close();
  }
}

run().catch(error => { console.error(error); process.exitCode = 1; });
