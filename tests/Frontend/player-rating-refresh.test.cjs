const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/player-rating-badges.js', 'utf8');

function harness({ enabled = true, svg = false, category = false } = {}) {
  const node = { dataset: { ratingPlayer: '7', ratingDraw: '0', ...(svg ? { ratingSvg: '1' } : {}), ...(category ? { ratingCategory: '3' } : {}) }, innerHTML: 'Old server badge' };
  const label = { textContent: '' };
  const timers = [];
  const listeners = {};
  const calls = [];
  let poll;
  let version = 'v1';
  let ratings = [{ title: 'Private rating <safe>', display_label: '61.2 | Medium' }];
  let failed = false;
  let networkFailure = false;
  const document = { readyState: 'complete', hidden: false, body: {},
    querySelectorAll(selector) {
      if (selector === '[data-rating-status]') return [label];
      return selector.includes(':not') && node.dataset.ratingDone ? [] : [node];
    },
    addEventListener(name, callback) { listeners[name] = callback; },
  };
  const window = { ...(enabled ? { CTPlayerRatingConfig: { endpoint: '/private/badges', drawId: 99 } } : {}),
    setTimeout(callback) { timers.push(callback); },
    setInterval(callback, delay) { assert.equal(delay, 60000); poll = callback; },
  };
  const fetch = async url => {
    calls.push(url);
    if (networkFailure) throw new Error('Offline');
    return { ok: true, json: async () => ({ status: { version, last_updated: 'Today SAST', pending: false, failed },
      ratings: { 'p:7': ratings } }) };
  };
  vm.runInNewContext(source, { window, document, fetch, URLSearchParams, MutationObserver: class { observe() {} } });
  async function flush() {
    for (let n = 0; n < 12; n++) {
      await Promise.resolve();
      if (timers.length) timers.shift()();
    }
  }
  return { node, label, calls, document, flush, poll: () => poll?.(),
    visibility: () => listeners.visibilitychange?.(),
    update(nextVersion, nextRatings) { version = nextVersion; ratings = nextRatings; },
    failure() { failed = true; }, offline() { networkFailure = true; } };
}

test('private rendered badges refresh after version changes and removed ratings clear old HTML', async () => {
  const h = harness();
  await h.flush();
  assert.match(h.node.innerHTML, /61.2 \| Medium/);
  assert.match(h.node.innerHTML, /&lt;safe&gt;/);
  assert.ok(h.calls.some(url => url.includes('players%5B%5D=7')));
  assert.ok(h.calls.every(url => !url.includes('draw_id=99'))); // explicit global SSR context stays global
  h.update('v2', []);
  await h.poll(); await h.flush();
  assert.equal(h.node.innerHTML, '');
  assert.equal(h.node.dataset.ratingDone, '1');
});

test('unrated server markers receive new ratings and SVG stays SVG', async () => {
  const h = harness({ svg: true, category: true });
  h.update('v1', []);
  await h.flush();
  assert.equal(h.node.innerHTML, '');
  h.update('v2', [{ title: 'New evidence', display_label: '52.0 | Low' }]);
  await h.poll(); await h.flush();
  assert.match(h.node.innerHTML, /<tspan class="player-rating-badge"/);
  assert.match(h.node.innerHTML, /\[52.0 \| Low\]/);
  assert.ok(h.calls.some(url => url.includes('category_id=3')));
});

test('unchanged versions reuse badges, hidden tabs pause polling and resume when visible', async () => {
  const h = harness(); await h.flush();
  const lookups = () => h.calls.filter(url => url.includes('?')).length;
  const initial = lookups();
  await h.poll(); await h.flush();
  assert.equal(lookups(), initial);
  h.document.hidden = true;
  const count = h.calls.length;
  await h.poll();
  assert.equal(h.calls.length, count);
  h.document.hidden = false;
  h.visibility(); await h.flush();
  assert.equal(h.calls.length, count + 1);
});

test('failure status changes without badge revision and offline failures retain badges', async () => {
  const h = harness(); await h.flush();
  h.failure(); await h.poll();
  assert.match(h.label.textContent, /Update failed; retry pending/);
  const html = h.node.innerHTML;
  h.offline(); await h.poll();
  assert.equal(h.node.innerHTML, html);
});

test('pages without private rating configuration never poll or render', async () => {
  const h = harness({ enabled: false }); await h.flush(); await h.poll();
  assert.equal(h.calls.length, 0);
  assert.equal(h.node.innerHTML, 'Old server badge');
});
