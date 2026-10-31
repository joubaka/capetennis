const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function scoreEditor(stage, format = 'timed', savedWinner = null) {
  const values = new Map(), handlers = new Map(), properties = new Map(), requests = [], errors = [];
  const fixture = {id: 7, stage, r1_id: 11, r2_id: 22, home: 'Home', away: 'Away', score: '3-3', winner: savedWinner};
  function $(selector) {
    if (typeof selector === 'function') { selector(); return; }
    const chain = {
      val(value) { if (value === undefined) return values.get(selector) ?? ''; values.set(selector, String(value)); return chain; },
      text() { return chain; }, html(value) { if (value === undefined) return ''; return chain; },
      prop(key, value) { properties.set(selector + ':' + key, value); return chain; },
      on(event, target, fn) { handlers.set(selector + ':' + event, fn || target); return chain; },
      each() { return chain; }, find() { return chain; }, toggleClass() { return chain; }, addClass() { return chain; }, removeClass() { return chain; }
    };
    return chain;
  }
  const context = {window: {RR_SCORE_FORMAT: format, RR_SCORE_MAX_SETS: 1, RR_CAN_SCORE: true}, jQuery: $, document: {getElementById() { return {}; }},
    AdminState: {getOop: () => [fixture], getFixtures: () => ({}), scoreSaved() {}},
    AdminToast: {warning: message => errors.push(message), error: message => errors.push(message), success() {}},
    AdminApi: {post: (url, payload) => { requests.push(payload); return Promise.resolve({}); }},
    AdminLoading: {button: () => () => {}}, AdminRoutes: {score: () => '/score'}, bootstrap: {Modal: class {show() {} static getInstance() { return {hide() {}}; }}}
  };
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/scores.js', 'utf8'), context);
  context.window.RRScores.open(7, 'Home', 'Away');
  return {values, properties, requests, errors, submit: () => handlers.get('#rr-score-modal-form:submit')({preventDefault() {}})};
}

test('equal timed group games save as a draw without a deciding winner', () => {
  const page = scoreEditor('RR'); page.submit();
  assert.equal(page.requests.length, 1);
  assert.deepEqual(Array.from(page.requests[0].sets), ['3-3']);
  assert.equal(page.requests[0].deciding_point_winner_id, undefined);
  assert.equal(page.properties.get('#rrm-deciding-point:hidden'), true);
});

test('equal timed playoff games require and prefill the actual deciding winner', () => {
  const page = scoreEditor('MAIN'); page.submit();
  assert.equal(page.requests.length, 0);
  assert.match(page.errors[0], /deciding point/);
  const saved = scoreEditor('MAIN', 'timed', 22);
  assert.equal(saved.values.get('#rrm-deciding-winner'), '22');
  saved.submit();
  assert.equal(saved.requests[0].deciding_point_winner_id, 22);
  assert.deepEqual(Array.from(saved.requests[0].sets), ['3-3']);
});

test('ordinary formats never expose or submit a deciding-point override', () => {
  const page = scoreEditor('MAIN', 'one_set_to_4'); page.submit();
  assert.equal(page.properties.get('#rrm-deciding-point:hidden'), true);
  assert.equal(page.requests[0].deciding_point_winner_id, undefined);
});

test('all round-robin standings rank points above wins', () => {
  for (const timed of [true, false]) {
    let output = '';
    const wrapper = {length: 1, text(value) {this.label = value; return this;}, html(value) {if (value === undefined) return this.label || ''; output = value; return this;}, append(value) {output += value; return this;}, on() {return this;}};
    const rows = [{player: 'One win', wins: 1, losses: 0, draws: 0, points: 2, sets_won: 1, sets_lost: 0}, {player: 'Three draws', wins: 0, losses: 0, draws: 3, points: 3, sets_won: 0, sets_lost: 0}];
    const context = {jQuery: () => wrapper, window: {RR_SCORE_FORMAT: timed ? 'timed' : 'one_set_to_4'},
      AdminState: {on() {}, getGroups: () => [{id: 1, name: 'A'}], getStandings: () => ({1: rows}), getFixtures: () => ({})}};
    vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/standings.js', 'utf8'), context);
    context.window.RRStandings.render();
    assert.equal(output.indexOf('Three draws') < output.indexOf('One win'), true);
    assert.equal(output.includes('>Pts</th>'), true);
  }
});

test('public timed standings retain points and terminate when three players remain equal', () => {
  for (const asset of ['resources/assets/js/roundrobin-public.js', 'public/assets/js/roundrobin-public.js']) {
    let output = '';
    const wrapper = {length: 1, data() {return 1;}, ready() {}, text() {return this;}, html(value) {if (value === undefined) return ''; output = value; return this;}, append(value) {output += value; return this;}};
    const rows = ['A', 'B', 'C'].map(player => ({player, reg_id: player, wins: 0, draws: 2, points: 2, losses: 0, sets_won: 0, sets_lost: 0, games_won: 6, games_lost: 6}));
    const context = {jQuery: () => wrapper, console: {log() {}}, document: {}, window: {RR_SCORE_FORMAT: 'timed', RR_GROUPS: [{id: 1, name: 'A'}], RR_STANDINGS: {1: rows}}};
    const source = fs.readFileSync(asset, 'utf8').replace('})(jQuery, window, document);', 'window.testStandings = renderStandings; })(jQuery, window, document);');
    vm.runInNewContext(source, context);
    context.window.testStandings();
    assert.match(output, />Pts<\/th>/);
    assert.equal((output.match(/<tr class=/g) || []).length, 3);
  }
});
