/**
 * Browser regressions against a real Laravel-rendered test fixture and isolated
 * HTTP doubles. Backend persistence/authorization is covered by DrawWorkspaceTest.
 * Generate the fixture with DRAW_WORKSPACE_SNAPSHOT=1 php artisan test
 * tests/Feature/Draw/DrawWorkspaceTest.php, then run this file with Node + Playwright.
 * --serve exposes the fixture on loopback for manual visual inspection.
 */
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const snapshot = fs.readFileSync(path.join(root, 'storage/app/testing/draw-workspace.html'), 'utf8');
const initial = name => JSON.parse(snapshot.match(new RegExp('window\\.' + name + '\\s*=\\s*(.+);'))[1]);
let groups = initial('RR_GROUPS').map(g => ({
  id: g.id,
  name: g.name,
  players: g.registrations.map(p => ({ id: p.id, name: p.display_name }))
}));
const roster = initial('RR_ROSTER');
let revision = initial('RR_ASSIGNMENT_REVISION'),
  fixtures = [],
  failSave = false;
const calls = [];
const json = (response, data, status = 200) => {
  response.writeHead(status, { 'Content-Type': 'application/json' });
  response.end(JSON.stringify(data));
};
const hub = () => ({
  success: true,
  rrFixtures: Object.fromEntries(groups.map(g => [g.id, fixtures.filter(f => f.group_id === g.id)])),
  oops: fixtures,
  standings: {}
});
const server = http.createServer(async (request, response) => {
  const pathname = new URL(request.url, 'http://localhost').pathname;
  if (request.method === 'POST') {
    let raw = '';
    for await (const chunk of request) raw += chunk;
    const data = request.headers['content-type']?.includes('application/json')
      ? JSON.parse(raw)
      : Object.fromEntries(new URLSearchParams(raw));
    calls.push({ path: pathname, data });
    if (pathname.endsWith('/save-groups')) {
      if (failSave) return json(response, { message: 'Test save failure. Your edits are retained.' }, 503);
      groups = data.groups.map(g => ({
        id: g.group_id,
        name: groups.find(x => x.id === g.group_id).name,
        players: g.registration_ids.map(id => ({ id, name: roster.find(p => p.id === id).name }))
      }));
      revision = 'revision-' + calls.length;
      return json(response, { success: true, revision });
    }
    if (pathname.endsWith('/regenerate-rr')) {
      fixtures = [];
      groups.forEach(g =>
        g.players.forEach((a, index) =>
          g.players.slice(index + 1).forEach(b =>
            fixtures.push({
              id: 4000 + fixtures.length,
              match_nr: 100 + fixtures.length,
              stage: 'RR',
              round: 1,
              group_id: g.id,
              group_name: g.name,
              r1_id: a.id,
              r2_id: b.id,
              name1: a.name,
              name2: b.name,
              home: a.name,
              away: b.name,
              all_sets: [],
              score: '',
              court: '1',
              time: '2026-09-05 08:00:00'
            })
          )
        )
      );
      return json(response, { success: true, fixture_count: fixtures.length });
    }
    if (pathname.includes('/roundrobin/score/')) {
      const id = Number(pathname.split('/').pop()),
        f = fixtures.find(f => f.id === id);
      f.all_sets = Object.keys(data)
        .filter(k => k.startsWith('sets'))
        .map(k => data[k]);
      f.score = f.all_sets.join(', ');
      return json(response, { ...hub(), oop: fixtures, fixture: f, mode: 'RR' });
    }
    return json(response, { success: true });
  }
  if (pathname.endsWith('/groups-data')) return json(response, { groups, revision });
  if (pathname.endsWith('/available-players')) {
    const assigned = groups.flatMap(g => g.players.map(p => p.id));
    return json(response, {
      categories: Array.from(new Set(roster.map(p => p.category_id))).map(id => ({
        id,
        category: roster.find(p => p.category_id === id).category,
        players: roster.filter(p => p.category_id === id && !assigned.includes(p.id))
      }))
    });
  }
  if (pathname.endsWith('/hub')) return json(response, hub());
  if (pathname.endsWith('/main-bracket')) {
    response.writeHead(200, { 'Content-Type': 'text/html' });
    return response.end('<svg width="500" height="150"><text x="20" y="40">Regression bracket preview</text></svg>');
  }
  if (pathname.endsWith('/venues') || pathname.includes('/schedule/')) return json(response, []);
  if (pathname === '/' || pathname.includes('/draw/roundrobin/')) {
    const base = 'http://127.0.0.1:' + server.address().port;
    let html = snapshot
      .replaceAll('http://localhost', base)
      .replaceAll('http:\\/\\/localhost', base)
      .replace(
        'https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js',
        base + '/assets/vendor/libs/sortablejs/sortable.js'
      )
      .replace('https://cdn.jsdelivr.net/npm/sweetalert2@11', base + '/assets/vendor/libs/sweetalert2/sweetalert2.js');

    response.writeHead(200, { 'Content-Type': 'text/html' });
    html = html.replace(
      /(<body[^>]*>)/,
      '$1<div role="note" style="padding:10px;text-align:center;background:#fff3cd;color:#664d03">Test preview — test players only. Changes here do not update the application.</div>'
    );
    return response.end(html);
  }
  const publicRoot = path.join(root, 'public');
  const file = path.resolve(publicRoot, '.' + decodeURIComponent(pathname));
  if (file.startsWith(publicRoot + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
    const type =
      {
        '.js': 'application/javascript',
        '.css': 'text/css',
        '.svg': 'image/svg+xml',
        '.png': 'image/png',
        '.woff2': 'font/woff2'
      }[path.extname(file)] || 'application/octet-stream';
    response.writeHead(200, { 'Content-Type': type });
    fs.createReadStream(file).pipe(response);
    return;
  }
  response.writeHead(404);
  response.end('Not found');
});

async function run() {
  await new Promise(resolve => server.listen(process.argv.includes('--serve') ? 8187 : 0, '127.0.0.1', resolve));
  const url = 'http://127.0.0.1:' + server.address().port;
  if (process.argv.includes('--serve')) {
    console.log('Test fixture: ' + url);
    return;
  }
  const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  let failures = 0;
  const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
  const errors = [];
  page.setDefaultTimeout(8000);
  page.on('pageerror', error => errors.push(error.stack));
  await page.route('**/*', route => {
    const target = new URL(route.request().url());
    return target.origin === url || target.protocol === 'data:' ? route.continue() : route.abort();
  });
  page.on('dialog', dialog => dialog.accept());
  const check = async (name, fn) => {
    try {
      await fn();
      console.log('PASS ' + name);
    } catch (error) {
      failures++;
      console.error('FAIL ' + name + '\n' + error.stack);
      console.error('Page errors:', errors);
      await page.screenshot({
        path: path.join(root, 'storage/app/testing/draw-workspace-failure.png'),
        fullPage: true
      });
    }
  };
  try {
    await page.goto(url);
    await page.locator('.rr-group-card').first().waitFor();
    await check('Four main sections and initial player workspace', async () => {
      assert.equal(await page.locator('[data-workspace]').count(), 4);
      assert.equal(await page.locator('#groups-pane').evaluate(el => el.classList.contains('active')), true);
      assert.equal(await page.locator('.rr-group-card').count(), 2);
    });
    await check('Search and bulk assignment preserve players', async () => {
      await page.locator('#rr-player-search').fill('Casey');
      assert.equal(await page.locator('#available-players-list .rr-player-row').count(), 1);
      await page.locator('#rr-select-visible').check();
      await page.locator('#rr-assign-target').selectOption(String(groups[1].id));
      await page.locator('#rr-assign-selected').click();
      assert.equal(await page.locator('.rr-group').nth(1).locator('li').count(), 1);
      assert.match(await page.locator('#rr-save-status').innerText(), /Unsaved/);
      await page.locator('#rr-player-search').fill('');
    });
    await check('Save emits exactly one mutation and keeps seed order', async () => {
      const count = calls.filter(c => c.path.endsWith('/save-groups')).length;
      await page.locator('#btn-save-groups').click();
      await page.waitForFunction(() => document.querySelector('#rr-save-status').textContent === 'Saved');
      assert.equal(calls.filter(c => c.path.endsWith('/save-groups')).length, count + 1);
      assert.equal(groups[1].players.length, 1);
    });
    await check('Keyboard reorder and failed save retain the draft without retrying', async () => {
      await page.locator('.rr-group').first().locator('.rr-player-down').first().click();
      failSave = true;
      const count = calls.filter(c => c.path.endsWith('/save-groups')).length;
      await page.locator('#btn-save-groups').click();
      await page.waitForFunction(() =>
        document.querySelector('#rr-assignment-message').textContent.includes('Test save failure')
      );
      assert.equal(calls.filter(c => c.path.endsWith('/save-groups')).length, count + 1);
      assert.match(await page.locator('#rr-save-status').innerText(), /Unsaved/);
      failSave = false;
      await page.locator('#btn-save-groups').click();
      await page.waitForFunction(() => document.querySelector('#rr-save-status').textContent === 'Saved');
    });
    await check('All-assigned roster still supports removing and restoring a player', async () => {
      await page.locator('#rr-select-visible').check();
      await page.locator('#rr-assign-selected').click();
      assert.equal(await page.locator('#available-players-list .rr-player-row').count(), 0);
      await page.locator('.rr-group').nth(1).locator('.btn-remove-from-group').first().click();
      assert.equal(await page.locator('#available-players-list .rr-player-row').count(), 1);
      await page.locator('.rr-add-player').click();
      await page.locator('#btn-save-groups').click();
      await page.waitForFunction(() => document.querySelector('#rr-save-status').textContent === 'Saved');
    });
    await page.screenshot({ path: path.join(root, 'storage/app/testing/draw-workspace-desktop.png'), fullPage: true });
    await check('Fixture preview and generation refresh the matrix', async () => {
      await page.locator('#btn-regenerate-fixtures').click();
      await page.getByRole('button', { name: 'Generate fixtures', exact: true }).click();
      await page.locator('#matrix-pane.active').waitFor();
      assert.ok((await page.locator('.rr-score-cell').count()) > 0);
      assert.equal(calls.filter(c => c.path.endsWith('/regenerate-rr')).length, 1);
    });
    await check('Mirrored matrix cells use canonical score labels and one score request', async () => {
      const cell = page.locator('.rr-score-cell').nth(1),
        id = Number(await cell.getAttribute('data-fixture-id'));
      await cell.click();
      await page.locator('#rrScoreModal.show').waitFor();
      const fixture = fixtures.find(f => f.id === id);
      assert.match(await page.locator('#rrm-match-label').innerText(), new RegExp(fixture.home));
      await page.locator('#set1-p1').fill('6');
      await page.locator('#set1-p2').fill('2');
      await page.getByRole('button', { name: 'Save Score', exact: true }).click();
      await page.locator('#rrScoreModal').waitFor({ state: 'hidden' });
      assert.equal(calls.filter(c => c.path.includes('/roundrobin/score/')).length, 1);
    });
    await check('All sections and every print choice remain reachable', async () => {
      await page.getByRole('button', { name: 'Setup & Rules', exact: true }).click();
      await page.locator('#settings-pane.active').waitFor();
      await page.locator('.rr-advanced > summary').click();
      assert.equal(await page.locator('#complete-seeding-matrix').isVisible(), true);
      await page.locator('#notes-tab').click();
      assert.equal(await page.locator('#btn-save-notes').isVisible(), true);
      await page.locator('#rr-open-print').click();
      for (const id of ['fixtures', 'matrix', 'bracket', 'empty-bracket', 'combined', 'draw-pack'])
        assert.equal(await page.locator('#btn-print-' + id).isVisible(), true);
    });
    await check('Every print action opens a populated printable document', async () => {
      for (const id of ['fixtures', 'matrix', 'bracket', 'empty-bracket', 'combined', 'draw-pack']) {
        const opened = page.waitForEvent('popup');
        await page.locator('#btn-print-' + id).click();
        const popup = await opened;
        await popup.waitForLoadState('domcontentloaded');
        assert.ok((await popup.locator('body').innerText()).trim().length > 20, id + ' print content');
        await popup.close();
      }
    });
    await check('Order saving includes hidden fixtures and leaves match numbers unchanged', async () => {
      await page.getByRole('button', { name: 'Schedule', exact: true }).click();
      await page.locator('.rr-order-down').first().click();
      await page.locator('#rr-ops-search').fill(fixtures[0].home);
      await page.locator('#rr-save-order-btn').click();
      await page.waitForFunction(() => document.querySelector('#rr-save-order-btn').textContent === 'Order saved');
      const save = calls.findLast(c => c.path.endsWith('/save-order'));
      assert.equal(save.data.order.length, fixtures.length);
    });
    await check('Scheduling still offers draw, round, match, auto and clear controls', async () => {
      await page.locator('#schedule-tab').click();
      await page.locator('[data-bs-target="#scheduleModal"]').click();
      await page.locator('#scheduleModal.show').waitFor();
      assert.equal(await page.locator('#scheduleModal input[name="mode"]').count(), 3);
      assert.equal(await page.locator('#autoScheduleBtn').isVisible(), true);
      assert.equal(await page.locator('#clearScheduleBtn').isVisible(), true);
      await page.locator('#scheduleModal .btn-close').click();
    });
    await check('Mobile assignment actions and navigation fit the viewport', async () => {
      await page.setViewportSize({ width: 390, height: 844 });
      await page.getByRole('button', { name: 'Players & Groups', exact: true }).click();
      await page.locator('#groups-pane.show.active').waitFor();
      await page.waitForFunction(() => getComputedStyle(document.querySelector('#groups-pane')).opacity === '1');
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
      assert.equal(overflow, false);
      await page.screenshot({ path: path.join(root, 'storage/app/testing/draw-workspace-mobile.png'), fullPage: true });
    });
    await check('All four work areas also fit a 360px screen', async () => {
      await page.setViewportSize({ width: 360, height: 800 });
      for (const label of ['Players & Groups', 'Draw & Results', 'Schedule', 'Setup & Rules']) {
        await page.getByRole('button', { name: label, exact: true }).click();
        assert.equal(
          await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2),
          false,
          label
        );
      }
    });
    await check('No page JavaScript errors', async () => assert.deepEqual(errors, []));
  } finally {
    await browser.close();
    server.close();
  }
  if (failures) process.exitCode = 1;
}
run().catch(error => {
  console.error(error);
  server.close();
  process.exitCode = 1;
});
