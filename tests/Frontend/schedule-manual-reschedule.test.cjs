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
