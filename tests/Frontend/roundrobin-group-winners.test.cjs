const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function render(status) {
  let html = '';
  const rows = [
    {reg_id: 1, player: 'Canonical winner', wins: 2, losses: 1, sets_won: 8, sets_lost: 2, games_won: 40, games_lost: 10, group_result_status: status, group_winner: status === 'complete'},
    {reg_id: 2, player: 'Head to head winner', wins: 2, losses: 1, sets_won: 5, sets_lost: 5, games_won: 30, games_lost: 30, group_result_status: status, group_winner: false}
  ];
  function $(selector) {
    return {length: 1, value: '', text(value) { this.value = value; return this; },
      html(value) { if (value === undefined) return this.value; html = value; return this; },
      append(value) { html += typeof value === 'string' ? value : value.value; return this; }, on() { return this; }};
  }
  const window = {};
  const context = vm.createContext({window, jQuery: $, AdminState: {
    getGroups: () => [{id: 1, name: 'A'}], getStandings: () => ({1: rows}),
    getFixtures: () => ({1: [{r1_id: 1, r2_id: 2, winner: 2}]}), on() {}
  }});
  vm.runInContext(fs.readFileSync('public/assets/js/admin/roundrobin/standings.js', 'utf8'), context);
  window.RRStandings.render();
  return html;
}

test('independent group standings retain canonical order and show the confirmed winner', () => {
  const html = render('complete');
  assert.ok(html.indexOf('Canonical winner') < html.indexOf('Head to head winner'));
  assert.match(html, /Canonical winner.*Group winner/);
});

test('incomplete and tied groups do not declare a winner', () => {
  for (const status of ['incomplete', 'tied']) {
    const html = render(status);
    assert.doesNotMatch(html, /badge bg-success/);
    assert.match(html, /Group winner pending/);
  }
});
