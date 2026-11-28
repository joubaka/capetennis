const {test} = require('node:test');
const assert = require('node:assert/strict');
const grid = require('../../public/js/set-score-grid.js');

test('zero-start playoffs retain zero game scores and actual deciding tiebreak points', () => {
  const root = fixture();
  const setTypes = ['full', 'full', 'match_tiebreak'];
  for (const scores of [[[6,0],[6,0]], [[6,0],[0,6],[10,0]], [[0,6],[6,0],[0,10]], [[6,0],[0,6],[12,10]]]) {
    grid.populate(root, {names:['A','B'], maxSets:3, setTypes, scores});
    assert.deepEqual(grid.read(root), scores);
  }
  for (const third of [[0,0], [10,9], [11,8]]) {
    grid.populate(root, {names:['A','B'], maxSets:3, setTypes, scores:[[6,0],[0,6],third]});
    assert.throws(() => grid.read(root), /match-tiebreak points/);
  }
});

test('extended deciding match tiebreaks suggest and validate both winners without a game-score cap',()=>{
  const types=['full_start_2','full_start_2','match_tiebreak'];
  for(const [loser,winner] of [[8,10],[10,12],[18,20],[997,999]]) {
    assert.equal(grid.opponentScore('match_tiebreak',String(loser)),winner);
    for(const third of [[winner,loser],[loser,winner]]) assert.doesNotThrow(()=>grid.validateCompleted([[6,2],[3,6],third],types));
  }
  assert.equal(grid.opponentScore('match_tiebreak','998'),null);
  for(const third of [[10,9],[11,8],[7,6],[1000,998]]) assert.throws(()=>grid.validateCompleted([[6,2],[3,6],third],types));
  assert.throws(()=>grid.validateCompleted([[6,2],[6,3],[12,10]],types));
  assert.throws(()=>grid.validateCompleted([[6,2],[3,6]],types));
  const root=fixture(); grid.populate(root,{names:['A','B'],maxSets:3,setTypes:types,scores:[[6,2],[3,6],[12,10]]});
  assert.deepEqual(grid.read(root),[[6,2],[3,6],[12,10]]);
  grid.populate(root,{names:['A','B'],maxSets:3,setTypes:types,scores:[[6,2],[3,6],[0,0]]});
  grid.setCompletionEnabled(root,false); assert.deepEqual(grid.read(root),[[6,2],[3,6],[0,0]]);
});

test('two-all full sets enforce completed best-of-three results and preserve retirement input', () => {
  const types=['full_start_2','full_start_2','full_start_2'];
  assert.equal(grid.opponentScore('full_start_2','0'),null);
  assert.equal(grid.opponentScore('full_start_2','2'),6);
  for(const sets of [[[6,2],[6,3]],[[6,2],[3,6],[7,6]]]) assert.doesNotThrow(()=>grid.validateCompleted(sets,types));
  for(const sets of [[[3,2],[3,2]],[[6,2]],[[6,2],[2,6]],[[2,2]],[[6,0],[6,3]],[[6,2],[6,3],[7,6]]]) assert.throws(()=>grid.validateCompleted(sets,types));
  const root=fixture();
  grid.populate(root,{names:['A','B'],maxSets:3,setTypes:types,scores:[[3,2]]});
  assert.throws(()=>grid.read(root));
  grid.setCompletionEnabled(root,false);
  assert.deepEqual(grid.read(root),[[3,2]]);
});

function fixture() {
  const input = () => ({value: '', disabled: false});
  const rows = Array.from({length: 5}, () => ({hidden: false, inputs: [input(), input()], querySelectorAll() { return this.inputs; }}));
  const labels = [{textContent: ''}, {textContent: ''}];
  return {rows, labels, listeners: {}, addEventListener(type, callback) { this.listeners[type] = callback; },
    removeEventListener(type) { delete this.listeners[type]; },
    type(row, side, value) { const target = rows[row].inputs[side]; target.value = value; this.listeners.input({target}); },
    querySelectorAll() { return rows; }, querySelector(selector) { return labels[selector.includes('home') ? 0 : 1]; }};
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

test('format completion suggests a winning opponent and leaves unsupported scores manual', () => {
  for (const type of ['full', 'pro8', 'target3', 'target4', 'target5', 'target6', 'match_tiebreak']) {
    const target = type === 'full' ? 6 : type === 'pro8' ? 8 : type === 'match_tiebreak' ? 10 : Number(type.slice(6));
    assert.equal(grid.opponentScore(type, '0'), target);
    assert.equal(grid.opponentScore(type, String(target)), type === 'match_tiebreak' ? 12 : (['full', 'pro8'].includes(type) ? target + 1 : null));
  }
  assert.equal(grid.opponentScore('full', '4'), 6);
  assert.equal(grid.opponentScore('full', '5'), 7);
  assert.equal(grid.opponentScore('full', '7'), null);
  assert.equal(grid.opponentScore('pro8', '7'), 9);
  assert.equal(grid.opponentScore('match_tiebreak', '9'), 11);
  for (const type of ['timed', 'custom', undefined]) assert.equal(grid.opponentScore(type, '3'), null);
  for (const value of ['', '-1', '1.5', '1e2', 'NaN']) assert.equal(grid.opponentScore('full', value), null);
});

test('full sets suggest 6 for 2 and 7 for 5 or 6 from either side without overwriting manual scores', () => {
  for (const type of ['full', 'full_start_2']) {
    for (const side of [0, 1]) {
      const root = fixture();
      grid.populate(root, {names: ['A', 'B'], maxSets: 1, setTypes: [type]});
      for (const [score, opponent] of [['2', '6'], ['5', '7'], ['6', '7']]) {
        root.type(0, side, score);
        assert.equal(root.rows[0].inputs[1 - side].value, opponent);
      }
      root.type(0, 1 - side, '5');
      root.type(0, side, '6');
      assert.equal(root.rows[0].inputs[1 - side].value, '5');
    }
  }
  assert.equal(grid.opponentScore('pro8', '8'), 9);
});

test('completion works from either side and preserves manual, loaded and manually cleared values', () => {
  const root = fixture();
  grid.populate(root, {names: ['A', 'B'], maxSets: 3, setTypes: ['full', 'full', 'match_tiebreak'], scores: [[6, 3]]});
  root.type(0, 1, '5');
  assert.equal(root.rows[0].inputs[0].value, 6);
  root.type(1, 1, '4');
  assert.equal(root.rows[1].inputs[0].value, '6');
  root.type(1, 1, '5');
  assert.equal(root.rows[1].inputs[0].value, '7');
  root.type(1, 0, '6'); // override the suggestion
  root.type(1, 1, '3');
  assert.equal(root.rows[1].inputs[0].value, '6');
  root.type(2, 0, '2');
  assert.equal(root.rows[2].inputs[1].value, '10');
  root.type(2, 0, '20'); // extended losing points update the winning suggestion
  assert.equal(root.rows[2].inputs[1].value, '22');
  root.type(2, 0, '');
  assert.equal(root.rows[2].inputs[1].value, '');
});

test('retirement disables completion and modal reuse resets format and suggestion provenance', () => {
  const root = fixture();
  grid.populate(root, {names: ['A', 'B'], maxSets: 1, setTypes: ['full']});
  grid.setCompletionEnabled(root, false);
  root.type(0, 0, '3');
  assert.equal(root.rows[0].inputs[1].value, '');
  grid.populate(root, {names: ['C', 'D'], maxSets: 1, setTypes: ['target4']});
  root.type(0, 0, '2');
  assert.equal(root.rows[0].inputs[1].value, '4');
  grid.populate(root, {names: ['E', 'F'], maxSets: 1, setTypes: ['timed'], scores: [[2, 4]]});
  root.type(0, 0, '3');
  assert.equal(root.rows[0].inputs[1].value, 4);
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
