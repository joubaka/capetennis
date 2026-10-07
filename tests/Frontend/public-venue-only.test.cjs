const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');

function body(source, name) {
  return source.match(new RegExp(`function ${name}\\([^)]*\\) \\{([\\s\\S]*?)\\n  \\}`))[1];
}

test('public round-robin schedule labels retain venue and time without court identifiers', () => {
  for (const path of ['resources/assets/js/roundrobin-public.js', 'public/assets/js/roundrobin-public.js']) {
    const source = fs.readFileSync(path, 'utf8');
    const label = new Function('formatScheduleParts', 'fx', body(source, 'formatDayTimeVenue'))
      .bind(null, () => ({date: 'Fri 9 Oct', time: '09:15'}));
    assert.equal(label({venue_name: 'Hermanus High School', court: 'PUBLIC-COURT-SENTINEL'}),
      'Fri 9 Oct 09:15 · Hermanus High School');
  }
});

test('Monrad public timetable excludes courts while organiser timetable retains allocations', () => {
  const source = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');
  function render(readOnly) {
    const root = {children: [], replaceChildren() { this.children = []; }, append(node) { this.children.push(node); }};
    const el = (tag, css, text) => ({tag, text, dataset: {}, children: [], append(node) { this.children.push(node); }});
    new Function('$', 'el', 'state', 'config', 'matchParticipantLabel', 'scheduleParts', body(source, 'renderTimetable'))(
      () => root, el,
      {matches: {one: {number: 1, players: [1, 2], schedule: {venue: 'Hermanus High School', court: 'PUBLIC-COURT-SENTINEL'}}}},
      {readOnly}, (match, slot) => `Player ${slot + 1}`, () => ({date: 'Fri 9 Oct', time: '09:15'})
    );
    return JSON.stringify(root);
  }
  assert.match(render(true), /Hermanus High School/);
  assert.doesNotMatch(render(true), /PUBLIC-COURT-SENTINEL|"Court"/);
  assert.match(render(false), /PUBLIC-COURT-SENTINEL/);
});
