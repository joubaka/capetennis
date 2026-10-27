const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

function block(startMarker, endMarker) {
  const start = source.indexOf(startMarker), end = source.indexOf(endMarker, start);
  assert.ok(start >= 0 && end > start);
  return source.slice(start, end);
}
function harness(plugin = true) {
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, {value:'', disabled:false, focusCalls:0, focus(){this.focusCalls++;}, classList:{toggle(){}}, listeners:{}, addEventListener(name,fn){this.listeners[name]=fn;}});
    return elements.get(id);
  };
  const select = get('new-venue-id'), modal = get('venue-management-modal');
  const calls = [], options = [];
  let enhanced = false;
  const control = {
    hasClass:() => enhanced,
    select2(value){if(typeof value === 'object'){options.push(value);enhanced=true;}else calls.push(value);return this;},
    trigger(name){calls.push({name,value:select.value,disabled:select.disabled});return this;},
  };
  const parent = {};
  const jquery = element => element === modal ? parent : control;
  jquery.fn = plugin ? {select2(){}} : {};
  const context = {window:{jQuery:jquery}, document:{getElementById:get,querySelectorAll:()=>[]}, existingVenue:select, newVenueName:get('new-venue-name')};
  vm.runInNewContext(block('  const venueModal =', '  let venueManagementChanged ='),context);
  vm.runInNewContext(block('  const setVenueMode =', "\n  document.querySelectorAll('[data-venue-mode]').forEach")+';globalThis.mode=setVenueMode;',context);
  return {get,select,modal,parent,calls,options,context,show:()=>modal.listeners['shown.bs.modal']()};
}

test('venue search initializes inside its modal once across reopenings', () => {
  const app = harness();
  app.select.value='7'; app.show(); app.show();
  assert.equal(app.options.length,1);
  assert.equal(app.options[0].dropdownParent,app.parent);
  assert.equal(app.options[0].minimumResultsForSearch,0);
  assert.equal(app.options[0].width,'100%');
  assert.equal(app.select.value,'7','enhancement preserves the venue used by the save action');
});
test('switching venue modes synchronizes disabled and cleared search state', () => {
  const app=harness(); app.show(); app.select.value='7';
  app.context.mode('new');
  assert.equal(app.select.disabled,true); assert.equal(app.select.value,'');
  assert.equal(app.get('new-venue-name').disabled,false);
  assert.deepEqual(app.calls,['close',{name:'change.select2',value:'',disabled:true}]);
  app.calls.length=0; app.context.mode('existing');
  assert.equal(app.select.disabled,false);
  assert.deepEqual(app.calls,['close',{name:'change.select2',value:'',disabled:false},'open']);
});
test('venue selection stays usable when Select2 is unavailable', () => {
  const app=harness(false); app.show(); app.context.mode('existing');
  assert.equal(app.options.length,0); assert.equal(app.select.focusCalls,1);
  app.context.mode('new'); assert.equal(app.get('new-venue-name').focusCalls,1);
});
