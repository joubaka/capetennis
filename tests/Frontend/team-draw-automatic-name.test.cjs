const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function dialog(initialMode = 'team') {
  const state = { mode: '', type: null, choices: {}, manual: [], bulkTypes: [], bulkCategories: [] };
  const name = { id: 'drawName', value: '', required: false };
  const bulk = { checked: false };
  const manual = { checked: false };
  const individualManual = { checked: false };
  const handlers = [];
  const formHandlers = {};
  const classes = {};
  const values = {};
  const attributes = {};
  const requests = [];
  const timers = new Map();
  const navigation = [];
  const errors = [];
  const submitButton = { disabled: false, textContent: 'Create Draw' };
  const previewButton = { disabled: false };
  let timerId = 0;
  const form = {
    querySelector(selector) {
      if (selector.includes('button[type')) return submitButton;
      if (selector.includes('draw_mode')) return { value: state.mode };
      if (selector.includes('draw_type_id')) return state.type;
      if (selector.startsWith('label[')) return { textContent: 'Team - Singles' };
      const match = selector.match(/name="([^"]+)"/);
      return match ? state.choices[match[1]] || null : null;
    },
    querySelectorAll(selector) {
      if (selector === 'input, select, textarea, button') return [submitButton, previewButton, name, bulk, manual, individualManual, ...Object.values(state.choices)];
      if (selector.includes('bulk_draw_types[]')) return state.bulkTypes;
      if (selector.includes('bulk_categories[]')) return state.bulkCategories;
      return state.manual;
    },
    setAttribute(key, value) { attributes[key] = value; },
    addEventListener(event, handler) { formHandlers[event] = handler; },
    reset() { state.mode = ''; state.type = null; state.choices = {}; state.manual = []; }
  };
  const document = { getElementById(id) { return { createDrawForm: form, drawName: name, bulkTeamDraws: bulk, manualTeamCategories: manual, manualIndividualCategories: individualManual, previewTeamDrawButton: previewButton }[id] || {}; } };
  function $(selector) {
    const chain = {
      on(event, target, handler) { handlers.push({ event, selector: typeof target === 'string' ? target : selector, handler: handler || target }); return chain; },
      off() { return chain; },
      toggleClass(cls, value) { classes[selector] = value; return chain; },
      prop(key, value) {
        if (selector === '#bulkTeamDraws') bulk[key] = value;
        if (selector === '#manualTeamCategories') manual[key] = value;
        if (selector === '#manualIndividualCategories') individualManual[key] = value;
        if (key === 'checked' && value === false && typeof selector === 'string' && selector.includes('CategoryChoices input')) state.choices = {};
        return chain;
      },
      text(value) { values[selector] = value; return chain; }, css(key, value) { values[selector + ':' + key] = value; return chain; }, addClass() { classes[selector] = true; return chain; }, removeClass() { classes[selector] = false; return chain; }, empty() { return chain; },
      val(value) { if (selector === '#drawName') { if (value !== undefined) name.value = value; return name.value; } return ''; }, attr(key, value) { if (value !== undefined) { attributes[selector + ':' + key] = value; return chain; } return 'csrf'; }
    };
    return chain;
  }
  $.post = function (url, payload) {
    const handlers = {};
    const request = { url, payload, resolve(response) { if (handlers.done) handlers.done(response); if (handlers.always) handlers.always(); },
      reject(xhr) { if (handlers.fail) handlers.fail(xhr); if (handlers.always) handlers.always(); } };
    requests.push(request);
    const chain = { done(handler) { handlers.done = handler; return chain; }, fail(handler) { handlers.fail = handler; return chain; }, always(handler) { handlers.always = handler; return chain; } };
    return chain;
  };
  const window = { HeadOffice: { individualDrawTypeId: 88, createUrl: '/team', individualCreateUrl: '/individual' }, jQuery: $,
    location: { reload() { navigation.push('reload'); }, assign(url) { navigation.push(url); } },
    toastr: { error(message) { errors.push(message); } },
    setTimeout(callback) { callback(); }, setInterval(callback) { timers.set(++timerId, callback); return timerId; }, clearInterval(id) { timers.delete(id); } };
  vm.runInNewContext(fs.readFileSync('public/js/team-draw-mode.js', 'utf8'), { window, document });
  function change(field) {
    handlers.filter(h => h.event === 'change' && (h.selector.includes('name="' + field + '"') || h.selector.includes('#' + field)))
      .forEach(h => h.handler.call(field === 'draw_mode' ? { value: state.mode } : {}));
  }
  state.mode = initialMode; change('draw_mode');
  return { state, name, bulk, manual, individualManual, classes, values, attributes, requests, timers, navigation, errors, submitButton, change,
    tick() { [...timers.values()].forEach(callback => callback()); },
    submit() { formHandlers.submit({ preventDefault() {}, stopImmediatePropagation() {} }); },
    hide() { let prevented = false; handlers.find(h => h.event === 'hide.bs.modal').handler({ preventDefault() { prevented = true; } }); return prevented; }, close() { handlers.find(h => h.event === 'hidden.bs.modal').handler(); }, customize(value) { name.value = value; formHandlers.input({ target: name }); } };
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


test('individual manual category mode clears hidden selection and preserves a custom name', () => {
  const ui = dialog('individual');
  ui.state.choices.category_choice = { value: '201', dataset: { age: 'u/13 Boys', pivotId: '201' } };
  ui.change('category_choice');
  assert.equal(ui.name.value, 'u/13 Boys – Singles');
  ui.customize('Schools final');
  ui.individualManual.checked = true; ui.change('manualIndividualCategories');
  assert.equal(ui.classes['#individualCategoryChoices'], true);
  assert.equal(ui.classes['#manualIndividualCategoryChoices'], false);
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.state.choices.category_choice = { value: '204', dataset: { age: 'u/13 Boys A division', pivotId: '204' } };
  ui.change('category_choice');
  assert.equal(ui.name.value, 'Schools final');
  ui.individualManual.checked = false; ui.change('manualIndividualCategories');
  assert.equal(ui.classes['#individualCategoryChoices'], false);
  assert.equal(ui.classes['#manualIndividualCategoryChoices'], true);
  assert.equal(ui.classes['#singleDrawNameGroup'], true);
  ui.close();
  assert.equal(ui.individualManual.checked, false);
});


test('individual creation shows estimated progress, prevents duplicate requests and completes only on confirmation', () => {
  const ui = dialog('individual');
  ui.state.choices.category_choice = { value: '201', disabled: false, dataset: { age: 'u/13 Boys', pivotId: '201' } };
  ui.change('category_choice');
  ui.submit();
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.requests[0].url, '/individual');
  assert.equal(ui.values['#drawCreationPercent'], '0%');
  assert.equal(ui.attributes['aria-busy'], 'true');
  assert.equal(ui.submitButton.disabled, true);
  assert.equal(ui.hide(), true);
  ui.close(); // Even an unexpected hidden event must not reset an in-flight request.
  assert.equal(ui.state.mode, 'individual');
  ui.submit();
  assert.equal(ui.requests.length, 1);
  for (let i = 0; i < 100; i++) ui.tick();
  assert.equal(ui.values['#drawCreationPercent'], '95%');
  assert.equal(ui.attributes['#drawCreationBar:aria-valuenow'], 95);
  assert.equal(ui.navigation.length, 0);
  ui.requests[0].resolve({ success: true, setup_url: '/draw/setup' });
  assert.equal(ui.values['#drawCreationPercent'], '100%');
  assert.equal(ui.timers.size, 0);
  assert.equal(ui.submitButton.disabled, true);
  assert.deepEqual(ui.navigation, ['/draw/setup']);
});

test('team creation errors stop progress, restore controls and permit a retry with the same creation key', () => {
  const ui = dialog('team');
  ui.state.type = { id: 'singles', value: '19', dataset: { code: 'singles' } };
  ui.state.choices.category_choice = { value: '201', disabled: false, dataset: { age: 'u/13 Boys', pivotId: '201' } };
  ui.change('category_choice');
  ui.submit(); ui.tick();
  assert.equal(ui.requests.length, 1);
  assert.notEqual(ui.values['#drawCreationPercent'], '0%');
  ui.requests[0].reject({ responseJSON: { errors: { category_ids: ['Select two teams.'] } } });
  assert.equal(ui.values['#drawCreationPercent'], '0%');
  assert.equal(ui.values['#drawCreationError'], 'Select two teams.');
  assert.equal(ui.timers.size, 0);
  assert.equal(ui.submitButton.disabled, false);
  assert.equal(ui.state.choices.category_choice.disabled, false);
  assert.equal(ui.hide(), false);
  assert.deepEqual(ui.errors, ['Select two teams.']);
  ui.submit();
  assert.equal(ui.requests.length, 2);
  assert.equal(ui.requests[0].payload.batch_key, ui.requests[1].payload.batch_key);
  ui.requests[1].resolve({ success: true });
  assert.equal(ui.values['#drawCreationPercent'], '100%');
  assert.deepEqual(ui.navigation, ['reload']);
});

test('unconfirmed creation response never displays completed progress', () => {
  const ui = dialog('individual');
  ui.state.choices.category_choice = { value: '201', dataset: { age: 'u/13 Boys', pivotId: '201' } };
  ui.change('category_choice'); ui.submit();
  ui.requests[0].resolve({ success: false });
  assert.equal(ui.values['#drawCreationPercent'], '0%');
  assert.equal(ui.submitButton.disabled, false);
  assert.equal(ui.navigation.length, 0);
  assert.match(ui.values['#drawCreationError'], /Check your draws before retrying/);
});


test('bulk creation uses one request with estimated progress and confirmed completion', () => {
  const ui = dialog('team');
  ui.bulk.checked = true; ui.change('bulkTeamDraws');
  ui.state.bulkTypes = [{ value: '19', dataset: { code: 'singles', name: 'Team-Singles' } }];
  ui.state.bulkCategories = [{ value: '201', dataset: { name: 'u/13 Boys', age: 'u/13', gender: 'boys', pivotIds: '[201]' } },
    { value: '206', dataset: { name: 'u/13 Girls', age: 'u/13', gender: 'girls', pivotIds: '[206]' } }];
  ui.submit();
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.requests[0].url, '/team');
  assert.equal(ui.requests[0].payload.draws.length, 2);
  assert.equal(ui.values['#drawCreationPercent'], '0%');
  ui.tick(); ui.submit();
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.hide(), true);
  ui.requests[0].resolve({ success: true, draws: [{ id: 1 }, { id: 2 }] });
  assert.equal(ui.values['#drawCreationPercent'], '100%');
  assert.equal(ui.timers.size, 0);
  assert.deepEqual(ui.navigation, ['reload']);
});
