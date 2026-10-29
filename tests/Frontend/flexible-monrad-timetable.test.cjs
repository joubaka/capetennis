const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');

function timetable(matches, readOnly = true) {
  const el = (tag, className, text = '') => ({tag, className, text, dataset: {}, children: [],
    append(...children) { this.children.push(...children); }, replaceChildren() { this.children = []; }});
  const root = el('div');
  const context = {el, $: () => root, state: {matches}, config: {readOnly},
    matchParticipantLabel: (_, slot) => ['Player A', 'Player B'][slot], Intl, Date};
  vm.createContext(context);
  const parts = source.slice(source.indexOf('  function scheduleParts('), source.indexOf('  function matchParticipantLabel('));
  const render = source.slice(source.indexOf('  function renderTimetable('), source.indexOf("  if (window.location.hash === '#schedule')"));
  vm.runInContext(parts + render + '\nrenderTimetable();', context);
  return {rows: root.children[0].children[1].children, context};
}

test('unscheduled and hidden timetable cells stay blank without leaking venue details', () => {
  const {rows} = timetable([
    {number: 1, players: [1, 2]},
    {number: 2, players: [1, 2], schedule: {venue: 'Unassigned venue', court: '3'}},
    {number: 3, players: [1, 2], schedule_hidden: true,
      schedule: {time: '2026-10-30 13:30:00', venue: 'Private venue', court: '8'}},
  ], false);
  for (const row of rows) {
    assert.deepEqual(row.children.slice(2).map(cell => cell.text), ['', '', '', '']);
  }
});

test('scheduled timetable retains date time venue and admin court, with venue in bracket label', () => {
  const schedule = {time: '2026-10-30 13:30:00', venue: 'Bloemhof', court: '2'};
  const {rows, context} = timetable([{number: 1, players: [1, 2], schedule}], false);
  assert.match(rows[0].children[2].text, /30.*Oct.*2026/);
  assert.equal(rows[0].children[3].text, '13:30');
  assert.equal(rows[0].children[4].text, 'Bloemhof');
  assert.equal(rows[0].children[5].text, 'Court 2');
  context.schedule = schedule;
  assert.match(vm.runInContext('scheduleLabel(schedule, true)', context), /13:30.*Bloemhof/);
  const publicRows = timetable([{number: 1, players: [1, 2], schedule}]).rows;
  assert.equal(publicRows[0].children.length, 5);
  assert.equal(publicRows[0].children[4].text, 'Bloemhof');
});
