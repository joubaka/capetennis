const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/draw/roundrobin/setup-scripts.blade.php', 'utf8');
const helpers = source.slice(source.indexOf('function allPlayersPlayoffConfig('), source.indexOf('\nrefreshAllPlayersPreset();'));

test('all-player preset covers every rank once beyond forty and supports single-group pairs', () => {
  const create = vm.runInNewContext(helpers + '\nallPlayersPlayoffConfig');
  const config = JSON.parse(JSON.stringify(create(4, 12)));
  assert.equal(config.length, 12);
  assert.deepEqual(config.flatMap(stage => stage.positions), Array.from({length: 12}, (_, index) => index + 1));
  assert.ok(config.every(stage => stage.enabled && stage.size === 4));
  assert.deepEqual(JSON.parse(JSON.stringify(create(1, 5))).map(stage => stage.positions), [[1, 2], [3, 4], [5]]);
  assert.equal(create(26, 1)[0].size, 32);
});

test('saved group refresh updates only the available option, preserving the current saved and edited config', () => {
  let counts = [3, 3, 3, 3];
  const saved = [{name: 'Saved custom playoff', positions: [2], enabled: true}];
  const draft = JSON.parse(JSON.stringify(saved));
  const option = {values: {}, text(value) {this.label = value; return this;},
    prop(key, value) {this.values[key] = value; return this;}, data(key, value) {this.values[key] = value; return this;}};
  const refresh = vm.runInNewContext(helpers + '\nrefreshAllPlayersPreset', {
    $: () => option, savedGroupCounts: () => counts, assignedPlayerTotal: () => counts.reduce((sum, count) => sum + count, 0),
    numGroups: 4, savedPlayoffs: saved, playoffConfig: draft,
  });
  refresh();
  assert.equal(option.label, 'All round-robin players (12 players)');
  counts = [6, 6, 5, 4]; refresh();
  assert.equal(option.label, 'All round-robin players (21 players)');
  assert.equal(option.values.config.length, 6);
  assert.equal(option.values['max-positions'], 6);
  assert.deepEqual(draft, saved);
  counts = []; refresh();
  assert.equal(option.values.disabled, true);
  assert.equal(option.values.config.length, 0);
});
