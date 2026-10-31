// Real Blade-rendered partial and shipped assets; HTTP doubles never award real prizes.
// Generate storage/framework/testing/lucky-prize-wheel.html with the feature snapshot first.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');

test('lucky wheel aligns the recorded winner and handles busy, retry, readiness and reduced motion', async () => {
  const snapshot = fs.readFileSync(path.join(root, 'storage/framework/testing/lucky-prize-wheel.html'), 'utf8');
  const players = ['Alex Example', 'Blair Example', 'Casey Example', 'Drew Example'].map((name, i) => ({id: i + 10, name}));
  const calls = [], recorded = new Map();
  let mode = 'ready', latency = 0, workspaceLatency = 0;
  const workspaceView = fs.readFileSync(path.join(root, 'resources/views/frontend/scoring/workspace.blade.php'), 'utf8');
  const refreshSource = workspaceView.slice(workspaceView.indexOf('  async function refreshWorkspace('), workspaceView.indexOf("  document.addEventListener('change'", workspaceView.indexOf('  async function refreshWorkspace(')));
  const pool = () => ({ready: mode !== 'blocked', message: mode === 'blocked' ? 'Resolve first place before spinning.' : 'Each eligible player has an equal chance.',
    players: mode === 'blocked' ? [] : players, champions: [{id: 1, name: 'Champion Example'}], previous_winners: [], revision: 'a'.repeat(64)});
  const server = http.createServer(async (request, response) => {
    const pathname = new URL(request.url, 'http://localhost').pathname;
    if (pathname.endsWith('/lucky-prize')) {
      const json = (status, data) => { response.writeHead(status, {'Content-Type': 'application/json'}); response.end(JSON.stringify(data)); };
      if (request.method === 'GET') return mode === 'load-error' ? json(503, {message: 'Temporary pool error.'}) : json(200, pool());
      let raw = ''; for await (const chunk of request) raw += chunk;
      const data = JSON.parse(raw); calls.push(data);
      if (mode === 'stale') return json(409, {message: 'The eligible players changed. Review the refreshed wheel before spinning.'});
      if (!recorded.has(data.token)) recorded.set(data.token, {winner: players[2], players});
      if (mode === 'lost-response') { mode = 'ready'; response.destroy(); return; }
      if (latency) await new Promise(resolve => setTimeout(resolve, latency));
      return json(200, recorded.get(data.token));
    }
    if (pathname === '/' || pathname === '/workspace') {
      const origin = `http://127.0.0.1:${server.address().port}`;
      const content = snapshot.replace(/https?:\/\/[^/"'\s]+(?=\/)/g, origin);
      if (pathname === '/workspace') {
        if (workspaceLatency) await new Promise(resolve => setTimeout(resolve, workspaceLatency));
        response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
        return response.end(`<div class="scoring-shell">${content}</div>`);
      }
      response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
      return response.end(`<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="browser-fixture"><link rel="stylesheet" href="/assets/vendor/css/core.css"><link rel="stylesheet" href="/assets/vendor/css/theme-default.css"><div class="scoring-shell">${content}</div><script src="/browser-bootstrap.js"></script>`);
    }
    if (pathname === '/browser-bootstrap.js') { response.writeHead(200, {'Content-Type': 'application/javascript'}); return fs.createReadStream(path.join(root, 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js')).pipe(response); }
    const file = path.resolve(root, 'public', '.' + decodeURIComponent(pathname));
    if (!file.startsWith(path.join(root, 'public') + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {response.writeHead(404); return response.end();}
    response.writeHead(200, {'Content-Type': {'.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2'}[path.extname(file)] || 'application/octet-stream'});
    fs.createReadStream(file).pipe(response);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try { browser = await chromium.launch({headless: true, channel: 'msedge'}); }
  catch (error) { await new Promise(resolve => server.close(resolve)); throw error; }
  const origin = `http://127.0.0.1:${server.address().port}`;
  try {
    for (const width of [390, 1440]) {
      mode = 'ready'; latency = 450;
      const page = await browser.newPage({viewport: {width, height: 950}});
      const errors = []; page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
      await page.goto(origin);
      await page.getByRole('button', {name: '🎁 Lucky prize wheel', exact: true}).click();
      const spin = page.locator('[data-prize-spin]');
      await spin.waitFor({state: 'visible'}); await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
      assert.equal(await page.locator('[data-prize-wheel] path').count(), 4);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      const before = calls.length;
      await spin.dblclick();
      assert.equal(await page.locator('[data-prize-draw]').isDisabled(), true);
      assert.equal(await page.locator('[data-prize-refresh]').isDisabled(), true);
      await page.getByText('Lucky prize winner: Casey Example!', {exact: true}).waitFor();
      assert.equal(calls.length, before + 1, 'double click creates one request');
      const transform = await page.locator('[data-prize-wheel]').evaluate(node => node.style.transform);
      const angle = Number(transform.match(/rotate\(([-\d.]+)deg\)/)[1]);
      assert.equal(((angle + (2 + 0.5) * 90) % 360 + 360) % 360, 0, 'server winner segment centre aligns top pointer');
      const pointedName = await page.locator('[data-prize-wheel]').evaluate(node => {
        const box = node.getBoundingClientRect();
        const target = document.elementFromPoint(box.left + box.width * 0.56, box.top + box.height * 0.17);
        return target?.querySelector('title')?.textContent;
      });
      assert.equal(pointedName, 'Casey Example', 'rendered segment under the pointer is the server winner');
      assert.equal(await spin.isDisabled(), true);
      assert.match(await page.locator('[data-prize-previous]').textContent(), /Casey Example/);
      assert.equal(await page.locator('[data-lucky-prize] .modal-body').evaluate(node => node.scrollWidth <= node.clientWidth + 1), true, 'completed wheel fits the modal horizontally');
      await page.screenshot({path: path.join(root, `storage/framework/testing/lucky-prize-wheel-${width}.png`), fullPage: true});
      await page.locator('[data-bs-dismiss="modal"]').click();
      await page.locator('[data-lucky-prize]').waitFor({state: 'hidden'});
      await page.getByRole('button', {name: '🎁 Lucky prize wheel', exact: true}).click();
      await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
      assert.equal(await spin.textContent(), 'Spin for a lucky winner', 'reopening resets completed-result button label');
      assert.deepEqual(errors, []); await page.close();
    }

    const page = await browser.newPage({viewport: {width: 390, height: 950}, reducedMotion: 'reduce'});
    await page.route('**/*', async route => {
      if (mode === 'lost-response' && route.request().method() === 'POST' && new URL(route.request().url()).pathname.endsWith('/lucky-prize')) {
        mode = 'ready';
        const recordedResponse = await route.fetch();
        await recordedResponse.dispose();
        return route.abort('failed');
      }
      return new URL(route.request().url()).origin === origin ? route.continue() : route.abort();
    });
    await page.goto(origin); await page.getByRole('button', {name: '🎁 Lucky prize wheel', exact: true}).click();
    const spin = page.locator('[data-prize-spin]'), refresh = page.locator('[data-prize-refresh]');
    await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
    await page.locator('[data-bs-dismiss="modal"]').click();
    await page.locator('[data-lucky-prize]').waitFor({state: 'hidden'});
    await page.evaluate(source => {
      const setup = `let workspaceRequestSequence = 0; const currentFilter = () => ''; const syncRememberedVenue = () => {}; const applyScoreFilter = () => {}; const showWorkspaceError = message => {window.workspaceError = message;};`;
      window.testRefresh = new Function(setup + source + '; return refreshWorkspace;')();
      document.querySelector('[data-lucky-prize]').dataset.originalModal = 'yes';
    }, refreshSource);
    await page.evaluate(() => window.testRefresh('/workspace'));
    assert.equal(await page.locator('[data-original-modal]').count(), 0, 'actual scoring refresh replaces modal');
    await page.evaluate(() => {window.CTLuckyPrize.init(); window.CTLuckyPrize.init();});
    await page.getByRole('button', {name: '🎁 Lucky prize wheel', exact: true}).click();
    await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
    assert.equal(await page.locator('[data-prize-wheel] path').count(), 4, 'replacement modal is initialised');

    workspaceLatency = 500;
    const workspaceRequest = page.waitForRequest(request => request.url().endsWith('/workspace'));
    await page.evaluate(() => {
      document.querySelector('[data-lucky-prize]').dataset.pendingModal = 'yes';
      window.pendingRefresh = window.testRefresh('/workspace');
    });
    await workspaceRequest;
    mode = 'lost-response'; latency = 0;
    await spin.click(); await page.getByRole('button', {name: 'Retry result', exact: true}).waitFor();
    await page.evaluate(() => window.pendingRefresh);
    assert.equal(await page.locator('[data-pending-modal]').count(), 1, 'in-flight refresh preserves modal with unresolved prize');
    assert.equal(await page.evaluate(() => window.CTLuckyPrize.pending()), true);
    assert.match(await page.evaluate(() => window.workspaceError), /pending lucky-prize result/);
    await page.evaluate(() => window.testRefresh('/workspace'));
    assert.equal(await page.locator('[data-pending-modal]').count(), 1, 'pending retry also blocks a new refresh');
    assert.equal(await refresh.isDisabled(), true); assert.equal(await page.locator('[data-prize-draw]').isDisabled(), true);
    const token = calls.at(-1).token, awards = recorded.size;
    const started = Date.now(); await spin.click(); await page.getByText('Lucky prize winner: Casey Example!', {exact: true}).waitFor();
    assert.ok(Date.now() - started < 3000, 'reduced motion skips four-second spin');
    assert.equal(calls.at(-1).token, token); assert.equal(recorded.size, awards, 'retry retrieves same recorded prize');
    assert.equal(await page.evaluate(() => window.CTLuckyPrize.pending()), false);
    assert.equal(await page.locator('[data-prize-wheel]').evaluate(node => getComputedStyle(node).transitionDuration), '0s');

    mode = 'stale'; await refresh.click(); await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
    await spin.click(); await page.getByText(/The eligible players changed/).waitFor();
    assert.equal(await spin.isDisabled(), true); assert.equal(await refresh.isEnabled(), true);
    mode = 'blocked'; await refresh.click(); await page.getByText('Resolve first place before spinning.', {exact: true}).waitFor();
    assert.equal(await spin.isDisabled(), true); assert.equal(await page.locator('[data-prize-wheel] path').count(), 0);
    mode = 'load-error'; await refresh.click(); await page.getByText('Temporary pool error.', {exact: true}).waitFor();
    assert.equal(await spin.isDisabled(), true);
    mode = 'ready'; await refresh.click(); await page.waitForFunction(() => !document.querySelector('[data-prize-spin]').disabled);
    await page.close();
  } finally {
    await browser.close(); await new Promise(resolve => server.close(resolve));
  }
});
