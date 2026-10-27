const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8').replace(/\r\n/g, '\n');
function harness(draws) {
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, {value:'',options:[],listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},add(option){this.options.push(option);},dispatchEvent(){}});
    return elements.get(id);
  };
  const choices=draws.map(draw=>({value:draw.id,checked:false,disabled:draw.locked}));
  let selected=[];
  const context={document:{getElementById:get,querySelectorAll:()=>choices}, values:()=>selected,programmeDayCount:3,programmePayload:{},programmeRefreshVersion:0,programmeCreateLabel:"Create preview",
    programmeSettings:new Map(),invalidatePreview:()=>{},rememberRankRules:()=>{},loadRankScope:()=>{},showWorkflowStep:()=>{},Option:function(text,value){this.value=value;},Event:function(){}};
  const start=source.indexOf('  const individualSelection =');
  const end=source.indexOf('  showWorkflowStep(1);\n  if (!document',start);
  const block=source.slice(start,end).replace(/@json\([^\n]+\)/,JSON.stringify(draws)).replace(/@json\(\(bool\) \$planningDate\)/g, "false");
  vm.runInNewContext(block,context);
  return {get,choices,context,select(ids,gender){selected=ids;get('individual-schedule-gender').value=gender;get('individual-schedule-selection').listeners.change();},configure(){get('individual-schedule-configure').listeners.click();}};
}
const draws=[{id:1,is_team:false,schedule_gender:'boys',programme_supported:true},{id:2,is_team:false,schedule_gender:'girls',programme_supported:true},{id:3,is_team:false,schedule_gender:null,programme_supported:false},{id:4,is_team:false,schedule_gender:'boys',programme_supported:true,locked:true}];
test('individual draws and matching gender are required before setup',()=>{
 const h=harness(draws);assert.equal(h.get('individual-schedule-configure').disabled,true);assert.equal(h.get('programme-wizard').hidden,true);
 h.select([1],'');assert.equal(h.get('individual-schedule-configure').disabled,true);
 h.select([1],'girls');assert.equal(h.get('individual-schedule-configure').disabled,true);
 h.select([1,2],'boys');assert.equal(h.get('individual-schedule-configure').disabled,false);
 h.configure();assert.deepEqual(h.choices.filter(c=>c.checked).map(c=>c.value),[1]);assert.equal(h.get('programme-age').value,'individual:1');
});
test('selected subset gets its own stable programme key and changing selection hides setup',()=>{
 const h=harness(draws);h.select([2,1,4],'all');h.configure();assert.equal(h.get('programme-age').value,'individual:1,2');assert.equal(h.get('programme-wizard').hidden,false);
 h.select([2],'girls');assert.equal(h.get('programme-wizard').hidden,true);assert.equal(h.choices.some(c=>c.checked),false);
});
test('unsupported individual workflows use general setup without programme submission',()=>{
 const h=harness(draws);h.select([3],'all');h.configure();assert.equal(h.get('programme-wizard').hidden,true);assert.equal(h.get('advanced-scheduling').open,true);assert.equal(h.get('programme-age').value,'');
});
test('programme group uses exact individual IDs and keeps team age selection',()=>{
 const get=()=>({value:'individual:2'});const context={document:{getElementById:get},programmeDraws:[{id:1,is_team:false,programme_age:'monrad:19'},{id:2,is_team:false,programme_age:'monrad:19'},{id:3,is_team:true,programme_age:'19'}]};
 const start=source.indexOf('  const programmeGroup =');const end=source.indexOf('\n',start);vm.runInNewContext(source.slice(start,end)+';globalThis.group=programmeGroup;',context);assert.deepEqual(Array.from(context.group(),d=>d.id),[2]);
 context.document.getElementById=()=>({value:'19'});assert.deepEqual(Array.from(context.group(),d=>d.id),[3]);
});


test('changing selection while preview is pending restores button and ignores stale response', async()=>{
 const h=harness(draws); h.select([1],'all'); h.configure();
 let complete;
 Object.assign(h.context,{programmeGroup:()=>[draws[0]],programmeDayIndexes:[0],allocationsDirty:false,programmeStatus:()=>{},buildPayload:()=>({}),previewUrl:'/preview',post:()=>new Promise(resolve=>{complete=resolve;}),render:()=>{throw new Error('stale preview rendered');}});
 const start=source.indexOf('  const refreshProgrammePreview =');
 const end=source.indexOf('  // Refresh only card details',start);
 h.context.document.querySelectorAll=selector=>selector==='[data-programme-draw]'?[]:h.choices;
 vm.runInNewContext(source.slice(start,end)+';globalThis.refresh=refreshProgrammePreview;',h.context);
 const pending=h.context.refresh();
 assert.equal(h.get('programme-create').disabled,true);
 h.select([2],'girls');
 assert.equal(h.get('programme-create').disabled,false);
 assert.equal(h.get('programme-create').innerHTML,'Create preview');
 complete({matches:[],unscheduled:[]});await pending;
 h.configure();assert.equal(h.get('programme-create').disabled,false);
 assert.equal(h.get('programme-age').value,'individual:2');
});
