const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('loading and retrying playoff preview only reads and gives a clear failure action', () => {
  const html = new Map(), handlers = new Map(), reads = [];
  const document = {};
  function $(value) {
    const selector = value?.selector || value;
    const chain = {
      on(event, target, callback) { handlers.set(`${event}:${typeof target === 'string' ? target : selector}`, callback || target); return chain; },
      html(value) { html.set(selector, value); return chain; },
      load(url, callback) { reads.push(url); callback.call({selector}, '', 'error'); return chain; },
      css() { return chain; }, text() { return chain; }
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
  window.RRBrackets.loadMainBracket();
  assert.match(html.get('#bracket-zoom-inner'), /Could not load the playoffs/);
  assert.match(html.get('#bracket-zoom-inner'), /Try again/);
  handlers.get('click:#rr-retry-bracket')();
  assert.deepEqual(reads, ['/backend/draw/42/main-bracket?force=0', '/backend/draw/42/main-bracket?force=0']);
});
