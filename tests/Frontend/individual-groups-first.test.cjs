const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function workspace(groups = [], locked = false, {confirm = false, failRequest = false} = {}) {
  const values = new Map([['#groups-tab-boxes', '2'], ['#rr-category-name:text', 'U19 Boys']]);
  const props = new Map(), handlers = new Map(), dialogs = [], requests = [];
  let sortables = 0;
  const document = { querySelectorAll() { return [{}]; }, dispatchEvent() {} };
  function $(selector) {
    let elementText = '';
    const chain = {
      on(event, target, callback) { handlers.set(`${event}:${typeof target === 'string' ? target : selector}`, callback || target); return chain; },
      text(value) { if (value === undefined) return values.get(`${selector}:text`) || ''; elementText = value; values.set(`${selector}:text`, value); return chain; },
      html(value) { if (value === undefined) return selector === '<div>' ? elementText : values.get(`${selector}:html`) || ''; values.set(`${selector}:html`, value); return chain; },
      val(value) { if (value === undefined) return values.get(selector) || ''; values.set(selector, value); return chain; },
      prop(key, value) { props.set(`${selector}:${key}`, value); return chain; },
      toggle() { return chain; }, toggleClass(name, value) { props.set(`${selector}:class:${name}`, value); return chain; }, each() { return chain; }
    };
    return chain;
  }
  const window = {
    RR_GROUPS: groups, RR_ROSTER: [{id: 1, name: 'Test Player', category_id: 3, category: 'U19 Boys'}],
    RR_CAN_ASSIGN: true, RR_CAN_GENERATE: true, RR_DRAW_LOCKED: locked, RR_DRAW_PUBLISHED: false,
    RR_ASSIGNMENT_REVISION: 'revision', addEventListener() {}, confirm() { return true; },
    Sortable: function () { sortables++; this.destroy = () => {}; },
    location: { reload() {} }
  };
  const context = { jQuery: $, window, document, Sortable: window.Sortable,
    AdminState: {setGroups() {}, on() {}},
    AdminRoutes: {drawUrl: (id, path) => `/draw/${id}${path}`},
    AdminApi: {request: async request => { requests.push(request); if (failRequest) throw new Error('Resize rejected'); return {}; }},
    AdminToast: {warning() {}, error() {}},
    Swal: {fire: async options => { dialogs.push(options); return {isConfirmed: confirm}; }}
  };
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/groups.js', 'utf8'), context);
  window.RRGroups.init(7);
  return {window, values, props, handlers, dialogs, requests, sortables: () => sortables};
}

test('zero groups shows explicit creation and blocks every placement action without hiding roster', async () => {
  const page = workspace();
  const html = page.values.get('#available-players-list:html');
  assert.match(html, /Test Player/);
  assert.match(html, /rr-player-select[^>]*disabled/);
  assert.match(html, /rr-add-player[^>]*disabled/);
  assert.equal(page.sortables(), 0);
  assert.equal(page.props.get('#rr-assign-selected, #rr-select-visible, #rr-assign-target:disabled'), true);
  assert.equal(page.values.get('#rr-apply-group-count:text'), 'Create groups');
  assert.equal(page.requests.length, 0);
  await page.handlers.get('click:#rr-apply-group-count')();
  assert.match(page.dialogs[0].html, /Create 2 empty groups for U19 Boys/);
  assert.equal(page.dialogs[0].confirmButtonText, 'Create groups');
  assert.equal(page.requests.length, 0, 'cancelled confirmation never creates groups');
});

test('saved groups enable placement, while locked groups preserve read-only controls', () => {
  const page = workspace([{id: 10, name: 'A', players: []}]);
  assert.doesNotMatch(page.values.get('#available-players-list:html'), /rr-add-player[^>]*disabled/);
  assert.equal(page.props.get('#rr-assign-selected, #rr-select-visible, #rr-assign-target:disabled'), false);
  assert.ok(page.sortables() > 0);
  assert.equal(page.values.get('#rr-apply-group-count:text'), 'Update groups');
  assert.match(page.values.get('#rr-placement-next-step:text'), /save assignments/);
  const locked = workspace([{id: 10, name: 'A', players: []}], true);
  assert.match(locked.values.get('#available-players-list:html'), /rr-add-player[^>]*disabled/);
  assert.equal(locked.sortables(), 0);
});

test('chosen group count survives searches and renders and is used by the single create action', async () => {
  const page = workspace();
  page.values.set('#groups-tab-boxes', '3');
  page.handlers.get('change:#groups-tab-boxes')();
  page.values.set('#rr-player-search', 'Test');
  page.handlers.get('input:#rr-player-search')();
  page.window.RRGroups.applyLockUI();
  assert.equal(page.values.get('#groups-tab-boxes'), '3');
  await page.handlers.get('click:#rr-apply-group-count')();
  assert.match(page.dialogs[0].html, /Create 3 empty groups for U19 Boys/);
  assert.equal(page.requests.length, 0);
});

test('failed group changes restore saved count while failed first creation keeps the chosen suggestion', async () => {
  const saved = workspace([{id: 10, name: 'A', players: []}], false, {confirm: true, failRequest: true});
  saved.values.set('#groups-tab-boxes', '3');
  await saved.handlers.get('click:#rr-apply-group-count')();
  assert.equal(saved.requests.length, 1);
  assert.equal(saved.values.get('#groups-tab-boxes'), 1);
  assert.match(saved.values.get('#rr-groups-row:html'), /Group A/);

  const fresh = workspace([], false, {confirm: true, failRequest: true});
  fresh.values.set('#groups-tab-boxes', '3');
  await fresh.handlers.get('click:#rr-apply-group-count')();
  assert.equal(fresh.requests.length, 1);
  assert.equal(fresh.values.get('#groups-tab-boxes'), 3);
  assert.equal(fresh.sortables(), 0);
});
