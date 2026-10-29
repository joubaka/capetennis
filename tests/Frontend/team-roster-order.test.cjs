const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {plan, apply} = require('../../public/js/team-roster-order.js');

function row(id, rank, player = 0) {
  const badge = {textContent: String(rank)};
  const hidden = [{value: String(rank)}, {value: String(rank)}];
  return {
    dataset: {rosterRow: String(id), rosterImportedId: String(id), rosterPlayerId: player ? String(player) : '', occupied: 'true'},
    badge, hidden, editor: {value: 'Unsaved name'}, open: true,
    matches: () => true,
    querySelector: selector => selector.includes('confirm_main') ? null : badge,
    querySelectorAll: selector => selector.includes('.badge') ? [badge] : hidden,
  };
}

test('canonical order validates identities, unique ranks and unlinked names before editing rows', () => {
  const rows = [row(10, 1, 44), row(11, 2)];
  const order = [{slot_id: 11, rank: 1, player_id: 0}, {slot_id: 10, rank: 2, player_id: 44}];
  assert.deepEqual(plan(rows, order, 'imported').map(item => item.row), [rows[1], rows[0]]);
  assert.equal(rows[0].badge.textContent, '1');
  assert.equal(plan(rows, [{...order[0], player_id: 99}, order[1]], 'imported'), null);
  assert.equal(plan(rows, [order[0], {...order[1], rank: 1}], 'imported'), null);
  assert.equal(plan(rows, order.slice(0, 1), 'imported'), null);
});

test('AJAX order moves the same rows and linked details, preserving editors and updating all stale-rank guards', () => {
  const first = row(10, 1, 44), second = row(11, 2);
  const detail = owner => ({dataset: {rosterDetailRow: owner.dataset.rosterRow}, open: true, matches: () => false,
    hidden: [{value: owner.badge.textContent}], querySelectorAll(selector) { return selector.includes('input') ? this.hidden : []; }});
  const one = detail(first), two = detail(second);
  const body = {children: [first, one, second, two], append(...nodes) {
    for (const node of nodes) { this.children.splice(this.children.indexOf(node), 1); this.children.push(node); }
  }};
  for (const node of body.children) Object.defineProperty(node, 'nextElementSibling', {get: () => body.children[body.children.indexOf(node) + 1]});
  const panel = {dataset: {regionId: '129'}}, other = {dataset: {regionId: '129', loaded: 'true'}};
  const form = {closest: selector => selector.includes('team]') ? {querySelector: () => body} : panel};
  global.document = {querySelectorAll: () => [panel, other]};
  global.scrollX = 0; global.scrollY = 750;
  let scroll;
  global.scrollTo = (...position) => { scroll = position; };
  const order = [{slot_id: 11, rank: 1, player_id: 0}, {slot_id: 10, rank: 2, player_id: 44}];
  assert.equal(apply(form, {order, order_type: 'imported'}), true);
  assert.deepEqual(body.children, [second, two, first, one]);
  assert.deepEqual(first.hidden.map(input => input.value), ['2', '2']);
  assert.equal(one.hidden[0].value, '2');
  assert.equal(first.editor.value, 'Unsaved name');
  assert.equal(one.open, true);
  assert.equal(other.dataset.loaded, undefined);
  assert.deepEqual(scroll, [0, 750]);
  assert.equal(apply(form, {order: [{slot_id: 10, rank: 1, player_id: 44}, {slot_id: 11, rank: 2, player_id: 0}], order_type: 'imported'}), true);
  assert.deepEqual(body.children, [first, one, second, two]);
});

function submissionHarness({failure = false} = {}) {
  const listeners = {}, requests = [], successes = [];
  let feedback;
  const button = {disabled: false, isConnected: true, focus() { this.focused = true; }};
  const panel = {dataset: {}, querySelectorAll: () => [button]};
  const form = {dataset: {}, elements: {direction: {value: 'down'}, expected_rank: {value: '1'}},
    closest: () => panel, getAttribute: () => '/backend/team/orderPlayerList', contains: () => true,
    parentElement: {querySelector: () => feedback}, after: node => { feedback = node; }};
  const window = {toastr: {success: message => successes.push(message)},
    refreshTeamRoster() { throw new Error('A confirmed ordinary move must not refresh the roster.'); },
    applyTeamRosterOrder(target, data) { target.elements.expected_rank.value = String(data.rank); return true; }};
  const document = {addEventListener: (name, handler) => { listeners[name] = handler; },
    createElement: () => ({dataset: {}, setAttribute() {}, remove() { feedback = null; }})};
  const source = fs.readFileSync('resources/views/backend/adminPage/admin_show/_roster-script.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
  vm.runInNewContext(source, {window, document, FormData: class {constructor(target) { this.rank = target.elements.expected_rank.value; }},
    fetch: async (url, options) => {
      requests.push({url, rank: options.body.rank});
      return {ok: !failure, status: failure ? 422 : 200, json: async () => failure
        ? {errors: {roster: ['This player changed.']}} : {rank: Number(options.body.rank) + 1}};
    }, clearTimeout() {}, setTimeout() {}, Option: class {}});
  const submit = () => listeners.submit({target: {closest: () => form}, submitter: button, preventDefault() {}});
  return {submit, requests, successes, form, button, feedback: () => feedback};
}

test('repeated arrows POST via AJAX using the new rank, without navigation or roster refresh', async () => {
  const h = submissionHarness();
  await h.submit(); await h.submit();
  assert.deepEqual(h.requests.map(request => request.rank), ['1', '2']);
  assert.equal(h.successes.length, 2);
  assert.equal(h.form.dataset.saved, undefined);
  assert.equal(h.form.dataset.busy, undefined);
  assert.equal(h.button.disabled, false);
  assert.equal(h.button.focused, true);
});

test('a rejected move keeps the old rank and enables a safe retry', async () => {
  const h = submissionHarness({failure: true});
  await h.submit();
  assert.equal(h.form.elements.expected_rank.value, '1');
  assert.equal(h.form.dataset.saved, undefined);
  assert.equal(h.button.disabled, false);
  assert.equal(h.feedback().textContent, 'This player changed.');
});
