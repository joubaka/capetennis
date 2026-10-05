const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
const start = source.indexOf('  const escapeHtml =');
const end = source.indexOf("  const ", source.indexOf("  }).join('');", start) + 15);
const render = new Function(`${source.slice(start, end)}; return lineupDetails;`)();
test('lineup details escape every player and region beneath unchanged path labels', () => {
  const output = render({lineup:{home:{region:'WC<script>',players:[{name:'A & B',rank:3},{name:'<img>',rank:4}]},away:{region:'KZN',players:[{name:'Imported Player',rank:null}]}}});
  assert.match(output, /Rank 3 · A &amp; B \(WC&lt;script&gt;\)/);
  assert.match(output, /Rank 4 · &lt;img&gt;/);
  assert.match(output, /Rank unassigned · Imported Player \(KZN\)/);
  assert.doesNotMatch(output, /<script>|<img>/);
  assert.ok(source.includes("'Participants determined by draw')}${lineupDetails(row)}</td>"));
  assert.ok(source.includes('${escapeHtml(row.reason)}${lineupDetails(row)}</li>'));
});
test('missing players use TBD and individual matches keep their existing presentation', () => {
  assert.equal(render({}), '');
  assert.match(render({lineup:{home:{region:'',players:[]}}}), /Rank unassigned · TBD/);
});
