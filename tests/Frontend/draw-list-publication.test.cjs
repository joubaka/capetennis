const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/draw-list-publication.js', 'utf8');

function harness(selectedIds, disabled = false, initiallyDisabled = false) {
  const calls = [];
  const target = { disabled: initiallyDisabled };
  const inputs = ['1', '2', '3'].map(value => ({ value, checked: true, dispatchEvent() { target.disabled = !inputs.some(input => input.checked); calls.push('change'); } }));
  const buttons = ['selected', 'all'].map(scope => ({ dataset: { drawListPublish: scope }, addEventListener(event, handler) { this.click = handler; } }));
  const panel = {
    open: false,
    querySelector(selector) { target.click = () => calls.push(selector.includes(':not') ? 'all' : 'selected'); return target; },
    querySelectorAll() { return inputs; },
    scrollIntoView() { calls.push('scroll'); },
  };
  const document = {
    querySelector() { return panel; },
    querySelectorAll(selector) { return selector.includes(':checked') ? selectedIds.map(value => ({ value })) : buttons; },
  };
  new Function('window', 'document', source)({ HeadOfficeDrawPublicationPending: disabled }, document);
  return { buttons, panel, inputs, calls };
}

test('selected publication replaces stale panel selection with checked draw cards', () => {
  const ui = harness(['2']);
  ui.buttons[0].click();
  assert.deepEqual(ui.inputs.map(input => input.checked), [false, true, false]);
  assert.equal(ui.panel.open, true);
  assert.deepEqual(ui.calls, ['change', 'change', 'change', 'selected', 'scroll']);
});

test('publish all invokes the existing event-wide action without changing selection', () => {
  const ui = harness([]);
  ui.buttons[1].click();
  assert.deepEqual(ui.calls, ['all', 'scroll']);
});

test('empty selection clears stale choices and opens guidance without publishing', () => {
  const ui = harness([]);
  ui.buttons[0].click();
  assert.ok(ui.inputs.every(input => !input.checked));
  assert.ok(!ui.calls.includes('selected'));
  assert.equal(ui.panel.open, true);
});

test('initially disabled selected action is enabled after card choices are synchronized', () => {
  const ui = harness(['2'], false, true);
  ui.buttons[0].click();
  assert.ok(ui.calls.includes('selected'));
});

test('pending or unconfirmed publication cannot be bypassed by toolbar actions', () => {
  const ui = harness(['1'], true);
  ui.buttons.forEach(button => button.click());
  assert.deepEqual(ui.calls, []);
  assert.equal(ui.panel.open, false);
});

test('setup guide starts collapsed and retains native keyboard toggle', () => {
  const view = fs.readFileSync('resources/views/backend/headOffice/partials/organiser-setup.blade.php', 'utf8');
  assert.match(view, /<details>\s*<summary/);
  assert.doesNotMatch(view, /<details[^>]*\bopen\b/);
  assert.match(view, /Show \/ hide setup/);
});
