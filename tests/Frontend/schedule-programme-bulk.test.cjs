const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

function scenario() {
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, {value:'', checked:false, innerHTML:'', addEventListener(_, fn) { this.listener = fn; }, dispatchEvent() { this.listener(); }});
    return elements.get(id);
  };
  const choices = [10,12].map(age => ({value:String(age),checked:true,addEventListener(_, fn) {this.listener=fn;}}));
  const draws = [10,12].flatMap((age, index) => ['singles','reverse_singles','doubles','mixed_doubles'].map((code, phase) => ({
    id:index*4+phase+1,programme_age:String(age),rubber_code:code,name:`Under ${age} ${code}`,is_team:true,rounds:[1,2],venues:[index+1]
  })));
  const rows = () => [...get('programme-rounds').innerHTML.matchAll(/<tr data-programme-draw="(\d+)" data-programme-round="(\d+)">([\s\S]*?)<\/tr>/g)].map(match => {
    const day = match[3].match(/<option value="(\d+)" selected/)[1];
    const sequence = match[3].match(/class="form-control programme-sequence"[^>]*value="(\d+)"/)[1];
    return {dataset:{programmeDraw:match[1],programmeRound:match[2]},querySelector:selector => ({value:selector === '.programme-day' ? day : sequence})};
  });
  const context = {document:{getElementById:get,querySelectorAll:selector => selector === '.programme-bulk-age:checked' ? choices.filter(input => input.checked) : selector === '.programme-bulk-age' ? choices : [],addEventListener() {}},
    programmeDraws:draws,programmeSettings:new Map(),selectedProgrammeAge:'',programmePayload:null,
    programmeDates:['2026-10-09'],programmeDayIndexes:[0],programmeDayNumbers:[1],programmeDayCount:1,
    programmeMatchOverrides:new Map(),readProgrammeMatchOverrides:() => [],programmeRows:rows,
    invalidatePreview:()=>{},renderProgrammeStages:()=>{},updateProgrammeDayPublicationLinks:()=>{},programmeStatus:()=>{},
    updateProgrammeSaveState:()=>{},updateProgrammeDayLabels:()=>{},programmeDayLabel:day=>`Day ${day}`,escapeHtml:String,markProgrammeDirty:()=>{},Event:class {}};
  get('programme-start-0').value='2026-10-09T08:00'; get('programme-end-0').value='2026-10-09T18:00';
  vm.createContext(context);
  let start=source.indexOf('  const programmeOptionControls ='),end=source.indexOf('  const programmeSavedAges =',start);
  vm.runInContext(source.slice(start,end),context);
  start=source.indexOf('  const programmeGroup =');end=source.indexOf('  const programmeStatus =',start);
  vm.runInContext(source.slice(start,end)+'globalThis.programmeGroup=programmeGroup;',context);
  start=source.indexOf("  document.getElementById('programme-age').addEventListener('change'");end=source.indexOf("  document.querySelectorAll('.programme-time')",start);
  vm.runInContext(source.slice(start,end),context);
  get('programme-age').value='bulk'; get('programme-age').listener();
  return {context,choices,get,rows,draws};
}

test('bulk selects complete checked age groups and defaults to all singles then reverse then doubles then mixed', () => {
  const app=scenario();
  assert.equal(app.context.programmeGroup().length,8);
  assert.equal(app.get('programme-bulk-ages').hidden,false);
  for (const first of [1,5]) {
    const phases=[0,1,2,3].map(offset => app.rows().filter(row => Number(row.dataset.programmeDraw) === first+offset).map(row => Number(row.querySelector('.programme-sequence').value)));
    assert.deepEqual(phases,[[1,2],[3,4],[5,6],[7,8]]);
  }
  app.choices[1].checked=false;app.choices[1].listener();
  assert.equal(app.context.programmeGroup().length,4);
  assert.ok(app.rows().every(row => Number(row.dataset.programmeDraw) <=4));
});

test('bulk checkbox toggles retain each age round draft', () => {
  const app=scenario();
  app.get('programme-rounds').innerHTML=app.get('programme-rounds').innerHTML.replace('value="1" aria-label="Order for Under 12 singles', 'value="9" aria-label="Order for Under 12 singles');
  app.choices[1].checked=false;app.choices[1].listener();
  app.choices[1].checked=true;app.choices[1].listener();
  const row=app.rows().find(row => Number(row.dataset.programmeDraw) ===5 && Number(row.dataset.programmeRound) ===1);
  assert.equal(Number(row.querySelector('.programme-sequence').value),9);
});

test('bulk loads saved round plans from individual age settings', () => {
  const app=scenario();
  app.context.programmeSettings.set('12',{programme:{days:[{start:'2026-10-09 08:00',end:'2026-10-09 18:00'}],rounds:[{drawId:5,round:1,day:1,sequence:9}]}});
  app.context.programmeSettings.delete('bulk'); app.context.selectedProgrammeAge='';
  app.get('programme-age').listener();
  const row=app.rows().find(row => Number(row.dataset.programmeDraw) ===5 && Number(row.dataset.programmeRound) ===1);
  assert.equal(Number(row.querySelector('.programme-sequence').value),9);
});
