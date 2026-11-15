const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/frontend/my-tennis/workspace-assets.blade.php', 'utf8')
  .match(/<script>\s*(function mountMyTennisRating\([\s\S]*?)<\/script>/)[1];

function harness() {
  const handlers = {}, documentHandlers = {}, windowHandlers = {}, pushed = [], requests = [];
  const node = () => ({dataset: {}, classList: {add() {}}, setAttribute() {}, append() {}, focus() {}});
  const button = {...node(), id: 'my-tennis-rating-tab'};
  const panel = {...node(), dataset: {ratingUrl: 'http://ct.test/my-tennis/players/7/rating', ratingWorkspaceUrl: 'http://ct.test/my-tennis?player=7&tab=rating'},
    contains: () => false, querySelectorAll: () => [], addEventListener: (name, fn) => {handlers[name] = fn;},
    querySelector(selector) {
      if (selector === '[data-rating-section].active') return {dataset: {ratingSection: 'progress'}};
      if (selector.startsWith('[data-rating-section="')) return {dataset: {ratingSection: selector.match(/"(.*?)"/)[1]}};
      return null;
    }, prepend() {}, removeAttribute() {}};
  const document = {activeElement: button, getElementById: id => id === button.id ? button : panel,
    querySelector: () => null, createElement: node, addEventListener: (name, fn) => {documentHandlers[name] = fn;},
    dispatchEvent() {}};
  const context = {document, URL, URLSearchParams, AbortController, CustomEvent: class {constructor(type) {this.type = type;}},
    setTimeout: () => 1, clearTimeout() {}, location: {href: 'http://ct.test/my-tennis?player=7&tab=rating'},
    PlayerRatingHistoryRefreshUI: class {sync() {}}, PlayerPathwayUI: class {},
    CapeLoading: {show: () => ({close() {}})}, bootstrap: {Tab: {getOrCreateInstance: element => ({show() {panel.selectedSection = element.dataset.ratingSection;}})}},
    addEventListener: (name, fn) => {windowHandlers[name] = fn;},
    fetch: async (url, options) => {requests.push({url, options}); return context.response || {ok: true, text: async () => '<section>saved</section>'};}};
  context.window = context;
  context.history = {pushState(_state, _title, url) {pushed.push(url); context.location.href = url;}};
  vm.runInNewContext(source, context);
  documentHandlers.DOMContentLoaded();
  const click = url => handlers.click({target: {closest: selector => selector === '[data-rating-link]' ? {getAttribute: () => url} : null}, button: 0, preventDefault() {}});
  const flush = async () => {await new Promise(resolve => setImmediate(resolve));};
  return {context, panel, handlers, windowHandlers, pushed, requests, click, flush};
}

test('chart and evidence links update canonical shareable URL only after a successful fragment', async () => {
  const h = harness();
  h.click('http://ct.test/my-tennis/players/7/rating?month=8&scope=all&scale=full&page=2&rating_section=history');
  await h.flush();
  assert.equal(h.requests[0].options.headers['X-Requested-With'], 'XMLHttpRequest');
  assert.equal(h.panel.selectedSection, 'progress');
  const url = new URL(h.pushed[0]);
  assert.equal(url.pathname, '/my-tennis');
  assert.deepEqual(Object.fromEntries(url.searchParams), {player: '7', tab: 'rating', month: '8', scope: 'all', scale: 'full', page: '2', rating_section: 'history'});
  h.context.response = {ok: false};
  h.click('http://ct.test/my-tennis/players/7/rating?month=9');
  await h.flush();
  assert.equal(h.pushed.length, 1, 'A failed request never changes the shared URL');
});

test('section selection is shareable and popstate cancels stale rating requests without pushing history', async () => {
  const h = harness();
  h.handlers['shown.bs.tab']({target: {dataset: {ratingSection: 'progress'}}});
  assert.equal(new URL(h.pushed[0]).searchParams.get('rating_section'), 'history');
  let finish;
  h.context.response = {ok: true, text: () => new Promise(resolve => {finish = resolve;})};
  h.click('http://ct.test/my-tennis/players/7/rating?month=9&rating_section=history');
  await h.flush();
  h.windowHandlers.popstate();
  assert.equal(h.requests[0].options.signal.aborted, true);
  finish('<section>late</section>');
  await h.flush();
  assert.equal(h.pushed.length, 1, 'Back/Forward cannot be overwritten by a late fragment');
});
