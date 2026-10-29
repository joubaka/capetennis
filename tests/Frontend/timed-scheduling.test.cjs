const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');

test('timeline ends use each timed match changeover, preserving explicit zero and saved booking bounds', () => {
  const start = source.indexOf('  const matchEnd =');
  const end = source.indexOf('  const venueRank =', start);
  const context = {asDate: value => new Date(value.replace(' ', 'T')), dateKey: value => new Date(value.replace(' ', 'T')).getTime()};
  vm.runInNewContext(source.slice(start, end) + ';globalThis.matchEnd = matchEnd;', context);
  const scheduled_at = '2026-10-30 08:00:00';
  const result = {input: {duration: 60, courtGap: 10}};
  const elapsed = row => (context.matchEnd({scheduled_at, ...row}, result).getTime() - context.dateKey(scheduled_at)) / 60000;
  assert.equal(elapsed({duration: 20, court_gap: 5}), 25);
  assert.equal(elapsed({duration: 20, court_gap: 0}), 20);
  assert.equal(elapsed({duration: 60}), 70);
  assert.equal(elapsed({duration: 20, court_gap: 5, ends_at: '2026-10-30 08:37:00'}), 37);
});
