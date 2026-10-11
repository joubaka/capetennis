const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

function scenario(programme = true) {
  let listener, resolveApply;
  const button = {disabled:false, innerHTML:'Save', addEventListener:(_, callback) => {listener=callback;}};
  const messages=[], requests=[], timers=[], redirects=[], rendered=[];
  const context = {
    payload:{draw_ids:[1], ...(programme ? {programme:{days:[{start:'2026-10-09'},{start:'2026-10-10'},{start:'2026-10-11'}],rounds:[{draw_id:1,round:1,day:2,sequence:1}]}} : {})},
    revision:'original-revision', selectedProgrammeAge:'13', programmeEditVersions:new Map([['13',1]]),
    programmeDirtyAges:new Set(programme ? ['13'] : []), programmeSavedAges:new Set(), programmeSettings:new Map(),
    document:{getElementById:id => id==='apply-preview' ? button : {id}},
    readProgrammeOptions:() => ({duration:30}), updateProgrammeSaveState:() => {},
    applyUrl:'/apply', previewUrl:'/preview', drawsUrl:'/saved', replanVenueIds:[],
    post:(url,body) => {requests.push({url,body}); return url==='/apply' ? new Promise(resolve => {resolveApply=resolve;}) : Promise.resolve({matches:[]});},
    buildPayload:() => {if (context.failCurrentControls) throw Error('Later invalid plan'); return {draw_ids:[1]};},
    render:result => rendered.push(result), invalidatePreview:() => {button.disabled=true; context.revision=null;},
    setStatus:(_,message) => messages.push(message), notify:() => {},
    startScheduleActivity:() => {}, setScheduleActivityPhase:() => {}, finishScheduleActivity:() => {}, stopScheduleActivity:() => {},
    applyActivityStages:[], revalidationActivityStages:[],
    window:{setTimeout:callback => timers.push(callback), location:{assign:url => redirects.push(url)}}
  };
  const start=source.indexOf("  document.getElementById('apply-preview').addEventListener");
  const end=source.indexOf('  try {',source.indexOf('    finally {',start));
  vm.runInNewContext(source.slice(start,end),context);
  return {context,button,messages,requests,timers,redirects,rendered,start:() => listener({currentTarget:button}),complete:() => resolveApply({count:3})};
}

test('full apply keeps later same-age edits and skips revalidation of invalid current controls', async () => {
  const app=scenario(); const pending=app.start();
  app.context.programmeEditVersions.set('13',2); app.context.failCurrentControls=true;
  app.context.payload.programme.rounds[0].day=3;
  app.complete(); await pending;
  assert.equal(app.requests.length,1); assert.equal(app.requests[0].body.programme.rounds[0].day,2,'submitted snapshot remains unchanged');
  assert.equal(app.context.programmeDirtyAges.has('13'),true); assert.equal(app.button.disabled,true);
  assert.match(app.messages.at(-1),/Saved 3 matches privately.*remain unsaved/);
  app.timers[0](); assert.deepEqual(app.redirects,[]);
});

test('full apply preserves another age draft and never redirects on an age switch', async () => {
  const app=scenario(); const pending=app.start();
  app.context.selectedProgrammeAge='15'; app.context.programmeDirtyAges.add('15');
  app.complete(); await pending;
  assert.equal(app.context.programmeSavedAges.has('13'),true);
  assert.equal(app.context.programmeDirtyAges.has('13'),false);
  assert.equal(app.context.programmeDirtyAges.has('15'),true);
  assert.equal(app.requests.length,1); app.timers[0](); assert.deepEqual(app.redirects,[]);
});

test('redirect checks edits made after successful revalidation', async () => {
  const app=scenario(); const pending=app.start(); app.complete(); await pending;
  assert.equal(app.requests.length,2); assert.equal(app.rendered.length,1);
  app.context.programmeDirtyAges.add('15'); app.timers[0]();
  assert.deepEqual(app.redirects,[]); assert.equal(app.button.disabled,true);
  assert.match(app.messages.at(-1),/remain unsaved/);
});

for (const programme of [true,false]) test('unchanged '+(programme ? 'programme' : 'general')+' save revalidates and opens saved schedule', async () => {
  const app=scenario(programme); const pending=app.start(); app.complete(); await pending;
  assert.equal(app.requests.length,2); assert.equal(app.rendered.length,1);
  app.timers[0](); assert.deepEqual(app.redirects,['/saved']);
});


test('edits during saved revalidation suppress stale preview errors without losing save success', async () => {
  const app=scenario(); let rejectPreview;
  const originalPost=app.context.post;
  app.context.post=(url,body) => url==='/preview' ? new Promise((_,reject) => {rejectPreview=reject;}) : originalPost(url,body);
  const pending=app.start(); app.complete(); await new Promise(resolve => setImmediate(resolve));
  app.context.programmeEditVersions.set('13',2); app.context.programmeDirtyAges.add('13');
  rejectPreview(Object.assign(new Error('Stale preview'),{stalePreview:true})); await pending;
  assert.match(app.messages.at(-1),/Saved 3 matches privately.*remain unsaved/);
  assert.equal(app.rendered.length,0); assert.equal(app.button.disabled,true);
  app.timers[0](); assert.deepEqual(app.redirects,[]);
});
