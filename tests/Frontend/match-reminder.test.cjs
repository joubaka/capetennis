const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('public/js/match-reminder.js', 'utf8');

async function run({ login = 'first-login', account = '1', autoOpen = '1', storage = new Map(), persistent = new Map(), blockedStorage = false, players = true, failed = false, now = '2026-10-07T08:00:00+02:00', scheduled = '2026-10-09 09:15:00' } = {}) {
  let requests = 0;
  let shown = 0;
  const handlers = {};
  const body = { children: [], append(node) { this.children.push(node); }, replaceChildren(...nodes) { this.children = nodes; } };
  const button = { addEventListener(event, handler) { this[event] = handler; }, setAttribute() {}, removeAttribute() {} };
  const preference = { value: 'login' };
  const apply = { addEventListener(event, handler) { this[event] = handler; } };
  const nextDay = { disabled: true };
  const modal = {
    dataset: { account, login, autoOpen, day: '2026-10-07', endpoint: '/my-tennis/match-reminder' },
    querySelector(selector) { return ({ '[data-reminder-preference]': preference, '[data-reminder-apply]': apply, '[data-reminder-next-day]': nextDay })[selector] || body; }, querySelectorAll() { return []; },
    addEventListener(event, handler) { handlers[event] = handler; },
  };
  const context = {
    document: { getElementById() { return modal; }, querySelectorAll() { return [button]; }, createElement() { return { style: {}, children: [], append(node) { this.children.push(node); } }; } },
    sessionStorage: {
      getItem(key) { if (blockedStorage) throw Error('blocked'); return storage.get(key); },
      setItem(key, value) { if (blockedStorage) throw Error('blocked'); storage.set(key, value); },
      removeItem(key) { storage.delete(key); },
    },
    localStorage: { getItem(key) { return persistent.get(key); }, setItem(key, value) { persistent.set(key, value); }, removeItem(key) { persistent.delete(key); } },
    Date: class extends Date { static now() { return Date.parse(now); } },
    bootstrap: { Modal: class { show() { shown++; } hide() { handlers['hidden.bs.modal'](); } } },
    fetch: async () => {
      requests++;
      if (failed) throw Error('Unavailable');
      return { ok: true, json: async () => ({ day: now.slice(0, 10), players: players ? [{ name: '<Player>', matches: [{ scheduled_at: scheduled, day: 'Friday', date: 'Fri 9 Oct', time: '09:15', label: 'Your match', participants: ['Player', 'Opponent'], event: 'Event', draw: 'Draw', venue: 'Club', court: '1', url: '/fixtures' }] }] : [] }) };
    },
  };
  context.window = context;
  vm.runInNewContext(source, context);
  await new Promise(resolve => setImmediate(resolve));
  return { get requests() { return requests; }, get shown() { return shown; }, handlers, body, storage, persistent, button, preference, apply, nextDay };
}

test('shows upcoming Friday fixture and suppresses it after dismissal within the same login', async () => {
  const first = await run();
  assert.equal(first.shown, 1);
  assert.equal(first.body.children[0].children[0].textContent, '<Player>');
  assert.equal(first.body.children[0].children[1].children[3].textContent, 'Club');
  first.handlers['hidden.bs.modal']();
  const repeat = await run({ storage: first.storage });
  assert.equal(repeat.requests, 0);
  assert.equal(repeat.shown, 0);
});

test('automatic opening requires the one-shot login flag but manual opening remains available', async () => {
  for (const autoOpen of ['0', null, 'true']) {
    const reminder = await run({ autoOpen });
    assert.equal(reminder.requests, 0);
    assert.equal(reminder.shown, 0);
    await reminder.button.click();
    assert.equal(reminder.requests, 1);
    assert.equal(reminder.shown, 1);
  }
});

test('reload and another tab do not auto open before dismissal, including blocked browser storage', async () => {
  for (const blockedStorage of [false, true]) {
    const first = await run({ blockedStorage });
    assert.equal(first.shown, 1);
    const reload = await run({ autoOpen: '0', storage: first.storage, blockedStorage });
    assert.equal(reload.requests, 0);
    assert.equal(reload.shown, 0);
    assert.equal((await run({ autoOpen: '0', blockedStorage })).shown, 0);
  }
});

test('expired snooze does not automatically reopen without a fresh login flag', async () => {
  const reminder = await run();
  reminder.preference.value = 'hour';
  reminder.apply.click();
  const options = { persistent: reminder.persistent, now: '2026-10-07T10:00:00+02:00' };
  assert.equal((await run({ ...options, autoOpen: '0' })).requests, 0);
  assert.equal((await run({ ...options, login: 'fresh-login', autoOpen: '1' })).shown, 1);
});

test('an empty or failed automatic reminder is not retried on the next page', async () => {
  for (const options of [{ players: false }, { failed: true }]) {
    assert.equal((await run(options)).shown, 0);
    assert.equal((await run({ ...options, autoOpen: '0' })).requests, 0);
  }
});

test('a fresh login or different account gets its own reminder dismissal scope', async () => {
  const first = await run();
  first.handlers['hidden.bs.modal']();
  assert.equal((await run({ login: 'next-login', storage: first.storage })).shown, 1);
  assert.equal((await run({ account: '2', storage: first.storage })).shown, 1);
});

test('empty matches do not open a modal and blocked storage does not prevent a reminder', async () => {
  assert.equal((await run({ players: false })).shown, 0);
  assert.equal((await run({ blockedStorage: true })).shown, 1);
});

test('manual reopen fetches fresh matches after dismissal without duplicating cards', async () => {
  const reminder = await run();
  reminder.handlers['hidden.bs.modal']();
  await reminder.button.click();
  assert.equal(reminder.requests, 2);
  assert.equal(reminder.shown, 2);
  assert.equal(reminder.body.children.length, 1);
  const dismissed = await run({ storage: reminder.storage });
  assert.equal(dismissed.requests, 0);
  await dismissed.button.click();
  assert.equal(dismissed.requests, 1);
  assert.equal(dismissed.shown, 1);
});

test('manual opening explains empty matches and fetch failures', async () => {
  const empty = await run({ players: false });
  await empty.button.click();
  assert.equal(empty.shown, 1);
  assert.match(empty.body.children[0].textContent, /No published, unfinished matches/);
  const failed = await run({ failed: true });
  assert.equal(failed.shown, 0);
  await failed.button.click();
  assert.equal(failed.shown, 1);
  assert.match(failed.body.children[0].textContent, /could not be loaded/);
  assert.equal(failed.button.disabled, false);
});

test('manual double click starts only one request', async () => {
  const reminder = await run();
  const pending = reminder.button.click();
  reminder.button.click();
  assert.equal(reminder.requests, 2);
  await pending;
  assert.equal(reminder.shown, 2);
});

test('close for this login remains closed across days but permits a fresh login', async () => {
  const reminder = await run();
  reminder.apply.click();
  assert.equal((await run({storage: reminder.storage, now: '2026-10-08T08:00:00+02:00'})).requests, 0);
  assert.equal((await run({storage: reminder.storage, login: 'new-login'})).shown, 1);
});

for (const [choice, expiry] of [['hour', '2026-10-07T09:00:00+02:00'], ['week', '2026-10-14T08:00:00+02:00'], ['match-day', '2026-10-09T00:00:00+02:00']]) {
  test(`${choice} persists across logins, expires, and remains account scoped`, async () => {
    const reminder = await run();
    reminder.preference.value = choice;
    reminder.apply.click();
    assert.equal(Number(reminder.persistent.get('ct.match-reminder.snooze.1')), Date.parse(expiry));
    const nextLogin = await run({ persistent: reminder.persistent, storage: reminder.storage, login: 'next-login' });
    assert.equal(nextLogin.requests, 0);
    await nextLogin.button.click();
    assert.equal(nextLogin.shown, 1);
    nextLogin.handlers['hidden.bs.modal']();
    assert.equal(Number(reminder.persistent.get('ct.match-reminder.snooze.1')), Date.parse(expiry));
    assert.equal((await run({ persistent: reminder.persistent, account: '2' })).shown, 1);
    assert.equal((await run({ persistent: reminder.persistent, storage: reminder.storage, now: expiry })).shown, 1);
  });
}

test('next match day falls back to today’s next start and disables when no future match exists', async () => {
  const today = await run({ scheduled: '2026-10-07 09:15:00' });
  assert.equal(today.nextDay.disabled, false);
  today.preference.value = 'match-day';
  today.apply.click();
  assert.equal(Number(today.persistent.get('ct.match-reminder.snooze.1')), Date.parse('2026-10-07T09:15:00+02:00'));
  const past = await run({ scheduled: '2026-10-07 07:00:00' });
  assert.equal(past.nextDay.disabled, true);
});

test('next match day uses South African day for UTC timestamps', async () => {
  const reminder = await run({ scheduled: '2026-10-08T22:30:00Z' });
  reminder.preference.value = 'match-day';
  reminder.apply.click();
  assert.equal(Number(reminder.persistent.get('ct.match-reminder.snooze.1')), Date.parse('2026-10-09T00:00:00+02:00'));
});
