const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8').replace(/\r\n/g, '\n');

function harness() {
  const listeners = {};
  const programmeMatchOverrides = new Map();
  const controls = {'.programme-day':{value:'1'}, '.programme-sequence':{value:'2'}};
  const round = {querySelector:selector => controls[selector]};
  const context = {programmeMatchOverrides, programmePayload:{},
    document:{getElementById:()=>({addEventListener:(name,fn)=>listeners[name]=fn}),querySelector:()=>round},
    syncProgrammeMatchControls:()=>{},invalidatePreview:()=>{},markProgrammeDirty:()=>{},renderProgrammeStages:()=>{},programmeStatus:()=>{},
    programmeGroup:()=>[{id:7,programme_round_details:{2:[{matches:[{fixture_id:12},{fixture_id:11}]}]}}],
  };
  const start = source.indexOf('  const changeProgrammeMatchOverrides =');
  const end = source.indexOf("  document.getElementById('programme-stages').addEventListener('click'", start);
  vm.runInNewContext(source.slice(start,end), context);
  const read = source.indexOf('  const readProgrammeMatchOverrides =');
  vm.runInNewContext(source.slice(read,source.indexOf('\n',read))+';globalThis.read = readProgrammeMatchOverrides;', context);
  const section = {dataset:{matchGroupDraw:'7',matchGroupRound:'2',matchGroupIndex:'0'},
    querySelector:selector=>({value:selector.includes('day')?'3':'4'})};
  return {context,listeners,programmeMatchOverrides,
    group(reset=false) {
      const button={closest:()=>section,hasAttribute:name=>name===(reset?'data-reset-match-group':'data-apply-match-group')};
      listeners.click({target:{closest:selector=>selector==='[data-reset-match]'?null:button}});
    },
  };
}

test('position group apply creates fixture overrides and individual Follow round resets one',()=>{
  const h=harness();h.group();
  assert.deepEqual(Array.from(h.context.read(), row=>({...row})),[
    {draw_id:7,fixture_id:11,day:3,sequence:4},{draw_id:7,fixture_id:12,day:3,sequence:4},
  ]);
  const row={dataset:{matchOverride:'11'}};
  const button={closest:()=>row};
  h.listeners.click({target:{closest:selector=>selector==='[data-reset-match]'?button:null}});
  assert.equal(h.programmeMatchOverrides.has(11),false);
  assert.equal(h.programmeMatchOverrides.has(12),true);
  h.group(true);assert.equal(h.programmeMatchOverrides.size,0);
});

test('specific match day/order can override independently and clearing follows round',()=>{
  const h=harness();
  const day={value:'2'},order={value:''};
  const row={dataset:{matchOverride:'11',matchOverrideDraw:'7',matchOverrideRound:'2'},querySelector:selector=>selector.includes('day')?day:order};
  const target={value:'2',matches:selector=>selector.includes('.programme-match-day'),closest:()=>row};
  h.listeners.change({target});
  assert.deepEqual({...h.programmeMatchOverrides.get(11)},{draw_id:7,fixture_id:11,day:2,sequence:2});
  target.value='';h.listeners.change({target});assert.equal(h.programmeMatchOverrides.has(11),false);
});

test('advanced group/match markup keeps separate attrs from round payload rows',()=>{
  const context={programmeDayNumbers:[1,2,3],programmeDayLabel:day=>'Day '+day,escapeHtml:value=>String(value).replace(/</g,'&lt;'),programmeMatchOverrides:new Map()};
  const start=source.indexOf('  const programmeMatchChoices =');
  const end=source.indexOf('  const syncProgrammeMatchControls =',start);
  vm.runInNewContext(source.slice(start,end)+';globalThis.controls=programmeMatchControls;',context);
  const html=context.controls({id:7,programme_round_details:{2:[{label:'Positions 13–14 final',matches:[{fixture_id:11,number:26}]}]}},2,1,2);
  assert.match(html,/Positions 13–14 final/);assert.match(html,/Match 26/);assert.match(html,/Follow round/);
  assert.match(html,/data-match-override="11"/);assert.doesNotMatch(html,/data-programme-draw=/);
});
