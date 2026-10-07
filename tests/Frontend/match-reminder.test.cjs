const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('public/js/match-reminder.js', 'utf8');

async function run({ login = 'first-login', account = '1', storage = new Map(), blockedStorage = false, players = true } = {}) {
  let requests = 0;
  let shown = 0;
  const handlers = {};
  const body = { children: [], append(node) { this.children.push(node); } };
  const modal = {
    dataset: { account, login, day: '2026-10-07', endpoint: '/my-tennis/match-reminder' },
    querySelector() { return body; }, querySelectorAll() { return []; },
    addEventListener(event, handler) { handlers[event] = handler; },
  };
  const context = {
    document: { getElementById() { return modal; }, createElement() { return { style: {}, children: [], append(node) { this.children.push(node); } }; } },
    sessionStorage: {
      getItem(key) { if (blockedStorage) throw Error('blocked'); return storage.get(key); },
      setItem(key, value) { if (blockedStorage) throw Error('blocked'); storage.set(key, value); },
    },
    bootstrap: { Modal: class { show() { shown++; } } },
    fetch: async () => {
      requests++;
      return { ok: true, json: async () => ({ day: '2026-10-07', players: players ? [{ name: '<Player>', matches: [{ day: 'Friday', date: 'Fri 9 Oct', time: '09:15', label: 'Your match', participants: ['Player', 'Opponent'], event: 'Event', draw: 'Draw', venue: 'Club', court: '1', url: '/fixtures' }] }] : [] }) };
    },
  };
  context.window = context;
  vm.runInNewContext(source, context);
  await new Promise(resolve => setImmediate(resolve));
  return { requests, shown, handlers, body, storage };
}

test('shows upcoming Friday fixture and suppresses it after dismissal within the same login', async () => {
  const first = await run();
  assert.equal(first.shown, 1);
  assert.equal(first.body.children[0].children[0].textContent, '<Player>');
  first.handlers['hidden.bs.modal']();
  const repeat = await run({ storage: first.storage });
  assert.equal(repeat.requests, 0);
  assert.equal(repeat.shown, 0);
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
