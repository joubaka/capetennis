const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
function handler(id) {
  if (id === 'programme-create') {
    const start = source.indexOf('  const refreshProgrammePreview = async');
    const end = source.indexOf("  document.querySelectorAll('.draw-choice').forEach", start);
    assert.ok(start >= 0 && end > start, 'Missing programme refresh helper');
    return source.slice(start, end);
  }
  const start = source.indexOf(`  document.getElementById('${id}').addEventListener('click', async event => {`);
  const end = source.indexOf('\n  });', start) + '\n  });'.length;
  assert.ok(start >= 0 && end > start, `Missing ${id} handler`);
  return source.slice(start, end);
}

function harness(id, fail = false) {
  let listener, complete;
  const elements = new Map();
  const element = name => {
    if (!elements.has(name)) elements.set(name, {value:'', disabled:false, addEventListener:(_, callback) => { listener = callback; }});
    return elements.get(name);
  };
  ['duration','rest','gap','gender'].forEach(name => element(`programme-${name}`).value = '30');
  for (let day = 0; day < 3; day++) {
    element(`programme-start-${day}`).value = `2026-10-${9 + day}T08:00`;
    element(`programme-end-${day}`).value = `2026-10-${9 + day}T18:00`;
  }
  element('programme-reuse-source').value = '1';
  const target = {checked:false};
  const messages = [];
  const context = {
    allocationsDirty:false, scheduleDirty:false, programmePayload:null, payload:null, revision:null, previewUrl:'/preview',
    programmeRefreshVersion:0, programmeCreateLabel:'Create programme preview',
    document:{addEventListener:() => {}, getElementById:element, querySelector: selector => selector.includes('[value=') ? target : null,
      querySelectorAll:selector => selector.startsWith('.assignment-choice') ? [{value:'4'}] : []},
    programmeGroup:() => [{id:2, locked:false, published:false}], programmeStatus:(...args) => messages.push(args),
    buildPayload:() => ({start:'single-day start', ...(context.programmePayload || {})}),
    post:() => new Promise((resolve, reject) => { complete = () => fail ? reject(new Error('Preview failed')) : resolve({matches:[],unscheduled:[]}); }),
    saveAllocationsAndTiming:() => new Promise(resolve => { complete = () => resolve(!fail); }),
    refreshProgrammeStageSummaries:() => {}, render:() => {}, showWorkflowStep:() => {}, invalidatePreview:() => {}, updateCourtSummary:() => {}, updateDrawSummary:() => {},
    setStatus:() => {}, startScheduleActivity:() => {}, stopScheduleActivity:() => {}, finishScheduleActivity:() => {}, previewActivityStages:[],
    markAllocationsDirty:() => { context.allocationsDirty = true; }
  };
  vm.runInNewContext(handler(id), context);
  return {context, messages, target, button:element(id), run() {
    const event = {currentTarget:element(id)};
    const pending = listener(event);
    // Native dispatch clears currentTarget before an asynchronous handler resumes.
    event.currentTarget = null;
    assert.equal(element(id).disabled, true);
    complete();
    return pending;
  }};
}

for (const fail of [false, true]) test(`programme preview restores its button after asynchronous ${fail ? 'failure' : 'success'}`, async () => {
  const app = harness('programme-create', fail);
  await app.run();
  assert.equal(app.button.disabled, false);
  assert.equal(app.messages.at(-1)[1], fail ? 'danger' : 'success');
});

test('failed venue reuse keeps the edited allocation pending and restores its button', async () => {
  const app = harness('programme-reuse', true);
  await app.run();
  assert.equal(app.target.checked, true);
  assert.equal(app.context.allocationsDirty, true);
  assert.equal(app.button.disabled, false);
  assert.match(app.messages.at(-1)[0], /not saved/);
});

test('regular combined preview resets a previous age-group programme', async () => {
  const app = harness('generate-preview');
  app.context.programmePayload = {draw_ids:[2], programme:{days:['old programme']}};
  await app.run();
  assert.equal(app.context.programmePayload, null);
  assert.equal(app.context.payload.start, 'single-day start');
  assert.equal(app.context.payload.programme, undefined);
  assert.equal(app.context.payload.draw_ids, undefined);
  assert.equal(app.button.disabled, false);
});


test('real programme payload honors rescheduling and limits venue selection to its age group', () => {
  const start = source.indexOf('  const selectedAssignedVenueIds =');
  const end = source.indexOf('  const post =', start);
  const checkbox = {checked:true};
  const context = {
    programmePayload:{draw_ids:[2,3], programme:{days:[]}, replan_venue_ids:[]},
    roundVenueSetups:[{draw_id:2,round:2,venue_ids:[6]}, {draw_id:9,round:1,venue_ids:[98]}, {draw_id:9,round:2,venue_ids:[97]}],
    replanVenueIds:[99], buildScheduleDraft:() => ({reschedule_existing:checkbox.checked}),
    values:() => [9], readDrawRounds:() => [],
    document:{getElementById:() => checkbox, querySelectorAll:selector => {
      if (selector === '.draw-start') return [];
      if (selector.includes('data-draw="2"')) return [{value:'4'}];
      if (selector.includes('data-draw="3"')) return [{value:'5'}, {value:'4'}];
      return [{value:'99'}];
    }}
  };
  vm.runInNewContext(source.slice(start,end)+'; globalThis.actualPayload = buildPayload;',context);
  assert.deepEqual(Array.from(context.actualPayload().replan_venue_ids),[4,5,6]);
  assert.deepEqual(Array.from(context.actualPayload().draw_ids),[2,3]);
  checkbox.checked=false;
  assert.deepEqual(Array.from(context.actualPayload().replan_venue_ids),[]);
  context.programmePayload=null;
  assert.deepEqual(Array.from(context.actualPayload().replan_venue_ids),[99]);
  checkbox.checked=true;
  context.readDrawRounds=() => [{draw_id:9,rounds:[2]}];
  assert.deepEqual(Array.from(context.actualPayload().replan_venue_ids),[99,97]);
});
