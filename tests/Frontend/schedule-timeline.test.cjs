const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {createState, mount} = require('../../public/js/schedule-timeline.js');
const start = '2026-10-30 08:00:00', end = '2026-10-30 12:00:00';
const at = time => `2026-10-30 ${time}:00`;
const base = ['08:00', '09:00', '10:00'].map(at);
const labels = dates => dates.map(date => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`);

test('empty row edits reorder targets, support repeat edits and merge duplicate timestamps per venue', () => {
  const state = createState();
  state.edit(1, at('09:00'), at('10:30'), start, end);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '10:00', '10:30']);
  state.edit(1, at('10:30'), at('08:30'), start, end);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '08:30', '10:00']);
  state.edit(1, at('08:30'), at('10:00'), start, end);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '10:00']);
  assert.deepEqual(labels(state.times(2, base, [], start, end)), ['08:00', '09:00', '10:00']);
  state.edit(1, at('10:00'), at('08:00'), start, end);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00']);
});

test('occupied starts stay visible and no fixture data changes', () => {
  const state = createState(), booking = {scheduled_at:at('09:00'), court:'1'};
  state.edit(1, at('09:00'), at('09:15'), start, end);
  assert.deepEqual(labels(state.times(1, base.map(value => new Date(value.replace(' ', 'T'))), [booking.scheduled_at], new Date(start.replace(' ', 'T')), new Date(end.replace(' ', 'T')))), ['08:00', '09:00', '09:15', '10:00']);
  assert.equal(booking.scheduled_at, at('09:00'));
  state.reset();
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '09:00', '10:00']);
});

test('invalid and out-of-window times reject without changing targets', () => {
  const state = createState();
  for (const time of ['invalid', at('07:59'), end]) assert.throws(() => state.edit(1, at('09:00'), time, start, end), /scheduling window/);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '09:00', '10:00']);
});

test('editor cancellation does not change state; submitting applies once and stale context rejects', () => {
  const elements = new Map();
  const element = id => {
    if (!elements.has(id)) elements.set(id, {listeners:{}, addEventListener(name, fn) { this.listeners[name] = fn; }, querySelectorAll:() => [], querySelector:() => null, focus(){}});
    return elements.get(id);
  };
  let current = {name:'Test venue', start, end}, changed = 0, shown = 0, hidden = 0;
  const state = createState();
  mount({document:{getElementById:element}, bootstrap:{Modal:{getOrCreateInstance:() => ({show(){shown++;},hide(){hidden++;}})}}, state, context:() => current, onChange:() => changed++});
  const open = () => element('venue-slot-grids').listeners.click({target:{closest:() => ({dataset:{venue:'1', time:at('09:00')}})}});
  const submit = () => element('edit-timeline-form').listeners.submit({preventDefault(){}});
  open();
  element('edit-timeline-time').value = '2026-10-30T09:15';
  element('editTimelineModal').listeners['hidden.bs.modal']();
  submit();
  assert.equal(changed, 0);
  assert.deepEqual(labels(state.times(1, base, [], start, end)), ['08:00', '09:00', '10:00']);
  open(); element('edit-timeline-time').value = '2026-10-30T09:15'; submit();
  assert.equal(changed, 1); assert.equal(shown, 2); assert.equal(hidden, 1);
  open(); current = {...current, version:{}}; submit();
  assert.match(element('edit-timeline-status').textContent, /window changed/);
  assert.equal(changed, 1);
  current = null; submit();
  assert.equal(changed, 1);
});

test('real grid rerenders custom drop targets with occupancy and original bookings intact', () => {
  const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
  const state = createState();
  state.edit(1, at('09:00'), at('09:15'), start, end);
  const rows = [{scheduled_at:at('09:00'), ends_at:at('10:00'), court:'1', draw_name:'Test draw', fixture_id:1, round:1, fixed:true, editable:false}];
  const context = {timelineState:state, asDate:value => new Date(value.replace(' ', 'T')), venueRows:() => rows,
    matchEnd:row => new Date(row.ends_at.replace(' ', 'T')), dateKey:value => new Date(value.replace(' ', 'T')).getTime(),
    escapeHtml:String, assignmentTime:date => date.toLocaleString('sv-SE'), formatSlotTime:date => date.toLocaleString('sv-SE'),
    matchLabel:() => 'Match 1', venueActions:() => '', ageGroupScheduleSummary:() => '', unapplyUrl:null};
  vm.runInNewContext(source.slice(source.indexOf('  const timelineWindow ='), source.indexOf('  function venueChangeWarnings'))+';globalThis.grid=slotGrid;', context);
  const html = context.grid({input:{start,end,duration:60}}, {id:1,name:'Test venue',court_labels:['1','2'],courts:2});
  assert.match(html, /data-edit-timeline/);
  assert.match(html, /data-time="2026-10-30 09:15:00"/);
  assert.match(html, /In use/);
  assert.equal((html.match(/Test draw/g) || []).length, 1);
  assert.match(source, /previewGeneration\+\+;\s+timelineState.reset\(\)/);
});
