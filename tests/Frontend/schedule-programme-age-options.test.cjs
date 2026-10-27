const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

for (const key of ['draw_id', 'drawId']) test(`age selection restores saved options and round order using ${key}, and keeps unsaved edits on switching`, () => {
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, {value:'', checked:false, innerHTML:'', addEventListener(_, callback) { this.listener = callback; }});
    return elements.get(id);
  };
  for (let day = 0; day < 3; day++) {
    get(`programme-start-${day}`).value = `2026-10-${String(9+day).padStart(2,'0')}T08:00`;
    get(`programme-end-${day}`).value = `2026-10-${String(9+day).padStart(2,'0')}T18:00`;
  }
  get('gender-waves').value = 'combined';
  const settings = new Map([['13', {duration:85, player_rest:45, court_gap:7, gender_wave_release:'court_ready', reschedule_existing:true,
    programme:{days:[0,1,2].map(day => ({start:`2026-10-${String(9+day).padStart(2,'0')} 09:30:00`, end:`2026-10-${String(9+day).padStart(2,'0')} 17:15:00`, gender_waves:'girls_then_boys', break_start:`2026-10-${String(9+day).padStart(2,'0')} 12:00:00`, break_end:`2026-10-${String(9+day).padStart(2,'0')} 13:00:00`})), rounds:[{[key]:1, round:1, day:2, sequence:8}]} }], ['15', {programme:{days:[0,1,2].map(day => ({start:`2026-10-${String(9+day).padStart(2,'0')}T08:00`,end:`2026-10-${String(9+day).padStart(2,'0')}T18:00`})), rounds:[{[key]:2,round:1,day:3,sequence:1}]}}]]);
  let rows = [];
  const context = {document:{getElementById:get, addEventListener() {}}, programmeSettings:settings, selectedProgrammeAge:'', programmePayload:null,
    programmeDates:['2026-10-09','2026-10-10','2026-10-11'], programmeDayIndexes:[0,1,2], programmeDayNumbers:[1,2,3], programmeDayCount:3,
    programmeMatchOverrides:new Map(), readProgrammeMatchOverrides:() => [],
    programmeGroup:() => get('programme-age').value === '13' ? [{id:1,name:'Mixed doubles',rounds:[1],venues:[]}] : [{id:2,name:'Mixed doubles',rounds:[1],venues:[]}],
    programmeRows:() => rows, invalidatePreview:() => {}, renderProgrammeStages:() => {}, updateProgrammeDayPublicationLinks:() => {}, programmeStatus:() => {}, updateProgrammeSaveState:() => {}, updateProgrammeDayLabels:() => {}, programmeDayLabel:day => `Day ${day}`, escapeHtml:value => value};
  const setupStart = source.indexOf('  const programmeOptionControls =');
  const setupEnd = source.indexOf('  const programmeSavedAges =', setupStart);
  vm.runInNewContext(source.slice(setupStart, setupEnd), context);
  const start = source.indexOf("  document.getElementById('programme-age').addEventListener('change'");
  const end = source.indexOf("  document.querySelectorAll('.programme-time')", start);
  vm.runInNewContext(source.slice(start, end), context);
  get('programme-age').value = '13'; get('programme-age').listener();
  assert.equal(get('programme-start-0').value, '2026-10-09T09:30');
  assert.equal(get('programme-end-0').value, '2026-10-09T17:15');
  assert.equal(get('programme-break-start-0').value, '12:00');
  assert.equal(get('programme-break-end-0').value, '13:00');
  assert.equal(get('programme-gender-0').value, 'girls_then_boys');
  assert.equal(get('programme-duration').value, 85);
  assert.equal(get('schedule-duration').value, 85);
  assert.equal(get('programme-rest').value, 45);
  assert.equal(get('schedule-rest').value, 45);
  assert.equal(get('programme-gap').value, 7);
  assert.equal(get('schedule-gap').value, 7);
  assert.equal(get('programme-gender-wave-release').value, 'court_ready');
  assert.equal(get('gender-wave-release').value, 'court_ready');
  assert.equal(get('programme-reschedule-existing').checked, true);
  assert.equal(get('reschedule-existing').checked, true);
  assert.match(get('programme-rounds').innerHTML, /value="2" selected/);
  assert.match(get('programme-rounds').innerHTML, /value="8"/);
  get('programme-start-0').value = '2026-10-09T10:45';
  rows = [{dataset:{programmeDraw:'1',programmeRound:'1'},querySelector:selector => ({value:selector === '.programme-day' ? '3' : '9'})}];
  get('programme-age').value = '15'; get('programme-age').listener();
  assert.equal(get('programme-break-start-0').value, '');
  assert.equal(get('programme-break-end-0').value, '');
  assert.equal(get('programme-start-0').value, '2026-10-09T08:00');
  assert.equal(get('programme-end-0').value, '2026-10-09T18:00');
  assert.equal(get('programme-gender-0').value, 'combined');
  assert.match(get('programme-rounds').innerHTML, /value="3" selected/);
  assert.equal(get('programme-duration').value, '');
  assert.equal(get('programme-reschedule-existing').checked, false);
  rows = [];
  get('programme-age').value = '13'; get('programme-age').listener();
  assert.equal(get('programme-start-0').value, '2026-10-09T10:45');
  assert.match(get('programme-rounds').innerHTML, /value="3" selected/);
  assert.match(get('programme-rounds').innerHTML, /value="9"/);
});


test('programme labels use selected dates and save status keeps later edits pending', () => {
  const elements = new Map(['programme-age','programme-save-state', 'programme-start-0','programme-start-1','programme-start-2'].map(id => [id, {value:'',textContent:''}]));
  elements.get('programme-age').value='13';
  ['09','10','11'].forEach((day,index) => elements.get('programme-start-'+index).value='2026-10-'+day+'T08:00');
  const days = ['09','10','11'].map(day => ({start:`2026-10-${day}T08:00`,end:`2026-10-${day}T18:00`}));
  const context = {document:{getElementById:id => elements.get(id)}, programmeDayCount:3, programmeDates:['2026-10-09','2026-10-10','2026-10-11'], programmeSettings:new Map([['13',{programme:{days,rounds:[1]}}],['15',{programme:{days,rounds:[1]}}]])};
  const start = source.indexOf('  const programmeSavedAges =');
  const end = source.indexOf("  let selectedProgrammeAge =",start);
  vm.runInNewContext(source.slice(start,end)+'; globalThis.api={programmeDayLabel,markProgrammeDirty,updateProgrammeSaveState,programmeDirtyAges,programmeSavedAges,programmeEditVersions};',context);
  assert.equal(context.api.programmeDayLabel(2),'Saturday 10 October');
  assert.equal(context.api.programmeDayLabel(3),'Sunday 11 October');
  context.api.updateProgrammeSaveState(); assert.match(elements.get('programme-save-state').textContent,/Saved programme loaded/);
  context.api.markProgrammeDirty(); assert.match(elements.get('programme-save-state').textContent,/Unsaved/);
  const submittedEditVersion=context.api.programmeEditVersions.get('13');
  elements.get('programme-age').value='15'; context.api.updateProgrammeSaveState();
  assert.match(elements.get('programme-save-state').textContent,/Under 15: Saved/);
  elements.get('programme-age').value='13'; context.api.markProgrammeDirty();
  Object.assign(context,{submittedProgramme:{days:[1,2,3]},submittedAge:'13',submittedOptions:{duration:30},submittedEditVersion});
  Object.assign(context,context.api);
  const applyStart=source.indexOf('      if (submittedProgramme?.days?.length');
  const applyEnd=source.indexOf('      replanVenueIds =',applyStart);
  vm.runInNewContext(source.slice(applyStart,applyEnd),context);
  assert.equal(context.api.programmeDirtyAges.has('13'),true,'later edit remains pending');
  assert.equal(context.programmeSettings.get('13').duration,undefined,'newer draft was not overwritten');
  context.submittedEditVersion=context.api.programmeEditVersions.get('13');
  vm.runInNewContext(source.slice(applyStart,applyEnd),context);
  assert.equal(context.api.programmeDirtyAges.has('13'),false);
  assert.equal(context.programmeSettings.get('13').duration,30);
});
