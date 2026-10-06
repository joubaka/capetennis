const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

test('programme gender release stays synchronized and invalidates the preview through the shared control', () => {
  const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
  const start = source.indexOf("  document.getElementById('gender-wave-release').addEventListener('change'");
  const end = source.indexOf("  document.querySelectorAll('.draw-choice').forEach", start);
  assert.ok(start >= 0 && end > start);
  const controls = new Map();
  let invalidations = 0;
  for (const id of ['gender-wave-release', 'programme-gender-wave-release']) {
    controls.set(id, {
      value: 'whole_wave', listeners: [],
      addEventListener(_, callback) { this.listeners.push(callback); },
      dispatchEvent(event) { for (const listener of this.listeners) listener({...event, currentTarget: this}); }
    });
  }
  const shared = controls.get('gender-wave-release');
  shared.addEventListener('change', () => invalidations++);
  vm.runInNewContext(source.slice(start, end), {
    document: {getElementById: id => controls.get(id)},
    Event: class {constructor(type, options) { this.type = type; this.bubbles = options.bubbles; }}
  });
  const wizard = controls.get('programme-gender-wave-release');
  wizard.value = 'court_ready';
  wizard.dispatchEvent({type: 'change'});
  assert.equal(shared.value, 'court_ready');
  assert.equal(invalidations, 1);
  shared.value = 'whole_wave';
  shared.dispatchEvent({type: 'change'});
  assert.equal(wizard.value, 'whole_wave');
  assert.equal(invalidations, 2);
});
