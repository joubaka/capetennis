const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function editor(preview = false) {
  const listeners = {};
  let unload;
  const form = {addEventListener: (name, callback) => {listeners[name] = callback;}};
  const source = fs.readFileSync('resources/views/backend/schedule/partials/unsaved-guard.blade.php', 'utf8')
    .replace('<script>', '').replace('</script>', '')
    .replace('@json($editorId)', '"editor"').replace('@json($pendingPreview)', String(preview));
  vm.runInNewContext(source, {document:{getElementById:()=>form}, window:{addEventListener:(_, callback)=>{unload=callback;}}});
  return {listeners, blocked: () => {let blocked=false; unload({preventDefault:()=>{blocked=true;}}); return blocked;}};
}

test('saved editor leaves freely until inputs, choices or tie drops change', () => {
  for (const type of ['input', 'change', 'drop']) {
    const app = editor();
    assert.equal(app.blocked(), false);
    app.listeners[type]();
    assert.equal(app.blocked(), true);
  }
});

test('unsaved preview warns on day or method navigation and valid submit can proceed', () => {
  const app = editor(true);
  assert.equal(app.blocked(), true);
  app.listeners.submit();
  assert.equal(app.blocked(), false);
});

test('workspace guard protects pending work but allows navigation after saving', () => {
  const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
  const start = source.indexOf("  window.addEventListener('beforeunload'");
  const end = source.indexOf('  let scheduleActivityTimer', start);
  let unload;
  const context = {leavingSavedSchedule:false, allocationsDirty:false, scheduleDirty:false, programmeDirtyAges:new Set(), revision:null, lastScheduleResult:null,
    window:{addEventListener:(_, callback)=>{unload=callback;}}};
  vm.runInNewContext(source.slice(start, end), context);
  const blocked = () => {let result=false; unload({preventDefault:()=>{result=true;}}); return result;};
  assert.equal(blocked(), false);
  context.lastScheduleResult = {matches:[]}; context.revision = 'existing-only';
  assert.equal(blocked(), false);
  context.lastScheduleResult.matches.push({fixture_key:'team:1'});
  assert.equal(blocked(), true);
  context.leavingSavedSchedule = true;
  assert.equal(blocked(), false);
  context.leavingSavedSchedule = false; context.revision = null;
  context.programmeDirtyAges.add('13');
  assert.equal(blocked(), true);
});

test('day navigation reveals the active day horizontally without scrolling the page', () => {
  const source = fs.readFileSync('resources/views/backend/schedule/partials/day-navigation-script.blade.php', 'utf8')
    .replace('<script>', '').replace('</script>', '');
  const nav = {scrollLeft:0, getBoundingClientRect:()=>({left:20,right:340}),
    querySelector:()=>({getBoundingClientRect:()=>({left:500,right:700})})};
  vm.runInNewContext(source, {document:{querySelectorAll:()=>[nav]}});
  assert.equal(nav.scrollLeft, 360);
  nav.querySelector = () => ({getBoundingClientRect:()=>({left:0,right:200})});
  vm.runInNewContext(source, {document:{querySelectorAll:()=>[nav]}});
  assert.equal(nav.scrollLeft, 340);
});
