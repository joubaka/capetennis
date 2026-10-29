const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');
const css = fs.readFileSync('public/css/flexible-monrad.css', 'utf8');

function printRows(matches) {
  const el = (tag, className, text = '') => ({ tag, className, text, style: {}, children: [],
    append(...children) { this.children.push(...children); }, replaceChildren() { this.children = []; } });
  const root = el('div');
  const context = { el, $: () => root, state: { generated: true, matches }, config: {},
    matchParticipantLabel: (match, slot) => match.labels[slot], scheduleLabel: () => '' };
  vm.createContext(context);
  vm.runInContext(source.slice(source.indexOf('  function renderPrint()'), source.indexOf('  async function request(')) + '\nrenderPrint();', context);
  return root.children[0].children[1].children[1].children;
}

test('printed result cells stay empty and writable while feeder names and recorded scores remain', () => {
  const rows = printRows([
    { section: 'Main draw', number: 7, label: 'Final', players: [null, null], labels: ['Winner of Match 3', 'Loser of Match 4'], sets: [] },
    { section: 'Main draw', number: 3, label: 'Semifinal', players: [1, 2], labels: ['Alex Example', 'Sam Example'], sets: [[6, 4], [6, 2]] },
    { section: 'Main draw', number: 4, label: 'Semifinal', players: [1, 2], labels: ['Alex Example', 'Sam Example'], sets: [], automatic: 'walkover' },
  ]);
  assert.equal(rows[0].children[4].text, '');
  assert.equal(rows[0].children[4].className, 'fm-print-result');
  assert.deepEqual(rows[0].children.slice(2, 4).map(cell => cell.text), ['Winner of Match 3', 'Loser of Match 4']);
  assert.equal(rows[1].children[4].text, '6-4, 6-2');
  assert.equal(rows[2].children[4].text, 'Walkover');
});

test('online pending labels are retained and hidden only for print without removing blank space', () => {
  assert.match(source, /winnerName\.textContent = 'Awaiting result'; winnerName\.classList\.add\('fm-pending-result'\)/);
  assert.match(source, /!p\.bye && !p\.vacant \? ' fm-pending-result' : ''/);
  assert.match(css, /@media print\s*\{\s*:where\(\.fm-surface\) \.fm-pending-result \{ visibility: hidden !important; \}/);
  assert.match(css, /\.fm-print-result \{ width: 20%; min-width: 32mm; \}/);
});
