const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');

function harness({canSwap = true, readOnly = false, ok = true} = {}) {
  const el = (tag, className = '', text = '') => ({tag, className, text, dataset: {}, children: [], listeners: {},
    append(...nodes) { this.children.push(...nodes); }, replaceChildren() { this.children = []; },
    addEventListener(name, handler) { this.listeners[name] = handler; }});
  const timetable = el('div');
  const context = {config: {canSwap, readOnly, canScore: true, urls: {swapPlayers: '/swap'}},
    state: {revision: 4, generated: true, locked: false, draft: {size: 4, slots: {}}, swappable_registration_ids: [1, 2, 3, 4],
      players: [1, 2, 3, 4].map(id => ({id, name: `Player ${id}`})),
      matches: {a: {number: 1, players: [1, 2], schedule: {time: '2026-10-30 09:00:00', court: '1', venue: 'Courts'}},
        b: {number: 2, players: [3, 4], schedule: {time: '2026-10-30 13:00:00', court: '2', venue: 'Courts'}}}},
    busy: false, dirty: false, history: [], draft: {size: 4, slots: {}}, draggedPlayer: null,
    clone: value => JSON.parse(JSON.stringify(value)), el, $: () => timetable, Date, Intl,
    messages: [], requests: [], renderCount: 0,
    message(text, error) { this.messages.push({text, error}); },
    document: {querySelector: () => ({content: 'csrf'})}, clearDrag() {},
  };
  context.message = (text, error) => context.messages.push({text, error});
  context.matchParticipantLabel = (match, slot) => context.state.players.find(player => player.id === match.players[slot]).name;
  context.fetch = async (url, options) => {
    context.requests.push({url, ...options, body: JSON.parse(options.body)});
    const next = context.clone(context.state);
    next.revision++;
    next.matches.a.players = [3, 2]; next.matches.b.players = [1, 4];
    return {ok, json: async () => ok ? next : {message: 'A player’s first match has started.'}};
  };
  context.render = () => { context.renderCount++; vm.runInContext('renderTimetable()', context); };
  context.editable = () => !context.state.generated && !context.busy;
  vm.createContext(context);
  const helpers = source.slice(source.indexOf('  function draggablePlayer('), source.indexOf('  function allowReturnToList('));
  const requests = source.slice(source.indexOf('  async function request('), source.indexOf('  async function saveDraft('));
  const schedule = source.slice(source.indexOf('  function scheduleParts('), source.indexOf('  function matchParticipantLabel('));
  const renderTimetable = source.slice(source.indexOf('  function renderTimetable('), source.indexOf("  if (window.location.hash === '#schedule')"));
  vm.runInContext(helpers + requests + schedule + renderTimetable, context);
  function label(id, match = {}) {
    const node = el('span'); context.node = node; context.id = id; context.match = match;
    vm.runInContext('generatedPlayerDrag(node, id, match)', context);
    return node;
  }
  function event() {
    return {prevented: false, stopped: false, preventDefault() { this.prevented = true; },
      stopPropagation() { this.stopped = true; }, dataTransfer: {setData() {}, effectAllowed: '', dropEffect: ''}};
  }
  async function swap(from, to) {
    const start = event(); from.listeners.dragstart(start);
    const over = event(); to.listeners.dragover(over);
    const drop = event(); to.listeners.drop(drop);
    await new Promise(setImmediate);
    return {start, over, drop};
  }
  return {context, label, swap, event, timetable};
}

test('generated drag and drop saves revision then renders swapped names at identical times and courts', async () => {
  const h = harness();
  const from = h.label(1), to = h.label(3);
  assert.equal(from.draggable, true);
  const {over, drop} = await h.swap(from, to);
  assert.equal(over.prevented, true); assert.equal(drop.stopped, true);
  assert.equal(h.context.requests.length, 1);
  assert.deepEqual(h.context.requests[0].body, {first_registration_id: 1, second_registration_id: 3, revision: 4});
  assert.equal(h.context.state.revision, 5);
  const rows = h.timetable.children[0].children[1].children;
  assert.deepEqual(rows.map(row => row.children[1].text), ['Player 3 vs Player 2', 'Player 1 vs Player 4']);
  assert.deepEqual(rows.map(row => row.children[3].text), ['09:00', '13:00']);
  assert.deepEqual(rows.map(row => row.children[5].text), ['Court 1', 'Court 2']);
  assert.match(h.context.messages.at(-1).text, /same times and courts/);
});

test('server conflict keeps original names and revision and shows the actual error', async () => {
  const h = harness({ok: false});
  await h.swap(h.label(1), h.label(3));
  assert.equal(h.context.state.revision, 4);
  assert.deepEqual(h.context.state.matches.a.players, [1, 2]);
  assert.equal(h.context.messages.at(-1).error, true);
  assert.match(h.context.messages.at(-1).text, /first match has started/);
});

test('public viewers scorers locked started and display-only bye labels cannot initiate swaps', () => {
  for (const options of [{readOnly: true}, {canSwap: false}]) {
    const h = harness(options); assert.notEqual(h.label(1).draggable, true);
  }
  const h = harness();
  assert.notEqual(h.label(1, {displayOnly: true}).draggable, true);
  assert.notEqual(h.label(null).draggable, true);
  h.context.state.swappable_registration_ids = [3];
  assert.notEqual(h.label(1).draggable, true);
  h.context.state.locked = true;
  assert.notEqual(h.label(3).draggable, true);
});

test('generated swaps do not enable the existing draft drag-to-list mutation path', () => {
  const h = harness(); const button = {listeners: {}, addEventListener(name, callback) {this.listeners[name] = callback;}};
  h.context.button = button;
  vm.runInContext('draggablePlayer(button, 1, true)', h.context);
  const event = h.event(); button.listeners.dragstart(event);
  assert.equal(event.prevented, true); assert.equal(h.context.draggedPlayer, null);
  h.context.state.generated = false;
  const draftEvent = h.event(); button.listeners.dragstart(draftEvent);
  assert.equal(draftEvent.prevented, false); assert.equal(h.context.draggedPlayer, 1);
});
