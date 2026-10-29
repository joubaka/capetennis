const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function workspace(enabled = true) {
  const html = new Map(), values = new Map(), handlers = new Map();
  const document = {readyState: 'loading', addEventListener() {}, querySelector() { return null; }};
  function $(selector) {
    const element = {
      text(value) { this.content = String(value ?? ''); return this; },
      html(value) {
        if (value === undefined) return selector === '<div>' ? this.content.replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[c])) : html.get(selector);
        html.set(selector, value); return this;
      },
      val(value) { if (value === undefined) return values.get(selector) || ''; values.set(selector, value); return this; },
      on(event, target, callback) { handlers.set(typeof target === 'string' ? target : selector, callback || target); return this; },
      prop() { return this; }, toggleClass() { return this; }, toggle() { return this; }, each() { return this; }
    };
    return element;
  }
  const window = {
    ...(enabled ? {CTPlayerRatingConfig: {endpoint: '/ratings', drawId: 42}} : {}),
    RR_GROUPS: [{id: 1, name: 'A', registrations: [{id: 10, display_name: 'Assigned player'}]}],
    RR_ROSTER: [{id: 10, name: 'Assigned player', category_id: 7}, {id: 11, name: 'Available <player>', category_id: 7}],
    RR_CAN_ASSIGN: true, addEventListener() {}, confirm() { return true; }
  };
  const context = vm.createContext({window, document, jQuery: $, MutationObserver: class {},
    AdminState: {on() {}, setGroups() {}},
    AdminRoutes: {drawUrl: (_, suffix) => suffix},
    AdminApi: {get: async url => url === '/groups-data'
      ? {groups: [{id: 1, name: 'A', players: [{id: 11, name: 'Available <player>'}]}], revision: 'next'}
      : {categories: [{id: 7, category: 'Singles', players: [{id: 10, name: 'Assigned player'}, {id: 11, name: 'Available <player>'}]}]}}
  });
  vm.runInContext(fs.readFileSync('public/assets/js/player-rating-badges.js', 'utf8'), context);
  vm.runInContext(fs.readFileSync('public/assets/js/admin/roundrobin/groups.js', 'utf8'), context);
  window.RRGroups.init(42);
  return {window, html, handlers};
}

test('assigned and available entries use real private rating markers, including after assignment refresh', async () => {
  const h = workspace();
  assert.match(h.html.get('#rr-groups-row'), /data-rating-registration="10".*data-rating-draw="42"/);
  assert.match(h.html.get('#available-players-list'), /Available &lt;player&gt;/);
  assert.match(h.html.get('#available-players-list'), /data-rating-registration="11".*data-rating-field="7"/);
  await h.window.RRGroups.refresh(true);
  assert.match(h.html.get('#rr-groups-row'), /data-rating-registration="11".*data-rating-draw="42"/);
  assert.match(h.html.get('#available-players-list'), /data-rating-registration="10".*data-rating-field="7"/);
});

test('workspace keeps names and seed order without rating assets for unauthorized audiences', () => {
  const h = workspace(false);
  assert.match(h.html.get('#rr-groups-row'), /rr-seed">1<.*Assigned player/);
  assert.doesNotMatch(h.html.get('#rr-groups-row'), /data-rating-/);
  assert.doesNotMatch(h.html.get('#available-players-list'), /data-rating-/);
});
