const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function review(search, authorized = true) {
  const tools = {open: false};
  let shown = 0, focused = 0;
  const toggle = {closest: () => tools, focus() {focused++;}};
  const document = {
    querySelector: selector => selector === '[data-publication-review-toggle]' && authorized ? toggle : null,
    querySelectorAll: () => [], getElementById: () => null
  };
  vm.runInNewContext(fs.readFileSync('public/js/draw-workspace-navigation.js', 'utf8'), {
    document, location: {search}, URLSearchParams,
    window: {bootstrap: {Dropdown: {getOrCreateInstance: () => ({show() {shown++;}})}}}
  });
  return {open: tools.open, shown, focused};
}

test('explicit publication review reveals authorized existing controls without activating publication', () => {
  assert.deepEqual(review('?review=publication'), {open: true, shown: 1, focused: 1});
  assert.deepEqual(review(''), {open: false, shown: 0, focused: 0});
  assert.deepEqual(review('?review=publication', false), {open: false, shown: 0, focused: 0});
});
