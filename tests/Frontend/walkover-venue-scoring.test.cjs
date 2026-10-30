const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('resources/views/frontend/scoring/workspace.blade.php', 'utf8');
const helpers = source.slice(source.indexOf('  function venueNeedsDecidingPoint('), source.indexOf('  function syncTimedDecision('));
const scoring = vm.runInNewContext(helpers + '\n({venueScorePayload})');
const match = {engine: 'standard', homeRegistration: '11', awayRegistration: '22'};

test('walkovers submit only a participant winner without stale sets or deciding points', () => {
  for (const engine of ['standard', 'flexible']) {
    const payload = scoring.venueScorePayload({...match, engine}, [[6, 4]], '11', '22');
    assert.deepEqual(JSON.parse(JSON.stringify(payload)), {walkover_winner_id: 22});
  }
});

test('walkover selection rejects missing, unrelated and team winners', () => {
  for (const id of ['', '99', '11.5', 'NaN']) {
    assert.throws(() => scoring.venueScorePayload(match, [], '', id), /walkover winner/);
  }
  assert.throws(() => scoring.venueScorePayload({...match, engine: 'team'}, [], '', '11'), /walkover winner/);
});

test('switching back to played scores restores only visible set inputs', () => {
  const rows = [{hidden: false, inputs: [{disabled: false}, {disabled: false}]},
    {hidden: true, inputs: [{disabled: true}, {disabled: true}]}];
  rows.forEach(row => { row.querySelectorAll = () => row.inputs; });
  const grid = {hidden: false, querySelectorAll: () => rows};
  const resultType = {value: 'walkover'};
  const walkoverPanel = {}, walkoverWinner = {}, decidingPanel = {}, decidingWinner = {}, saveButton = {};
  const sync = source.slice(source.indexOf('  function syncResultType('), source.indexOf("  resultType.addEventListener('change'"));
  const refresh = vm.runInNewContext(sync + '\nsyncResultType', {
    active: match, resultType, walkoverPanel, walkoverWinner, decidingPanel, decidingWinner, saveButton,
    retiredPanel: {}, retiredPlayer: {},
    scoreGuidance: {dataset: {played: 'Enter completed sets'}},
    modalElement: {querySelector: () => grid}, syncTimedDecision() {}
  });
  refresh();
  assert.equal(grid.hidden, true);
  assert.equal(walkoverWinner.required, true);
  assert.equal(decidingPanel.hidden, true);
  assert.ok(rows.every(row => row.inputs.every(input => input.disabled)));
  resultType.value = 'played';
  refresh();
  assert.equal(grid.hidden, false);
  assert.equal(walkoverWinner.disabled, true);
  assert.ok(rows[0].inputs.every(input => !input.disabled));
  assert.ok(rows[1].inputs.every(input => input.disabled));
});

test('retirement keeps partial scores and submits the retiring participant without a deciding-point override', () => {
  for (const engine of ['standard', 'flexible']) {
    const payload = scoring.venueScorePayload({...match, engine}, [[6, 4], [2, 3]], '11', undefined, '11');
    assert.deepEqual(JSON.parse(JSON.stringify(payload)), {sets: [[6, 4], [2, 3]], retired_registration_id: 11});
    assert.throws(() => scoring.venueScorePayload({...match, engine}, [[2, 3]], '', undefined, ''), /player who retired/);
    assert.throws(() => scoring.venueScorePayload({...match, engine}, [[2, 3]], '', undefined, '99'), /player who retired/);
  }
  assert.throws(() => scoring.venueScorePayload({...match, engine: 'team'}, [[2, 3]], '', undefined, '11'), /player who retired/);
  assert.throws(() => scoring.venueScorePayload(match, [[2, 3]], '', '22', '11'), /player who retired/);
});
