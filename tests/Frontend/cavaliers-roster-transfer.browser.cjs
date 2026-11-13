// First export the actual Blade fragment with CT_ROSTER_TRANSFER_QA_OUTPUT in CavaliersTrialRosterDraftTest.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const {buildFixture} = require('./cavaliers-roster-transfer-fixture.cjs');

test('roster transfers persist ordered full drafts, refill merit, reject caps and recover ambiguous failures without publishing', async () => {
  const directory = process.env.CT_ROSTER_TRANSFER_QA_OUTPUT || 'storage/app/testing/cavaliers-transfer-qa';
  const html = buildFixture(fs.readFileSync(path.join(directory, 'roster.html'), 'utf8'));
  const browser = await chromium.launch({headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
  try {
    const page = await browser.newPage({viewport: {width: 480, height: 900}});
    page.on('dialog', dialog => dialog.accept());
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.route('https://roster.test/**', route => {
      const file = new URL(route.request().url()).pathname;
      if (file.startsWith('/public/')) return route.fulfill({path: path.resolve('.' + file)});
      return route.fulfill({contentType: 'text/html', body: html});
    });
    await page.goto('https://roster.test/');
    const list = key => page.locator(`[data-list="${key}"] > li .fw-semibold`);
    const names = key => list(key).allTextContents();
    const merit = () => page.locator('[data-merit-list] > li .fw-semibold').allTextContents();
    const saved = async count => { await page.waitForFunction(count => window.rosterTransferFixture.puts.length === count && document.querySelector('[data-status]').textContent.includes('Draft saved.'), count); };
    const add = (id, key) => page.locator(`[data-player-id="${id}"][data-transfer-target="${key}"]`).click();
    const action = async (key, index, label) => {
      const row = page.locator(`[data-list="${key}"] > li`).nth(index);
      await row.getByRole('button', {name: /^Actions —/}).click();
      await row.getByRole('button', {name: `${label} — Fictional Player ${String((await row.locator('.fw-semibold').innerText()).match(/\d+/)[0]).padStart(2,'0')}`, exact: true}).click();
    };
    await page.locator('[data-player-id="5"][data-transfer-target="selected"]').waitFor();
    await add(5, 'selected'); await saved(1);
    assert.deepEqual(await names('selected'), ['Fictional Player 01','Fictional Player 02','Fictional Player 05']);
    assert.equal((await merit())[0], 'Fictional Player 06'); assert.equal((await merit()).length, 10);
    await add(6, 'reserves'); await saved(2);
    await action('selected', 1, 'Move to reserves'); await saved(3);
    assert.deepEqual(await names('reserves'), ['Fictional Player 03','Fictional Player 06','Fictional Player 02']);
    await action('reserves', 0, 'Move to nominations'); await saved(4);
    assert.deepEqual(await names('selected'), ['Fictional Player 01','Fictional Player 05','Fictional Player 03']);
    await action('selected', 1, 'Return to merit list'); await saved(5);
    assert.equal((await merit())[0], 'Fictional Player 05');
    // Pending manual reordering is included atomically by the next direct transfer.
    await page.locator('[data-list="selected"] > li').nth(1).getByRole('button', {name: /^Up —/}).click();
    await add(7, 'selected'); await saved(6);
    assert.deepEqual(await page.evaluate(() => window.rosterTransferFixture.draft.selected.map(p => p.id)), [3,1,7]);
    assert.equal(await page.locator('[data-save]').isDisabled(), true);
    await page.selectOption('[data-fixture-mode]', 'reject422');
    await add(8, 'selected'); await page.waitForFunction(() => document.querySelector('[data-status]').textContent.includes('Transfer rejected.'));
    assert.deepEqual(await names('selected'), ['Fictional Player 03','Fictional Player 01','Fictional Player 07']);
    assert.equal(await page.locator('[data-player-id="8"][data-transfer-target="selected"]').isEnabled(), true);
    await page.selectOption('[data-fixture-mode]', 'commit503'); await add(8, 'selected');
    await page.waitForFunction(() => document.querySelector('[data-status]').textContent.includes('outcome is unconfirmed'));
    assert.equal((await names('selected')).length, 3); assert.equal(await page.locator('[data-player-id="8"][data-transfer-target="selected"]').isDisabled(), true);
    assert.equal(await page.evaluate(() => window.rosterTransferFixture.draft.selected.at(-1).id), 8);
    await page.locator('[data-reload]').click(); await list('selected').nth(3).waitFor();
    await page.selectOption('[data-fixture-mode]', 'conflict409'); await add(9, 'selected');
    await page.waitForFunction(() => document.querySelector('[data-status]').textContent.includes('Reload it before continuing'));
    assert.equal(await page.locator('[data-player-id="9"][data-transfer-target="selected"]').isDisabled(), true);
    await page.locator('[data-reload]').click(); await page.locator('[data-player-id="9"][data-transfer-target="selected"]:enabled').waitFor();
    await page.fill('[data-fixture-delay]', '400');
    const before = await page.evaluate(() => window.rosterTransferFixture.puts.length);
    await add(9, 'selected'); assert.equal(await page.locator('[data-division-choice]').first().isDisabled(), true);
    await page.evaluate(() => document.querySelector('[data-player-id="10"][data-transfer-target="selected"]').click());
    await saved(before + 1); assert.equal(await page.evaluate(() => window.rosterTransferFixture.puts.length), before + 1);
    await page.locator('[data-fixture-full]').click(); await page.waitForFunction(() => document.querySelector('[data-count="selected"]').textContent === '32 / 32');
    assert.equal(await page.locator('[data-merit-list] [data-transfer-target="selected"]').first().isDisabled(), true);
    assert.equal(await page.locator('[data-merit-list] [data-transfer-target="reserves"]').first().isDisabled(), true);
    assert.equal(await page.evaluate(() => window.rosterTransferFixture.publicationCalls), 0);
    assert.deepEqual(errors, []);
    for (const width of [480,768,1280]) { await page.setViewportSize({width,height:900}); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); }
  } finally { await browser.close(); }
});
