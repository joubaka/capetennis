const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function workspace(hash = '', stored = null) {
  const listeners = {}, shown = [], pushes = [], replaces = [];
  const storage = new Map(stored ? [['rr-view-42', stored]] : []);
  const location = {hash};
  const buttons = ['players', 'results', 'schedule', 'setup', 'rules'].map(area => ({
    dataset: {workspace: area}, active: false,
    classList: {toggle(_, value) { buttons.find(button => button.dataset.workspace === area).active = value; }},
    setAttribute() {}
  }));
  const tabs = ['groups','matrix','standings','main-bracket','oop','schedule','settings','notes','print'].map(id => ({id: id + '-tab', hidden: false}));
  const document = {
    getElementById(id) { return tabs.find(tab => tab.id === id); },
    querySelectorAll(selector) { return selector === '[data-workspace]' ? buttons : []; }
  };
  const events = new Map();
  function $(value) {
    if (typeof value === 'function') { value(); return; }
    const chain = {
      on(event, callback) { events.set(`${event}:${value}`, callback); return chain; },
      attr(name) { return typeof value === 'object' ? value[name] : ''; },
      each(callback) { if (value === '#rrTabs > li') tabs.forEach(tab => callback.call(tab)); return chain; },
      find() { return chain; },
      toggleClass(_, hidden) { if (typeof value === 'object') value.hidden = hidden; return chain; },
      addClass() { tabs.forEach(tab => tab.hidden = true); return chain; },
      text() { return chain; }
    };
    return chain;
  }
  $.ajaxSetup = () => {};
  const window = {RR_OOP: [], addEventListener(event, callback) {listeners[event] = callback;}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/workspace.js', 'utf8'), {
    jQuery: $, window, document, location, DRAW_ID: 42,
    bootstrap: {Tab: {getOrCreateInstance: tab => ({show() { shown.push(tab.id); }})}},
    history: {
      replaceState(_, __, hash) { replaces.push(hash); location.hash = hash; },
      pushState(_, __, hash) { pushes.push(hash); location.hash = hash; }
    },
    sessionStorage: {getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value)},
    AdminState: {on() {}}
  });
  return {window, shown, buttons, tabs, listeners, location, storage, pushes, replaces};
}

test('settings and notes deep links and stored views activate separate top-level destinations', () => {
  for (const [hash, stored, area, tab] of [
    ['#settings', null, 'setup', 'settings-tab'], ['#notes', null, 'rules', 'notes-tab'],
    ['#notes-pane', null, 'rules', 'notes-tab'], ['', 'notes', 'rules', 'notes-tab'], ['', 'settings', 'setup', 'settings-tab'],
    ['', 'notes-pane', 'rules', 'notes-tab'], ['', '#notes-tab', 'rules', 'notes-tab']
  ]) {
    const page = workspace(hash, stored);
    assert.equal(page.shown.at(-1), tab);
    assert.equal(page.buttons.find(button => button.active).dataset.workspace, area);
    assert.ok(page.tabs.every(tab => tab.hidden), 'singleton destinations need no duplicate subtab row');
  }
});

test('legacy setup calls, explicit rules navigation and browser history preserve destinations', () => {
  const page = workspace('#settings');
  page.window.RRWorkspace.open('setup', 'notes');
  assert.equal(page.shown.at(-1), 'notes-tab');
  assert.equal(page.location.hash, '#notes');
  assert.equal(page.storage.get('rr-view-42'), 'notes');
  assert.deepEqual(page.pushes, ['#notes']);
  page.location.hash = '#settings';
  page.listeners.popstate();
  assert.equal(page.shown.at(-1), 'settings-tab');
  assert.equal(page.buttons.find(button => button.active).dataset.workspace, 'setup');
  assert.deepEqual(page.pushes, ['#notes']);
  page.window.RRWorkspace.open('rules');
  assert.equal(page.shown.at(-1), 'notes-tab');
  page.window.RRWorkspace.open('setup');
  assert.equal(page.shown.at(-1), 'settings-tab');
  page.location.hash = '#notes';
  page.listeners.hashchange();
  assert.equal(page.buttons.find(button => button.active).dataset.workspace, 'rules');
});
