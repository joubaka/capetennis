const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function preview(active = false) {
  const html = new Map(), handlers = new Map(), reads = [];
  const document = {};
  function $(value) {
    const selector = value?.selector || value;
    const chain = {
      on(event, target, callback) { handlers.set(`${event}:${typeof target === 'string' ? target : selector}`, callback || target); return chain; },
      html(value) { html.set(selector, value); return chain; },
      load(url, callback) { reads.push(url); callback.call({selector}, '', 'error'); return chain; },
      css() { return chain; }, text() { return chain; },
      hasClass(name) { return name === 'active' && active && ['#main-bracket-pane', '#main-bracket-tab'].includes(selector); },
      attr(name) { return value?.[name]; }
    };
    return chain;
  }
  const window = {};
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/brackets.js', 'utf8'), {
    jQuery: $, window, document,
    AdminRoutes: {appUrl: () => ''}, AdminState: {on() {}},
    AdminApi: {post() { throw new Error('Preview must never generate fixtures'); }}
  });
  window.RRBrackets.init(42);
  return {html, handlers, reads, window, activate(value) { active = value; }};
}

test('loading and retrying playoff preview only reads and gives a clear failure action', () => {
  const {html, handlers, reads, window} = preview();
  window.RRBrackets.loadMainBracket();
  assert.match(html.get('#bracket-zoom-inner'), /Could not load the playoffs/);
  assert.match(html.get('#bracket-zoom-inner'), /Try again/);
  handlers.get('click:#rr-retry-bracket')();
  assert.deepEqual(reads, ['/backend/draw/42/main-bracket?force=0', '/backend/draw/42/main-bracket?force=0']);
});

test('refreshing an already restored playoff tab loads once even when shown arrives later', () => {
  const {reads, handlers} = preview(true);
  assert.equal(reads.length, 1);
  handlers.get('shown.bs.tab:button[data-bs-toggle="tab"]')({target: {id: 'main-bracket-tab'}});
  assert.equal(reads.length, 1);
});

test('other initial tabs stay lazy and revisiting playoffs reloads the preview', () => {
  const page = preview();
  const shown = page.handlers.get('shown.bs.tab:button[data-bs-toggle="tab"]');
  const hide = page.handlers.get('hide.bs.tab:button[data-bs-toggle="tab"]');
  assert.equal(page.reads.length, 0);
  page.activate(true);
  shown({target: {id: 'main-bracket-tab'}});
  assert.equal(page.reads.length, 1);
  hide({target: {id: 'main-bracket-tab'}});
  page.activate(false);
  shown({target: {id: 'matrix-tab'}});
  assert.equal(page.reads.length, 1);
  page.activate(true);
  shown({target: {id: 'main-bracket-tab'}});
  assert.equal(page.reads.length, 2);
});
