const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-workspace.js', 'utf8');
const refreshSource = source.slice(source.indexOf('  const rosterRefreshes ='), source.indexOf('  function selectRegion('));

function roster() {
  const timers = new Map(); let timerId = 0;
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
  new Function('window', 'document', 'requests', 'workspaceUrl', 'applyFilters', 'fetch', 'Event', 'setTimeout', 'clearTimeout', refreshSource)(
    window, document, new Map(), value => value, () => { filters++; },
    (url, options) => new Promise((resolve, reject) => {
      calls.push({ url, options }); pending.push(resolve);
      options.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), {name: 'AbortError'})));
    }), Event,
    callback => { timers.set(++timerId, callback); return timerId; }, id => timers.delete(id),
  );
  return { window, panel, teams, opposite, badge, calls, pending, timers, expire() { [...timers.values()].forEach(callback => callback()); }, stats: () => ({ count, filters, events }), respond(index, body = '1', extra = {}) { pending[index]({ ok: true, text: async () => body, ...extra }); } };
}

test('slow refresh aborts its GET, retains the list and permits a fresh refresh', async () => {
  const f = roster(), pending = f.window.refreshTeamRoster(f.panel);
  f.expire(); await assert.rejects(pending, /Refreshing the player list took too long/);
  assert.equal(f.calls[0].options.signal.aborted, true);
  assert.equal(f.stats().count, 2);
  assert.equal(f.timers.size, 0);
  const retry = f.window.refreshTeamRoster(f.panel); f.respond(1); await retry;
  assert.equal(f.stats().count, 1);
  assert.equal(f.timers.size, 0);
});

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

function mutation(options = {}) {
  const handlers = {}, buttons = [{ disabled: false }], panel = { dataset: {}, querySelectorAll() { return buttons; } };
  const label = {}, clock = {}, percent = {};
  const feedback = { dataset: {}, setAttribute() {}, matches(selector) { return selector === '[data-team-roster-feedback]' && 'teamRosterFeedback' in this.dataset; },
    querySelector(selector) { return selector.includes('message') ? label : selector.includes('time') ? clock : percent; },
    append(button) { this.retry = button; }, set textContent(value) { this.message = value; this.firstChild = { textContent: value }; } };
  const form = { dataset: {}, elements: {}, closest(selector) { return selector === '[data-roster-panel]' ? panel : null; }, querySelectorAll() { return buttons; }, getAttribute() { return '/save'; }, after(node) { this.nextElementSibling = node; }, contains(button) { return buttons.includes(button); } };
  const document = { addEventListener(type, callback) { handlers[type] = callback; }, createElement(tag) { return tag === 'p' ? feedback : { dataset: {}, setAttribute() {}, addEventListener(type, callback) { this.click = callback; } }; } };
  let posts = 0, gets = 0, failed = true, now = 0, timerId = 0;
  const timers = new Map(), postResolvers = [], refreshResolvers = [];
  const window = { refreshTeamRoster: async () => { gets++; if (options.pendingRefresh) await new Promise(resolve => refreshResolvers.push(resolve)); if (failed) throw Error('refresh failed'); }, toastr: { success() {} }, location: { reload() { assert.fail('must not navigate'); } } };
  const script = fs.readFileSync('resources/views/backend/adminPage/admin_show/_roster-script.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
  new Function('window', 'document', 'fetch', 'FormData', 'setTimeout', 'clearTimeout', 'Date', script)(window, document, async () => {
    posts++;
    if (options.pendingPost) await new Promise(resolve => postResolvers.push(resolve));
    if (options.networkFailure) throw Error('Network lost');
    const status = options.status || 200;
    return { ok: status < 400, status, json: async () => {
      if (options.malformedJson) throw new SyntaxError('Unexpected HTML response');
      return options.response || {};
    } };
  }, class {}, (callback, delay) => { const id = ++timerId; timers.set(id, { callback, due: now + delay }); return id; }, id => timers.delete(id), { now: () => now });
  return { form, panel, buttons, feedback, label, clock, postResolvers, refreshResolvers, timers,
    advance(milliseconds) { const end = now + milliseconds; for (;;) { const next = [...timers].sort((a, b) => a[1].due - b[1].due)[0]; if (!next || next[1].due > end) break; now = next[1].due; timers.delete(next[0]); next[1].callback(); } now = end; },
    submit: () => handlers.submit({ target: { closest() { return form; } }, preventDefault() {} }), counts: () => ({ posts, gets }), recover() { failed = false; } };
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

test('pending save shows an animated spinner, elapsed time and slow feedback without a duplicate POST', async () => {
  const f = mutation({ pendingPost: true }), pending = f.submit();
  assert.match(f.feedback.innerHTML, /spinner-border/);
  assert.equal(f.label.textContent, 'Saving player change…');
  assert.equal(f.buttons[0].disabled, true);
  f.advance(10000);
  assert.match(f.label.textContent, /waiting for save confirmation.*do not submit again/i);
  assert.equal(f.clock.textContent, '10s elapsed');
  await f.submit(); assert.equal(f.counts().posts, 1);
  f.postResolvers[0](); await pending;
  assert.equal(f.timers.size, 0);
});

test('confirmed save changes feedback while the roster refresh is still pending', async () => {
  const f = mutation({ pendingRefresh: true }); f.recover();
  const pending = f.submit(); await new Promise(resolve => setImmediate(resolve));
  assert.equal(f.label.textContent, 'Saved. Updating the player list…');
  assert.equal(f.buttons[0].disabled, true);
  f.advance(10000); assert.match(f.label.textContent, /player list is taking longer/);
  f.refreshResolvers[0](); await pending;
  assert.equal(f.timers.size, 0);
});

test('a timed-out or lost save response blocks blind resubmission and offers GET-only recovery', async () => {
  for (const options of [{ pendingPost: true }, { networkFailure: true }]) {
    const f = mutation(options), pending = f.submit();
    if (options.pendingPost) f.advance(90000);
    await pending;
    assert.match(f.feedback.message, /may still complete/);
    assert.equal(f.form.dataset.saveUnconfirmed, '1');
    assert.equal(f.buttons[0].disabled, true);
    f.recover(); await f.feedback.retry.click();
    assert.deepEqual(f.counts(), { posts: 1, gets: 1 });
    await f.submit(); assert.deepEqual(f.counts(), { posts: 1, gets: 2 });
    assert.equal(f.timers.size, 0);
  }
});

test('explicit session and validation rejections show the reason and restore safe controls', async () => {
  for (const options of [
    { status: 419, malformedJson: true, expected: /session expired/ },
    { status: 422, response: { errors: { player_id: ['Choose an eligible replacement.'] } }, expected: /Choose an eligible replacement/ },
  ]) {
    const f = mutation(options); await f.submit();
    assert.match(f.feedback.message, options.expected);
    assert.equal(f.buttons[0].disabled, false);
    assert.equal(f.form.dataset.busy, undefined);
    assert.equal(f.panel.dataset.rosterSaving, undefined);
    assert.equal(f.form.dataset.saveUnconfirmed, undefined);
    assert.equal(f.form.dataset.saved, undefined);
    assert.equal(f.feedback.retry, undefined);
    assert.deepEqual(f.counts(), { posts: 1, gets: 0 });
    assert.equal(f.timers.size, 0);
  }
});

test('server errors and malformed save responses remain unconfirmed and recover without replaying POST', async () => {
  for (const options of [
    { status: 500, response: { message: 'Internal server error' } },
    { status: 500, malformedJson: true },
    { status: 200, malformedJson: true },
  ]) {
    const f = mutation(options); await f.submit();
    assert.match(f.feedback.message, /may still complete/);
    assert.equal(f.form.dataset.saveUnconfirmed, '1');
    assert.equal(f.form.dataset.saved, undefined);
    assert.equal(f.buttons[0].disabled, true);
    assert.equal(f.form.dataset.busy, undefined);
    assert.equal(f.panel.dataset.rosterSaving, undefined);
    f.recover(); await f.feedback.retry.click();
    assert.deepEqual(f.counts(), { posts: 1, gets: 1 });
    await f.submit(); assert.deepEqual(f.counts(), { posts: 1, gets: 2 });
    assert.equal(f.timers.size, 0);
  }
});
