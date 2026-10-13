const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/selection-preview-share.js', 'utf8');

function picker(initial = [], cached = []) {
  class Element {
    constructor() { this.children = []; this.dataset = {}; this.handlers = {}; this.attributes = {}; Object.defineProperty(this, 'innerHTML', { set() { throw new Error('Untrusted HTML rendering'); } }); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    addEventListener(type, callback) { const prior = this.handlers[type]; this.handlers[type] = prior ? (...args) => { prior(...args); return callback(...args); } : callback; }
    setAttribute(key, value) { this.attributes[key] = value; }
    removeAttribute(key) { delete this.attributes[key]; }
    closest(selector) { return selector.includes("share-user") && "shareUser" in this.dataset || selector.includes("share-grant-page") && "shareGrantPage" in this.dataset || selector === "[data-share-page]" && "sharePage" in this.dataset || selector.includes("share-revoke") && "shareRevoke" in this.dataset ? this : null; }
    querySelectorAll() { return this.children.flatMap(node => [...('shareUser' in node.dataset ? [node] : []), ...node.querySelectorAll()]); }
  }
  const root = new Element(), results = new Element(), list = new Element(), hidden = new Element(), status = new Element(), search = new Element(), q = new Element(), count = new Element(), send = new Element(), clear = new Element(), sendForm = new Element(), resultCount = new Element(), whatsapp = new Element(), whatsappOpen = new Element(), whatsappStatus = new Element(), seed = new Element(), grants = new Element(), grantStatus = new Element(), grantCount = new Element();
  q.value = ''; search.elements = { q }; seed.textContent = JSON.stringify(initial); root.dataset = { storageKey: 'preview-share:241:1', searchUrl: '/share', sendComplete: '0' };
  const elements = { '[data-share-grants]': grants, '[data-share-grant-status]': grantStatus, '[data-share-grant-count]': grantCount, '[data-share-results]': results, '[data-share-selected-list]': list, '[data-share-hidden-inputs]': hidden, '[data-share-search-status]': status, '[data-share-search]': search, '[data-share-count]': count, '[data-share-send-button]': send, '[data-share-clear]': clear, '[data-share-send]': sendForm, '[data-share-initial]': seed, '[data-share-result-count]': resultCount, '[data-share-whatsapp]': whatsapp, '[data-share-whatsapp-open]': whatsappOpen, '[data-share-whatsapp-status]': whatsappStatus };
  root.querySelector = selector => elements[selector]; sendForm.action = '/send'; sendForm.elements = { _token: {value: 'csrf'}, request_key: {value: 'first-key'} };
  const storage = new Map([[root.dataset.storageKey, JSON.stringify(cached)]]), timers = new Map(), requests = []; let timerId = 0;
  const document = { querySelector: () => root, createElement: () => new Element() };
  const window = { location: { href: 'http://localhost/share' }, history: { replaceState() {} }, clearTimeout(id) { timers.delete(id); }, setTimeout(callback) { timers.set(++timerId, callback); return timerId; } };
  const sessionStorage = { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) };
  const fetch = (url, options) => new Promise(resolve => requests.push({ url, options, resolve }));
  vm.runInNewContext(source, { document, window, sessionStorage, fetch, AbortController, URL, JSON, Map, Number, String, Error });
  return { revoke() { const form=new Element(), button=new Element(); form.dataset.shareRevoke=""; form.action="/revoke/1"; form.querySelector=()=>button; return root.handlers.submit({target:form,preventDefault() {}}); }, grantPage(url) { const link=new Element(); link.href=url; link.dataset.shareGrantPage=""; root.handlers.click({target:link,preventDefault() {}}); }, sendForm, grants, grantStatus, email() { return sendForm.handlers.submit({preventDefault() {}}); }, hidden, count, status, list, results, requests, whatsappOpen, whatsappStatus, whatsapp() { return whatsapp.handlers.click(); }, async respondGrant(index, data) { requests[index].resolve({ok: true, json: async () => data}); await new Promise(resolve => setImmediate(resolve)); }, choose(id, name = 'Person ' + id) { const input = new Element(); input.value = String(id); input.checked = true; input.dataset = { shareUser: '', name, email: 'p***@example.test' }; root.handlers.change({ target: input }); },
    input(value) { q.value = value; q.handlers.input(); }, tick() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); },
    async respond(index, users) { requests[index].resolve({ ok: true, json: async () => ({ users, pagination: { current_page: 1, last_page: 1, total: users.length, previous_page_url: null, next_page_url: null } }) }); await new Promise(resolve => setImmediate(resolve)); },
    page(url) { const link = new Element(); link.href = url; link.dataset.sharePage = ''; root.handlers.click({ target: link, preventDefault() {} }); } };
}

test('selected users survive live searches and paging, with unique submitted account IDs', async () => {
  const f = picker(); f.choose(1); f.choose(2); f.input('Leonie'); f.tick();
  await f.respond(0, [{ id: 3, name: 'Leonie', masked_email: 'l***@example.test', selectable: true }]);
  assert.deepEqual(f.hidden.children.map(input => Number(input.value)), [1, 2]);
  f.choose(3); f.page('/share?users_page=2'); await f.respond(1, [{ id: 1, name: 'Person 1', masked_email: 'p***@example.test', selectable: true }]);
  assert.deepEqual(f.hidden.children.map(input => Number(input.value)), [1, 2, 3]);
  assert.equal(f.results.querySelectorAll()[0].checked, true);
  f.choose(1); assert.equal(f.count.textContent, '3 / 30');
});

test('search typing is debounced and names render as text', async () => {
  const f = picker(); f.input('A'); f.input('Ab'); f.input('Abc'); f.tick(); assert.equal(f.requests.length, 1);
  await f.respond(0, [{ id: 1, name: '<img src=x onerror=alert(1)>', masked_email: 'p***@example.test', selectable: true }]);
  assert.equal(f.results.children[0].children[0].children[1].children[0].textContent, '<img src=x onerror=alert(1)>');
});

test('old submitted choices take priority and merged storage cannot exceed thirty', () => {
  const initial = Array.from({ length: 30 }, (_, id) => ({ id: id + 1, name: 'Submitted ' + id, masked_email: 's***@example.test' }));
  const cached = Array.from({ length: 30 }, (_, id) => ({ id: id + 31, name: 'Cached ' + id, masked_email: 'c***@example.test' }));
  const f = picker(initial, cached);
  assert.equal(f.hidden.children.length, 30); assert.equal(f.hidden.children[0].value, 1);
  assert.match(f.status.textContent, /submitted selections were kept first/);
  f.choose(90); assert.equal(f.hidden.children.length, 30);
});

test('late search response cannot replace newer search results', async () => {
  const f = picker(); f.input('First'); f.tick(); f.input('Second'); f.tick();
  await f.respond(1, [{ id: 2, name: 'Second', masked_email: 's***@example.test', selectable: true }]);
  await f.respond(0, [{ id: 1, name: 'First', masked_email: 'f***@example.test', selectable: true }]);
  assert.equal(f.results.querySelectorAll()[0].value, '2');
});

test('WhatsApp grants only selected accounts before showing an explicit open link', async () => {
  const f = picker(); f.choose(1); const pending = f.whatsapp();
  assert.equal(f.requests[0].options.method, 'POST');
  assert.deepEqual(JSON.parse(f.requests[0].options.body).recipient_ids, [1]);
  assert.equal(JSON.parse(f.requests[0].options.body).channel, 'whatsapp');
  assert.equal(f.whatsappOpen.hidden, true);
  await f.respondGrant(0, { recipients: 1, whatsapp_url: 'https://wa.me/?text=private%20link', next_request_key: 'next-key' }); await f.respondGrant(1, {grants: [], pagination: {current_page:1,last_page:1,total:0}}); await pending;
  assert.equal(f.whatsappOpen.href, 'https://wa.me/?text=private%20link');
  assert.equal(f.whatsappOpen.hidden, false);
  assert.match(f.whatsappStatus.textContent, /No email was sent/);
  f.choose(2); assert.equal(f.whatsappOpen.hidden, true);
});

test('AJAX email retains choices on failure and rotates key after confirmed success', async () => {
 const f=picker(); f.choose(1); const failed=f.email();
 f.requests[0].resolve({ok:false,json:async()=>({message:'Try again'})}); await failed;
 assert.equal(f.hidden.children.length,1); assert.equal(f.sendForm.elements.request_key.value,'first-key');
 const saved=f.email(); f.choose(2); assert.equal(f.hidden.children.length,1);
 await f.respondGrant(1,{recipients:1,queued:1,prepared:0,sending:0,accepted:0,failed:0,skipped:0,unknown:0,next_request_key:'fresh-key'});
 await f.respondGrant(2,{grants:[{id:1,name:'One',masked_email:'o***@example.test',revoke_url:'/revoke/1'}],pagination:{current_page:1,last_page:1,total:1}}); await saved;
 assert.equal(f.hidden.children.length,0); assert.equal(f.sendForm.elements.request_key.value,'fresh-key'); assert.equal(f.grants.children[0].children[0].children[0].textContent,'One');
});

test('AJAX revoke and shared-account paging use canonical requests without reloading', async () => {
 const f=picker(); f.choose(4); const revoke=f.revoke();
 assert.equal(f.requests[0].options.method,'DELETE'); assert.equal(f.requests[0].options.headers['X-CSRF-TOKEN'],'csrf');
 await f.respondGrant(0,{revoked:true}); await f.respondGrant(1,{grants:[],pagination:{current_page:1,last_page:1,total:0}}); await revoke;
 assert.equal(f.hidden.children.length,1);
 f.grantPage('/share?grants_page=2'); assert.match(f.requests[2].url,/grants_page=2.*section=grants/);
 await f.respondGrant(2,{grants:[],pagination:{current_page:2,last_page:2,total:16}});
});
