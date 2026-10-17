const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const script = readFileSync(resolve(__dirname, '../../resources/views/frontend/event/partials/team-payment-recovery-script.blade.php'), 'utf8').replace(/<\/?script>/g, '');
function fixture(present = true) {
  let loaded;
  let opened = 0;
  let valid = false;
  const form = { checkValidity: () => valid, addEventListener(name, callback) { assert.equal(name, 'submit'); this.submit = callback; } };
  const button = { disabled: false, textContent: 'Restart registration' };
  const elements = { teamPaymentRecoveryModal: {}, teamPaymentRecoveryForm: form, teamPaymentRecoveryRetry: button };
  vm.runInNewContext(script, {
    document: { addEventListener(name, callback) { loaded = callback; }, getElementById: id => present ? elements[id] : null },
    bootstrap: { Modal: { getOrCreateInstance: () => ({ show: () => opened++ }) } },
  });
  loaded();
  return {
    button,
    get opened() { return opened; },
    confirm() { valid = true; },
    submit() { let prevented = false; form.submit({ preventDefault() { prevented = true; } }); return prevented; },
  };
}
test('restart is immediately available with explicit confirmation and duplicate-submit protection', () => {
  const page = fixture();
  assert.equal(page.opened, 1);
  assert.equal(page.button.disabled, false);
  assert.equal(page.submit(), true);
  assert.equal(page.button.disabled, false);
  page.confirm();
  assert.equal(page.submit(), false);
  assert.equal(page.button.disabled, true);
  assert.equal(page.button.textContent, 'Restarting registration…');
  assert.equal(page.submit(), true);
});
test('event pages without recovery context do not open a modal', () => {
  assert.equal(fixture(false).opened, 0);
});
