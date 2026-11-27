const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function screen() {
  class Element {
    constructor(id) { this.id = id; this.dataset = {}; this.attributes = {}; this.handlers = {}; this.options = []; this.value = ''; this.checked = false; this.hidden = false; this.style = {}; this.textContent = ''; this.classes = new Set(); this.classList = {add: value => this.classes.add(value), remove: value => this.classes.delete(value)}; }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener(name, callback) { this.handlers[name] = callback; }
    focus() { focused = this; }
    closest(selector) { return selector === '[data-ll-fixture]' && this.dataset.llFixture ? this : null; }
    contains(value) { return value === this || value === elements.get('playoff-ll-action') && this.id === 'playoff-ll-menu'; }
    getBoundingClientRect() { return {left: 260, bottom: 120}; }
    replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; }
    add(option) { this.options.push(option); }
  }
  const elements = new Map();
  const get = id => { if (!elements.has(id)) elements.set(id, new Element(id)); return elements.get(id); };
  let focused, shown = 0, reloaded = 0, rejectSave = true;
  const handlers = {}, requests = [];
  get('playoff-ll-control').dataset = {candidatesUrl: '/candidates', storeUrl: '/replace', revision: 'reviewed-draw-revision'};
  get('playoff-ll-form').reset = () => { get('playoff-ll-confirm').checked = false; get('playoff-ll-candidate').value = ''; };
  get('playoff-ll-form').reportValidity = () => !!get('playoff-ll-candidate').value && get('playoff-ll-confirm').checked;
  const document = {getElementById: get, addEventListener: (name, callback) => {handlers[name] = callback;}, querySelector: () => ({content: 'csrf-token'})};
  const context = {document, window: {innerWidth: 390, innerHeight: 844}, URL, setTimeout, clearTimeout,
    Option: class {constructor(text, value) {this.text = text; this.value = String(value);}},
    location: {href: 'https://fixture.local/draw', reload: () => {reloaded++;}},
    bootstrap: {Modal: {getOrCreateInstance: () => ({show: () => {shown++;}})}},
    fetch: async (url, options) => {
      requests.push({url: String(url), options});
      if (options.method === 'POST') return {ok: !rejectSave, json: async () => rejectSave ? {message: 'This player already has an active playoff place.'} : {success: true}};
      const search = String(url).includes('q=');
      return {ok: true, json: async () => ({candidates: [{id: search ? 29 : String(url).includes('page=2') ? 18 : 11, name: search ? 'Fictional Search' : 'Fictional Entrant', division: 'Other event division'}], next_page_url: String(url) === '/candidates' ? '/candidates?page=2' : null})};
    }};
  vm.runInNewContext(fs.readFileSync('public/js/playoff-lucky-loser.js', 'utf8'), context);
  function player(id, slot, registration, name) {const element = new Element(name); element.dataset = {llFixture: String(id), llSlot: String(slot), llRegistration: String(registration), llName: name}; return element;}
  async function dispatch(name, target, extra = {}) {await handlers[name]({target, preventDefault() {}, stopPropagation() {}, ...extra});}
  async function click(id) {await get(id).handlers.click({target: get(id)}); await new Promise(resolve => setImmediate(resolve));}
  return {get, requests, player, dispatch, click, focus: () => focused, shown: () => shown, reloaded: () => reloaded,
    acceptSave: () => {rejectSave = false;}};
}

test('player-name menu selects the exact playoff slot, searches event entrants, confirms and retains selection on rejection', async () => {
  const app = screen();
  const player = app.player(42, 2, 87, 'Fictional Outgoing');
  await app.dispatch('keydown', player, {key: 'Enter'});
  assert.equal(player.attributes['aria-expanded'], 'true');
  assert.equal(app.get('playoff-ll-menu').hidden, false);
  assert.equal(app.focus().id, 'playoff-ll-action');
  await app.click('playoff-ll-action');
  assert.equal(app.shown(), 1);
  assert.match(app.get('playoff-ll-outgoing').textContent, /Fictional Outgoing/);
  assert.match(app.get('playoff-ll-candidate').options[1].text, /Other event division/);
  assert.equal(app.get('playoff-ll-more').hidden, false);
  await app.click('playoff-ll-more');
  assert.equal(app.get('playoff-ll-candidate').options.length, 3);
  assert.equal(app.requests[1].url, '/candidates?page=2');
  app.get('playoff-ll-search').value = 'Fictional Search';
  app.get('playoff-ll-search').handlers.input();
  await new Promise(resolve => setTimeout(resolve, 300));
  assert.match(app.requests[2].url, /q=Fictional\+Search/);
  assert.equal(app.get('playoff-ll-candidate').options[1].value, '29');
  app.get('playoff-ll-candidate').value = '29';
  await app.get('playoff-ll-form').handlers.submit({preventDefault() {}});
  assert.equal(app.requests.filter(request => request.options.method === 'POST').length, 0, 'confirmation is required');
  app.get('playoff-ll-confirm').checked = true;
  await app.get('playoff-ll-form').handlers.submit({preventDefault() {}});
  const saved = app.requests.find(request => request.options.method === 'POST');
  assert.deepEqual(JSON.parse(saved.options.body), {target: '42:2:87', source_entry_id: 29, revision: 'reviewed-draw-revision', confirm_withdrawal: true});
  assert.equal(saved.options.headers['X-CSRF-TOKEN'], 'csrf-token');
  assert.match(app.get('playoff-ll-error').textContent, /active playoff place/);
  assert.equal(app.get('playoff-ll-candidate').value, '29');
  assert.equal(app.reloaded(), 0);
  app.acceptSave();
  await app.get('playoff-ll-form').handlers.submit({preventDefault() {}});
  assert.equal(app.reloaded(), 1);
});

test('click and Escape keep the menu bound to the selected player and return keyboard focus', async () => {
  const app = screen();
  const first = app.player(4, 1, 8, 'First Player');
  const second = app.player(5, 2, 9, 'Second Player');
  await app.dispatch('click', first);
  await app.dispatch('click', second);
  assert.equal(first.attributes['aria-expanded'], 'false');
  assert.equal(second.attributes['aria-expanded'], 'true');
  await app.dispatch('keydown', app.get('playoff-ll-action'), {key: 'Escape'});
  assert.equal(app.get('playoff-ll-menu').hidden, true);
  assert.equal(app.focus(), second);
  await app.dispatch('click', second);
  await app.click('playoff-ll-action');
  assert.match(app.get('playoff-ll-outgoing').textContent, /Second Player/);
  assert.equal(second.attributes['aria-expanded'], 'false');
});
