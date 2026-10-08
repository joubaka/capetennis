const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-draw-scoring-readiness.js', 'utf8');

function harness(responses = []) {
  const badges = [0, 1].map(() => ({ textContent: '', classList: { toggle() {} } }));
  const buttons = [0, 1].map(() => ({ disabled: false, hidden: false, textContent: 'Enable scoring',
    dataset: { drawId: '7', url: '/draw/7/enable-scoring', statusUrl: '/draw/7/scoring-status', locked: 'false' } }));
  const feedback = { textContent: '' };
  let click;
  const calls = [];
  const document = {
    querySelectorAll(selector) { return selector.startsWith('[data-scoring-status]') ? badges : buttons; },
    querySelector(selector) { return selector.startsWith('meta') ? { content: 'token' } : feedback; },
    addEventListener(name, listener) { if (name === 'click') click = listener; },
  };
  const window = { fetch: async (url, options = {}) => {
    calls.push({ url, method: options.method || 'GET' });
    const response = responses.shift();
    if (response instanceof Error) throw response;
    return typeof response === 'function' ? response() : response;
  } };
  vm.runInNewContext(source, { window, document, Set, Number, Boolean, Error });
  return { window, badges, buttons, feedback, calls,
    click: (index = 0) => click({ target: { closest: () => buttons[index] }, preventDefault() {}, stopPropagation() {} }) };
}

function response(ready = true, extra = {}) {
  return { ok: true, status: 200, json: async () => ({ success: true, id: 7,
    scoring: { ready }, published: true, locked: false, message: 'Scoring ready.', ...extra }) };
}

test('successful enable updates duplicate badges and hides every enable action', async () => {
  const h = harness([response()]);
  await h.click();
  assert.deepEqual(h.calls, [{ url: '/draw/7/enable-scoring', method: 'POST' }]);
  assert.ok(h.badges.every(badge => badge.textContent === 'Scoring ready'));
  assert.ok(h.buttons.every(button => button.hidden));
  assert.equal(h.feedback.textContent, 'Scoring ready.');
  assert.equal(h.window.HeadOfficeDrawPublicationPending, false);
});

test('validation failure explains blocking tie and permits a deliberate retry', async () => {
  const h = harness([{ ok: false, status: 422, json: async () => ({ success: false, message: 'Tie #12: Missing player.' }) }, response()]);
  await h.click();
  assert.equal(h.feedback.textContent, 'Tie #12: Missing player.');
  assert.ok(h.buttons.every(button => !button.disabled && !button.hidden));
  await h.click(1);
  assert.deepEqual(h.calls.map(call => call.method), ['POST', 'POST']);
});

test('pending mutation blocks duplicate clicks and other publication writes', async () => {
  let resolve;
  const h = harness([() => new Promise(done => { resolve = done; })]);
  const first = h.click();
  assert.equal(h.window.HeadOfficeDrawPublicationPending, true);
  await h.click(1);
  assert.equal(h.calls.length, 1);
  resolve(response());
  await first;
  h.window.HeadOfficeDrawPublicationPending = true;
  await h.click();
  assert.equal(h.calls.length, 1);
});

test('uncertain POST reconciles with GET and never repeats a mutation automatically', async () => {
  const h = harness([new Error('Connection lost'), response()]);
  await h.click();
  assert.deepEqual(h.calls.map(call => call.method), ['POST', 'GET']);
  assert.match(h.feedback.textContent, /Current scoring status checked/);
  assert.ok(h.buttons.every(button => button.hidden));
});

test('unconfirmed status switches retry to GET only until state can be confirmed', async () => {
  const h = harness([new Error('Connection lost'), new Error('Unavailable'), new Error('Unavailable'), new Error('Unavailable'), response(false), response()]);
  await h.click();
  assert.equal(h.buttons[0].textContent, 'Check scoring status');
  await h.click();
  assert.deepEqual(h.calls.map(call => call.method), ['POST', 'GET', 'GET', 'GET']);
  await h.click();
  assert.equal(h.calls.at(-1).method, 'GET');
  assert.equal(h.buttons[0].textContent, 'Enable scoring');
  await h.click();
  assert.equal(h.calls.at(-1).method, 'POST');
});

test('publication and locked state control action visibility and prevent locked mutation', async () => {
  const h = harness();
  h.window.TeamDrawScoringReadiness.apply(7, { ready: false }, false, false);
  assert.ok(h.buttons.every(button => button.hidden));
  h.window.TeamDrawScoringReadiness.apply(7, { ready: false }, true, true);
  assert.ok(h.buttons.every(button => !button.hidden && button.disabled));
  await h.click();
  assert.equal(h.calls.length, 0);
  h.window.TeamDrawScoringReadiness.apply(7, { ready: false }, true, false);
  assert.ok(h.buttons.every(button => !button.hidden && !button.disabled));
});

test('reconciliation retains a server-confirmed lock through finally cleanup', async () => {
  const h = harness([new Error('Connection lost'), response(false, { locked: true })]);
  await h.click();
  assert.ok(h.buttons.every(button => button.disabled));
  await h.click(1);
  assert.equal(h.calls.length, 2);
});

test('external confirmed publication state clears the status-check label and pause', async () => {
  const h = harness([new Error('Connection lost'), new Error('Unavailable'), response()]);
  await h.click();
  assert.equal(h.buttons[0].textContent, 'Check scoring status');
  h.window.TeamDrawScoringReadiness.apply(7, { ready: false }, true, false);
  assert.ok(h.buttons.every(button => button.textContent === 'Enable scoring'));
  await h.click();
  assert.equal(h.calls.at(-1).method, 'POST');
});
