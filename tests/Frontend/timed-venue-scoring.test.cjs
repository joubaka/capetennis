const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('resources/views/frontend/scoring/workspace.blade.php', 'utf8');
const helpers = source.slice(source.indexOf('  function venueNeedsDecidingPoint('), source.indexOf('  function syncTimedDecision('));
const scoring = vm.runInNewContext(helpers + '\n({venueNeedsDecidingPoint, venueScorePayload})');
const match = {scoreFormat: 'timed', engine: 'standard', stage: 'RR', homeRegistration: '11', awayRegistration: '22'};
const payload = (entry, scores, winner = '') => JSON.parse(JSON.stringify(scoring.venueScorePayload(entry, scores, winner)));

test('timed group matches submit equal games as a draw without a winner override', () => {
  assert.equal(scoring.venueNeedsDecidingPoint(match, [[3, 3]]), false);
  assert.deepEqual(payload(match, [[3, 3]], '11'), {sets: [[3, 3]]});
  assert.deepEqual(payload(match, [[0, 0]]), {sets: [[0, 0]]});
});

test('tied timed playoffs require a participant deciding-point winner without inventing a game', () => {
  const playoff = {...match, stage: 'MAIN'};
  assert.equal(scoring.venueNeedsDecidingPoint(playoff, [[3, 3]]), true);
  assert.throws(() => payload(playoff, [[3, 3]]), /deciding point/);
  assert.throws(() => payload(playoff, [[3, 3]], '99'), /deciding point/);
  assert.deepEqual(payload(playoff, [[3, 3]], '22'), {sets: [[3, 3]], deciding_point_winner_id: 22});
  assert.deepEqual(payload(playoff, [[4, 3]], '22'), {sets: [[4, 3]]});
});

test('ordinary formats and team rubbers retain their existing score payload', () => {
  assert.deepEqual(payload({...match, scoreFormat: 'one_full_set', stage: 'MAIN'}, [[6, 4]]), {sets: [[6, 4]]});
  assert.deepEqual(payload({...match, engine: 'team', stage: ''}, [[3, 3]]), {sets: [[3, 3]]});
  const flexible = {...match, engine: 'flexible', stage: 'MAIN'};
  assert.deepEqual(payload(flexible, [[2, 2]], '11'), {sets: [[2, 2]], deciding_point_winner_id: 11});
});

test('changing a tied playoff to an unequal score clears the stale deciding winner; group draws never show it', () => {
  const inputs = [{value: '3'}, {value: '3'}];
  const decidingPanel = {hidden: true}, decidingWinner = {disabled: true, value: '22'};
  const active = {...match, stage: 'MAIN'};
  const state = {active, decidingPanel, decidingWinner, resultType: {value: 'played'},
    modalElement: {querySelector() {return {querySelectorAll() {return inputs;}};}}};
  const sync = source.slice(source.indexOf('  function syncTimedDecision('), source.indexOf("  modalElement.querySelector('[data-set-score-grid]').addEventListener"));
  const refresh = vm.runInNewContext(helpers + sync + '\nsyncTimedDecision', state);
  refresh();
  assert.equal(decidingPanel.hidden, false);
  assert.equal(decidingWinner.disabled, false);
  inputs[1].value = '2'; refresh();
  assert.equal(decidingPanel.hidden, true);
  assert.equal(decidingWinner.value, '');
  active.stage = 'RR'; inputs[1].value = '3'; refresh();
  assert.equal(decidingPanel.hidden, true);
  assert.equal(decidingWinner.disabled, true);
});
