const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function ui({confirmed = true, reviews, failures = {}} = {}) {
  function node(value) { return {value, checked: false, disabled: false, listeners: {}, addEventListener(event, handler) { this.listeners[event] = handler; }, setAttribute() {}, click() { return this.listeners.click?.({stopPropagation() {}}); }, change() { return this.listeners.change?.(); }}; }
  const controls = Object.fromEntries(['submit', 'select-all', 'clear', 'count', 'feedback'].map(key => [key, node()]));
  const inputs = [node('11'), node('22')];
  const toolbar = {dataset: {eventId: '7', previewUrl: '/preview', deleteUrl: '/delete'}, setAttribute() {},
    querySelector(selector) { return controls[selector.match(/data-draw-delete-([^\]]+)/)[1]]; },
    querySelectorAll() { return [controls.submit, controls['select-all'], controls.clear]; }};
  const requests = [], confirmations = [], reloads = [];
  const window = {location: {reload() { reloads.push(true); }}, Swal: {async fire(options) { confirmations.push(options); return {isConfirmed: confirmed}; }},
    async fetch(url, options) {
      const body = JSON.parse(options.body); requests.push({url, method: options.method, body});
      const result = reviews || {success: true, event_id: 7, revision: 'a'.repeat(64), draws: body.draw_ids.map(id => ({id, name: 'Draw ' + id})), individual_fixtures: 0, team_fixtures: 12, deleted: options.method === 'DELETE'};
      const failure = failures[options.method];
      return {ok: !failure, async json() { return failure ? {message: failure} : result; }};
    }};
  const document = {querySelector(selector) { return selector.startsWith('meta') ? {content: 'csrf'} : toolbar; }, querySelectorAll() { return inputs; }};
  vm.runInNewContext(fs.readFileSync('public/js/bulk-draw-deletion.js', 'utf8'), {window, document});
  return {controls, inputs, window, requests, confirmations, reloads};
}

test('Select all includes every draw checkbox and clear resets count and delete action', () => {
  const view = ui();
  assert.equal(view.controls.submit.disabled, true);
  view.controls['select-all'].click();
  assert.equal(view.controls.count.textContent, '2 draws selected');
  assert.equal(view.controls.submit.disabled, false);
  view.controls.clear.click();
  assert.equal(view.controls.count.textContent, '0 draws selected');
  assert.equal(view.controls.submit.disabled, true);
});

test('deletion reviews exact server names and sends one signed selected batch before refreshing', async () => {
  const view = ui(); view.controls['select-all'].click();
  await view.controls.submit.click();
  assert.equal(view.requests.length, 2);
  assert.deepEqual(view.requests[0], {url: '/preview', method: 'POST', body: {draw_ids: [11, 22]}});
  assert.deepEqual(view.requests[1], {url: '/delete', method: 'DELETE', body: {draw_ids: [11, 22], revision: 'a'.repeat(64)}});
  assert.match(view.confirmations[0].title, /2 selected draws/);
  assert.match(view.confirmations[0].text, /Draw 11\nDraw 22/);
  assert.match(view.confirmations[0].text, /12 team fixtures/);
  assert.equal(view.reloads.length, 1);
});

test('cancelling confirmation never submits deletion and preserves selected draws', async () => {
  const view = ui({confirmed: false}); view.controls['select-all'].click();
  await view.controls.submit.click();
  assert.equal(view.requests.length, 1);
  assert.equal(view.reloads.length, 0);
  assert.equal(view.inputs.every(input => input.checked && !input.disabled), true);
  assert.match(view.controls.feedback.textContent, /cancelled/);
});

test('a failed preview or mismatched event/selection cannot reach deletion', async () => {
  for (const options of [{failures: {POST: 'Protected draw'}}, {reviews: {success: true, event_id: 8, revision: 'a'.repeat(64), draws: [{id: 11, name: 'Wrong event'}]}}]) {
    const view = ui(options); view.controls['select-all'].click();
    await view.controls.submit.click();
    assert.equal(view.requests.length, 1);
    assert.equal(view.confirmations.length, 0);
    assert.equal(view.reloads.length, 0);
    assert.equal(view.controls.submit.disabled, false);
  }
});

test('an uncertain deletion is never repeated automatically or reported as completed', async () => {
  const view = ui({failures: {DELETE: 'Request interrupted'}}); view.controls['select-all'].click();
  await view.controls.submit.click();
  assert.equal(view.requests.length, 2);
  assert.equal(view.reloads.length, 0);
  assert.match(view.controls.feedback.textContent, /Refresh the draw list before retrying/);
});

test('pending deletion blocks duplicate requests and simultaneous publication actions', async () => {
  const view = ui(); view.controls['select-all'].click();
  const pending = view.controls.submit.click();
  await view.controls.submit.click();
  assert.equal(view.requests.length, 1);
  assert.equal(view.window.HeadOfficeDrawPublicationPending, true);
  await pending;
  assert.equal(view.requests.length, 2);
  assert.equal(view.window.HeadOfficeDrawPublicationPending, false);
});
