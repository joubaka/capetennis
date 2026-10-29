const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-workspace.js', 'utf8');
const refreshSource = source.slice(source.indexOf('  const rosterRefreshes ='), source.indexOf('  function selectRegion('));

function roster() {
  let count = 2, filters = 0, events = 0;
  const teams = [{ dataset: { rosterTeam: '1' }, open: false }, { dataset: { rosterTeam: '2' }, open: true }];
  const opposite = { dataset: { loaded: 'true', rosterPanel: 'order' } }, badge = { textContent: '10' }, calls = [], pending = [];
  const panel = {
    dataset: { regionId: '128', rosterPanel: 'players', loaded: 'true' }, isConnected: true, hidden: false,
    closest() { return this; }, setAttribute() {}, removeAttribute() {},
    querySelectorAll(selector) { return selector.includes('data-occupied') ? Array(count).fill({}) : teams; },
    replaceChildren(content) { count = content.count; teams.forEach(team => { team.open = true; }); },
  };
  const document = {
    querySelector(selector) { return selector.includes('badge') ? badge : panel; },
    querySelectorAll() { return [panel, opposite]; },
    createElement() { return { content: { querySelector() { return this.valid; } }, set innerHTML(value) { this.content.count = Number(value); this.content.valid = value !== 'invalid'; } }; },
    dispatchEvent() { events++; },
  };
  const window = { scrollY: 345, scrollTo(x, y) { this.scrolled = y; }, location: { reload() { assert.fail('must not navigate'); } } };
  new Function('window', 'document', 'requests', 'workspaceUrl', 'applyFilters', 'fetch', 'Event', refreshSource)(
    window, document, new Map(), value => value, () => { filters++; },
    (url, options) => new Promise(resolve => { calls.push({ url, options }); pending.push(resolve); }), Event,
  );
  return { window, panel, teams, opposite, badge, calls, pending, stats: () => ({ count, filters, events }), respond(index, body = '1', extra = {}) { pending[index]({ ok: true, text: async () => body, ...extra }); } };
}

test('refresh replaces only its region and retains expansion, filters, count and current scroll', async () => {
  const f = roster(), promise = f.window.refreshTeamRoster(f.panel);
  f.window.scrollY = 567;
  f.respond(0); await promise;
  assert.deepEqual(f.calls[0].url, { roster_region: '128', panel: 'players' });
  assert.deepEqual(f.teams.map(team => team.open), [false, true]);
  assert.equal(f.badge.textContent, 9);
  assert.equal(f.opposite.dataset.loaded, undefined);
  assert.equal(f.window.scrolled, 567);
  assert.deepEqual(f.stats(), { count: 1, filters: 1, events: 1 });
});

test('failed, redirected and malformed fragments retain old list and allow retry', async () => {
  for (const response of [{ ok: false }, { redirected: true }, { body: 'invalid' }]) {
    const f = roster(), promise = f.window.refreshTeamRoster(f.panel);
    f.respond(0, response.body || '1', response); await assert.rejects(promise);
    assert.equal(f.stats().count, 2); assert.equal(f.badge.textContent, '10');
    const retry = f.window.refreshTeamRoster(f.panel); f.respond(1); await retry;
    assert.equal(f.stats().count, 1);
  }
});

test('switching region while refresh waits does not move scroll', async () => {
  const f = roster(), promise = f.window.refreshTeamRoster(f.panel);
  f.panel.hidden = true; f.respond(0); await promise;
  assert.equal(f.window.scrolled, undefined);
});

test('overlapping refresh requests fetch again after the first response', async () => {
  const f = roster(), first = f.window.refreshTeamRoster(f.panel), second = f.window.refreshTeamRoster(f.panel);
  assert.equal(f.calls.length, 1); f.respond(0); await first;
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(f.calls.length, 2); f.respond(1, '2'); await second;
  assert.equal(f.stats().count, 2);
});

function mutation() {
  const handlers = {}, buttons = [{ disabled: false }], panel = { dataset: {}, querySelectorAll() { return buttons; } };
  const feedback = { dataset: {}, setAttribute() {}, append(button) { this.retry = button; }, set textContent(value) { this.message = value; this.firstChild = { textContent: value }; } };
  const form = { dataset: {}, elements: {}, closest(selector) { return selector === '[data-roster-panel]' ? panel : null; }, querySelectorAll() { return buttons; }, getAttribute() { return '/save'; }, parentElement: { querySelector() { return feedback; } }, contains(button) { return buttons.includes(button); } };
  const document = { addEventListener(type, callback) { handlers[type] = callback; }, createElement() { return { dataset: {}, setAttribute() {}, addEventListener(type, callback) { this.click = callback; } }; } };
  let posts = 0, gets = 0, failed = true;
  const window = { refreshTeamRoster: async () => { gets++; if (failed) throw Error('refresh failed'); }, toastr: { success() {} }, location: { reload() { assert.fail('must not navigate'); } } };
  const script = fs.readFileSync('resources/views/backend/adminPage/admin_show/_roster-script.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
  new Function('window', 'document', 'fetch', 'FormData', script)(window, document, async () => { posts++; return { ok: true, json: async () => ({}) }; }, class {});
  return { form, panel, buttons, feedback, submit: () => handlers.submit({ target: { closest() { return form; } }, preventDefault() {} }), counts: () => ({ posts, gets }), recover() { failed = false; } };
}

test('saved mutation with failed refresh retries GET without replaying POST', async () => {
  const f = mutation(); await f.submit();
  assert.deepEqual(f.counts(), { posts: 1, gets: 1 });
  assert.equal(f.form.dataset.busy, undefined); assert.equal(f.panel.dataset.rosterSaving, undefined);
  assert.equal(f.buttons[0].disabled, true);
  f.recover(); await f.feedback.retry.click();
  assert.deepEqual(f.counts(), { posts: 1, gets: 2 });
  await f.submit(); assert.deepEqual(f.counts(), { posts: 1, gets: 3 });
});
