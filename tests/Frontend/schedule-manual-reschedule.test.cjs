const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

test('manual reschedule refresh keeps saved matches fixed and includes the new day', () => {
  const controls = new Map(['reschedule-existing', 'programme-reschedule-existing'].map(id => [id, {checked:true}]));
  const context = {programmePayload:{programme:{days:[{}]}}, replanVenueIds:[7],
    document:{getElementById:id => controls.get(id)},
    buildPayload:() => ({start:'2026-10-30T08:00', end:'2026-10-30T18:00', programme:{days:[{}]}, replan_venue_ids:[7], reschedule_existing:true}),
  };
  const start = source.indexOf('  const fixedBookingPreviewPayload =');
  const end = source.indexOf('  const placeMatchManually =', start);
  vm.runInNewContext(source.slice(start, end)+';globalThis.preview = fixedBookingPreviewPayload;', context);
  const payload = context.preview('2026-10-31T09:00');
  assert.equal(payload.programme, undefined);
  assert.equal(payload.reschedule_existing, false);
  assert.deepEqual(Array.from(payload.replan_venue_ids), []);
  assert.equal(payload.end, '2026-10-31T23:59');
  assert.equal(context.programmePayload, null);
  assert.deepEqual(Array.from(context.replanVenueIds), []);
  assert.equal(controls.get('reschedule-existing').checked, false);
  assert.equal(controls.get('programme-reschedule-existing').checked, false);
  const earlier = context.preview('2026-10-29T10:00');
  assert.equal(earlier.start, '2026-10-29T00:00');
  const sameDayEarlier = context.preview('2026-10-30 07:00:00');
  assert.equal(sameDayEarlier.start, '2026-10-30T00:00');
  const sameDayLater = context.preview('2026-10-30T19:00');
  assert.equal(sameDayLater.end, '2026-10-30T23:59');
  const atEnd = context.preview('2026-10-30T18:00');
  assert.equal(atEnd.end, '2026-10-30T23:59');
});

test('daily manual refresh keeps its moved fixture identity and day court pool',()=>{
 const controls=new Map(['reschedule-existing','programme-reschedule-existing'].map(id=>[id,{checked:true}]));
 const original={day_batch:true,full_day_scope:'2026-10-30',start:'2026-10-30T08:00',end:'2026-10-30T18:00',
  draw_ids:[1,3],draw_order:[1,3],draw_order_mode:'sequential',selected_fixture_keys:['individual:1','team:1'],
  venue_ids:[7],day_court_labels:{7:['2']},reschedule_existing:true,full_day_tie_destinations:{stale:{venue_id:7}}};
 const context={programmePayload:null,replanVenueIds:[7],document:{getElementById:id=>controls.get(id)},buildPayload:()=>({...original}),
  lastScheduleResult:{existing_matches:[{fixture_key:'team:1',draw_id:3,round:2}]},fixtureKey:row=>row.fixture_key};
 const start=source.indexOf('  const fixedBookingPreviewPayload =');
 const end=source.indexOf('  const placeMatchManually =',start);
 vm.runInNewContext(source.slice(start,end)+';globalThis.preview=fixedBookingPreviewPayload;',context);
 const payload=context.preview('2026-10-31T09:00','team:1');
 assert.equal(payload.full_day_scope,'2026-10-31');assert.equal(payload.draw_order_mode,'mixed');
 assert.deepEqual(Array.from(payload.draw_ids),[3]);assert.deepEqual(Array.from(payload.selected_fixture_keys),['team:1']);
 assert.deepEqual(JSON.parse(JSON.stringify(payload.day_court_labels)),{7:['2']});
 assert.equal(payload.reschedule_existing,false);assert.equal(payload.full_day_tie_destinations,undefined);
 assert.deepEqual(original.draw_ids,[1,3],'refresh must not change the original draw selection');
});
