const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

test('round setup scope supports this, next, onward, all and nonconsecutive selections', () => {
  const start = source.indexOf("    if (event.target.id === 'programme-setup-round-mode')");
  const end = source.indexOf("    if (!event.target.matches('.programme-setup-venue'))", start);
  assert.ok(start >= 0 && end > start);
  const inputs = [1,2,3].map(value => ({value:String(value), checked:value === 2}));
  const mode = {value:''};
  const context = {event:{target:mode}, programmeSetupRound:2, programmeSetupRounds:[1,2,3],
    programmeSetupModal:{querySelectorAll:() => inputs}, document:{getElementById:() => mode}};
  const run = value => {
    mode.id='programme-setup-round-mode'; mode.value=value;
    vm.runInNewContext(`(() => {${source.slice(start,end)}})()`, context);
    return inputs.filter(input => input.checked).map(input => Number(input.value));
  };
  assert.deepEqual(run('current'),[2]);
  assert.deepEqual(run('next'),[2,3]);
  assert.deepEqual(run('onward'),[2,3]);
  assert.deepEqual(run('all'),[1,2,3]);
  inputs.forEach(input => input.checked = input.value !== '2');
  assert.deepEqual(run('selected'),[1,3]);
  context.programmeSetupRound=3;
  assert.deepEqual(run('next'),[3]);
});

test('shared boys and girls rank bands load once per draw and scoped payload targets selected rounds', () => {
  const start = source.indexOf('    const rules = draws.flatMap(draw => (programmeRoundSetup');
  const end = source.indexOf('\n', start);
  const shared = {draw_ids:[1,2], min_rank:1, max_rank:4, venue_id:9};
  const context = {draws:[{id:1},{id:2}], programmeSetupRound:1, programmeRoundSetup:() => null, programmeSetupRules:() => [shared]};
  vm.runInNewContext(source.slice(start,end)+'; globalThis.loaded = rules;',context);
  assert.equal(context.loaded.filter(rule => rule.draw_ids.includes(1)).length,1);
  assert.equal(context.loaded.filter(rule => rule.draw_ids.includes(2)).length,1);
  context.setup={assignments:[{draw_id:1,venue_ids:[9]},{draw_id:2,venue_ids:[9]}], rules:context.loaded};
  context.rounds=[1,3];
  const payloadStart = source.indexOf('      const round_venue_setups = setup.assignments.flatMap');
  const payloadEnd = source.indexOf('\n',payloadStart);
  vm.runInNewContext(source.slice(payloadStart,payloadEnd)+'; globalThis.rows = round_venue_setups;',context);
  assert.deepEqual(JSON.parse(JSON.stringify(context.rows.map(row => [row.draw_id,row.round]))),[[1,1],[1,3],[2,1],[2,3]]);
  assert.ok(context.rows.every(row => row.rank_venue_preferences.length === 1));
});
