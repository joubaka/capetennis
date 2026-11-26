const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/live-results.js'), 'utf8');

test('draw checks skip unchanged results, back off after failures, and stop on unpublication', async () => {
  const status = { dataset: { autoRefresh: '1', drawRevision: 'initial' } };
  let cleared = false;
  const region = { dataset: { liveResults: 'team-fixtures' }, innerHTML: 'matches', replaceChildren: () => { cleared = true; } };
  const replacement = { ...region, querySelectorAll: () => [] };
  const listeners = {};
  const document = {
    hidden: false,
    querySelector: selector => selector === '[data-live-results-status]' ? status : null,
    querySelectorAll: () => [region],
    addEventListener: (name, callback) => { listeners[name] = callback; },
  };
  const modes = [];
  const responses = [
    { revision: 'initial' },
    { status: 503 },
    { revision: 'scored' },
    { status: 200 },
    { revision: 'scored' },
    { revision: 'scored' },
    { status: 200 },
    { status: 403 },
  ];
  const timers = new Map();
  let nextTimer = 0;
  let now = 0;
  vm.runInNewContext(script, {
    document,
    window: { location: { href: 'http://localhost/draw' } },
    AbortController,
    Date: class extends Date { static now() { return now; } },
    DOMParser: class {
      parseFromString() {
        return { querySelector: () => status, querySelectorAll: () => [replacement] };
      }
    },
    Math: { min: Math.min, random: () => 0.5 },
    setTimeout: (callback, delay) => {
      timers.set(++nextTimer, { callback, delay });
      return nextTimer;
    },
    clearTimeout: id => timers.delete(id),
    fetch: async (_, options) => {
      modes.push(options.headers['X-Draw-Live']);
      const entry = responses.shift();
      const code = entry.status ?? 200;
      return { status: code, ok: code === 200, json: async () => ({ enabled: true, revision: entry.revision }), text: async () => '' };
    },
  });

  const tick = async delay => {
    assert.equal(timers.size, 1);
    const [[id, timer]] = timers;
    assert.equal(timer.delay, delay);
    timers.delete(id);
    now += delay;
    await timer.callback();
  };
  document.hidden = true;
  listeners.visibilitychange();
  assert.equal(timers.size, 0);
  document.hidden = false;
  listeners.visibilitychange();
  await tick(35000);
  assert.deepEqual(modes, ['revision']);
  await tick(35000);
  await tick(65000);
  assert.deepEqual(modes, ['revision', 'revision', 'revision', 'fragment']);
  await tick(35000);
  assert.equal(modes.at(-1), 'revision');
  now += 6 * 60 * 1000;
  await tick(35000);
  assert.deepEqual(modes.slice(-2), ['revision', 'fragment']);
  await tick(35000);
  assert.equal(cleared, true);
  assert.equal(timers.size, 0);
});

test('round-robin revision changes reload the whole workspace while retaining its tab hash', async () => {
  const status = {dataset: {autoRefresh: '1', drawRevision: 'initial'}};
  const region = {dataset: {liveResults: 'round-robin', liveResultsReload: '', liveResultsUrl: 'http://localhost/frontend/fixtures/draw/index/8'}};
  const timers = new Map();
  let timerId = 0, reloads = 0, responseRevision = 'initial';
  const requests = [];
  const location = {href: 'http://localhost/roundrobin/8#standings', hash: '#standings', reload: () => reloads++};
  vm.runInNewContext(script, {
    document: {hidden: false, querySelectorAll: () => [region], querySelector: selector => selector === '[data-live-results-status]' ? status : null, addEventListener() {}},
    window: {location}, AbortController, Date, Math: {min: Math.min, random: () => 0},
    setTimeout(callback, delay) {timers.set(++timerId, {callback, delay}); return timerId;},
    clearTimeout: id => timers.delete(id),
    fetch: async (url, options) => {
      requests.push([url, options.headers['X-Draw-Live']]);
      return {status: 200, ok: true, json: async () => ({enabled: true, revision: responseRevision})};
    },
  });
  const tick = async () => {
    const [id, timer] = [...timers][0]; timers.delete(id); await timer.callback();
  };
  await tick();
  assert.equal(reloads, 0);
  responseRevision = 'new-score';
  await tick();
  assert.equal(reloads, 1);
  assert.equal(location.hash, '#standings');
  assert.deepEqual(requests, [[region.dataset.liveResultsUrl, 'revision'], [region.dataset.liveResultsUrl, 'revision']]);
});
