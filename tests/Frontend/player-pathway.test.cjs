const test = require('node:test');
const assert = require('node:assert/strict');
const PlayerPathwayUI = require('../../public/assets/js/player-pathway.js');

function harness() {
  const listeners = {};
  let status = { textContent: '' };
  let tools = { open: false };
  let reloads = 0;
  let selectedUrl = '/my-tennis/players/1/rating?month=3&scale=full&scope=all';
  const reloadUrls = [];
  const panel = {
    addEventListener(name, callback) { listeners[name] = callback; },
    querySelector(selector) { return selector === '[data-pathway-status]' ? status : tools; }
  };
  const ui = new PlayerPathwayUI(panel, async () => {
    reloads++; reloadUrls.push(selectedUrl);
    status = { textContent: '' }; tools = { open: false }; // actual panel replacement
  }, { csrf: 'test-csrf' });
  const form = () => {
    const button = { disabled: false };
    return { action: '/my-tennis/players/1/pathway/stage', button, querySelectorAll() { return [button]; } };
  };
  return { ui, listeners, form, status: () => status, tools: () => tools, reloads: () => reloads, reloadUrls,
    changeMonth() { selectedUrl = '/my-tennis/players/1/rating?month=7&scale=full&scope=all'; } };
}

test('delegated pathway saves survive replacement and reload the current month/filter URL', async () => {
  const oldFetch = global.fetch, oldFormData = global.FormData;
  const requests = [];
  global.FormData = class { constructor(form) { this.form = form; } };
  global.fetch = async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => ({ message: 'Playing stage saved.' }) };
  };
  try {
    const h = harness();
    for (let i = 0; i < 2; i++) {
      const form = h.form(); let prevented = false;
      h.listeners.submit({ target: { closest: () => form }, preventDefault() { prevented = true; } });
      await new Promise(resolve => setImmediate(resolve));
      assert.equal(prevented, true);
      assert.equal(form.button.disabled, false);
      assert.equal(h.status().textContent, 'Playing stage saved.');
      assert.equal(h.tools().open, true);
      h.changeMonth();
    }
    assert.equal(h.reloads(), 2);
    assert.match(h.reloadUrls[0], /month=3&scale=full&scope=all/);
    assert.match(h.reloadUrls[1], /month=7&scale=full&scope=all/);
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(requests[0].options.credentials, 'same-origin');
  } finally { global.fetch = oldFetch; global.FormData = oldFormData; }
});

test('validation failures keep the form available and show server messages as text', async () => {
  const oldFetch = global.fetch, oldFormData = global.FormData;
  global.FormData = class {};
  global.fetch = async () => ({ ok: false, json: async () => ({ errors: { stage: ['<script>Reload stage</script>'] } }) });
  try {
    const h = harness(); const form = h.form();
    await h.ui.save(form);
    assert.equal(h.status().textContent, '<script>Reload stage</script>');
    assert.equal(h.reloads(), 0);
    assert.equal(h.ui.pending, false);
    assert.equal(form.button.disabled, false);
  } finally { global.fetch = oldFetch; global.FormData = oldFormData; }
});

test('changing starting stage preserves an independently recorded current Wilson division', async () => {
  const h = harness(); const division = { value: 'A', disabled: false };
  for (const startingStage of ['red', 'orange', '', 'standard']) {
    h.listeners.change?.({ target: { matches: () => true, value: startingStage, closest: () => ({ querySelector: () => division }) } });
    assert.equal(division.disabled, false);
    assert.equal(division.value, 'A');
  }
});

test('repeated submits remain suppressed while a pathway save is pending', async () => {
  const h = harness();
  h.ui.pending = true;
  await h.ui.save(h.form());
  assert.equal(h.reloads(), 0);
});

function infoCard() {
  const attrs = { 'aria-expanded': 'false' };
  const content = { hidden: true, dataset: {}, textContent: '', innerHTML: '', setAttribute() {}, removeAttribute() {} };
  const button = { disabled: false, dataset: { pathwayInfo: '/my-tennis/players/1/pathway/info/red' },
    closest() { return { querySelector() { return content; } }; },
    getAttribute(name) { return attrs[name]; }, setAttribute(name, value) { attrs[name] = value; } };
  return { button, content, attrs };
}

test('stage details load on demand, suppress duplicate clicks and toggle cached content', async () => {
  const oldFetch = global.fetch;
  let finish; const requests = [];
  global.fetch = (url, options) => { requests.push({ url, options }); return new Promise(resolve => { finish = resolve; }); };
  try {
    const h = harness(), card = infoCard();
    const pending = h.ui.info(card.button);
    assert.equal(card.content.hidden, false);
    assert.equal(card.button.disabled, true);
    await h.ui.info(card.button);
    assert.equal(requests.length, 1);
    finish({ ok: true, text: async () => '<div data-pathway-stage-details="red">Red guidance</div>' });
    await pending;
    assert.equal(card.content.dataset.loaded, 'true');
    assert.equal(card.button.disabled, false);
    await h.ui.info(card.button);
    assert.equal(card.content.hidden, true);
    assert.equal(card.attrs['aria-expanded'], 'false');
    await h.ui.info(card.button);
    assert.equal(card.content.hidden, false);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.cache, 'no-store');
    assert.equal(requests[0].options.credentials, 'same-origin');
  } finally { global.fetch = oldFetch; }
});

test('stage detail failures and login redirects stay retryable without inserting response HTML', async () => {
  const oldFetch = global.fetch;
  try {
    const h = harness(), card = infoCard();
    global.fetch = async () => ({ ok: true, redirected: true, text: async () => '<form>Login</form>' });
    await h.ui.info(card.button);
    assert.match(card.content.textContent, /try again/);
    assert.equal(card.content.innerHTML, '');
    assert.equal(card.button.disabled, false);
    assert.equal(card.attrs['aria-expanded'], 'true');
    global.fetch = async () => ({ ok: true, text: async () => '<div data-pathway-stage-details="red">Ready</div>' });
    await h.ui.info(card.button);
    assert.equal(card.content.dataset.loaded, 'true');
  } finally { global.fetch = oldFetch; }
});
