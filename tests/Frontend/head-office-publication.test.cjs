const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-draw-mode.js', 'utf8');
const handlerSource = source.slice(source.indexOf('(function (window, document) {'), source.lastIndexOf('(function (window, document) {'));

function harness(ids, replies, confirmed = true) {
  const requests = [], confirmations = [];
  const classes = () => ({ removed: [], remove(value) { this.removed.push(value); } });
  const feedback = { textContent: '', classList: classes() };
  const refresh = { classList: classes() };
  const summary = { textContent: 'Partly published · 1 published · 200 unpublished' };
  let reloads = 0;
  const buttons = ['publish', 'unpublish'].map(action => ({ dataset: { bulkDrawAction: action }, disabled: false, addEventListener(event, fn) { this.click = fn; } }));
  const panel = { dataset: { drawIds: JSON.stringify(ids), url: '/events/123/draws/bulk-publication' },
    querySelectorAll() { return buttons; }, querySelector(selector) { return selector.includes('feedback') ? feedback : selector.includes('summary') ? summary : refresh; } };
  const document = { querySelector(selector) { return selector.includes('csrf-token') ? { content: 'csrf-local' } : panel; } };
  const window = { location: { reload() { reloads++; } }, confirm(message) { confirmations.push(message); return confirmed; }, async fetch(url, options) {
    requests.push({ url, options, payload: JSON.parse(options.body) });
    const reply = replies.shift();
    if (reply instanceof Error) throw reply;
    return { ok: true, async json() { return reply; } };
  } };
  new Function('window', 'document', handlerSource)(window, document);
  return { buttons, requests, feedback, refresh, summary, confirmations, get reloads() { return reloads; } };
}

test('event-wide publication confirms all draws and sends sequential batches across every tab', async () => {
  const ids = Array.from({ length: 201 }, (_, index) => index + 1);
  const ui = harness(ids, [{ changed: ids.slice(0, 200), unchanged: [], failed: [] }, { changed: [201], unchanged: [], failed: [] }]);
  const running = ui.buttons[0].click();
  assert.equal(ui.buttons[1].disabled, true);
  await running;
  assert.match(ui.confirmations[0], /all 201 draws across every age-group tab/);
  assert.deepEqual(ui.requests.map(request => request.payload.draw_ids.length), [200, 1]);
  assert.deepEqual(ui.requests.flatMap(request => request.payload.draw_ids), ids);
  assert.equal(ui.requests[0].payload.operation, 'draws');
  assert.equal(ui.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf-local');
  assert.match(ui.feedback.textContent, /201 draws published, 0 unchanged/);
  assert.equal(ui.buttons[0].disabled, false);
  assert.deepEqual(ui.refresh.classList.removed, ['d-none']);
  assert.equal(ui.reloads, 1);
});

test('locked failures are displayed safely as text and no all-success claim replaces partial results', async () => {
  const ui = harness([1, 2], [{ changed: [1], unchanged: [], failed: [{ id: 2, name: '<img src=x>', message: '<script>locked</script>' }] }]);
  await ui.buttons[1].click();
  assert.equal(ui.requests[0].payload.action, 'unpublish');
  assert.match(ui.feedback.textContent, /1 draws unpublished, 0 unchanged.*Issues: <img src=x>: <script>locked<\/script>/);
  assert.equal(ui.feedback.innerHTML, undefined);
  assert.equal(ui.buttons[1].disabled, false);
  assert.match(ui.summary.textContent, /Refresh to see current counts/);
  assert.equal(ui.reloads, 0);
});

test('interrupted later batch preserves confirmed counts and tells user to refresh uncertain statuses', async () => {
  const ids = Array.from({ length: 201 }, (_, index) => index + 1);
  const ui = harness(ids, [{ changed: ids.slice(0, 199), unchanged: [200], failed: [] }, new Error('Network failed')]);
  await ui.buttons[0].click();
  assert.match(ui.feedback.textContent, /199 draws published, 1 unchanged/);
  assert.match(ui.feedback.textContent, /1 remaining draws have unconfirmed status. Refresh before retrying/);
  assert.equal(ui.buttons[0].disabled, true);
  assert.match(ui.summary.textContent, /status unconfirmed/);
  assert.equal(ui.reloads, 0);
});

test('cancelled confirmation sends no publication request', async () => {
  const ui = harness([1, 2], [], false);
  await ui.buttons[0].click();
  assert.equal(ui.requests.length, 0);
  assert.equal(ui.buttons[0].disabled, false);
});
