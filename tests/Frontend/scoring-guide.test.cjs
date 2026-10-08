const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/scoring-guide.js', 'utf8');

function run({ stored = null, automatic = '1', failStorage = false } = {}) {
  const calls = { shown: 0, hidden: 0, stored: null };
  let snooze;
  const guide = {
    dataset: { user: '17', day: '2026-10-08', automatic },
    querySelector: () => ({ addEventListener: (_, callback) => { snooze = callback; } }),
  };
  const bootstrap = { Modal: { getOrCreateInstance: () => ({
    show: () => calls.shown++, hide: () => calls.hidden++,
  }) } };
  vm.runInNewContext(source, {
    document: { readyState: 'complete', getElementById: () => guide },
    window: { bootstrap }, bootstrap,
    localStorage: {
      getItem: () => { if (failStorage) throw Error('blocked'); return stored; },
      setItem: (key, value) => { if (failStorage) throw Error('blocked'); calls.stored = [key, value]; },
    },
  });
  return { calls, snooze };
}

test('opens once when eligible; session suppression does not auto-open', () => {
  assert.equal(run().calls.shown, 1);
  assert.equal(run({ automatic: '0' }).calls.shown, 0);
});
test('every new login opens even if this device previously snoozed today', () => {
  assert.equal(run({ stored: '2026-10-08' }).calls.shown, 1);
  assert.equal(run({ stored: '2026-10-07' }).calls.shown, 1);
});
test('opening does not depend on device storage', () => {
  const { calls } = run({ failStorage: true });
  assert.equal(calls.shown, 1);
});
