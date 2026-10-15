const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Node {
  constructor(tag = 'div', attrs = {}) { this.tag = tag; this.attrs = attrs; this.children = []; this.dataset = {}; this.events = {}; this.value = ''; Object.entries(attrs).forEach(([key, value]) => this.setAttribute(key, value)); }
  setAttribute(key, value) { this.attrs[key] = value; if(key.startsWith('data-')) this.dataset[key.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = value; }
  append(...nodes) { this.children.push(...nodes); }
  replaceChildren(...nodes) { this.children = nodes; }
  addEventListener(type, listener) { this.events[type] = listener; }
  dispatchEvent(event) { return this.events[event.type]?.(event); }
  click() { return this.events.click?.({}); }
  get lastChild() { return this.children.at(-1); }
  querySelectorAll(selector) {
    const matches = node => {
      const attribute = selector.match(/^\[([^=\]]+)(?:="([^"]*)")?\]/);
      const tag = selector.match(/(?:^|\s)(select)$/);
      return (!attribute || Object.hasOwn(node.attrs, attribute[1]) && (attribute[2] === undefined || node.attrs[attribute[1]] === attribute[2])) && (!tag || node.tag === tag[1]);
    };
    const descendants = node => node.children.flatMap(child => [child, ...descendants(child)]);
    if(selector.includes(' select')) {
      const [parent] = selector.split(' select');
      return this.querySelectorAll(parent).flatMap(node => node.querySelectorAll('select'));
    }
    return descendants(this).filter(matches);
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

function setup(search = '') {
  const root = new Node('div', {'data-url':'/create'});
  const ids = {};
  const add = (id, tag = 'div', attrs = {}) => { const node = new Node(tag, attrs); ids[id] = node; root.append(node); return node; };
  const select = add('competition-group', 'select');
  add('competition-submit', 'button'); add('competition-message'); add('competition-team-summary');
  add('competition-form', 'form');
  ['2', '3', '4'].forEach(step => root.append(new Node('section', {'data-competition-panel':step})));
  ['round_robin', 'playoff'].forEach(phase => root.append(new Node('div', {'data-phase':phase}), new Node('span', {'data-count':phase})));
  root.append(new Node('button', {'data-competition-next':''}), new Node('button', {'data-competition-back':''}), new Node('p', {'data-competition-step':''}), new Node('p', {'data-competition-selected-summary':''}), new Node('input', {'name':'_token'}));
  root.querySelector('[name="_token"]').value = 'token';
  const row = {name:'Opening singles',rubber_code:'singles',gender_rule:'male',home_positions:[1],away_positions:[1]};
  ids['competition-groups'] = {textContent:JSON.stringify([{label:'U15',size:2,teams:[{name:'East Boys',size:2},{name:'East Girls',size:2}],category_ids:[11,12],round_robin:[row],playoff:[row]}])};
  const requests = [], navigation = [];
  let response = {ok:true, json:async() => ({url:'/guide'})};
  const window = {location:{search,assign:url => navigation.push(url)}};
  const document = {getElementById:id => id === 'placement-competition' ? root : ids[id],createElement:tag => new Node(tag)};
  vm.runInNewContext(fs.readFileSync('public/js/team-placement-competition.js','utf8'), {document,window,URLSearchParams,Event:class {constructor(type){this.type=type;}},fetch:async(url, options) => {requests.push({url,options});return response;}});
  return {root,ids,select,requests,navigation,fail(){response={ok:false,json:async()=>({message:'Review match setup'})};},async submit(){await ids['competition-form'].events.submit({preventDefault(){}});}};
}

test('regional wizard validates participants, advances format and preserves editable matches on Back', () => {
  const ui = setup();
  ui.root.querySelector('[data-competition-next]').click();
  assert.match(ui.ids['competition-message'].textContent, /Choose an age group/);
  ui.select.value = '0'; ui.select.dispatchEvent({type:'change'});
  ui.root.querySelector('[data-competition-next]').click();
  assert.match(ui.root.querySelector('[data-competition-step]').textContent, /Competition format/);
  ui.root.querySelector('[data-competition-next]').click();
  const match = ui.root.querySelector('[data-phase="round_robin"]').children[0].querySelector('[data-field="name"]');
  match.value = 'Custom opener';
  ui.root.querySelector('[data-competition-back]').click();
  ui.root.querySelector('[data-competition-next]').click();
  assert.equal(match.value, 'Custom opener');
  assert.equal(ui.requests.length, 0);
});

test('selected age opens Match setup, submits both phase formats and continues to guide only on success', async () => {
  const ui = setup('?group=0&step=4');
  assert.match(ui.root.querySelector('[data-competition-step]').textContent, /Match setup/);
  assert.match(ui.root.querySelector('[data-competition-selected-summary]').textContent, /U15.*East Boys/);
  ui.fail(); await ui.submit();
  assert.deepEqual(ui.navigation, []);
  assert.equal(ui.ids['competition-submit'].disabled, false);
  assert.match(ui.ids['competition-message'].textContent, /Review match setup/);
  const body = JSON.parse(ui.requests[0].options.body);
  assert.deepEqual(body.category_ids, [11,12]);
  assert.equal(body.round_robin[0].name, 'Opening singles');
  assert.equal(body.playoff[0].rubber_code, 'singles');
  const success = setup('?group=0&step=4'); await success.submit(); await success.submit();
  assert.deepEqual(success.navigation, ['/guide']);
  assert.equal(success.requests.length, 1, 'Double submit is ignored after confirmation');
});
