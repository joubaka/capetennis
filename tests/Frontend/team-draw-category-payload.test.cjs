const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function submit(type, choices, manualChoices, bulkChoices) {
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
    querySelectorAll(selector) {
      if (selector.includes('bulk_draw_types')) return bulkChoices ? bulkChoices.types : [];
      if (selector.includes('bulk_categories')) return bulkChoices ? bulkChoices.categories : [];
      return manualChoices || [];
    },
    addEventListener(name, handler) { if (name === 'submit') submitHandler = handler; }
  };
  const toggle = { checked: false };
  const otherToggle = { checked: false };
  const bulkToggle = { checked: false };
  const document = { getElementById(id) { return id === 'createDrawForm' ? form : (id === 'manualTeamCategories' ? toggle : (id === 'bulkTeamDraws' ? bulkToggle : otherToggle)); } };
  function $(selector) {
    const chain = { on() { return chain; }, off() { return chain; }, toggleClass() { return chain; }, prop() { return chain; },
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
  bulkToggle.checked = !!bulkChoices;
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

test('bulk creates only selected types and retains both A and B mixed groups', () => {
  const payload = submit('1', {}, null, {
    types: [{ value: '3', dataset: { code: 'mixed_doubles', name: 'Mixed doubles' } }],
    categories: [
      { dataset: { gender: 'boys', age: 'u/13', pivotIds: '[11,12]' } },
      { dataset: { gender: 'girls', age: 'u/13', pivotIds: '[13]' } },
      { dataset: { gender: 'boys', age: 'u/13 b division', pivotIds: '[14]' } },
      { dataset: { gender: 'girls', age: 'u/13 b division', pivotIds: '[15]' } }
    ]
  });
  assert.equal(payload.draws.length, 2);
  assert.deepEqual(payload.draws[0].category_ids, [11, 12, 13]);
  assert.deepEqual(payload.draws[1].category_ids, [14, 15]);
  assert.ok(payload.draws.every(draw => draw.draw_type_id === '3'));
  assert.ok(payload.batch_key);
});

test('bulk combinations create each chosen standard type per category and one mixed age draw', () => {
  const payload = submit('1', {}, null, {
    types: [{ value: '1', dataset: { code: 'singles', name: 'Team - Singles' } }, { value: '3', dataset: { code: 'mixed_doubles', name: 'Mixed doubles' } }],
    categories: [
      { dataset: { gender: 'boys', age: 'u/13', name: 'u/13 Boys', pivotIds: '[11]' } },
      { dataset: { gender: 'girls', age: 'u/13', name: 'u/13 Girls', pivotIds: '[12]' } }
    ]
  });
  assert.equal(payload.draws.length, 3);
  assert.equal(payload.draws[0].drawName, 'u/13 Boys – Singles');
  assert.deepEqual(payload.draws[2].category_ids, [11, 12]);
});

test('bulk incomplete mixed age is sent for visible backend warnings instead of silently dropped', () => {
  const payload = submit('1', {}, null, {
    types: [{ value: '3', dataset: { code: 'mixed_doubles' } }],
    categories: [{ dataset: { gender: 'boys', age: 'u/13', pivotIds: '[11]' } }]
  });
  assert.equal(payload.draws.length, 1);
  assert.deepEqual(payload.draws[0].category_ids, [11]);
});
