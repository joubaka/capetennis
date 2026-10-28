const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function workspace(hash = '', stored = null, search = '') {
  const listeners = {}, shown = [], pushes = [], replaces = [];
  const storage = new Map(stored ? [['rr-view-42', stored]] : []);
  const location = {hash, search};
  const review = {active: false, attrs: {}, classList: {toggle(_, value) {review.active = value;}}, setAttribute(name, value) {this.attrs[name] = value;}};
  const nextSchedule = {hidden: true};
  const stateListeners = {}, fixtureState = {fixtures: {}, oop: []};
  const buttons = ['players', 'results', 'schedule', 'setup', 'rules'].map(area => ({
    dataset: {workspace: area}, active: false,
    classList: {toggle(_, value) { buttons.find(button => button.dataset.workspace === area).active = value; }},
    setAttribute() {}
  }));
  const tabs = ['groups','matrix','standings','main-bracket','oop','schedule','settings','notes','print'].map(id => ({id: id + '-tab', hidden: false}));
  const document = {
    getElementById(id) { return tabs.find(tab => tab.id === id); },
    querySelectorAll(selector) { return selector === '[data-workspace]' ? buttons : selector === '[data-draw-next-schedule]' ? [nextSchedule] : selector === '[data-workspace-review]' ? [review] : []; }
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
    jQuery: $, window, document, location, URLSearchParams, DRAW_ID: 42,
    bootstrap: {Tab: {getOrCreateInstance: tab => ({show() { shown.push(tab.id); }})}},
    history: {
      replaceState(_, __, hash) { replaces.push(hash); location.hash = hash; },
      pushState(_, __, hash) { pushes.push(hash); location.hash = hash; }
    },
    sessionStorage: {getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value)},
    AdminState: {
      on(event, callback) { stateListeners[event] = callback; },
      getFixtures() { return fixtureState.fixtures; },
      getOop() { return fixtureState.oop; }
    }
  });
  return {window, shown, buttons, tabs, listeners, location, storage, pushes, replaces, nextSchedule, fixtureState, stateListeners, review, events};
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

test('generating fixtures reveals the next scheduling action without a reload and clearing them hides it again', () => {
  const page = workspace('#groups');
  assert.equal(page.nextSchedule.hidden, true);
  page.fixtureState.fixtures = {A: [{id: 7}, {id: 8}]};
  page.stateListeners['rr:fixtures:updated']();
  assert.equal(page.nextSchedule.hidden, false);
  page.window.RRWorkspace.open('results');
  assert.equal(page.nextSchedule.hidden, false, 'reviewing the generated draw keeps the next action available');
  page.fixtureState.fixtures = {};
  page.stateListeners['rr:fixtures:updated']();
  assert.equal(page.nextSchedule.hidden, true, 'a draw without generated matches cannot appear ready to schedule');
  assert.deepEqual(page.fixtureState.oop, [], 'navigation never writes match times');
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

test('publication review highlights step five and ordinary navigation restores its own active step', () => {
  const page = workspace('#matrix', null, '?review=publication');
  assert.equal(page.review.active, true);
  assert.equal(page.review.attrs['aria-current'], 'step');
  assert.equal(page.buttons.some(button => button.active), false);
  page.window.RRWorkspace.open('results');
  assert.equal(page.review.active, false, 'clicking Draw & Results works even when the hash is unchanged');
  assert.equal(page.buttons.find(button => button.active).dataset.workspace, 'results');
  assert.equal(page.location.search, '?review=publication', 'a stale query cannot keep step five selected');
  page.window.RRWorkspace.open('schedule');
  assert.equal(page.buttons.find(button => button.active).dataset.workspace, 'schedule');
  assert.equal(page.review.attrs['aria-current'], 'false');
});

test('individual Schedule opens scheduling choices directly while match-list deep links and team defaults remain available', () => {
  const page = workspace('#matrix');
  const scheduleButton = page.buttons.find(button => button.dataset.workspace === 'schedule');
  scheduleButton.dataset.workspaceView = 'schedule';
  page.events.get('click:[data-workspace]').call(scheduleButton);
  assert.equal(page.shown.at(-1), 'schedule-tab');
  assert.equal(page.location.hash, '#schedule');
  const legacy = workspace('#oop');
  assert.equal(legacy.shown.at(-1), 'oop-tab');
  scheduleButton.dataset.workspaceView = 'oop';
  page.events.get('click:[data-workspace]').call(scheduleButton);
  assert.equal(page.shown.at(-1), 'oop-tab', 'team scheduling keeps its existing match-list destination');
});

test('Monrad publication review and normal tab changes select the correct progression step without publishing', () => {
  const source = fs.readFileSync('public/js/draw-workspace-navigation.js', 'utf8');
  const start = source.indexOf('  const aliases =');
  const end = source.indexOf("  window.addEventListener('hashchange', open);", start);
  const control = dataset => ({dataset, attrs: {}, listeners: {}, active: false,
    classList: {toggle(_, value) {this.owner.active = value;}},
    setAttribute(name, value) {this.attrs[name] = value;},
    addEventListener(event, callback) {this.listeners[event] = callback;}});
  const buttons = ['groups', 'matrix', 'schedule', 'settings'].map(tab => control({flexibleTab: tab}));
  const review = control({});
  [...buttons, review].forEach(button => {button.classList.owner = button;});
  const panels = ['editor', 'schedule', 'settings'].map(panel => ({dataset: {flexiblePanel: panel}, hidden: true}));
  const location = {search: '?review=publication', value: '#matrix', get hash() {return this.value;}, set hash(value) {this.value = value.startsWith('#') ? value : '#'+value;}};
  const workspace = {dataset: {hasGeneratedDraw: '1'}, querySelectorAll(selector) {
    return selector === '[data-flexible-tab]' ? buttons : selector === '[data-flexible-panel]' ? panels : [review];
  }};
  vm.runInNewContext(source.slice(start, end)+'; open();', {workspace, location, URLSearchParams, Event,
    window: {dispatchEvent() {}}});
  assert.equal(review.active, true);
  assert.equal(review.attrs['aria-current'], 'step');
  assert.equal(buttons.some(button => button.active), false);
  buttons.find(button => button.dataset.flexibleTab === 'matrix').listeners.click();
  assert.equal(review.active, false);
  assert.equal(buttons.find(button => button.active).dataset.flexibleTab, 'matrix');
  buttons.find(button => button.dataset.flexibleTab === 'schedule').listeners.click();
  assert.equal(buttons.find(button => button.active).dataset.flexibleTab, 'schedule');
  assert.equal(panels.find(panel => !panel.hidden).dataset.flexiblePanel, 'schedule');
  assert.equal(location.search, '?review=publication');
});
