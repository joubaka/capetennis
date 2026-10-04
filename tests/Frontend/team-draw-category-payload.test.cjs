const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function submit(type, choices, manualChoices) {
  let submitHandler;
  let posted;
  const button = {};
  const form = {
    querySelector(selector) {
      if (selector === 'button[type="submit"]') return button;
      if (selector.includes('draw_mode')) return { value: 'team' };
      if (selector.includes('draw_type_id')) return { value: type, dataset: {} };
      return choices[selector.match(/name="([^"]+)"/)[1]];
    },
    querySelectorAll() { return manualChoices || []; },
    addEventListener(name, handler) { if (name === 'submit') submitHandler = handler; }
  };
  const toggle = { checked: false };
  const document = { getElementById(id) { return id === 'createDrawForm' ? form : toggle; } };
  function $(selector) {
    const chain = { on() { return chain; }, toggleClass() { return chain; }, prop() { return chain; },
      empty() { return chain; }, addClass() { return chain; }, removeClass() { return chain; },
      val() { return selector === '#drawName' ? 'u/10 Boys' : ''; }, attr() { return 'csrf'; } };
    return chain;
  }
  $.post = (url, payload) => {
    posted = payload;
    const chain = { done() { return chain; }, fail() { return chain; }, always() { return chain; } };
    return chain;
  };
  const window = { jQuery: $, HeadOffice: { createUrl: '/draw' } };
  vm.runInNewContext(fs.readFileSync('public/js/team-draw-mode.js', 'utf8'), { window, document });
  toggle.checked = !!manualChoices;
  submitHandler({ preventDefault() {}, stopImmediatePropagation() {} });
  return posted ? JSON.parse(JSON.stringify(posted)) : undefined;
}

test('team preview/create payload keeps every alias category ID', () => {
  const payload = submit('1', { category_choice: { dataset: { pivotId: '11', pivotIds: '[11,12,13]' } } });
  assert.deepEqual(payload.category_ids, ['11', '12', '13']);
});

test('mixed payload includes every boys and girls alias, without duplicates', () => {
  const payload = submit('3', {
    category_choice_boys: { dataset: { pivotIds: '[11,12]' } },
    category_choice_girls: { dataset: { pivotIds: '[13,14,13]' } }
  });
  assert.deepEqual(payload.category_ids, ['11', '12', '13', '14']);
});

test('manual categories override automatic alias choice for preview/create', () => {
  const payload = submit('1', {}, [{ value: '21', dataset: {} }, { value: '31', dataset: {} }]);
  assert.deepEqual(payload.category_ids, ['21', '31']);
});

test('manual mixed selection preserves boys and girls categories', () => {
  const payload = submit('3', {}, [
    { value: '21', dataset: { gender: 'boys' } },
    { value: '31', dataset: { gender: 'girls' } }
  ]);
  assert.deepEqual(payload.category_ids, ['21', '31']);
});

test('manual empty and incomplete mixed selections cannot submit', () => {
  assert.equal(submit('1', {}, []), undefined);
  assert.equal(submit('3', {}, [{ value: '21', dataset: { gender: 'boys' } }]), undefined);
});
