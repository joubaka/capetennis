const test = require('node:test');
const assert = require('node:assert/strict');
const bind = require('../../public/assets/js/my-tennis-player-switch.js');

function harness(loader = true) {
  let submitted = 0, shown = 0, closed = 0, callback, fail = false;
  const events = {}, attrs = {}, listeners = {};
  const form = {getAttribute: name => attrs[name] ?? null, setAttribute(name, value) {attrs[name] = value;}, removeAttribute(name) {delete attrs[name];},
    addEventListener(name, fn) {listeners[name] = fn;}, removeEventListener(name) {delete listeners[name];},
    submit() {if (fail) throw new Error('Navigation failed'); submitted++;}};
  const select = {form, value: '2694', disabled: false};
  const window = {setTimeout(fn, delay) {assert.equal(delay, 30000); callback = fn; return 1;}, clearTimeout() {callback = null;},
    addEventListener(name, fn) {events[name] = fn;}, removeEventListener(name) {delete events[name];}};
  if (loader) window.CapeLoading = {show(options) {shown++; assert.equal(options.title, 'Changing player'); return {close() {closed++;}};}};
  const controller = bind(window, select);
  return {window, select, attrs, events, listeners, controller, submitted: () => submitted, shown: () => shown, closed: () => closed,
    expire() {callback();}, fail() {fail = true;}};
}

test('native/Select2 onchange shows one shared loader and submits the enabled selected value', () => {
  const h = harness(); h.select.onchange();
  assert.equal(h.shown(), 1); assert.equal(h.submitted(), 1);
  assert.equal(h.select.disabled, false); assert.equal(h.select.value, '2694'); assert.equal(h.attrs['aria-busy'], 'true');
  h.listeners.submit(); assert.equal(h.shown(), 1);
});
test('back-forward restoration and timeout close the loader and restore form state', () => {
  const h = harness(); h.select.onchange(); h.events.pageshow();
  assert.equal(h.closed(), 1); assert.equal(h.attrs['aria-busy'], undefined);
  h.select.onchange(); h.expire(); assert.equal(h.closed(), 2); assert.equal(h.attrs['aria-busy'], undefined);
});
test('failed navigation closes immediately and missing loading helper never blocks changing players', () => {
  const h = harness(); h.fail(); assert.throws(() => h.select.onchange(), /Navigation failed/);
  assert.equal(h.closed(), 1); assert.equal(h.attrs['aria-busy'], undefined);
  const fallback = harness(false); fallback.select.onchange(); assert.equal(fallback.submitted(), 1); fallback.expire();
});
test('binding is idempotent and disposal restores the previous handler', () => {
  const h = harness(); assert.equal(bind(h.window, h.select), h.controller);
  h.controller.destroy(); assert.equal(h.select.onchange, undefined); assert.equal(h.events.pageshow, undefined);
  assert.equal(bind(h.window, null), null);
});
