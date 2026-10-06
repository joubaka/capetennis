const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/head-office-draw-publication.js', 'utf8');
const bulkSource = fs.readFileSync('public/js/team-draw-mode.js', 'utf8');
const bulkScript = bulkSource.slice(bulkSource.indexOf('(function (window, document) {'), bulkSource.lastIndexOf('(function (window, document) {'));
const state = (published = false, second = false) => ({ success: true, event_id: 123,
  draw_states: [{ id: 1, published, locked: false, oop_published: true }, { id: 2, published: second, locked: false, oop_published: false }],
  draw_summary: { published: Number(published) + Number(second), unpublished: 2 - Number(published) - Number(second), status: 'Current' }, changed: [1], unchanged: [], failed: [] });
function harness(replies, useBulk = false) {
  let click, prevented = 0, stopped = 0, reloads = 0;
  const requests = [];
  const classes = () => ({ remove() {}, toggle() {} });
  function makeCard(id) {
    const card = { dataset: { drawId: String(id) }, open: false };
    const quick = { dataset: { published: 'false' }, disabled: false, classList: classes(), setAttribute(key, value) { this[key] = value; }, closest(selector) { return selector === '[data-quick-publication-card]' ? card : this; } };
    const expanded = { dataset: { status: '0' }, disabled: false, closest(selector) { return selector === '[data-quick-publication-card]' ? card : this; } };
    const scheduleButton = { dataset: { status: '1' } };
    const badges = [{ classList: classes() }, { classList: classes() }];
    const schedule = { classList: classes() };
    const oop = { dataset: { created: '1' }, classList: classes() };
    const feedback = { classList: classes(), textContent: '' };
    const retry = { hidden: true, addEventListener(event, handler) { this.click = handler; } };
    const fields = { '[data-quick-draw-publication]': quick, '[data-quick-draw-feedback]': feedback, '[data-quick-draw-status-retry]': retry, h6: { textContent: 'u/10 Draw ' + id }, '.event-oop-summary': oop };
    Object.assign(card, { quick, expanded, scheduleButton, badges, oop, feedback, retry,
      querySelector(selector) { return fields[selector]; }, querySelectorAll(selector) { return selector === '.toggle-publish' ? [expanded] : selector === '.toggle-publish-schedule' ? [scheduleButton] : selector === '.event-schedule-status' ? [schedule] : badges; } });
    return card;
  }
  const cards = [makeCard(1), makeCard(2)];
  const summary = { textContent: 'Unpublished · 0 published · 2 unpublished' };
  const feedback = { textContent: '', classList: classes() }, refresh = { classList: classes() };
  const bulk = ['publish', 'unpublish'].map(action => ({ dataset: { bulkDrawAction: action }, disabled: false, addEventListener(event, handler) { this.click = handler; } }));
  const panel = { dataset: { eventId: '123', drawIds: '[1,2]', url: '/bulk', statusUrl: '/status' }, querySelectorAll() { return bulk; },
    querySelector(selector) { return selector.includes('feedback') ? feedback : selector.includes('refresh') ? refresh : summary; } };
  const document = { querySelector(selector) { return selector.includes('csrf-token') ? { content: 'csrf' } : panel; }, querySelectorAll() { return cards; },
    addEventListener(event, handler, capture) { assert.equal(capture, true); click = handler; } };
  const window = { confirm() { return true; }, location: { reload() { reloads++; } }, async fetch(url, options) {
    requests.push({ url, options, payload: options.body ? JSON.parse(options.body) : null });
    const reply = await replies.shift();
    if (reply instanceof Error) throw reply;
    return { ok: !reply.error, async json() { return reply; } };
  } };
  new Function('window', 'document', source)(window, document);
  if (useBulk) new Function('window', 'document', bulkScript)(window, document);
  return { cards, summary, requests, window, bulk, get prevented() { return prevented; }, get stopped() { return stopped; }, get reloads() { return reloads; },
    async click(button = cards[0].quick) { return click({ target: button, preventDefault() { prevented++; }, stopImmediatePropagation() { stopped++; } }); },
    async retry(card = cards[0]) { return card.retry.click({ preventDefault() {}, stopPropagation() {} }); } };
}

test('quick header uses one draw ID, keeps details closed and refreshes expanded controls and event counts', async () => {
  const ui = harness([state(true), state(false)]);
  await ui.click();
  assert.deepEqual(ui.requests[0].payload, { operation: 'draws', action: 'publish', draw_ids: [1] });
  assert.equal(ui.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
  assert.equal(ui.cards[0].open, false);
  assert.equal(ui.prevented, 1); assert.equal(ui.stopped, 1);
  assert.equal(ui.cards[0].quick['aria-pressed'], 'true');
  assert.equal(ui.cards[0].quick.textContent, 'Unpublish');
  assert.equal(ui.cards[0].expanded.dataset.status, '1');
  assert.equal(ui.cards[0].badges[0].textContent, 'Draw published');
  assert.match(ui.summary.textContent, /Partly published · 1 published · 1 unpublished/);
  await ui.click(ui.cards[0].expanded);
  assert.equal(ui.requests[1].payload.action, 'unpublish');
  assert.equal(ui.cards[0].quick['aria-pressed'], 'false');
  assert.match(ui.cards[0].oop.textContent, /Preview only/);
  assert.equal(ui.reloads, 0);
});

test('canonical locked or readiness failures preserve actual state and render error text safely', async () => {
  const result = state(false); result.success = false; result.changed = []; result.failed = [{ id: 1, message: '<script>Not ready</script>' }];
  const ui = harness([result]);
  await ui.click();
  assert.equal(ui.cards[0].quick.dataset.published, 'false');
  assert.equal(ui.cards[0].quick.disabled, false);
  assert.equal(ui.cards[0].feedback.textContent, '<script>Not ready</script>');
  assert.equal(ui.cards[0].feedback.innerHTML, undefined);
  result.draw_states[0].published = true; result.draw_states[0].locked = true;
  ui.window.HeadOfficeDrawPublicationUI.apply(result);
  await ui.click();
  assert.equal(ui.cards[0].quick.disabled, true);
  assert.equal(ui.requests.length, 1);
});

test('uncertain quick outcome reconciles by read only and failed reconciliation pauses actions until retry', async () => {
  const ui = harness([new Error('Disconnected'), new Error('Disconnected'), state(true)]);
  await ui.click();
  assert.deepEqual(ui.requests.map(request => request.options.method || 'GET'), ['POST', 'GET']);
  assert.equal(ui.cards[0].quick.disabled, true);
  assert.equal(ui.cards[0].retry.hidden, false);
  await ui.click(ui.cards[1].quick);
  assert.equal(ui.requests.length, 2);
  await ui.retry();
  assert.equal(ui.requests[2].url, '/status');
  assert.equal(ui.requests[2].options.method, undefined);
  assert.equal(ui.cards[0].quick.textContent, 'Unpublish');
  assert.equal(ui.cards[0].quick.disabled, false);
});

test('partial bulk result reconciles all quick states before another single draw action', async () => {
  const partial = state(true); partial.success = false; partial.failed = [{ id: 2, name: 'Draw2', message: 'Locked' }];
  const ui = harness([partial, state(true), state(false)], true);
  await ui.bulk[0].click();
  assert.equal(ui.cards[0].quick.textContent, 'Unpublish');
  assert.equal(ui.requests[1].url, '/status');
  await ui.click();
  assert.deepEqual(ui.requests[2].payload.draw_ids, [1]);
  assert.equal(ui.requests[2].payload.action, 'unpublish');
  assert.equal(ui.reloads, 0);
});

test('unknown bulk outcome disables stale quick actions and readonly retry reconciles current flags', async () => {
  const ui = harness([new Error('Disconnected'), new Error('Disconnected'), state(true)], true);
  await ui.bulk[0].click();
  assert.equal(ui.cards[0].quick.disabled, true);
  assert.equal(ui.cards[0].retry.hidden, false);
  await ui.click();
  assert.equal(ui.requests.length, 2);
  await ui.retry();
  assert.equal(ui.cards[0].quick.textContent, 'Unpublish');
  assert.equal(ui.cards[0].quick.disabled, false);
  assert.equal(ui.window.HeadOfficeDrawPublicationUnconfirmed, false);
});
