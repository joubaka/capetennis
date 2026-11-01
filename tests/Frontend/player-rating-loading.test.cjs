const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/frontend/my-tennis/index.blade.php', 'utf8');
const script = source.match(/<script>\s*(document.addEventListener\('DOMContentLoaded',[\s\S]*?)<\/script>/)[1];
function harness() {
  let ready, shown, status, content = '', requests = 0, settle;
  const listeners = {}, attributes = {}, placeholder = {hidden: false};
  const element = () => ({dataset: {}, children: [], setAttribute() {},
    set textContent(value) {this.text = value; this.children = [];},
    get textContent() {return this.text || '';}, append(...children) {this.children.push(...children);} });
  const panel = {dataset: {ratingUrl: '/rating'},
    querySelector() {return status;},
    querySelectorAll(selector) {return selector.includes('placeholder') ? [placeholder] : [];},
    setAttribute(name, value) {attributes[name] = value;}, removeAttribute(name) {delete attributes[name];},
    prepend(value) {status = value;}, addEventListener(name, callback) {listeners[name] = callback;},
    set innerHTML(value) {content = value; status = undefined;}, get innerHTML() {return content;}
  };
  const document = {createElement: element, addEventListener(name, callback) {ready = callback;},
    getElementById(id) {return id.endsWith('-tab') ? {addEventListener(name, callback) {shown = callback;}} : panel;}, querySelector() {return null;}};
  const context = {document, window: {location: {hash: ''}, PlayerPathwayUI: class {}, PlayerRatingHistoryRefreshUI: class {sync() {}}},
    fetch() {requests++; return new Promise(resolve => {settle = resolve;});}};
  vm.runInNewContext(script, context); ready();
  return {start: () => shown(), status: () => status, placeholder, attributes, panel, requests: () => requests,
    respond(ok) {settle({ok, redirected: false, text: async () => '<p>Saved ratings</p>'});},
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
