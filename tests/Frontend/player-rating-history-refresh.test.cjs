const test = require('node:test');
const assert = require('node:assert/strict');
const RefreshUI = require('../../public/assets/js/player-rating-history-refresh.js');

function harness(responses) {
  const button = { disabled: false, textContent: '' };
  const label = { textContent: '' };
  const controls = { dataset: { refreshUrl: '/history/refresh', statusUrl: '/history/status' },
    querySelector: selector => selector === '[data-history-refresh]' ? button : label };
  const panel = { addEventListener() {}, querySelector: () => controls };
  const calls = []; const timers = []; let reloads = 0;
  const ui = new RefreshUI(panel, async () => { reloads++; }, { csrf: 'csrf-fixture',
    fetch: async (url, options) => {
      calls.push({ url, options });
      const next = responses.shift();
      if (next instanceof Error) throw next;
      return { ok: next.state !== 'failed', status: next.state === 'failed' ? 503 : 200, json: async () => next };
    }, setTimer: callback => { timers.push(callback); return timers.length; }, clearTimer() {},
  });
  return { ui, button, label, calls, timers, reloads: () => reloads };
}

test('manual POST carries CSRF and disables duplicate clicks while pending', async () => {
  const h = harness([{ state: 'pending', message: 'Starting' }]);
  await h.ui.start(); await h.ui.start();
  assert.equal(h.calls.length, 1);
  assert.equal(h.calls[0].options.method, 'POST');
  assert.equal(h.calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-fixture');
  assert.equal(h.button.disabled, true);
  assert.equal(h.label.textContent, 'Starting');
  assert.equal(h.timers.length, 1);
});

test('running update polls cheaply and refreshes filtered graph once on completion', async () => {
  const h = harness([{ state: 'pending', message: 'Starting' }, { state: 'running', message: 'Working' },
    { state: 'completed', message: 'Saved', completed_at: 42 }, { state: 'completed', message: 'Saved', completed_at: 42 }]);
  await h.ui.start(); await h.ui.sync();
  assert.equal(h.button.disabled, true);
  await h.ui.sync(); await h.ui.sync();
  assert.equal(h.reloads(), 1);
  assert.equal(h.label.textContent, 'Saved');
  assert.equal(h.button.disabled, false);
});

test('failed update permits retry and never reloads successful saved display', async () => {
  const h = harness([{ state: 'failed', message: 'Previous history retained' }, { state: 'pending', message: 'Retry starting' }]);
  await h.ui.start();
  assert.equal(h.button.disabled, false);
  assert.equal(h.button.textContent, 'Try update again');
  assert.equal(h.reloads(), 0);
  await h.ui.start();
  assert.equal(h.button.disabled, true);
});

test('network failure preserves saved graph and enables retry', async () => {
  const h = harness([new Error('Offline')]);
  await h.ui.start();
  assert.equal(h.button.disabled, false);
  assert.equal(h.reloads(), 0);
  assert.match(h.label.textContent, /could not be requested/);
});

test('a nightly update already running is shown and reloaded on actual completion', async () => {
  const h = harness([{ state: 'running', message: 'Nightly build' }, { state: 'completed', message: 'Saved' }]);
  await h.ui.sync();
  assert.equal(h.button.disabled, true);
  await h.ui.sync();
  assert.equal(h.reloads(), 1);
  assert.equal(h.calls[0].url, '/history/status');
});
