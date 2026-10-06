const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
function block(first, next) {
  const start = source.indexOf(first), end = source.indexOf(next, start);
  assert.ok(start >= 0 && end > start, `Missing ${first}`);
  return source.slice(start, end);
}
const settle = () => new Promise(resolve => setImmediate(resolve));

function scenario() {
  const row = (draw, round, day, order) => {
    const fields = {'.programme-day':{value:day}, '.programme-sequence':{value:order}};
    return {dataset:{programmeDraw:String(draw), programmeRound:String(round)}, querySelector:key => fields[key]};
  };
  const rows = [row(1,1,1,1), row(2,1,1,1), row(1,2,1,2), row(2,2,1,2), row(3,1,2,1)];
  const card = members => ({dataset:{programmeMembers:members}, querySelector: key => {
    if (!card.fields.has(members + key)) card.fields.set(members + key, {});
    return card.fields.get(members + key);
  }});
  card.fields = new Map();
  const singlesOne = card('1:1,2:1'), singlesTwo = card('1:2,2:2'), reverse = card('3:1');
  const lanes = [1,2,3].map(day => ({dataset:{day:String(day)}, cards:[], querySelectorAll(){return this.cards;}}));
  lanes[0].cards = [singlesOne, singlesTwo]; lanes[1].cards = [reverse];
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, {value:'', innerHTML:'Create preview', disabled:false, replaceChildren(){}, classList:{add(){}}});
    return elements.get(id);
  };
  for (let day = 0; day < 3; day++) {
    get(`programme-start-${day}`).value = `2026-10-${String(9+day).padStart(2,'0')}T08:00`;
    get(`programme-end-${day}`).value = `2026-10-${String(9+day).padStart(2,'0')}T18:00`;
  }
  const requests = [], sortables = [], rendered = [];
  const context = {
    document:{getElementById:get, querySelectorAll: selector => selector === '.programme-day-lane' ? lanes : rows},
    programmeGroup:() => [{id:1,rubber_code:'singles'},{id:2,rubber_code:'singles'},{id:3,rubber_code:'reverse_singles'}],
    Sortable:class {constructor(lane, options){sortables.push({lane, options});} destroy(){}},
    refreshProgrammeStageSummaries:() => {}, escapeHtml:String, allocationsDirty:false, programmePayload:null, payload:null, revision:null,
    previewGeneration:0, previewUrl:'/preview', csrf:'test', programmeStatus:() => {}, showWorkflowStep:() => {}, setStatus:() => {}, lastScheduleResult:null,
    buildPayload:() => ({...context.programmePayload}),
    render:result => {rendered.push(result); get('apply-preview').disabled = false;},
    fetch:(url, options) => new Promise(resolve => requests.push({url, body:JSON.parse(options.body), finish:() => resolve({ok:true,json:async()=>({matches:[],unscheduled:[]})})}))
  };
  vm.createContext(context);
  vm.runInContext(block('  const invalidatePreview = ', '  const markAllocationsDirty = '), context);
  vm.runInContext(block('  const post = async', '  const announcementTitle'), context);
  // Extract drag helpers only; venue setup contains server-rendered Blade data.
  vm.runInContext(block('  let programmeSortables = [];', '  const programmeSetupRules = '), context);
  vm.runInContext(block('  const applyProgrammeStageOrder = ', "  document.getElementById('programme-age').addEventListener"), context);
  vm.runInContext(block('  const refreshProgrammePreview = async', '  // Refresh only card details'), context);
  vm.runInContext('globalThis.drawStages = renderProgrammeStages;', context);
  context.drawStages();
  return {context, rows, lanes, singlesOne, singlesTwo, reverse, requests, sortables, rendered, get};
}

test('drag cards group boys and girls and renumber every member after moving within a day', async () => {
  const app = scenario();
  assert.match(app.get('programme-stages').innerHTML, /data-programme-members="1:1,2:1"/);
  assert.match(app.get('programme-stages').innerHTML, /data-programme-members="1:2,2:2"/);
  app.lanes[0].cards = [app.singlesTwo, app.singlesOne];
  app.sortables[0].options.onEnd();
  assert.equal(app.get('apply-preview').disabled, true);
  assert.deepEqual(app.rows.slice(0,4).map(row=>row.querySelector('.programme-sequence').value), [2,2,1,1]);
  assert.equal(app.requests.length, 1);
  assert.equal(app.requests[0].url, '/preview');
  assert.equal(app.requests[0].body.programme.rounds.length, 5);
  app.requests[0].finish(); await settle();
});

test('cross-day drop updates both gender draws and never saves the schedule', async () => {
  const app = scenario();
  app.lanes[0].cards = [app.singlesTwo]; app.lanes[1].cards = [app.reverse, app.singlesOne];
  app.sortables[1].options.onEnd();
  assert.deepEqual(app.rows.slice(0,2).map(row=>row.querySelector('.programme-day').value), ['2','2']);
  assert.deepEqual(app.rows.slice(0,2).map(row=>row.querySelector('.programme-sequence').value), [2,2]);
  assert.equal(app.requests.length, 1);
  assert.equal(app.requests[0].url, '/preview');
  assert.equal(app.requests[0].body.allow_partial, false);
  app.requests[0].finish(); await settle();
});

test('an older AJAX drag response cannot render or enable Save while the latest request is pending', async () => {
  const app = scenario();
  app.sortables[0].options.onEnd();
  app.lanes[0].cards = [app.singlesTwo, app.singlesOne];
  app.sortables[0].options.onEnd();
  assert.equal(app.requests.length, 2);
  app.requests[0].finish(); await settle();
  assert.equal(app.rendered.length, 0);
  assert.equal(app.get('apply-preview').disabled, true);
  assert.equal(app.get('programme-create').disabled, true);
  app.requests[1].finish(); await settle();
  assert.equal(app.rendered.length, 1);
  assert.equal(app.get('apply-preview').disabled, false);
  assert.equal(app.get('programme-create').disabled, false);
});
