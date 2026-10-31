// Real Laravel HTTP/API journey in a throw-away SQLite runtime. No API mocks or live data.
// Run: node --test tests/Browser/scoring-playoff-journey.cjs
// Requires Node >=20, PHP with SQLite, installed Microsoft Edge, and Playwright
// available through normal module resolution or NODE_PATH. No dependency install
// is performed. Runtime DB/logs/screenshots remain under storage/framework/testing/.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const http = require('node:http');
const {spawn, execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const helper = path.join(__dirname, 'fixtures/scoring-playoff-journey.php');

test('real scoring, confirmation and scheduled qualifier progression on phone and desktop', {timeout: 240000}, async () => {
  const runtime = fs.mkdtempSync(path.join(root, 'storage/framework/testing/scoring-playoff-journey-'));
  const database = path.join(runtime, 'database.sqlite'); fs.writeFileSync(database, '');
  const port = await new Promise(resolve => {const probe = net.createServer(); probe.listen(0, '127.0.0.1', () => {const p = probe.address().port; probe.close(() => resolve(p));});});
  const origin = `http://127.0.0.1:${port}`;
  const env = {...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: origin,
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DATABASE_URL: '', DB_URL: '',
    APP_CONFIG_CACHE: path.join(runtime, 'config.php'), APP_ROUTES_CACHE: path.join(runtime, 'routes.php'),
    APP_EVENTS_CACHE: path.join(runtime, 'events.php'), VIEW_COMPILED_PATH: path.join(runtime, 'views'),
    CACHE_DRIVER: 'array', SESSION_DRIVER: 'database', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', LOG_CHANNEL: 'null', BCRYPT_ROUNDS: '4'};
  fs.mkdirSync(env.VIEW_COMPILED_PATH);
  const php = (...args) => execFileSync('php', args, {cwd: root, env, encoding: 'utf8', windowsHide: true, timeout: 120000, maxBuffer: 2 * 1024 * 1024});
  const manifest = JSON.parse(php(helper, 'seed').trim());
  console.log('Seeded isolated journey runtime: ' + runtime);
  const inspect = name => JSON.parse(php(helper, 'inspect', name).trim());
  const serverLog = [];
  const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', helper], {cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
  server.stderr.on('data', chunk => {serverLog.push(chunk.toString()); if (serverLog.length > 30) serverLog.shift();});
  let browser;
  try {
    for (let attempt = 0; attempt < 100; attempt++) {
      const status = await new Promise(resolve => {const req = http.get(origin + '/login', response => {response.resume(); resolve(response.statusCode);}); req.on('error', () => resolve(0)); req.setTimeout(2000, () => {req.destroy(); resolve(0);});});
      if (status === 200) break;
      if (attempt === 99) throw new Error('Isolated Laravel server failed to start: ' + serverLog.join('').slice(-1500));
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({headless: true, channel: 'msedge'});
    async function open(name, width = 1440, role = 'admin') {
      const context = await browser.newContext({viewport: {width, height: 950}});
      await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
      const page = await context.newPage();
      const errors = []; page.on('pageerror', error => errors.push(error.message));
      await page.goto(origin + '/login');
      await page.locator('#login-email').fill(manifest[role]);
      await page.locator('input[name="password"]').fill(manifest.password);
      const loginResponse = page.waitForResponse(response => response.url() === origin + '/login' && response.request().method() === 'POST');
      await page.locator('#formAuthentication button[type="submit"]').click();
      assert.ok((await loginResponse).status() < 400, 'real login succeeds');
      const fixture = manifest.cases[name];
      await page.goto(`${origin}/events/${fixture.event}/scoring?venue=${fixture.venue}&schedule_source=working`);
      await page.locator('.scoring-shell').waitFor();
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'workspace fits viewport');
      return {page, context, errors, fixture};
    }
    async function saveLast(page, fixture) {
      const button = page.locator(`.js-open-score[data-fixture="${fixture.lastFixture}"]`);
      const store = await button.getAttribute('data-store');
      await button.click();
      const games = page.locator('#score-entry-modal input[type="number"]');
      await games.nth(0).fill('3'); await games.nth(1).fill('1');
      await page.locator('#score-save').click();
      return store;
    }
    async function approveGroups(page, doubleSubmit = false, screenshotName = null) {
      for (const group of ['A', 'B']) {
        await page.getByRole('heading', {name: 'Confirm ' + group, exact: true}).waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'review modal does not overflow the viewport');
        if (page.viewportSize().width < 600) {
          const responsive = page.locator('.swal2-popup .table-responsive');
          assert.equal(await responsive.evaluate(node => node.scrollWidth > node.clientWidth), true, 'phone review scrolls its readable table inside the modal');
          const nameCell = page.locator('.swal2-popup tbody tr').first().locator('td').nth(1);
          assert.equal(await nameCell.evaluate(node => getComputedStyle(node).whiteSpace), 'nowrap', 'player names remain readable');
          await responsive.evaluate(node => {node.scrollLeft = node.scrollWidth;});
          assert.equal(await responsive.evaluate(node => node.scrollLeft > 0), true, 'the scorer can reach the points columns');
          await responsive.evaluate(node => {node.scrollLeft = 0;});
        }
        if (group === 'A' && screenshotName) await page.screenshot({path: path.join(runtime, 'review-' + screenshotName + '.png')});
        await page.locator('.swal2-popup input[id^="confirm-progress-group-"]').check();
        const confirm = page.getByRole('button', {name: group === 'A' ? 'Confirm & next group' : 'Confirm & progress to playoffs', exact: true});
        if (doubleSubmit && group === 'B') await confirm.dblclick(); else await confirm.click();
      }
    }
    function assertPreserved(before, after, fixture) {
      assert.deepEqual(after.times, before.times, 'every saved reservation remains identical');
      const playoff = rows => rows.filter(row => row.stage !== 'RR');
      const structure = rows => playoff(rows).map(row => ({id: row.id, stage: row.stage, round: row.round,
        match_nr: row.match_nr, parent: row.parent_fixture_id, loser: row.loser_parent_fixture_id}));
      assert.deepEqual(structure(after.fixtures), structure(before.fixtures), 'fixture IDs and bracket graph retained');
      const opening = playoff(after.fixtures).filter(row => row.round === 1).sort((a, b) => a.match_nr - b.match_nr);
      const indexes = [[0, 5], [4, 1], [2, 7], [6, 3]];
      assert.deepEqual(opening.map(row => [row.registration1_id, row.registration2_id]), indexes.map(pair => pair.map(index => fixture.entrants[index])), 'exact canonical qualifiers populate Main and Plate');
      assert.equal(after.results.length, 12, 'last score saved once');
    }
    for (const [name, width] of [['desktop', 1440], ['phone', 390]]) {
      const before = inspect(name);
      const {page, context, errors, fixture} = await open(name, width);
      const progressionPosts = []; page.on('request', request => {if (request.method() === 'POST' && /\/progress$/.test(request.url())) progressionPosts.push(request);});
      await saveLast(page, fixture);
      await page.getByRole('heading', {name: 'Group stage complete', exact: true}).waitFor();
      await page.getByRole('button', {name: 'Review & progress', exact: true}).click();
      await approveGroups(page, true, name);
      await page.getByRole('heading', {name: 'Draw progressed', exact: true}).waitFor();
      await page.getByRole('button', {name: 'Show playoff matches', exact: true}).click();
      await page.locator(`.js-open-score[data-stage="MAIN"]`).first().waitFor();
      assert.equal(await page.getByRole('button', {name: 'Review & progress to playoffs', exact: true}).count(), 0);
      assert.equal(progressionPosts.length, 1, 'repeat click produces one progression request');
      assertPreserved(before, inspect(name), fixture);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      await page.screenshot({path: path.join(runtime, name + '.png')});
      assert.deepEqual(errors, [], 'no browser script errors');
      console.log(name + ': real final-score prompt, confirmation and preserved qualifiers passed');
      await context.close();
    }
    {
      const {page, context, fixture} = await open('cancel');
      await saveLast(page, fixture);
      await page.getByRole('button', {name: 'Not now', exact: true}).click();
      await page.getByRole('button', {name: 'Review & progress to playoffs', exact: true}).waitFor();
      const after = inspect('cancel'); assert.equal(after.results.length, 12);
      assert.equal(after.fixtures.filter(row => row.stage !== 'RR' && row.registration1_id).length, 0, 'cancel does not progress');
      await page.reload();
      await page.getByRole('button', {name: 'Review & progress to playoffs', exact: true}).waitFor();
      assert.equal(await page.getByRole('heading', {name: 'Group stage complete', exact: true}).count(), 0, 'reload does not repeat prompt');
      await context.close();
    }
    {
      const {page, context, fixture} = await open('stale');
      const store = await saveLast(page, fixture);
      await page.getByRole('button', {name: 'Review & progress', exact: true}).click();
      await page.getByRole('heading', {name: 'Confirm A', exact: true}).waitFor();
      const csrf = await page.locator('head meta[name="csrf-token"]').first().getAttribute('content');
      const correction = await page.request.post(store, {data: {sets: ['4-1']}, headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf}});
      assert.ok(correction.ok(), 'concurrent actual result correction saves: ' + correction.status() + ' ' + (await correction.text()).slice(0, 300));
      await approveGroups(page);
      await page.getByText('The round-robin standings changed during review. Please review every group again.', {exact: true}).waitFor();
      assert.equal(inspect('stale').fixtures.filter(row => row.stage !== 'RR' && row.registration1_id).length, 0, 'stale review cannot place qualifiers');
      await context.close();
    }
    {
      const {page, context, fixture} = await open('restricted', 390, 'scorer');
      await saveLast(page, fixture);
      await page.waitForFunction(() => !document.querySelector('#score-entry-modal').classList.contains('show'));
      assert.equal(inspect('restricted').results.length, 12, 'restricted scorer can save legitimate result');
      assert.equal(await page.getByRole('button', {name: 'Review & progress to playoffs', exact: true}).count(), 0);
      assert.equal(await page.getByRole('heading', {name: 'Group stage complete', exact: true}).count(), 0);
      const denied = await page.request.get(`${origin}/backend/draw/${fixture.draw}/progress-review`, {headers: {Accept: 'application/json'}});
      assert.equal(denied.status(), 403, 'server denies progression to scorer-only account');
      await context.close();
    }
    console.log('Journey evidence: ' + runtime);
  } finally {
    fs.writeFileSync(path.join(runtime, 'server.log'), serverLog.join(''));
    if (browser) await browser.close();
    server.kill();
  }
});
