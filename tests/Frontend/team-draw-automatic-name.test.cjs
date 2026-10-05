const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function dialog(initialMode = 'team') {
  const state = { mode: '', type: null, choices: {}, manual: [] };
  const name = { id: 'drawName', value: '', required: false };
  const bulk = { checked: false };
  const manual = { checked: false };
  const handlers = [];
  const formHandlers = {};
  const classes = {};
  const form = {
    querySelector(selector) {
      if (selector.includes('button[type')) return {};
      if (selector.includes('draw_mode')) return { value: state.mode };
      if (selector.includes('draw_type_id')) return state.type;
      if (selector.startsWith('label[')) return { textContent: 'Team - Singles' };
      const match = selector.match(/name="([^"]+)"/);
      return match ? state.choices[match[1]] || null : null;
    },
    querySelectorAll() { return state.manual; },
    addEventListener(event, handler) { formHandlers[event] = handler; },
    reset() { state.mode = ''; state.type = null; state.choices = {}; state.manual = []; }
  };
  const document = { getElementById(id) { return { createDrawForm: form, drawName: name, bulkTeamDraws: bulk, manualTeamCategories: manual }[id] || {}; } };
  function $(selector) {
    const chain = {
      on(event, target, handler) { handlers.push({ event, selector: typeof target === 'string' ? target : selector, handler: handler || target }); return chain; },
      off() { return chain; },
      toggleClass(cls, value) { classes[selector] = value; return chain; },
      prop(key, value) {
        if (selector === '#bulkTeamDraws') bulk[key] = value;
        if (selector === '#manualTeamCategories') manual[key] = value;
        if (key === 'checked' && value === false && typeof selector === 'string' && selector.includes('CategoryChoices input')) state.choices = {};
        return chain;
      },
      css() { return chain; }, addClass() { return chain; }, removeClass() { return chain; }, empty() { return chain; },
      val(value) { if (selector === '#drawName') { if (value !== undefined) name.value = value; return name.value; } return ''; }, attr() { return 'csrf'; }
    };
    return chain;
  }
  const window = { HeadOffice: { individualDrawTypeId: 88 }, jQuery: $, setTimeout(callback) { callback(); } };
  vm.runInNewContext(fs.readFileSync('public/js/team-draw-mode.js', 'utf8'), { window, document });
  function change(field) {
    handlers.filter(h => h.event === 'change' && (h.selector.includes('name="' + field + '"') || h.selector.includes('#' + field)))
      .forEach(h => h.handler.call(field === 'draw_mode' ? { value: state.mode } : {}));
  }
  state.mode = initialMode; change('draw_mode');
  return { state, name, bulk, manual, classes, change, close() { handlers.find(h => h.event === 'hidden.bs.modal').handler(); }, customize(value) { name.value = value; formHandlers.input({ target: name }); } };
}

test('single names fill from selected type and category and update automatically', () => {
  const ui = dialog();
  assert.equal(ui.name.value, '');
  assert.equal(ui.name.required, false);
  ui.state.type = { id: 'singles', value: '19', dataset: { code: 'singles' } };
  ui.change('draw_type_id');
  assert.equal(ui.name.value, '');
  ui.state.choices.category_choice = { dataset: { age: 'u/13 Girls' } };
  ui.change('category_choice');
  assert.equal(ui.name.value, 'u/13 Girls – Singles');
  ui.state.type.dataset.code = 'reverse_singles'; ui.change('draw_type_id');
  assert.equal(ui.name.value, 'u/13 Girls – Reverse singles');
});

test('mixed names wait for both same-age sides and clear a stale generated name', () => {
  const ui = dialog();
  ui.state.type = { id: 'mixed', value: '19', dataset: { code: 'mixed_doubles' } };
  ui.state.choices.category_choice_boys = { dataset: { age: 'u/13' } };
  ui.change('category_choice_boys');
  assert.equal(ui.name.value, '');
  ui.state.choices.category_choice_girls = { dataset: { age: 'u/13' } };
  ui.change('category_choice_girls');
  assert.equal(ui.name.value, 'u/13 – Mixed doubles');
  ui.state.choices.category_choice_girls.dataset.age = 'u/12'; ui.change('category_choice_girls');
  assert.equal(ui.name.value, '');
});

test('manual alias category names deduplicate and use selected type', () => {
  const ui = dialog();
  ui.state.type = { id: 'double', value: '19', dataset: { code: 'doubles' } };
  ui.manual.checked = true;
  ui.state.manual = [{ dataset: { name: 'u/13 Boys', age: 'u/13', gender: 'boys' } }, { dataset: { name: 'u/13 Boys', age: 'u/13', gender: 'boys' } }];
  ui.change('manual_category_ids[]');
  assert.equal(ui.name.value, 'u/13 Boys – Doubles');
  ui.state.manual.push({ dataset: { name: 'u/13 Girls', age: 'u/13', gender: 'girls' } });
  ui.state.type.dataset.code = 'mixed_doubles'; ui.change('manual_category_ids[]');
  assert.equal(ui.name.value, 'u/13 – Mixed doubles');
});

test('individual names appear after category choice and custom names survive category and mode changes', () => {
  const ui = dialog('');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.state.mode = 'individual'; ui.change('draw_mode');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.state.choices.category_choice = { dataset: { age: 'u/13 Boys' } }; ui.change('category_choice');
  assert.equal(ui.classes['#singleDrawNameGroup'], false);
  assert.equal(ui.name.value, 'u/13 Boys – Singles');
  ui.customize('Schools championship');
  ui.state.choices.category_choice.dataset.age = 'u/12 Boys'; ui.change('category_choice');
  assert.equal(ui.name.value, 'Schools championship');
  ui.state.mode = 'team'; ui.change('draw_mode');
  ui.state.type = { id: 'single', value: '1', dataset: { code: 'singles' } };
  ui.state.choices.category_choice = { dataset: { age: 'u/12 Boys' } }; ui.change('category_choice');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  assert.equal(ui.name.value, 'u/12 Boys – Singles');
  ui.state.mode = 'individual'; ui.change('draw_mode');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.state.choices.category_choice = { dataset: { age: 'u/13 Girls' } }; ui.change('category_choice');
  assert.equal(ui.classes['#singleDrawNameGroup'], false);
  assert.equal(ui.name.value, 'Schools championship');
  ui.close();
  assert.equal(ui.state.mode, '');
  assert.equal(ui.name.value, '');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.state.mode = 'individual'; ui.change('draw_mode');
  ui.state.choices.category_choice = { dataset: { age: 'u/12 Boys' } }; ui.change('category_choice');
  assert.equal(ui.name.value, 'u/12 Boys – Singles');
});

test('bulk hides unused name field, explains automatic naming and requires no name', () => {
  const ui = dialog();
  ui.bulk.checked = true; ui.change('bulkTeamDraws');
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  assert.equal(ui.classes['#bulkDrawNameHelp'], false);
  assert.equal(ui.name.required, false);
});
