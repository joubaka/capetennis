const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function harness(file, {secondPlayer = false, invalidField = false} = {}) {
  const handlers = new Map(), requests = [], messages = [];
  const field = (value, required = true) => ({value, required, props: {}, attrs: {}, classes: new Set()});
  function card(id) {
    const form = {fields: {'.pd-name': field('Fictional'), '.pd-surname': field('Player'),
      '.pd-dob': field('2010-01-01'), '.pd-gender': field('Female'), '.pd-cell': field('0000000000'),
      '.pd-email': field('test@example.test', false)}};
    const node = {attrs: {id: `confirm-card-${id}`, 'data-confirmed': '0'}, classes: new Set(['border-warning']),
      form, badge: {attrs: {}, classes: new Set(['bg-warning']), html: 'Pending'},
      heading: {attrs: {}, classes: new Set(), html: 'Fictional Player'}};
    node.button = {attrs: {'data-player-id': String(id)}, props: {disabled: false}, html: 'Save & Confirm', card: node};
    return node;
  }
  const cards = [card(7), ...(secondPlayer ? [card(8)] : [])];
  if (invalidField) cards[0].form.fields['.pd-name'].value = '';
  const cart = {props: {disabled: true}}, document = {querySelector: () => ({querySelectorAll: () => []})};
  let currentCard = cards[0];
  class Wrapper {
    constructor(nodes = []) { this.nodes = nodes; this.length = nodes.length; nodes.forEach((node, index) => { this[index] = node; }); }
    each(fn) { this.nodes.forEach((node, index) => fn.call(node, index)); return this; }
    find(selector) {
      const node = this[0]; if (!node) return new Wrapper();
      if (selector === '.player-detail-form') return new Wrapper([node.form]);
      if (selector === '.confirm-badge') return new Wrapper([node.badge]);
      if (selector === 'h6') return new Wrapper([node.heading]);
      if (selector === '.btn-save-player') return new Wrapper([node.button]);
      if (selector === '.btn-confirm-player') return new Wrapper();
      if (selector === '.player-detail-form input, .player-detail-form select') return new Wrapper(Object.values(node.form.fields));
      if (selector === 'input, select') return new Wrapper(Object.values(node.fields));
      if (selector === '[required]') return new Wrapper(Object.values(node.fields).filter(item => item.required));
      if (node.fields?.[selector]) return new Wrapper([node.fields[selector]]);
      return new Wrapper();
    }
    closest() { return new Wrapper([this[0].card]); }
    attr(key, value) { if (arguments.length === 1) return this[0]?.attrs?.[key]; return this.each(function () { (this.attrs ??= {})[key] = value; }); }
    removeAttr(key) { return this.each(function () { delete this.attrs?.[key]; }); }
    prop(key, value) { if (arguments.length === 1) return this[0]?.props?.[key]; return this.each(function () { (this.props ??= {})[key] = value; }); }
    val() { return this[0]?.value; }
    html(value) { if (arguments.length === 0) return this[0]?.html; return this.each(function () { this.html = value; }); }
    hide() { return this.each(function () { this.hidden = true; }); }
    addClass(value) { return this.each(function () { value.split(' ').forEach(name => this.classes.add(name)); }); }
    removeClass(value) { return this.each(function () { value.split(' ').forEach(name => this.classes.delete(name)); }); }
    on(event, selector, handler) { if (typeof handler === 'function') handlers.set(`${event}:${selector}`, handler); return this; }
  }
  function $(selector) {
    if (typeof selector === 'function') { selector(); return new Wrapper(); }
    if (typeof selector === 'object') return new Wrapper([selector]);
    if (selector === '#goToCart') return new Wrapper([cart]);
    if (selector === '.confirm-player-card') return new Wrapper(cards);
    if (selector === '#confirm-card-7') return new Wrapper(currentCard ? [currentCard] : []);
    if (selector === '#confirm-card-8') return new Wrapper(cards[1] ? [cards[1]] : []);
    if (selector.startsWith('meta[')) return new Wrapper([{attrs: {content: 'csrf-test'}}]);
    return new Wrapper();
  }
  $.ajax = options => { requests.push(options); };
  $._data = () => ({});
  vm.runInNewContext(fs.readFileSync(file, 'utf8'), {document, window: {}, $, APP_URL: 'https://fixture.test',
    Stepper: class {}, toastr: {success: text => messages.push(text), error: text => messages.push(text)}});
  return {cards, cart, requests, messages,
    click(index = 0) { handlers.get('click:.btn-save-player').call(cards[index].button); },
    success(index = 0, body) { const request = requests[index]; request.success(body ?? {success: true, player: {id: 7, name: 'Saved', surname: 'Player'}}); request.complete(); },
    error(status, textStatus = 'error', responseJSON) { const request = requests.at(-1); request.error({status, responseJSON}, textStatus); request.complete(); },
    replaceCard() { currentCard = card(7); return currentCard; }};
}

for (const file of ['public/assets/js/wizard-ex-checkout.js', 'resources/assets/js/wizard-ex-checkout.js']) {
  test(`${file}: successful save ends spinner and retains confirmed fields and cart gate`, () => {
    const h = harness(file); h.click();
    assert.match(h.cards[0].button.html, /Saving/);
    assert.equal(h.cards[0].button.attrs['aria-busy'], 'true');
    assert.equal(h.requests[0].data.name, 'Fictional');
    assert.ok(Object.values(h.cards[0].form.fields).every(item => item.props.disabled));
    h.click(); assert.equal(h.requests.length, 1);
    h.success();
    assert.match(h.cards[0].button.html, /Saved &amp; Confirmed/);
    assert.doesNotMatch(h.cards[0].button.html, /Saving|spinner/);
    assert.equal(h.cards[0].button.attrs['aria-busy'], undefined);
    assert.equal(h.cards[0].button.props.disabled, true);
    assert.equal(h.cards[0].attrs['data-confirmed'], '1');
    assert.ok(Object.values(h.cards[0].form.fields).every(item => item.props.disabled));
    assert.equal(h.cart.props.disabled, false);
    h.click(); assert.equal(h.requests.length, 1);
  });

  test(`${file}: cart stays gated until every selected player is confirmed`, () => {
    const h = harness(file, {secondPlayer: true}); h.click(); h.success();
    assert.equal(h.cart.props.disabled, true);
    h.click(1); h.success(1, {success: true, player: {id: 8, name: 'Second', surname: 'Player'}});
    assert.equal(h.cart.props.disabled, false);
  });

  for (const [status, textStatus, pattern] of [[422, 'error', /Invalid phone/], [419, 'error', /session has expired/],
    [401, 'error', /session has expired/], [0, 'timeout', /Reload this step/], [0, 'error', /Reload this step/], [500, 'error', /Failed to save/]]) {
    test(`${file}: ${status}/${textStatus} restores pending form without a spinner or auto-retry`, () => {
      const h = harness(file); h.click();
      assert.equal(h.requests[0].timeout, 30000);
      h.error(status, textStatus, {errors: {cellNr: ['Invalid phone']}});
      assert.doesNotMatch(h.cards[0].button.html, /Saving|spinner/);
      assert.equal(h.cards[0].button.props.disabled, false);
      assert.equal(h.cards[0].button.attrs['aria-busy'], undefined);
      assert.ok(Object.values(h.cards[0].form.fields).every(item => item.props.disabled === false));
      assert.equal(h.cards[0].attrs['data-confirmed'], '0');
      assert.equal(h.cart.props.disabled, true);
      assert.match(h.messages.at(-1), pattern);
      assert.equal(h.requests.length, 1);
    });
  }

  test(`${file}: missing required values make no request and mismatched success cannot confirm`, () => {
    const invalid = harness(file, {invalidField: true}); invalid.click(); assert.equal(invalid.requests.length, 0);
    const h = harness(file); h.click(); h.success(0, {success: true, player: {id: 99}});
    assert.equal(h.cards[0].attrs['data-confirmed'], '0'); assert.equal(h.cart.props.disabled, true);
    assert.doesNotMatch(h.cards[0].button.html, /Saving|spinner/);
  });

  test(`${file}: stale response cannot confirm a replacement card with the same player ID`, () => {
    const h = harness(file); h.click(); const replacement = h.replaceCard(); h.success();
    assert.equal(replacement.attrs['data-confirmed'], '0');
    assert.equal(h.cards[0].attrs['data-confirmed'], '0');
    assert.equal(h.cart.props.disabled, true);
    assert.doesNotMatch(h.cards[0].button.html, /Saving|spinner/);
  });
}
