const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/player-ratings-leaderboard-refresh.js', 'utf8');

function harness(next, { offline = false } = {}) {
  const calls = [], timers = [], message = {}, retry = { hidden: true, addEventListener(_, fn) { this.click = fn; } };
  let reloads = 0;
  let clock = 0;
  const window = { CTLeaderboardRefresh: { requestUrl: '/refresh', statusUrl: '/status', csrf: 'csrf', version: 'old', snapshotVersion: 'saved-old' },
    setTimeout(fn) { timers.push(fn); }, clearTimeout() {}, location: { reload() { reloads++; } } };
  vm.runInNewContext(source, { window, document: { querySelector(selector) { return selector.includes('message') ? message : retry; } },
    Date: { now: () => clock }, fetch: async (url, options) => {
      calls.push({ url, options });
      if (offline) throw new Error('Offline');
      return { ok: true, json: async () => next };
    } });
  async function flush() { for (let i = 0; i < 10; i++) await Promise.resolve(); }
  return { calls, retry, message, flush, reloads: () => reloads, async poll(time = 0) { clock = time; timers.shift()?.(); await flush(); } };
}

test('page load requests one CSRF protected update and reloads newly saved ratings even with later changes pending', async () => {
  const h = harness({ refreshing: false, status: { version: 'new', snapshot_version: 'saved-new', pending: true, failed: false } });
  await h.flush();
  assert.equal(h.calls.length, 1);
  assert.equal(h.calls[0].options.method, 'POST');
  assert.equal(h.calls[0].options.headers['X-CSRF-TOKEN'], 'csrf');
  await h.poll();
  assert.equal(h.reloads(), 1);
});

test('failure stops automatic requests and makes retry visible', async () => {
  const h = harness({ refreshing: false, status: { version: 'old', snapshot_version: 'saved-old', pending: true, failed: true } });
  await h.flush(); await h.poll();
  assert.equal(h.retry.hidden, false);
  assert.match(h.message.textContent, /could not finish/);
  assert.equal(h.calls.length, 2);
  assert.equal(h.reloads(), 0);
});

test('expired launch lease stops polling with retry rather than waiting indefinitely', async () => {
  const h = harness({ refreshing: false, status: { version: 'changed-generation', snapshot_version: 'saved-old', pending: true, failed: false } });
  await h.flush(); await h.poll();
  assert.equal(h.retry.hidden, false);
  assert.equal(h.reloads(), 0);
  assert.match(h.message.textContent, /in a minute/);
});

test('network failure and ten-minute timeout are bounded and visible', async () => {
  const offline = harness({}, { offline: true });
  await offline.flush();
  assert.equal(offline.retry.hidden, false);
  const pending = harness({ refreshing: true, status: { version: 'old', pending: true, failed: true } });
  await pending.flush(); await pending.poll(600000);
  assert.equal(pending.retry.hidden, false);
  assert.equal(pending.calls.length, 1);
});
