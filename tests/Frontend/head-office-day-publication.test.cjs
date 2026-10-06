const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-draw-mode.js', 'utf8');
const script = source.slice(source.lastIndexOf('(function (window, document) {'));
const revision = value => value.repeat(64);
const day = (date, status = 'Unpublished') => ({ date, label: date, status, saved: 1, published: status === 'Published' ? 1 : 0, matched: status === 'Published' ? 1 : 0, pending: status === 'Published' ? 0 : 1 });
const snapshot = (days, token = 'a', action = 'publish') => ({ success: true, event_id: 123, days, revision: revision(token), action });
const classes = () => ({ remove() {}, toggle() {} });
function card(date = '2026-10-09') {
  const button = { dataset: { disabled: 'false' }, classList: classes(), disabled: false, focus() { this.focused = true; }, setAttribute(key, value) { this[key] = value; } };
  function form(action, control) {
    const fields = { '[name="date"]': { value: date }, '[name="revision"]': { value: revision('a') }, button: control };
    return { action, matches() { return true; }, querySelector(selector) { return fields[selector]; } };
  }
  const main = form('/publish', button), hide = form('/hide', { dataset: { disabled: 'true' }, disabled: true });
  const fields = { '[data-day-toggle]': button, '[data-main-day-action]': main };
  ['label', 'status', 'counts', 'pending', 'review', 'hide-menu'].forEach(name => { fields['[data-day-' + name + ']'] = { textContent: '', hidden: false }; });
  return { dataset: { date }, main, hide, fields, cloneNode() { return card(''); },
    querySelector(selector) { return fields[selector]; }, querySelectorAll() { return [main, hide]; } };
}
function harness(replies, initialUnconfirmed = false) {
  let current = [card('2026-10-09'), card('2026-10-10')], submit;
  const requests = [];
  const feedback = { textContent: '', classList: classes(), setAttribute() {}, focus() {} };
  const retry = { hidden: !initialUnconfirmed, disabled: false, addEventListener(event, fn) { this.click = fn; } };
  const cards = { replaceChildren(...updated) { current = updated; }, appendChild() {} };
  const template = { content: { firstElementChild: card('') } };
  const fields = { '[data-day-cards]': cards, '[data-day-card-template]': template, '[data-day-feedback]': feedback, '[data-day-status-retry]': retry };
  const panel = { dataset: { initialUnconfirmed: initialUnconfirmed ? 'true' : 'false', eventId: '123', statusUrl: '/status', publishUrl: '/publish', hideUrl: '/hide', calendarUrl: '/calendar' },
    setAttribute() {}, querySelector(selector) { const date = selector.match(/data-date="([^"]+)"/); return date ? current.find(row => row.dataset.date === date[1])?.fields['[data-day-toggle]'] : fields[selector]; },
    querySelectorAll() { return current.flatMap(row => [row.main.querySelector('button'), row.hide.querySelector('button')]); },
    addEventListener(event, fn) { submit = fn; } };
  const document = { querySelector(selector) { return selector.includes('csrf-token') ? { content: 'local-csrf' } : panel; }, createElement() { return {}; } };
  const window = { async fetch(url, options) {
    requests.push({ url, options, payload: options.body ? JSON.parse(options.body) : null });
    const reply = await replies.shift();
    if (reply instanceof Error) throw reply;
    return { ok: !reply.error, async json() { return reply; } };
  } };
  new Function('window', 'document', script)(window, document);
  return { requests, feedback, retry, get current() { return current; }, async submit(form) { return submit({ target: form, preventDefault() {} }); } };
}

test('one day toggles publish then hide using refreshed revisions and updates every card without reload', async () => {
  const ui = harness([snapshot([day('2026-10-09', 'Published'), day('2026-10-10')], 'b'), snapshot([day('2026-10-09'), day('2026-10-10')], 'c', 'hide')]);
  await ui.submit(ui.current[0].main);
  assert.equal(ui.current[0].fields['[data-day-toggle]'].textContent, 'Hide day');
  assert.equal(ui.current[0].fields['[data-day-toggle]']['aria-pressed'], 'true');
  assert.equal(ui.current[0].fields['[data-day-toggle]'].focused, true);
  assert.equal(ui.current[1].main.querySelector('[name="revision"]').value, revision('b'));
  await ui.submit(ui.current[0].main);
  assert.deepEqual(ui.requests.map(request => request.url), ['/publish', '/hide']);
  assert.deepEqual(ui.requests.map(request => request.payload), [{ date: '2026-10-09', revision: revision('a') }, { date: '2026-10-09', revision: revision('b') }]);
  assert.equal(ui.requests[0].options.headers['X-CSRF-TOKEN'], 'local-csrf');
  assert.equal(ui.current[0].fields['[data-day-toggle]'].textContent, 'Publish day');
  assert.equal(ui.current[0].fields['[data-day-toggle]']['aria-pressed'], 'false');
});

test('moved snapshots remove obsolete cards and partial state retains explicit hide availability', async () => {
  const partial = { ...day('2026-10-10'), status: 'Partly published', saved: 2, published: 1, matched: 1 };
  const ui = harness([snapshot([partial], 'b')]);
  await ui.submit(ui.current[1].main);
  assert.equal(ui.current.length, 1);
  assert.equal(ui.current[0].dataset.date, '2026-10-10');
  assert.equal(ui.current[0].fields['[data-day-toggle]'].textContent, 'Publish updates');
  assert.equal(ui.current[0].fields['[data-day-hide-menu]'].hidden, false);
  assert.equal(ui.current[0].hide.querySelector('button').disabled, false);
});

test('stale or uncertain write recovers status by GET and never automatically retries a mutation', async () => {
  const ui = harness([{ error: true, success: false, message: '<script>stale</script>' }, snapshot([day('2026-10-09', 'Published')], 'b')]);
  await ui.submit(ui.current[0].main);
  assert.deepEqual(ui.requests.map(request => [request.url, request.options.method || 'GET']), [['/publish', 'POST'], ['/status', 'GET']]);
  assert.match(ui.feedback.textContent, /Current day statuses have been refreshed/);
  assert.equal(ui.feedback.innerHTML, undefined);
  assert.equal(ui.current[0].main.querySelector('button').disabled, false);
});

test('failed recovery pauses all day actions until explicit read-only retry succeeds', async () => {
  const ui = harness([new Error('Disconnected'), new Error('Disconnected'), snapshot([day('2026-10-09')], 'c')]);
  await ui.submit(ui.current[0].main);
  assert.equal(ui.retry.hidden, false);
  assert.equal(ui.current[0].main.querySelector('button').disabled, true);
  await ui.submit(ui.current[1].main);
  assert.equal(ui.requests.length, 2);
  await ui.retry.click();
  assert.equal(ui.requests[2].url, '/status');
  assert.equal(ui.requests[2].options.method, undefined);
  assert.equal(ui.current[0].main.querySelector('button').disabled, false);
  assert.equal(ui.retry.hidden, true);
});

test('global pending lock prevents a second day from submitting an old revision', async () => {
  let complete;
  const ui = harness([new Promise(resolve => { complete = resolve; })]);
  const first = ui.submit(ui.current[0].main);
  assert.equal(ui.current[1].main.querySelector('button').disabled, true);
  await ui.submit(ui.current[1].main);
  assert.equal(ui.requests.length, 1);
  complete(snapshot([day('2026-10-09', 'Published'), day('2026-10-10')], 'b'));
  await first;
  assert.equal(ui.current[1].main.querySelector('[name="revision"]').value, revision('b'));
});

test('unconfirmed initial page starts paused and only status retry can restore actions', async () => {
  const ui = harness([snapshot([day('2026-10-09')], 'b')], true);
  assert.equal(ui.current[0].main.querySelector('button').disabled, true);
  await ui.submit(ui.current[0].main);
  assert.equal(ui.requests.length, 0);
  await ui.retry.click();
  assert.equal(ui.requests[0].url, '/status');
  assert.equal(ui.requests[0].options.method, undefined);
  assert.equal(ui.current[0].main.querySelector('[name="revision"]').value, revision('b'));
  assert.equal(ui.current[0].main.querySelector('button').disabled, false);
});
