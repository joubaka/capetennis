const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync('resources/js/pages/headOffice.js', 'utf8');
const start = source.indexOf("  $(document).off('click.publish'");
const end = source.indexOf('  // DELETE DRAW', start);
assert.ok(start >= 0 && end > start, 'Publication handler must exist');

function harness({ schedule = false, drawStatus = 0, scheduleStatus = 0 } = {}) {
  let handler;
  let callbacks = {};
  let request;
  const messages = [];
  const labels = {};
  const colors = {};
  const draw = element(drawStatus);
  const times = element(scheduleStatus);
  const card = { find(selector) {
    if (selector === '.toggle-publish') return draw;
    if (selector === '.toggle-publish-schedule') return times;
    return { removeClass() { return this; }, addClass(value) { colors[selector] = value; return this; }, text(value) { labels[selector] = value; } };
  } };
  function element(status) {
    const data = { status, url: '/scoped/publication' };
    return { data(key, value) { if (arguments.length === 2) data[key] = value; return data[key]; },
      prop(key, value) { this[key] = value; return this; },
      html(value) { this.content = value; }, closest() { return card; },
      hasClass(name) { return schedule && name === 'toggle-publish-schedule'; } };
  }
  const button = schedule ? times : draw;
  const document = {};
  const chain = { off() { return this; }, on(event, selector, fn) { handler = fn; } };
  function $(target) { return target === document ? chain : target; }
  $.post = (url, payload) => {
    request = { url, payload };
    const ajax = { done(fn) { callbacks.done = fn; return this; }, fail(fn) { callbacks.fail = fn; return this; }, always(fn) { callbacks.always = fn; return this; } };
    return ajax;
  };
  new Function('$', 'document', 'csrfToken', 'toastr', source.slice(start, end))($, document, 'csrf-value', {
    success: value => messages.push(['success', value]), error: value => messages.push(['error', value])
  });
  handler.call(button);
  return { button, draw, times, labels, colors, messages, request,
    complete(response) { callbacks.done(response); callbacks.always(); },
    fail(xhr) { callbacks.fail(xhr); callbacks.always(); } };
}

test('draw response updates draw state while retaining independent schedule state', () => {
  const ui = harness({ scheduleStatus: 1 });
  assert.equal(ui.button.disabled, true);
  assert.deepEqual(ui.request, { url: '/scoped/publication', payload: { _token: 'csrf-value', status: 0 } });
  ui.complete({ success: true, published: true });
  assert.equal(ui.draw.data('status'), 1);
  assert.equal(ui.times.data('status'), 1);
  assert.equal(ui.labels['.event-schedule-status'], 'Schedule published');
  assert.equal(ui.colors['.event-draw-status'], 'bg-label-success');
  assert.match(ui.button.content, /Hide draw/);
  assert.equal(ui.button.disabled, false);
});

test('schedule reads oop_published and explains preview-only state without publishing draw', () => {
  const ui = harness({ schedule: true });
  ui.complete({ success: true, oop_published: true, preview_only: true });
  assert.equal(ui.times.data('status'), 1);
  assert.equal(ui.draw.data('status'), 0);
  assert.equal(ui.labels['.event-draw-status'], 'Draw hidden');
  assert.equal(ui.colors['.event-draw-status'], 'bg-label-warning');
  assert.equal(ui.labels['.event-schedule-status'], 'Schedule preview only');
  assert.match(ui.messages[0][1], /Publish the draw to make these times public/);
  assert.match(ui.button.content, /Hide schedule/);
  assert.equal(ui.button.disabled, false);
});

test('hiding draw retains schedule flag and changes schedule label to preview-only', () => {
  const ui = harness({ drawStatus: 1, scheduleStatus: 1 });
  ui.complete({ success: true, published: false });
  assert.equal(ui.times.data('status'), 1);
  assert.equal(ui.labels['.event-schedule-status'], 'Schedule preview only');
  assert.match(ui.button.content, /Publish draw/);
});

test('failed response and network failure preserve state and re-enable controls', () => {
  const rejected = harness({ schedule: true, scheduleStatus: 1 });
  rejected.complete({ success: false, message: 'Draw is locked.' });
  assert.equal(rejected.times.data('status'), 1);
  assert.deepEqual(rejected.messages, [['error', 'Draw is locked.']]);
  assert.equal(rejected.button.disabled, false);
  const failed = harness();
  failed.fail({ responseJSON: { message: 'Permission denied.' } });
  assert.equal(failed.draw.data('status'), 0);
  assert.deepEqual(failed.messages, [['error', 'Permission denied.']]);
  assert.equal(failed.button.disabled, false);
});
