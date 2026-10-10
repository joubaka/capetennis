const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/player-ratings-leaderboard-refresh.js', 'utf8');

function harness(next, { offline = false, status = 200, invalidJson = false } = {}) {
  const calls = [], timers = [], message = {}, retry = { hidden: true, addEventListener(_, fn) { this.click = fn; } };
  let reloads = 0;
  let clock = 0;
  const window = { CTLeaderboardRefresh: { requestUrl: '/refresh', statusUrl: '/status', csrf: 'csrf', version: 'old', snapshotVersion: 'saved-old' },
    setTimeout(fn) { timers.push(fn); }, clearTimeout() {}, location: { reload() { reloads++; } } };
  vm.runInNewContext(source, { window, document: { querySelector(selector) { return selector.includes('message') ? message : retry; } },
    Date: { now: () => clock }, fetch: async (url, options) => {
      calls.push({ url, options });
      if (offline) throw new Error('Offline');
      return { ok: status >= 200 && status < 300, status, json: async () => { if(invalidJson) throw new Error('invalid'); return next; } };
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

for (const [status, pattern] of [[403,/account cannot/],[401,/session expired/],[419,/session expired/]]) {
  test(`HTTP ${status} has a specific message rather than connection advice`, async()=>{
    const h=harness({}, {status});await h.flush();assert.match(h.message.textContent,pattern);assert.doesNotMatch(h.message.textContent,/connection/);
  });
}
test('server errors include a safe reference and invalid JSON is distinguished from network errors',async()=>{
  const reference='5b8db81a-9a8f-4f67-8cfe-a29b43a640bc';
  const server=harness({error:{reference_id:reference}}, {status:503});await server.flush();assert.match(server.message.textContent,/server could not start/);assert.match(server.message.textContent,new RegExp(reference));
  const invalid=harness({}, {invalidJson:true});await invalid.flush();assert.match(invalid.message.textContent,/unexpected response/);
  const malformed=harness({status:{},refreshing:true});await malformed.flush();assert.match(malformed.message.textContent,/unexpected response/);
  const network=harness({}, {offline:true});await network.flush();assert.match(network.message.textContent,/Check your connection/);
});
test('a recorded deferred launch failure stops polling and shows its reference',async()=>{
  const h=harness({status:{pending:true,failed:false},refreshing:false,launch_failure:{reference_id:'5b8db81a-9a8f-4f67-8cfe-a29b43a640bc'}});await h.flush();assert.match(h.message.textContent,/Reference:/);assert.equal(h.retry.hidden,false);
});
