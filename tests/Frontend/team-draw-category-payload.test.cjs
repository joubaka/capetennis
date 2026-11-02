const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function submit(type, choices, manualChoices, bulkChoices, chooseOpening, playerCount) {
  let submitHandler;
  let inputHandler;
  let posted;
  let previewHandler;
  const selects = [];
  function element(tag) { return { tag, children: [], classList: { remove() {} }, setAttribute() {}, replaceChildren() { this.children = []; }, appendChild(child) { this.children.push(child); }, addEventListener(name, handler) { this[name] = handler; } }; }
  const previewPanel = element('div');
  const button = {};
  const nameInput = { id: 'drawName', value: '' };
  Object.values(choices).forEach(choice => { choice.dataset.age = choice.dataset.age || 'u/10 Boys'; });
  if (manualChoices) manualChoices.forEach(choice => { choice.dataset.name = choice.dataset.name || 'u/10 ' + (choice.dataset.gender || 'Boys'); choice.dataset.age = 'u/10'; });
  if (type === '3') Object.values(choices).forEach(choice => { choice.dataset.age = 'u/10'; });
  const form = { setAttribute() {},
    querySelector(selector) {
      if (selector === 'button[type="submit"]') return button;
      if (selector.includes('draw_mode')) return { value: 'team' };
      if (selector.includes('draw_type_id')) return { value: type, dataset: {} };
      if (selector.startsWith('label[')) return { textContent: type === '3' ? 'Mixed doubles' : 'Singles' };
      return choices[selector.match(/name="([^"]+)"/)?.[1]];
    },
    querySelectorAll(selector) {
      if (selector.includes('bulk_draw_types')) return bulkChoices ? bulkChoices.types : [];
      if (selector.includes('bulk_categories')) return bulkChoices ? bulkChoices.categories : [];
      return manualChoices || [];
    },
    addEventListener(name, handler) { if (name === 'submit') submitHandler = handler; if (name === 'input') inputHandler = handler; }
  };
  const toggle = { checked: false };
  const otherToggle = { checked: false };
  const bulkToggle = { checked: false };
  const document = { querySelector() { return null; }, querySelectorAll() { return []; }, createTextNode(text) { return { textContent: text }; }, createElement(tag) { const node = element(tag); if (tag === 'select') selects.push(node); return node; }, getElementById(id) { return id === 'players_per_team' ? { value: playerCount || '' } : id === 'teamDrawPreview' ? previewPanel : id === 'createDrawForm' ? form : (id === 'drawName' ? nameInput : (id === 'manualTeamCategories' ? toggle : (id === 'bulkTeamDraws' ? bulkToggle : otherToggle))); } };
  function $(selector) {
    const chain = { on(name, handler) { if (selector === '#previewTeamDrawButton') previewHandler = handler; return chain; }, trigger() { previewHandler.call({}); return chain; }, off() { return chain; }, toggleClass() { return chain; }, text() { return chain; }, css() { return chain; }, prop() { return chain; },
      empty() { return chain; }, addClass() { return chain; }, removeClass() { return chain; },
      val(value) { if (selector === '#drawName') { if (value !== undefined) nameInput.value = value; return nameInput.value; } return ''; }, attr() { return 'csrf'; } };
    return chain;
  }
  $.post = (url, payload) => {
    posted = payload;
    const chain = { done(handler) {
      if (url === '/preview') {
        const readiness = item => ({ opening_round: item.opening_round, opening_round_choices: [{ value: 1, label: 'East vs West; North vs South' }, { value: 2, label: 'East vs North; West vs South' }], rounds: [] });
        handler({ readiness: readiness(payload.draws ? payload.draws[0] : payload), draws: payload.draws?.map(item => ({ drawName: item.drawName, readiness: readiness(item) })) });
      }
      return chain;
    }, fail() { return chain; }, always(handler) { handler(); return chain; } };
    return chain;
  };
  const window = { setInterval() { return 1; }, clearInterval() {}, crypto:{randomUUID:()=> 'synthetic-batch'}, jQuery: $, HeadOffice: { createUrl: '/draw', previewUrl: '/preview' } };
  vm.runInNewContext(fs.readFileSync('public/js/team-draw-mode.js', 'utf8'), { window, document });
  toggle.checked = !!manualChoices;
  bulkToggle.checked = !!bulkChoices;
  if (chooseOpening) {
    previewHandler.call({});
    assert.match(selects[0].children[1].textContent, /East vs North/);
    selects[0].value = '2';
    inputHandler({ target: { hasAttribute: name => name === 'data-opening-round' } });
    selects[0].change({ stopPropagation() {} });
    assert.equal(selects.at(-(bulkChoices ? bulkChoices.categories.length : 1)).children[1].selected, true);
    previewHandler.call({});
    assert.equal(selects.at(-(bulkChoices ? bulkChoices.categories.length : 1)).children[1].selected, true);
  }
  submitHandler({ preventDefault() {}, stopImmediatePropagation() {} });
  return posted ? JSON.parse(JSON.stringify(posted)) : undefined;
}

test('changing opening pairings re-previews, retains selection and submits chosen round', () => {
  const payload = submit('1', { category_choice: { dataset: { pivotIds: '[11]' } } }, null, null, true);
  assert.equal(payload.opening_round, 2);
});

test('batch retains independent opening choices and applies players per source team to every draw', () => {
  const payload = submit('1', {}, null, {
    types: [{ value: '1', dataset: { code: 'singles', name: 'Singles' } }],
    categories: [
      { dataset: { age: 'u/13', name: 'u/13 Boys', pivotIds: '[11]' } },
      { dataset: { age: 'u/13', name: 'u/13 Girls', pivotIds: '[12]' } }
    ]
  }, true, '6');
  assert.deepEqual(payload.draws.map(item => item.players_per_team), ['6', '6']);
  assert.deepEqual(payload.draws.map(item => item.opening_round), [2, 1]);
});

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
