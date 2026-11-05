// Generate settings.html with CT_POINT_SHARING_FIXTURE_DIR and EventPointSharingTest.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const { chromium } = require('playwright');

const publicRoot = path.resolve(__dirname, '../../public');
const fixtureRoot = process.env.CT_POINT_SHARING_FIXTURE_DIR
  || path.resolve(__dirname, '../../storage/framework/testing/event-settings-qa');
const origin = 'http://localhost';

test('Event Settings navigation and point sharing work on desktop and phones', async () => {
  const browser = await chromium.launch(process.env.CT_BROWSER_EXECUTABLE
    ? { executablePath: process.env.CT_BROWSER_EXECUTABLE }
    : { channel: 'msedge' });
  try {
    for (const width of [1440, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, hasTouch: width < 600 });
      page.setDefaultTimeout(8000);
      const pageErrors = [];
      const saves = [];
      const generalSaves = [];
      page.on('pageerror', error => pageErrors.push(error.message));
      await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== origin) return route.abort();
        if (request.method() === 'PATCH' && url.pathname.endsWith('/settings')) {
          generalSaves.push(request.postDataJSON());
          return route.fulfill({ json: { message: 'Event settings saved.' } });
        }
        if (request.method() === 'PUT' && url.pathname.endsWith('/settings/point-sharing')) {
          saves.push(request.postDataJSON());
          if (saves.length === 1) {
            return route.fulfill({ status: 422, json: { message: 'Check the recipient regions.' } });
          }
          return route.fulfill({ json: { message: 'Point sharing saved.' } });
        }
        if (request.method() !== 'GET') return route.abort();
        if (request.isNavigationRequest()) return route.fulfill({ path: path.join(fixtureRoot, 'settings.html') });
        const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) {
          return route.fulfill({ path: asset });
        }
        return route.abort();
      });
      const settingsUrl = origin + '/backend/event/1/settings';
      await page.goto(settingsUrl);
      await page.locator('#settings-basics').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#settings-point-sharing').isVisible(), false);
      await page.screenshot({ path: path.join(fixtureRoot, 'basics-' + width + '.png') });
      await page.locator('#event-name').fill('Synthetic settings edit');
      await page.locator('#event-name').press('End');
      await page.waitForFunction(() => document.querySelector('[data-settings-status="basics"]').textContent === 'Changes saved.');
      assert.equal(generalSaves.length, 1);
      assert.equal(generalSaves[0].name, 'Synthetic settings edit');
      assert.equal(generalSaves[0].admins.length, 1);
      assert.ok(Array.isArray(generalSaves[0].scoring_accounts));
      assert.equal(await page.locator('[data-settings-status="access"]').textContent(), '');
      const open = async hash => {
        if (width < 768) await page.locator('#settings-section-select').selectOption(hash);
        else await page.locator('.settings-section-links a[href="' + hash + '"]').press('Enter');
        await page.locator(hash).waitFor({ state: 'visible' });
      };

      await page.goto(settingsUrl + '#settings-point-sharing');
      const sharing = page.locator('#settings-point-sharing');
      await sharing.waitFor({ state: 'visible' });
      assert.equal(await page.locator('#settings-basics').isVisible(), false);
      const search = sharing.locator('#sharing-team-search');
      const rows = sharing.locator('[data-point-sharing-team]');
      assert.equal(await rows.filter({ visible: true }).count(), 2);
      const boys = rows.filter({ hasText: 'Invitational U13 Boys' });
      await boys.locator('[data-sharing-edit]').click();
      await boys.locator('[data-sharing-active]').uncheck();
      await boys.locator('[data-sharing-edit]').click();
      await search.fill('Girls');
      assert.equal(await rows.filter({ visible: true }).count(), 1);
      const girls = rows.filter({ visible: true }).first();
      await girls.locator('[data-sharing-edit]').click();
      await girls.locator('[data-sharing-mode]').selectOption('split');
      assert.match(await girls.locator('[data-sharing-example]').innerText(), /12 ÷ 2/);
      await sharing.locator('#point-sharing-enabled').uncheck();
      assert.equal(await girls.locator('[data-sharing-summary-status]').innerText(), 'Ready');
      assert.equal(await sharing.locator('[data-sharing-state]').innerText(), 'Unsaved changes');
      assert.match(await sharing.locator('#point-sharing-status').innerText(), /unsaved/i);
      await sharing.locator('#save-point-sharing').click();
      await sharing.getByText('Check the recipient regions.', { exact: true }).waitFor();
      await open('#settings-access');
      assert.equal(await sharing.isVisible(), false);
      await open('#result-controls');
      assert.equal(await girls.locator('[data-sharing-mode]').inputValue(), 'split');
      await sharing.locator('#save-point-sharing').click();
      await sharing.getByText('Point sharing saved.', { exact: true }).waitFor();
      assert.equal(await sharing.locator('[data-sharing-state]').innerText(), 'Off');
      assert.equal(saves.length, 2);
      assert.deepEqual(saves[0], saves[1]);
      assert.equal(saves[1].rules.length, 2);
      assert.equal(saves[1].enabled, false);
      const editedTeam = Number(await girls.getAttribute('data-point-sharing-team'));
      assert.equal(saves[1].rules.find(rule => rule.team_id === editedTeam).mode, 'split');
      assert.equal(saves[1].rules.filter(rule => rule.mode === 'full').length, 1);
      assert.equal(saves[1].rules.find(rule => rule.mode === 'full').enabled, false);
      assert.equal(saves[1].rules.every(rule => rule.region_ids.length === 2), true);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
      assert.deepEqual(pageErrors, []);
      await page.screenshot({ path: path.join(fixtureRoot, 'point-sharing-' + width + '.png') });
      console.log('Verified Settings at ' + width + 'px');
      await page.close();
    }
  } finally {
    await browser.close();
  }
});
