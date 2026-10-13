const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/selection-preview-discussion.js', 'utf8');

function discussion() {
  class Element {
    constructor() { this.children = []; this.style = {}; this.handlers = {}; Object.defineProperty(this, 'innerHTML', { set() { throw new Error('Untrusted HTML rendering'); } }); }
    append(...elements) { this.children.push(...elements); }
    replaceChildren() { this.children = []; }
    addEventListener(type, callback) { this.handlers[type] = callback; }
  }
  const status = new Element(), original = new Element(), title = new Element(), thread = new Element(), more = new Element(), submit = new Element(), form = new Element();
  form.elements = { body: { value: '' } }; form.reset = () => { form.elements.body.value = ''; }; form.querySelector = () => submit;
  const selectors = { '[data-preview-note-status]': status, '[data-preview-discussion]': thread, '[data-preview-comment-form]': form, '[data-preview-more]': more, '[data-preview-original-note]': original, '#selection-note-title': title };
  const modal = { dataset: { noteUrl: '/comments' }, querySelector: selector => selectors[selector] };
  const handlers = {}, requests = [];
  const document = { getElementById: () => modal, querySelector: () => ({ content: 'csrf' }), createElement: () => new Element(), addEventListener(type, callback) { handlers[type] = callback; } };
  const fetch = (url, options) => new Promise(resolve => requests.push({ url, options, resolve }));
  vm.runInNewContext(source, { document, window: { location: { href: 'http://localhost/preview' } }, fetch, bootstrap: { Modal: { getOrCreateInstance: () => ({ show() {} }) } }, URL, Date, JSON, Error });
  return { status, thread, original, requests, click(key) { handlers.click({ target: { closest: () => ({ dataset: { groupKey: '10-boys', playerKey: key, playerName: key } }) } }); },
    async respond(index, data) { requests[index].resolve({ ok: true, json: async () => data }); await new Promise(resolve => setImmediate(resolve)); } };
}

test('original notes and discussion bodies render as text including HTML-looking content', async () => {
  const f = discussion(); f.click('1');
  await f.respond(0, { draft_version: 2, original_note: '<script>original</script>', next_page_url: null, comments: [{ author: '<img>Author', body: '<img src=x onerror=alert(1)>', draft_version: 1, created_at: '2026-10-13T12:00:00+02:00' }] });
  assert.equal(f.original.textContent, '<script>original</script>');
  assert.equal(f.thread.children[0].children[0].textContent, '<img>Author');
  assert.equal(f.thread.children[0].children[2].textContent, '<img src=x onerror=alert(1)>');
});

test('late responses cannot display another player discussion in a newly opened modal', async () => {
  const f = discussion(); f.click('1'); f.click('2');
  await f.respond(1, { draft_version: 1, original_note: 'Player 2', comments: [], next_page_url: null });
  await f.respond(0, { draft_version: 1, original_note: 'Player 1', comments: [], next_page_url: null });
  assert.equal(f.original.textContent, 'Player 2');
});
