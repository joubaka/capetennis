const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/frontend/my-tennis/index.blade.php', 'utf8');
const script = source.match(/<script>\s*(document.addEventListener\('DOMContentLoaded',[\s\S]*?)<\/script>/)[1];
function harness() {
  let ready, shown, status, content = '', requests = 0, settle, focusRestored = false, sectionRestored = false;
  const urls = [], options = [];
  const focused = {id: 'ctr-rating-gender', focus() {focusRestored = true;}};
  const activeSection = {dataset: {ratingSection: 'explore'}};
  const listeners = {}, attributes = {}, placeholder = {hidden: false};
  const element = () => ({dataset: {}, children: [], setAttribute() {},
    set textContent(value) {this.text = value; this.children = [];},
    get textContent() {return this.text || '';}, append(...children) {this.children.push(...children);} });
  const panel = {dataset: {ratingUrl: '/rating'},
    querySelector(selector) {
      if (selector === '[data-rating-section].active') return activeSection;
      if (selector === '[data-rating-section="explore"]') return activeSection;
      return status;
    },
    contains(element) {return element === focused;},
    querySelectorAll(selector) {return selector.includes('placeholder') ? [placeholder] : [];},
    setAttribute(name, value) {attributes[name] = value;}, removeAttribute(name) {delete attributes[name];},
    prepend(value) {status = value;}, addEventListener(name, callback) {listeners[name] = callback;},
    set innerHTML(value) {content = value; status = undefined;}, get innerHTML() {return content;}
  };
  const document = {createElement: element, addEventListener(name, callback) {ready = callback;},
    activeElement: focused,
    getElementById(id) {return id === focused.id ? focused : id.endsWith('-tab') ? {addEventListener(name, callback) {shown = callback;}} : panel;}, querySelector() {return null;}};
  const context = {document, window: {location: {hash: ''}, PlayerPathwayUI: class {}, PlayerRatingHistoryRefreshUI: class {sync() {}}},
    URL, URLSearchParams, FormData: class {constructor(form) {this.values = Object.entries(form.values);} [Symbol.iterator]() {return this.values[Symbol.iterator]();}},
    bootstrap: {Tab: {getOrCreateInstance() {return {show() {sectionRestored = true;}};}}},
    fetch(url, requestOptions) {requests++; urls.push(url); options.push(requestOptions); return new Promise(resolve => {settle = resolve;});}};
  vm.runInNewContext(script, context); ready();
  return {start: () => shown(), status: () => status, placeholder, attributes, panel, urls, options, requests: () => requests,
    focusRestored: () => focusRestored, sectionRestored: () => sectionRestored,
    change(values, name, hidden = []) {const form = {action: 'https://ct.test/rating', values, elements: {namedItem(key) {return {type: hidden.includes(key) ? 'hidden' : 'select-one'};}}}; listeners.change({target: {name, closest() {return form;}}});},
    respond(ok, html = '<p>Saved ratings</p>') {settle({ok, redirected: false, text: async () => html});},
    retry() {return listeners.click({target: {closest(selector) {return selector === '[data-rating-retry]' ? {} : null;}}});}};
}
test('loading displays a spinner and hides placeholder until successful response', async () => {
  const h = harness(); const request = h.start();
  assert.equal(h.attributes['aria-busy'], 'true'); assert.equal(h.placeholder.hidden, true);
  assert.match(h.status().children[0].className, /spinner-border/);
  assert.match(h.status().children[1], /Loading saved rating history/);
  await h.start(); assert.equal(h.requests(), 1);
  h.respond(true); await request;
  assert.equal(h.status(), undefined); assert.equal(h.attributes['aria-busy'], undefined);
  assert.match(h.panel.innerHTML, /Saved ratings/);
  assert.equal(h.focusRestored(), true);
  assert.equal(h.sectionRestored(), true);
  assert.equal(h.options[0].headers['X-Requested-With'], 'XMLHttpRequest');
});

test('rapid changes keep latest choices across forms and never render stale results', async () => {
  const h = harness();
  const initial = h.start(); h.respond(true); await initial;
  h.change({rating_age: 'u9', rating_gender: 'girls', rating_level: 'red', month: '0', scope: 'all', scale: 'focused'}, 'rating_level');
  h.change({rating_age: 'u9', rating_gender: 'girls', rating_level: 'overall', month: '3', scope: 'all', scale: 'focused'}, 'month');
  h.change({rating_age: 'u9', rating_gender: '', rating_level: 'overall', month: '0', scope: 'all', scale: 'focused'}, 'rating_gender');
  assert.equal(h.requests(), 2, 'Only one filter request runs at a time');
  h.respond(true, '<p>Stale red response</p>');
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.requests(), 3, 'Intermediate selections collapse to latest request');
  assert.doesNotMatch(h.panel.innerHTML, /Stale/);
  const query = new URL(h.urls[2]).searchParams;
  assert.equal(query.get('rating_level'), 'red');
  assert.equal(query.get('month'), '3');
  assert.equal(query.get('rating_gender'), '', 'Explicit all genders survives');
  h.respond(true, '<p>Latest ratings</p>');
  await new Promise(resolve => setImmediate(resolve));
  assert.match(h.panel.innerHTML, /Latest ratings/);
  assert.equal(h.attributes['aria-busy'], undefined);
});
test('failed response removes spinner and offers retry, which restores loading indicator', async () => {
  const h = harness(); const request = h.start(); h.respond(false); await request;
  assert.match(h.status().textContent, /could not be loaded/);
  assert.equal(h.status().children.length, 1); assert.equal(h.status().children[0].textContent, 'Try again');
  assert.equal(h.attributes['aria-busy'], undefined);
  h.retry(); assert.equal(h.requests(), 2); assert.match(h.status().children[0].className, /spinner-border/);
  h.respond(true); await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.attributes['aria-busy'], undefined); assert.equal(h.status(), undefined);
});

test('changing another form after failure preserves the latest requested hidden filters', async () => {
  const h = harness(); const initial = h.start(); h.respond(true); await initial;
  h.change({rating_level: 'red', rating_gender: 'girls', month: '0'}, 'rating_level');
  h.respond(false); await new Promise(resolve => setImmediate(resolve));
  h.change({rating_level: 'overall', rating_gender: 'girls', month: '3'}, 'month', ['rating_level', 'rating_gender']);
  const query = new URL(h.urls[2]).searchParams;
  assert.equal(query.get('rating_level'), 'red');
  assert.equal(query.get('month'), '3');
  h.respond(true); await new Promise(resolve => setImmediate(resolve));
});
