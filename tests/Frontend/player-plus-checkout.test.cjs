const test = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {runInNewContext} = require('node:vm');
const {launch, PaymentReturn} = require('../../public/assets/js/player-plus-checkout.js');

test('launch submits the supplied fake form once and preserves manual fallback on failure', () => {
  let submits = 0;
  const form = {dataset: {}, submit() { submits++; }};
  launch(form); launch(form);
  assert.equal(submits, 1);
  const blocked = {dataset: {}, submit() { throw new Error('blocked'); }};
  launch(blocked);
  assert.equal(blocked.dataset.plusSubmitted, undefined);
});

test('an unfinished checkout rechecks its receipt once at the server-provided delay', () => {
  const source = readFileSync(require.resolve('../../public/assets/js/player-plus-checkout.js'), 'utf8');
  const link = {href: 'https://fictional.test/player-plus/orders/7?checkout=cancelled', dataset: {plusRetryRefreshMs: '27000'}};
  const timers = [];
  const visits = [];
  runInNewContext(source, {window: {
    document: {readyState: 'complete', querySelector: selector => selector === '[data-plus-retry-refresh-ms]' ? link : null},
    setTimeout: (callback, delay) => timers.push({callback, delay}),
    location: {replace: href => visits.push(href)},
  }});
  assert.equal(timers.length, 1);
  assert.equal(timers[0].delay, 27000);
  assert.deepEqual(visits, []);
  timers[0].callback();
  assert.deepEqual(visits, [link.href]);
});

function harness(responses) {
  const label = {textContent: ''}; const calls = []; const timers = []; const destinations = [];
  const panel = {dataset: {statusUrl: '/player-plus/orders/7/payment-status'}, querySelector: () => label};
  const ui = new PaymentReturn(panel, {origin: 'https://fictional.test',
    fetch: async (url, options) => {
      calls.push({url, options}); const next = responses.shift();
      if (next instanceof Error) throw next;
      return {ok: true, redirected: false, json: async () => next};
    }, setTimer: callback => { timers.push(callback); }, navigate: url => destinations.push(url)});
  return {ui, label, calls, timers, destinations};
}

test('pending checks are read-only and bounded to twelve requests', async () => {
  const h = harness(Array.from({length: 20}, () => ({state: 'pending', destination: null})));
  await h.ui.check();
  while (h.timers.length) await h.timers.shift()();
  await h.ui.check();
  assert.equal(h.calls.length, 12);
  assert.equal(h.destinations.length, 0);
  assert.match(h.label.textContent, /Automatic checks have stopped/);
  assert.equal(h.calls[0].options.credentials, 'same-origin');
  assert.equal(h.calls[0].options.cache, 'no-store');
  assert.equal(h.calls[0].options.method, undefined);
});

test('verified completion navigates once to the server-approved player rating tab', async () => {
  const h = harness([{state: 'pending', destination: null}, {state: 'verified', destination: 'https://fictional.test/my-tennis?player=9#rating'}]);
  await h.ui.check(); await h.timers.shift()(); await h.ui.check();
  assert.deepEqual(h.destinations, ['https://fictional.test/my-tennis?player=9#rating']);
});

test('receipt result returns sponsors or closed checkouts to their receipt', async () => {
  const h = harness([{state: 'receipt', destination: 'https://fictional.test/player-plus/orders/7'}]);
  await h.ui.check();
  assert.deepEqual(h.destinations, ['https://fictional.test/player-plus/orders/7']);
  assert.equal(h.timers.length, 0);
});

test('network failure, unknown state and foreign destinations stop without navigation', async () => {
  for (const response of [new Error('offline'), {state: 'paid', destination: '/my-tennis'}, {state: 'verified', destination: 'https://other.test/private'}]) {
    const h = harness([response]); await h.ui.check(); await h.ui.check();
    assert.equal(h.calls.length, 1);
    assert.equal(h.destinations.length, 0);
    assert.match(h.label.textContent, /Automatic checks have stopped/);
  }
});

test('a stalled status request times out and leaves manual status checking available', async () => {
  let expire; let cleared = false;
  const label = {textContent: ''};
  const ui = new PaymentReturn({dataset: {statusUrl: '/status'}, querySelector: () => label}, {
    origin: 'https://fictional.test', navigate: () => assert.fail('Unexpected navigation'),
    setRequestTimer: callback => { expire = callback; return 7; },
    clearRequestTimer: id => { assert.equal(id, 7); cleared = true; },
    fetch: (_, options) => new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Timeout')))),
  });
  const pending = ui.check(); expire(); await pending;
  assert.equal(ui.checking, false);
  assert.equal(ui.stopped, true);
  assert.equal(cleared, true);
  assert.match(label.textContent, /Check payment status/);
});
