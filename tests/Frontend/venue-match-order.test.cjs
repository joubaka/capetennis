const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
const snippet = source.slice(source.indexOf('  const venueRank ='), source.indexOf('  const ageGroupScheduleSummary'));
const venueRows = new Function('dateKey', snippet + '; return venueRows;')(value => new Date(value.replace(' ', 'T')).getTime());
test('venue preview places earlier dates first and same-time lower rank before court or draw', () => {
 const result = {matches:[
  {fixture_id:1, venue_id:9, scheduled_at:'2026-10-10 07:00:00',rank:1,court:'1'},
  {fixture_id:2, venue_id:9, scheduled_at:'2026-10-09 09:00:00',rank:6,court:'1',draw_id:1},
  {fixture_id:3, venue_id:9, scheduled_at:'2026-10-09 09:00:00',rank:5,court:'99',draw_id:99},
  {fixture_id:4, venue_id:8, scheduled_at:'2026-10-09 07:00:00',rank:1}
 ], existing_matches:[{fixture_id:5,venue_id:9,scheduled_at:'2026-10-09 08:00:00',rank:8,court:'2'}]};
 assert.deepEqual(venueRows(result,9).map(row=>row.fixture_id),[5,3,2,1]);
 assert.equal(result.matches[0].fixture_id,1);
});
