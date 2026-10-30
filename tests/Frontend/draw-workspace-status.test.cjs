const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function badges() {
  const badge = textContent => ({textContent, hidden: true, classList: {toggle() {}}});
  const draw = badge('Draw: unpublished'), schedule = badge('Schedule: published'), lock = badge('Locked');
  const document = {querySelectorAll(selector) {
    return {'[data-workspace-draw-status]': [draw], '[data-workspace-lock-status]': [lock], '[data-workspace-schedule-status]': [schedule]}[selector] || [];
  }};
  return {draw, schedule, lock, document};
}

test('round-robin state changes update explicit draw and lock badges without changing schedule publication', () => {
  for (const path of ['public/assets/js/admin/roundrobin/state-badges.js', 'resources/js/admin/roundrobin/state-badges.js']) {
    const controls = badges();
    const chain = {removeClass() {return this;}, addClass() {return this;}, html() {return this;}, prop() {return this;}, toggleClass() {return this;}};
    const context = {...controls, jQuery: () => chain, window: {}};
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context);
    context.window.RRStateBadges.apply(true, false);
    assert.equal(controls.lock.hidden, false); assert.equal(controls.draw.textContent, 'Draw: unpublished');
    assert.equal(controls.schedule.textContent, 'Schedule: published');
    context.window.RRStateBadges.apply(false, true);
    assert.equal(controls.lock.hidden, true); assert.equal(controls.draw.textContent, 'Draw: published');
    assert.equal(controls.schedule.textContent, 'Schedule: published');
  }
});

test('flexible editor draw state keeps lock separate and never paints schedule badge from draw publication', () => {
  const source = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');
  const start = source.indexOf("    document.querySelectorAll('[data-workspace-draw-status]')");
  const end = source.indexOf('    // Fit the complete diagram', start);
  assert.ok(start >= 0 && end > start);
  const controls = badges(), context = {...controls, state: {locked: true, published: false}};
  vm.runInNewContext(source.slice(start, end), context);
  assert.equal(controls.lock.hidden, false);
  assert.equal(controls.draw.textContent, 'Draw: unpublished');
  assert.equal(controls.schedule.textContent, 'Schedule: published');
});
