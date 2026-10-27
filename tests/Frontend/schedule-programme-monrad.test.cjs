const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

for (const size of [8,16,32]) test('individual Monrad '+size+' uses a separate saved group and chronological three-day defaults', () => {
  const elements = new Map();
  const get = id => { if (!elements.has(id)) elements.set(id, {value:'',innerHTML:'',addEventListener(_,fn){this.listener=fn;}}); return elements.get(id); };
  get('programme-age').value='monrad:19';
  const rounds=Array.from({length:Math.log2(size)},(_,i)=>i+1);
  for (let day=0;day<3;day++) {
    get(`programme-start-${day}`).value=`2026-10-${String(9+day).padStart(2,'0')}T08:00`;
    get(`programme-end-${day}`).value=`2026-10-${String(9+day).padStart(2,'0')}T18:00`;
  }
  const days=[0,1,2].map(day=>({start:get(`programme-start-${day}`).value,end:get(`programme-end-${day}`).value}));
  const context={document:{getElementById:get,addEventListener(){}},programmeSettings:new Map(),selectedProgrammeAge:'',programmePayload:null,
    programmeDates:['2026-10-09','2026-10-10','2026-10-11'],programmeDayIndexes:[0,1,2],programmeDayNumbers:[1,2,3],programmeDayCount:3,
    programmeMatchOverrides:new Map(),readProgrammeMatchOverrides:()=>[],
    programmeDraws:[{id:1,programme_age:'monrad:19',name:'Girls',is_team:false,rounds,venues:[]},{id:2,programme_age:'19',name:'Team',is_team:true,rounds:[1],venues:[]}],
    programmeRows:()=>[],invalidatePreview:()=>{},renderProgrammeStages:()=>{},updateProgrammeDayPublicationLinks:()=>{},programmeStatus:()=>{},updateProgrammeSaveState:()=>{},updateProgrammeDayLabels:()=>{},programmeDayLabel:day=>'Day '+day,escapeHtml:String};
  vm.createContext(context);
  let start=source.indexOf('  const programmeOptionControls ='),end=source.indexOf('  const programmeSavedAges =',start);
  vm.runInContext(source.slice(start,end),context);
  start=source.indexOf('  const programmeGroup =');end=source.indexOf('\n',start);
  vm.runInContext(source.slice(start,end)+';globalThis.programmeGroup=programmeGroup;',context);
  assert.equal(context.programmeGroup().length,1);
  start=source.indexOf("  document.getElementById('programme-age').addEventListener('change'");end=source.indexOf("  document.querySelectorAll('.programme-time')",start);
  vm.runInContext(source.slice(start,end),context);
  get('programme-age').listener();
  const html=get('programme-rounds').innerHTML;
  const daySelections=[...html.matchAll(/<option value="([123])" selected/g)].map(match=>Number(match[1]));
  assert.deepEqual(daySelections,rounds.map(round=>Math.min(3,Math.floor((round-1)*3/rounds.length)+1)));
  context.programmeSettings.set('monrad:19',{duration:70,programme:{days,rounds:[{drawId:1,round:1,day:3,sequence:9}]}});
  context.selectedProgrammeAge='';get('programme-age').listener();
  assert.equal(get('programme-duration').value,70);
  assert.match(get('programme-rounds').innerHTML,/value="3" selected/);
  assert.match(get('programme-rounds').innerHTML,/value="9"/);
});
