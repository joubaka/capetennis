const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const template = fs.readFileSync('resources/views/backend/superadmin/index.blade.php', 'utf8');

function harness() {
  const handlers = new Map(), nodes = new Map(), requests = [];
  const document = {getElementById: id => ({id, value: 'Reviewed terms', reportValidity: () => true})};
  function node(key) {
    if (typeof key === 'object' && key !== document) return key;
    if (!nodes.has(key)) nodes.set(key, {
      disabled: false, checked: true, classes: new Set(), values: {},
      on(event, selector, handler) {handlers.set(`${key}:${event}:${typeof selector === 'string' ? selector : ''}`, handler || selector); return this;},
      prop(name, value) {if (value === undefined) return this[name]; this[name] = value; return this;},
      data(name, value) {if (value === undefined) return this.values[name]; this.values[name] = value; return this;},
      is() {return this.checked;},
      hasClass(value) {return this.classes.has(value);},
      find(selector) {return node(selector);},
      serialize() {assert.equal(node(':input:enabled').disabled, false); return 'registration_open=1';},
      attr() {return '/settings';},
      html(value) {this.content = value; return this;},
      text(value) {this.content = value; return this;},
      removeClass(value) {value.split(' ').forEach(c => this.classes.delete(c)); return this;},
      addClass(value) {value.split(' ').forEach(c => this.classes.add(c)); return this;},
      DataTable() {this.initialized = true; return {columns: {adjust: () => {this.adjusted = (this.adjusted || 0) + 1; return {draw() {}};}}};}
    });
    return nodes.get(key);
  }
  const $ = node;
  $.ajax = options => requests.push(options);
  const context = vm.createContext({$, document, URL, URLSearchParams, shownTabs: [], window: {location: {search: '?tab=wallets'}},
    setTimeout: () => 1, clearTimeout() {}, Quill: function () {this.root = {}; this.on = () => {};},
    bootstrap: {Tab: {getOrCreateInstance: element => ({show() {context.shownTabs.push(element.id); handlers.get(`#${element.id}:shown.bs.tab:`)?.();}})}}});
  context.saToggleUrl = '/settings/single'; context.saToggleToken = 'csrf';
  const slice = (start, end) => template.slice(template.indexOf(start), template.indexOf(end, template.indexOf(start)));
  vm.runInContext(slice('  function saShowToast(', '  // ── Email template save'), context);
  vm.runInContext(slice('  // ── Settings form — AJAX save', '  // Preserve local subtab context'), context);
  return {handlers, node, requests, context, slice};
}

test('full save waits for an in-flight toggle and then includes its enabled value', () => {
  const h = harness(), toggle = h.node('toggle');
  toggle.values['setting-key'] = 'registration_open';
  h.handlers.get('[object Object]:change:.sa-toggle-setting').call(toggle);
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  assert.equal(h.requests.length, 1);
  h.requests[0].complete();
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  assert.equal(h.requests[1].data, 'registration_open=1');
  assert.equal(h.node(':input:enabled').disabled, true);
  h.requests[1].complete();
  assert.equal(h.node(':input:enabled').disabled, false);
});

test('duplicate full save and toggle changes cannot overlap a full save', () => {
  const h = harness(), submit = h.handlers.get('#sa-settings-form:submit:');
  submit.call(h.node('#sa-settings-form'), {preventDefault() {}});
  submit.call(h.node('#sa-settings-form'), {preventDefault() {}});
  h.handlers.get('[object Object]:change:.sa-toggle-setting').call(h.node('toggle'));
  assert.equal(h.requests.length, 1);
  h.requests[0].error({status: 500});
  h.requests[0].complete();
  assert.equal(h.node(':input:enabled').disabled, false);
});

test('failed toggle restores its previous value and releases save guard', () => {
  const h = harness(), toggle = h.node('toggle');
  h.handlers.get('[object Object]:change:.sa-toggle-setting').call(toggle);
  h.requests[0].error({responseJSON: {message: 'Rejected'}});
  h.requests[0].complete();
  assert.equal(toggle.checked, false);
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  assert.equal(h.requests.length, 2);
});

test('dismissed feedback remains reusable on the next save', () => {
  const h = harness();
  vm.runInContext("saShowToast('Saved', false)", h.context);
  h.handlers.get('#sa-settings-toast-close:click:')();
  assert.equal(h.node('#sa-settings-toast').classes.has('d-none'), true);
  vm.runInContext("saShowToast('Failed', true)", h.context);
  assert.equal(h.node('#sa-settings-toast').classes.has('d-none'), false);
  assert.equal(h.node('#sa-settings-toast-msg').content, 'Failed');
});

test('Wallets navigation uses the server-selected page rather than client-only tab activation', () => {
  const link = template.match(/<a[^>]+id="sa-tab-wallets"[\s\S]*?<\/a>/)[0];
  assert.match(link, /href=.*backend.superadmin.workspace/);
  assert.match(link, /'tab' => 'wallets'/);
  assert.doesNotMatch(link, /data-bs-toggle/);
  assert.doesNotMatch(template, /dt-wallets'\)\.DataTable/);
});

test('content save callbacks share notifier scope and restore button after failure', () => {
  const h = harness();
  const content = h.slice('// ── Quill content editors', '</script>');
  // The final ready closure keeps editor handlers in the notifier's lexical scope.
  assert.match(content, /\}\(\)\);\s*\}\);\s*$/);
  vm.runInContext(content.replace(/\}\);\s*$/, '').replace(/\{\{[\s\S]*?\}\}/g, '/settings/content'), h.context);
  const button = h.node('content-button');
  button.values.key = 'terms_conditions_content'; button.values.textarea = 'terms';
  h.handlers.get('[object Object]:click:.sa-save-content-btn').call(button);
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  assert.equal(h.requests.length, 1, 'full form waits for content save');
  h.requests[0].error({responseJSON: {message: 'Could not save'}});
  h.requests[0].complete();
  assert.equal(button.disabled, false);
  assert.equal(h.node('#sa-settings-toast-msg').content, 'Could not save');
});

test('local subtab context restores only allowlisted destinations and preserves the server workspace', () => {
  const h = harness();
  h.context.window.location = {search: '?tab=settings&settings_tab=registration', href: 'http://localhost/backend/superadmin/workspace?tab=settings&settings_tab=registration'};
  h.context.window.history = {replaceState(_state, _title, url) {h.context.updatedUrl = String(url);}};
  vm.runInContext(h.slice('  // Preserve local subtab context', '  // Server-selected lists'), h.context);
  assert.deepEqual([...h.context.shownTabs], ['sa-stab-registration']);
  h.handlers.get('#sa-settings-tabs [data-bs-toggle]:shown.bs.tab:')({target: {id: 'sa-stab-access'}});
  assert.equal(new URL(h.context.updatedUrl).searchParams.get('tab'), 'settings');
  assert.equal(new URL(h.context.updatedUrl).searchParams.get('settings_tab'), 'access');
  h.context.window.location.search = '?settings_tab=unknown';
  vm.runInContext("saLocalTabs('sa-settings-tabs', 'sa-stab-', 'settings_tab', ['email', 'registration', 'payfast', 'access'])", h.context);
  assert.equal(h.context.shownTabs.length, 1);
});

test('annual price save blocks full form until completion and cannot start during full save', () => {
  const h = harness();
  vm.runInContext(h.slice("  $('#sa-plus-price-save').on", '  function saShowToast').replace(/\{\{[\s\S]*?\}\}/g, '/settings/price'), h.context);
  const savePrice = h.handlers.get('#sa-plus-price-save:click:');
  savePrice.call(h.node('#sa-plus-price-save'));
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  assert.equal(h.requests.length, 1);
  h.requests[0].complete();
  h.handlers.get('#sa-settings-form:submit:').call(h.node('#sa-settings-form'), {preventDefault() {}});
  savePrice.call(h.node('#sa-plus-price-save'));
  assert.equal(h.requests.length, 2, 'dedicated price save cannot overlap full form');
  h.requests[1].complete();
});

test('audit restoration initializes only the active table and adjusts Login on revisit', () => {
  const h = harness();
  vm.runInContext(h.slice('  // ── Login Audit DataTable', '  // ── Activity Detail Modal'), h.context);
  h.node('#sa-pane-activity').classes.add('active');
  vm.runInContext('initVisibleAudit()', h.context);
  assert.equal(h.node('#dt-activity').initialized, true);
  assert.notEqual(h.node('#dt-login-audit').initialized, true);
  assert.notEqual(h.node('#dt-activity-raw').initialized, true);
  h.node('#sa-pane-activity').classes.delete('active');
  h.node('#sa-pane-login').classes.add('active');
  vm.runInContext('initVisibleAudit(); initVisibleAudit()', h.context);
  assert.equal(h.node('#dt-login-audit').initialized, true);
  assert.equal(h.node('#dt-login-audit').adjusted, 1);
  h.node('#sa-pane-login').classes.delete('active');
  h.node('#sa-pane-activity').classes.add('active');
  h.node('#sa-activity-toggle').checked = false;
  vm.runInContext('initVisibleAudit()', h.context);
  assert.equal(h.node('#dt-activity-raw').initialized, true);
  assert.equal(h.node('#dt-activity').adjusted, undefined);
});
