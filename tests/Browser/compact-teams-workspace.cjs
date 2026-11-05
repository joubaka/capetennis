// Render fixtures with CT_CENTRAL_TEAMS_QA=1 using CentralTeamWorkspaceTest
// and TeamWorkspaceResultsTest::test_band_first_keeps_zero_win_player_above_winning_lower_band.
// This runs the rendered Teams shell, real JavaScript and local styles with
// synthetic Laravel responses. Every request is intercepted.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = path.join(root, 'storage/framework/testing');
const fixture = name => fs.readFileSync(path.join(fixtureRoot, 'qa-central-' + name), 'utf8');
const origin = 'http://localhost';

test('compact Teams tabs are reachable on desktop, tablet and phone', async () => {
  const browser = await chromium.launch(process.env.CT_BROWSER_EXECUTABLE
    ? { executablePath: process.env.CT_BROWSER_EXECUTABLE }
    : { channel: 'msedge' });
  try {
    for (const width of [1440, 900, 820, 390, 320]) {
      const viewport = { width, height: width >= 768 ? 900 : 844 };
      const page = await browser.newPage({ viewport, hasTouch: viewport.width < 600, serviceWorkers: 'block' });
      page.setDefaultTimeout(10000);
      const errors = [];
      let resultsScenario = false;
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.origin !== origin) return route.abort();
        if (url.pathname === '/backend/team/orderPlayerList' && route.request().method() === 'POST') {
          const fields = new URLSearchParams(route.request().postData());
          const players = [];
          for (let index = 0; fields.has(`order[${index}][id]`); index++) {
            const field = name => fields.get(`order[${index}][${name}]`);
            players.push({ id: Number(field('id')), type: field('type'), rank: Number(field('position')) });
          }
          assert.deepEqual(players.map(player => player.rank), [1, 2, 4]);
          return route.fulfill({ json: { players } });
        }
        if (resultsScenario && url.pathname.endsWith('/eventCategory/data') && route.request().method() === 'POST') {
          const body = route.request().postDataJSON();
          if (body?.result_group === '10-boys' && Array.isArray(body.formats)) {
            return route.fulfill({ contentType: 'application/json', body: fixture('results.json') });
          }
        }
        if (route.request().method() !== 'GET') return route.abort();
        if (resultsScenario && url.searchParams.has('group_key')) return route.fulfill({ json: { draft: null } });
        if (url.searchParams.has('workspace_fragment')) {
          return route.fulfill({ contentType: 'application/json', body: fixture('selection.json') });
        }
        if (url.searchParams.has('roster_region')) {
          const panel = url.searchParams.get('panel') === 'order' ? 'order' : 'roster';
          return route.fulfill({ contentType: 'text/html', body: fixture(panel + '.html') });
        }
        if (route.request().isNavigationRequest()) {
          return route.fulfill({ contentType: 'text/html', body: fixture(resultsScenario ? 'result-shell.html' : 'shell.html') });
        }
        const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) {
          return route.fulfill({ path: asset });
        }
        return route.abort();
      });
      await page.goto(origin + '/backend/event/1/teams');
      const workspace = page.locator('.team-admin-workspace');
      const clippedTabs = await workspace.locator('[data-teams-workspace] [role="tab"]').evaluateAll(tabs =>
        tabs.filter(tab => {
          const { left, right } = tab.getBoundingClientRect();
          return left < 0 || right > innerWidth;
        }).map(tab => tab.id));
      assert.deepEqual(clippedTabs, [], `${viewport.width}px tab visibility`);
      const region = workspace.locator('#roster-region');
      const team = workspace.locator('#workspace-team');
      const regionId = await region.inputValue();
      const teamId = await team.locator(`option[data-region="${regionId}"]`).first().getAttribute('value');
      await workspace.locator('#tab-players [data-roster-team] > summary').first().waitFor({ state: 'visible' });
      assert.equal(await workspace.locator('#tab-players [data-roster-team][open]').count(), 0);
      await workspace.locator('#order-tab').click();
      await workspace.locator('#tab-order [data-order-team] > summary').first().waitFor({ state: 'visible' });
      assert.equal(await workspace.locator('#tab-order [data-order-team][open]').count(), 0);
      await workspace.locator('#players-tab').click();
      await team.selectOption(teamId);

      const checkPanel = async (tab, panel, evidence) => {
        await workspace.locator(tab).click();
        await workspace.locator(panel).locator(evidence).first().waitFor({ state: 'visible' });
        await page.waitForFunction(selector => getComputedStyle(document.querySelector(selector)).opacity === '1', panel);
        assert.equal(await region.inputValue(), regionId, tab);
        assert.equal(await team.inputValue(), teamId, tab);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(fixtureRoot, `compact-teams-${viewport.width}-${tab.slice(1)}.png`) });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        assert.ok(overflow <= 2, `${viewport.width}px page overflow of ${overflow}px on ${tab}`);
      };

      await checkPanel('#players-tab', '#tab-players', '[data-roster-row]');
      if (width === 900) {
        const toolbar = workspace.locator('[data-team-context]');
        const controlBottoms = await toolbar.locator('#roster-region, #workspace-team, #roster-search, #roster-payment')
          .evaluateAll(controls => controls.map(control => Math.round(control.getBoundingClientRect().bottom)));
        assert.ok(Math.max(...controlBottoms) - Math.min(...controlBottoms) <= 3, `Roster filters wrapped at ${width}px`);
      }
      const contact = workspace.locator('#tab-players td[data-label="Contact"] a[href="mailto:confidential-roster@example.test"]');
      await contact.waitFor({ state: 'visible' });
      await workspace.locator('#tab-players .roster-contact a[href="tel:0215550100"]').waitFor({ state: 'visible' });
      await contact.scrollIntoViewIfNeeded();
      await page.screenshot({ path: path.join(fixtureRoot, `compact-teams-${viewport.width}-contact.png`) });
      if (width >= 768) {
        const ratedRow = workspace.locator('#tab-players [data-roster-row]:visible').filter({ has: page.locator('[data-ctr-badge]') }).first();
        const dimensions = await ratedRow.evaluate(row => ({
          display: getComputedStyle(row).display,
          height: row.getBoundingClientRect().height,
          cellTops: [...row.cells].map(cell => cell.getBoundingClientRect().top),
          cellContents: [...row.cells].map(cell => [cell.dataset.label, ...[...cell.children].map(child => Math.round(child.getBoundingClientRect().height))].join(' ')),
        }));
        assert.equal(dimensions.display, 'table-row', `${width}px collapsed rated player layout`);
        assert.ok(dimensions.height <= 64, `${width}px rated row is ${dimensions.height}px high (${dimensions.cellContents.join(', ')})`);
        assert.equal(new Set(dimensions.cellTops).size, 1, `${width}px player columns share a line`);
        await ratedRow.scrollIntoViewIfNeeded();
        await page.screenshot({ path: path.join(fixtureRoot, `compact-teams-${width}-dense-roster.png`) });
      }
      await workspace.locator('[data-roster-search]').fill('Does not exist');
      await workspace.locator('[data-roster-empty]').first().waitFor({ state: 'visible' });
      await workspace.locator('[data-roster-clear]').click();
      await workspace.locator('[data-roster-row]').first().waitFor({ state: 'visible' });
      await checkPanel('#order-tab', '#tab-order', '[data-order-move="up"]');
      const orderRows = workspace.locator('#tab-order tbody.sortablePlayers:visible tr.drag-item');
      const firstSlot = await orderRows.first().getAttribute('data-playerteamid');
      const move = orderRows.first().locator('[data-order-move="down"]');
      await move.focus();
      await move.press('Enter');
      await workspace.locator('#tab-order [data-order-status]:visible').filter({ hasText: 'Order saved' }).waitFor();
      assert.notEqual(await orderRows.first().getAttribute('data-playerteamid'), firstSlot);
      await checkPanel('#communications-tab', '#tab-communications', 'text=Regional announcements');
      await workspace.locator('#tab-communications [data-team-email-open]').first().click();
      await page.locator('#team-email-purpose').waitFor({ state: 'visible' });
      assert.match(await page.locator('[data-team-email-context]').innerText(), /North Primary/);
      await page.locator('#team-email-purpose .btn-close').click();
      await page.locator('#team-email-purpose').waitFor({ state: 'hidden' });
      await checkPanel('#campaigns-tab', '#tab-campaigns', 'text=Event setup guidance');
      assert.equal(await workspace.locator('[data-central-selection-host]').count(), 1);
      await checkPanel('#regions-tab', '#tab-regions', '#regionsAccordion');
      const regionToggle = workspace.locator('#regionsAccordion .accordion-button').first();
      if (await regionToggle.getAttribute('aria-expanded') === 'false') await regionToggle.click();
      const setupTeam = workspace.locator('#tab-regions [data-team-row]').first();
      await setupTeam.locator('.setup-team-tools > summary').click();
      await setupTeam.locator('.publishTeam').waitFor({ state: 'visible' });
      await checkPanel('#result-rank-button', '#tab-result-rank', '#category-table');
      await checkPanel('#players-tab', '#tab-players', '[data-roster-row]');

      resultsScenario = true;
      await page.goto(origin + '/backend/event/1/teams?roster_tab=result-rank#tab-result-rank');
      const ranking = workspace.locator('#tab-result-rank');
      const resultPlayers = ranking.locator('[data-result-player]');
      await resultPlayers.first().waitFor({ state: 'visible' });
      assert.equal(await resultPlayers.count(), 2);
      const evidence = ranking.locator('details.result-player-evidence > summary').first();
      await evidence.click();
      assert.equal(await evidence.locator('..').getAttribute('open'), '');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2), 'result evidence overflow');
      await page.screenshot({ path: path.join(fixtureRoot, `compact-teams-${viewport.width}-result-evidence.png`) });
      assert.deepEqual(errors, [], `${viewport.width}px page errors`);
      console.log(`Checked all six tabs at ${viewport.width}px`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
});
