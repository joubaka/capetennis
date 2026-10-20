const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/manual-email.js', 'utf8');

function harness() {
  const listeners = new Map();
  const compose = { hidden: false, isConnected: true, hasAttribute: () => false };
  const removed = [];
  const modal = {
    addEventListener(type, listener) { if (!listeners.has(type)) listeners.set(type, []); listeners.get(type).push(listener); },
    querySelectorAll() { return removed; },
    querySelector() { return null; },
  };
  let response;
  let parses = 0;
  const window = {};
  const documentListeners = new Map();
  const document = { addEventListener: (type, fn) => documentListeners.set(type, fn) };
  const context = vm.createContext({
    window, document, AbortController, WeakMap, Set,
    FormData: class { set() {} },
    fetch: () => new Promise(resolve => { response = resolve; }),
    DOMParser: class { parseFromString() { parses++; throw new Error('Unexpected stale render'); } },
    bootstrap: { Modal: { getOrCreateInstance: () => ({ show() {} }) } },
  });
  vm.runInContext(source.replace(/\}\)\(\);\s*$/, 'window.lifecycleTest = { lifecycle, requestFor, prepare };})();'), context);
  return {
    api: window.lifecycleTest, compose, modal, removed, documentListeners,
    emit(type) { (listeners.get(type) || []).forEach(fn => fn()); },
    resolve(value) { response(value); },
    get parses() { return parses; },
    get listeners() { return listeners; },
  };
}

test('lazy composer review close restores the original and removes all transient panels', () => {
  const h = harness();
  h.api.lifecycle(h.modal, h.compose);
  h.compose.hidden = true;
  for (let i = 0; i < 3; i++) h.removed.push({ remove() { this.removed = true; } });
  h.emit('hide.bs.modal'); h.emit('hidden.bs.modal');
  assert.equal(h.compose.hidden, false);
  assert.ok(h.removed.every(node => node.removed));
});

test('reopen and repeated previews install only one cleanup and restore the new composer', () => {
  const h = harness();
  h.api.lifecycle(h.modal, h.compose); h.api.lifecycle(h.modal, h.compose);
  assert.equal(h.listeners.get('hidden.bs.modal').length, 1);
  h.emit('hide.bs.modal'); h.emit('hidden.bs.modal');
  const next = { ...h.compose, hidden: true };
  h.api.lifecycle(h.modal, next);
  h.emit('hide.bs.modal'); h.emit('hidden.bs.modal');
  assert.equal(next.hidden, false);
});

test('cancel invalidates the request immediately before the fade transition finishes', () => {
  const h = harness();
  const request = h.api.requestFor(h.modal, h.compose);
  assert.equal(request.current(), true);
  h.emit('hide.bs.modal');
  assert.equal(request.current(), false);
  assert.equal(request.signal.aborted, true);
});

test('a newer preview supersedes an earlier request in the same modal', () => {
  const h = harness();
  const old = h.api.requestFor(h.modal, h.compose);
  const current = h.api.requestFor(h.modal, h.compose);
  assert.equal(old.current(), false);
  assert.equal(current.current(), true);
});

test('cancel while fetching cannot parse, reopen or display a stale preview', async () => {
  const h = harness();
  const button = { disabled: false };
  const form = { action: '/local/preview', querySelector: () => button };
  const pending = h.api.prepare(form, h.modal, h.compose);
  h.emit('hide.bs.modal'); h.emit('hidden.bs.modal');
  h.resolve({ ok: true, text: async () => '<stale-review>' });
  await pending;
  assert.equal(h.parses, 0);
  assert.equal(button.disabled, false);
  assert.equal(h.compose.hidden, false);
});

test('cancel while reading response body suppresses a late response', async () => {
  const h = harness();
  let finishBody;
  const button = { disabled: false };
  const pending = h.api.prepare({ action: '/local/preview', querySelector: () => button }, h.modal, h.compose);
  h.resolve({ ok: true, text: () => new Promise(resolve => { finishBody = resolve; }) });
  await new Promise(resolve => setImmediate(resolve));
  h.emit('hide.bs.modal');
  finishBody('<stale-review>');
  await pending;
  assert.equal(h.parses, 0);
  assert.equal(button.disabled, false);
});
