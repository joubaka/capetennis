const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/player-rating-badges.js', 'utf8');

function harness({ enabled = true, svg = false, category = false, compact = false, rendered = false } = {}) {
  const node = { dataset: { ratingPlayer: '7', ratingDraw: '0', ...(svg ? { ratingSvg: '1' } : {}), ...(category ? { ratingCategory: '3' } : {}) }, innerHTML: 'Old server badge',
    closest(selector) { return compact && selector === '.fm-player-identity' ? {} : null; } };
  if (rendered) node.dataset.ratingDone = '1';
  const timers = [];
  const calls = [];
  let ratings = [{ score: 61.2, rating_scope: 'all_age', confidence_label: 'Medium', title: 'Private rating <safe>', display_label: '61.2 | Medium' }];
  const document = { readyState: 'complete', hidden: false, body: {},
    querySelectorAll(selector) {
      return selector.includes(':not') && node.dataset.ratingDone ? [] : [node];
    },
  };
  const window = { ...(enabled ? { CTPlayerRatingConfig: { endpoint: '/private/badges', drawId: 99,
    stampTemplates: {
      ...Object.fromEntries(['unknown', 'red', 'orange', 'green', 'yellow', 'standard'].map(stage => [stage, {
        shared: '<span data-ctr-badge data-stage="' + stage + '" title="__CTR_TITLE__">CTR __CTR_SCORE__/100</span>',
        local: '<span data-ctr-badge data-stage="' + stage + '" title="__CTR_TITLE__">CTR __CTR_SCORE__/100</span><span>within-group only</span>'
      }]))
    } } } : {}),
    setTimeout(callback) { timers.push(callback); },
    setInterval() { assert.fail('Saved badges must never poll'); },
  };
  const fetch = async url => {
    calls.push(url);
    return { ok: true, json: async () => ({ ratings: { 'p:7': ratings } }) };
  };
  vm.runInNewContext(source, { window, document, fetch, URLSearchParams, MutationObserver: class { observe() {} } });
  async function flush() {
    for (let n = 0; n < 12; n++) {
      await Promise.resolve();
      if (timers.length) timers.shift()();
    }
  }
  return { node, calls, flush, update(nextRatings) { ratings = nextRatings; }, schedule: () => window.CTPlayerRatings.refresh() };
}

test('finish-only ball estimates retain explicit low-confidence local source labels', async () => {
  const h = harness();
  h.update([{ score: 55.1, rating_scope: 'ball', ball_type: 'orange', comparable: false,
    evidence_basis: 'finish_only', confidence_label: 'Low', title: 'Published final placings only' }]);
  await h.flush();
  assert.match(h.node.innerHTML, /within-group only/);
  assert.match(h.node.innerHTML, /Low confidence · final placings only/);
  assert.match(h.node.innerHTML, /data-stage="orange"/);
});

test('fixed-height draw rows retain rating caveats in the compact badge', async () => {
  const h = harness({ compact: true });
  h.update([{ score: 55.1, rating_scope: 'ball', ball_type: 'orange', comparable: false,
    evidence_basis: 'finish_only', confidence_label: 'Low' }]);
  await h.flush();
  assert.match(h.node.innerHTML, /fm-ctr-badge/);
  assert.match(h.node.innerHTML, /CTR 55\.1\*/);
  assert.match(h.node.innerHTML, /Low confidence · within-group only · final placings only/);
  assert.doesNotMatch(h.node.innerHTML, /data-ctr-badge/);
});

test('dynamic badges hydrate once and retain the saved values', async () => {
  const h = harness();
  await h.flush();
  assert.match(h.node.innerHTML, /data-ctr-badge/);
  assert.match(h.node.innerHTML, /61.2\/100/);
  assert.match(h.node.innerHTML, /Medium confidence/);
  assert.doesNotMatch(h.node.innerHTML, /within-group only/);
  assert.match(h.node.innerHTML, /&lt;safe&gt;/);
  assert.ok(h.calls.some(url => url.includes('players%5B%5D=7')));
  assert.ok(h.calls.every(url => !url.includes('draw_id=99'))); // explicit global SSR context stays global
  const saved = h.node.innerHTML;
  h.update([]);
  h.schedule(); await h.flush();
  assert.equal(h.node.innerHTML, saved);
  assert.equal(h.calls.length, 1);
});

test('AJAX stamps preserve within-group warnings and escape title and confidence text', async () => {
  const h = harness();
  h.update([{ score: 44.2, rating_scope: 'all_age', comparable: false,
    title: '$& \"\'><img src=x onerror=alert(1)>', confidence_label: '<script>unsafe</script>', historical_connection: true }]);
  await h.flush();
  assert.match(h.node.innerHTML, /CTR 44.2\/100/);
  assert.match(h.node.innerHTML, /within-group only/);
  assert.match(h.node.innerHTML, /old link/);
  assert.match(h.node.innerHTML, /&lt;script&gt;/);
  assert.match(h.node.innerHTML, /\$&amp;/);
  assert.doesNotMatch(h.node.innerHTML, /__CTR_TITLE__/);
  assert.doesNotMatch(h.node.innerHTML, /<img|<script/);
});

test('stage templates use only explicit validated ball metadata and retain local warnings', async () => {
  const h = harness();
  await h.flush();
  assert.match(h.node.innerHTML, /data-stage="unknown"/);
  for (const ball of ['red', 'orange', 'green', 'yellow', 'standard']) {
    const h = harness();
    h.update([{ score: 55.1, ball_type: ball, rating_scope: 'all_age', comparable: false, title: 'Explicit ball' }]);
    await h.flush();
    assert.match(h.node.innerHTML, new RegExp('data-stage="' + ball + '"'));
    assert.match(h.node.innerHTML, /within-group only/);
  }
  const invalid = harness();
  invalid.update([{ score: 99.9, ball_type: '<script>red</script>', category: 'Red ball U8', rating_scope: 'all_age', title: 'Unrecorded stage' }]);
  await invalid.flush();
  assert.match(invalid.node.innerHTML, /data-stage="unknown"/);
  assert.doesNotMatch(invalid.node.innerHTML, /<script>/);
});

test('dynamic markers retain SVG formatting', async () => {
  const h = harness({ svg: true, category: true });
  h.update([{ title: 'New evidence', display_label: '52.0 | Low' }]);
  await h.flush();
  assert.match(h.node.innerHTML, /<tspan class="player-rating-badge"/);
  assert.match(h.node.innerHTML, /\[52.0 \| Low\]/);
  assert.ok(h.calls.some(url => url.includes('category_id=3')));
});

test('a comparable independent ball rating keeps its level stamp without a false local warning', async () => {
  const h = harness();
  h.update([{ score: 64.2, ball_type: 'red', rating_scope: 'ball', comparable: true, title: 'Red-only results' }]);
  await h.flush();
  assert.match(h.node.innerHTML, /data-stage="red"/);
  assert.doesNotMatch(h.node.innerHTML, /within-group only/);
});

test('server-rendered ratings require no browser lookup', async () => {
  const h = harness({ rendered: true });
  await h.flush();
  assert.equal(h.calls.length, 0);
  assert.equal(h.node.innerHTML, 'Old server badge');
});

test('pages without private rating configuration never poll or render', async () => {
  const h = harness({ enabled: false }); await h.flush();
  assert.equal(h.calls.length, 0);
  assert.equal(h.node.innerHTML, 'Old server badge');
});
