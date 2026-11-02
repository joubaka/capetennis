const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/frontend/my-tennis/index.blade.php', 'utf8');
const script = source.match(/<script>\s*(document.addEventListener\('DOMContentLoaded',[\s\S]*?)<\/script>/)[1];
function harness() {
  let ready, shown, status, content = '', requests = 0, settle, timer, focusRestored = false, sectionRestored = false;
  const dialog = {open: false, shows: 0, closes: 0, addEventListener() {}, showModal() {this.open = true; this.shows++;}, close() {this.open = false; this.closes++;}};
  const age = {hidden: false}, level = {value: 'overall'};
  const urls = [], options = [];
  const focused = {id: 'ctr-rating-gender', focus() {focusRestored = true;}};
  const activeSection = {dataset: {ratingSection: 'explore'}};
  const listeners = {}, attributes = {}, placeholder = {hidden: false};
  const element = () => ({dataset: {}, children: [], setAttribute() {},
    set textContent(value) {this.text = value; this.children = [];},
    get textContent() {return this.text || '';}, append(...children) {this.children.push(...children);} });
  const panel = {dataset: {ratingUrl: '/rating'},
    querySelector(selector) {
      if (selector === '[data-rating-age-filter]') return age;
      if (selector === '[name="rating_level"]:not([type="hidden"])') return level;
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
    getElementById(id) {return id === 'my-tennis-rating-loading' ? dialog : id === focused.id ? focused : id.endsWith('-tab') ? {id, addEventListener(name, callback) {shown = callback;}} : panel;}, querySelector() {return null;}};
  const context = {document, window: {CapeLoading: {show() {dialog.showModal(); return {close() {dialog.close();}};}}, location: {hash: ''}, PlayerPathwayUI: class {}, PlayerRatingHistoryRefreshUI: class {sync() {}}},
    URL, URLSearchParams, AbortController,
    setTimeout(callback, delay) {assert.equal(delay, 30000); timer = callback; return 1;}, clearTimeout() {timer = null;},
    FormData: class {constructor(form) {this.values = Object.entries(form.values);} [Symbol.iterator]() {return this.values[Symbol.iterator]();}},
    bootstrap: {Tab: {getOrCreateInstance() {return {show() {sectionRestored = true;}};}}},
    fetch(url, requestOptions) {requests++; urls.push(url); options.push(requestOptions); return new Promise((resolve, reject) => {settle = resolve; requestOptions.signal.addEventListener('abort', () => reject(new Error('Timed out')));});}};
  vm.runInNewContext(script, context); ready();
  return {start: () => shown(), status: () => status, placeholder, attributes, panel, urls, options, dialog, age, level, requests: () => requests,
    focusRestored: () => focusRestored, sectionRestored: () => sectionRestored,
    expireTimeout() {timer();}, hasTimeout: () => timer !== null,
    change(values, name, hidden = []) {const form = {action: 'https://ct.test/rating', values, elements: {namedItem(key) {return {type: hidden.includes(key) ? 'hidden' : 'select-one'};}}}; listeners.change({target: {name, closest() {return form;}}});},
    respond(ok, html = '<p>Saved ratings</p>') {settle({ok, redirected: false, text: async () => html});},
    paginate(url) {let prevented = false; listeners.click({button: 0, preventDefault() {prevented = true;}, target: {closest(selector) {return selector === '[data-rating-link]' ? {getAttribute() {return url;}} : null;}}}); return prevented;},
    retry() {return listeners.click({target: {closest(selector) {return selector === '[data-rating-retry]' ? {} : null;}}});}};
}
test('loading displays a spinner and hides placeholder until successful response', async () => {
  const h = harness(); const request = h.start();
  assert.equal(h.attributes['aria-busy'], 'true'); assert.equal(h.placeholder.hidden, true);
  assert.equal(h.dialog.open, true);
  assert.match(h.status().children[0].className, /spinner-border/);
  assert.match(h.status().children[1], /Loading saved rating history/);
  await h.start(); assert.equal(h.requests(), 1);
  h.respond(true); await request;
  assert.equal(h.status(), undefined); assert.equal(h.attributes['aria-busy'], undefined);
  assert.match(h.panel.innerHTML, /Saved ratings/);
  assert.equal(h.focusRestored(), true);
  assert.equal(h.sectionRestored(), true);
  assert.equal(h.options[0].headers['X-Requested-With'], 'XMLHttpRequest');
  assert.equal(h.dialog.open, false);
  assert.equal(h.hasTimeout(), false);
});

test('rating pagination uses AJAX and filter changes reset to the first page', async () => {
  const h = harness(); const initial = h.start(); h.respond(true); await initial;
  assert.equal(h.paginate('https://ct.test/rating?rating_page=2&rating_gender=boys&rating_level=overall&rating_age=u9'), true);
  assert.equal(h.dialog.open, true);
  assert.equal(new URL(h.urls[1]).searchParams.get('rating_page'), '2');
  h.change({rating_page: '2', rating_gender: 'girls', rating_level: 'overall', rating_age: 'u9'}, 'rating_gender', ['rating_page']);
  h.respond(true, '<p>Old second page</p>');
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(new URL(h.urls[2]).searchParams.get('rating_page'), '1');
  assert.equal(new URL(h.urls[2]).searchParams.get('rating_gender'), 'girls');
  assert.doesNotMatch(h.panel.innerHTML, /Old second page/);
  h.respond(true, '<p>Filtered first page</p>');
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.dialog.open, false);
});

test('500 player size changes reset page via AJAX and survive cross-form updates after failure', async () => {
  const h = harness(); const initial = h.start(); h.respond(true); await initial;
  h.paginate('https://ct.test/rating?rating_page=2&rating_per_page=25');
  h.change({rating_page: '2', rating_per_page: '500', rating_level: 'overall'}, 'rating_per_page', ['rating_page']);
  h.respond(true); await new Promise(resolve => setImmediate(resolve));
  assert.equal(new URL(h.urls[2]).searchParams.get('rating_page'), '1');
  assert.equal(new URL(h.urls[2]).searchParams.get('rating_per_page'), '500');
  h.respond(false); await new Promise(resolve => setImmediate(resolve));
  h.change({rating_page: '2', rating_per_page: '25', month: '3'}, 'month', ['rating_page', 'rating_per_page']);
  assert.equal(new URL(h.urls[3]).searchParams.get('rating_per_page'), '500');
  assert.equal(new URL(h.urls[3]).searchParams.get('rating_page'), '1');
  h.respond(true); await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.dialog.open, false);
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
  assert.equal(h.dialog.open, true);
  assert.equal(h.dialog.shows, 2, 'Queued requests keep the same modal open');
  assert.doesNotMatch(h.panel.innerHTML, /Stale/);
  const query = new URL(h.urls[2]).searchParams;
  assert.equal(query.get('rating_level'), 'red');
  assert.equal(query.get('month'), '3');
  assert.equal(query.get('rating_gender'), '', 'Explicit all genders survives');
  h.respond(true, '<p>Latest ratings</p>');
  await new Promise(resolve => setImmediate(resolve));
  assert.match(h.panel.innerHTML, /Latest ratings/);
  assert.equal(h.attributes['aria-busy'], undefined);
  assert.equal(h.dialog.open, false);
});
test('failed response removes spinner and offers retry, which restores loading indicator', async () => {
  const h = harness(); const request = h.start(); h.respond(false); await request;
  assert.match(h.status().textContent, /could not be loaded/);
  assert.equal(h.status().children.length, 1); assert.equal(h.status().children[0].textContent, 'Try again');
  assert.equal(h.attributes['aria-busy'], undefined);
  assert.equal(h.dialog.open, false);
  h.retry(); assert.equal(h.requests(), 2); assert.match(h.status().children[0].className, /spinner-border/);
  h.respond(true); await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.attributes['aria-busy'], undefined); assert.equal(h.status(), undefined);
  assert.equal(h.dialog.open, false);
});

test('red and orange immediately hide age groups and overall restores the age control', async () => {
  const h = harness();
  for (const value of ['red', 'orange', 'overall']) {
    h.level.value = value;
    h.change({rating_level: value, rating_age: 'u9'}, 'rating_level');
    assert.equal(h.age.hidden, value !== 'overall');
    assert.equal(h.dialog.open, true);
    h.respond(true); await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.dialog.open, false);
  }
});

test('a stalled request times out and closes the loading modal with retry available', async () => {
  const h = harness(); const request = h.start();
  assert.equal(h.dialog.open, true);
  h.expireTimeout(); await request;
  assert.equal(h.options[0].signal.aborted, true);
  assert.equal(h.dialog.open, false);
  assert.equal(h.hasTimeout(), false);
  assert.match(h.status().textContent, /could not be loaded/);
  assert.equal(h.status().children[0].textContent, 'Try again');
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
