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


test('age cards restore only the clicked draw and round bands and courts', () => {
  const setups=[{draw_id:1,round:1,court_allocations:[{venue_id:8,court_labels:['1','2']}],rank_venue_preferences:[{venue_id:8,min_rank:1,max_rank:4}]},{draw_id:1,round:2,court_allocations:[{venue_id:9,court_labels:['3']}],rank_venue_preferences:[{venue_id:9,min_rank:5,max_rank:8}]},{draw_id:2,round:1,court_allocations:[{venue_id:10,court_labels:['4']}],rank_venue_preferences:[{venue_id:10,min_rank:9,max_rank:12}]}];
  const context={roundVenueSetups:setups,document:{querySelector:selector => ({dataset:{venueName:selector.includes('value="8"') ? 'Saturday venue' : selector.includes('value="9"') ? 'Round two venue' : 'Sunday venue'}})},escapeHtml:String,programmeCourtLabels:labels => labels.join(','),programmeBandDescription:(_,rule) => 'Positions '+rule.min_rank+'–'+rule.max_rank};
  const start=source.indexOf('  const programmeRoundSetup =');const end=source.indexOf('  const refreshProgrammeStageSummaries =',start);
  vm.runInNewContext(source.slice(start,end)+'; globalThis.setup=programmeDrawSetup;',context);
  const saturday=context.setup({id:1},[],1), sunday=context.setup({id:2},[],1), second=context.setup({id:1},[],2);
  assert.match(saturday,/Saturday venue · Courts 1,2 · Positions 1–4/);assert.doesNotMatch(saturday,/Sunday|5–8|9–12/);
  assert.match(sunday,/Sunday venue · Courts 4 · Positions 9–12/); assert.match(second,/Round two venue · Courts 3 · Positions 5–8/);
});
