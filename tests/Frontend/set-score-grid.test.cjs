const {test} = require('node:test');
const assert = require('node:assert/strict');
const grid = require('../../public/js/set-score-grid.js');

function fixture() {
  const input = () => ({value: '', disabled: false});
  const rows = Array.from({length: 5}, () => ({hidden: false, inputs: [input(), input()], querySelectorAll() { return this.inputs; }}));
  const labels = [{textContent: ''}, {textContent: ''}];
  return {rows, labels, querySelectorAll() { return rows; }, querySelector(selector) { return labels[selector.includes('home') ? 0 : 1]; }};
}

test('prefill handles best-of-one, three and five and clears stale hidden set scores', () => {
  const root = fixture();
  const five = [[6, 0], [4, 6], [7, 5], [3, 6], [7, 6]];
  grid.populate(root, {names: ['Player <A>', 'Player B'], maxSets: 5, scores: five});
  assert.deepEqual(grid.read(root), five);
  assert.equal(root.labels[0].textContent, 'Player <A>');
  grid.populate(root, {names: ['A', 'B'], maxSets: 3, scores: [[6, 4], [4, 6], [22, 20]]});
  assert.deepEqual(grid.read(root), [[6, 4], [4, 6], [22, 20]]);
  assert.equal(root.rows[3].hidden, true);
  assert.equal(root.rows[3].inputs[0].disabled, true);
  assert.equal(root.rows[3].inputs[0].value, '');
  grid.populate(root, {names: ['A', 'B'], maxSets: 1, scores: [[6, 0]]});
  assert.deepEqual(grid.read(root), [[6, 0]]);
  assert.equal(root.rows.filter(row => !row.hidden).length, 1);
});

test('blank trailing sets are ignored but one-sided sets and gaps need correction', () => {
  const root = fixture();
  grid.populate(root, {names: ['A', 'B'], maxSets: 3, scores: [[6, 4]]});
  assert.deepEqual(grid.read(root), [[6, 4]]);
  root.rows[1].inputs[0].value = '6';
  assert.throws(() => grid.read(root), /Complete both scores for set 2/);
  root.rows[1].inputs[0].value = '';
  root.rows[2].inputs[0].value = '6'; root.rows[2].inputs[1].value = '2';
  assert.throws(() => grid.read(root), /sets in order/);
  grid.populate(root, {names: ['A', 'B'], maxSets: 3, scores: []});
  assert.throws(() => grid.read(root), /at least one/);
});

test('grid accepts short, pro and long tiebreak numbers without replacing server format validation', () => {
  for (const scores of [[[3, 1]], [[8, 6]], [[22, 20]], [[10, 8]]]) {
    const root = fixture();
    grid.populate(root, {names: ['A', 'B'], maxSets: 1, scores});
    assert.deepEqual(grid.read(root), scores);
  }
  for (const bad of ['-1', '1.5', '1e2', 'NaN']) {
    const root = fixture();
    grid.populate(root, {names: ['A', 'B'], maxSets: 1, scores: [[6, 4]]});
    root.rows[0].inputs[0].value = bad;
    assert.throws(() => grid.read(root), /whole-number/);
  }
});
