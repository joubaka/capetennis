const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/edit-draw-day.blade.php', 'utf8');
const script = source.slice(source.indexOf('  const venueForm ='), source.indexOf('  const syncVenues ='));

function harness(fetch) {
  const element = () => ({value:'', disabled:false, hidden:false, children:[], dataset:{}, listeners:{},
    addEventListener(name, fn) {this.listeners[name] = fn;}, append(...children) {this.children.push(...children);},
    querySelector() {return null;}, reportValidity() {return true;}});
  const nodes = Object.fromEntries(['day-add-venue-form','day-venue-mode','day-existing-venue-panel','day-new-venue-panel','day-existing-venue','day-new-venue','day-add-venue-submit','day-add-venue-status','day-venue-list'].map(id => [id, element()]));
  const select = element(), templateSelect = element();
  nodes['day-band-template'] = {content:{querySelectorAll:()=>[templateSelect]}};
  nodes['day-add-venue-form'].action = '/events/247/venues';
  let invalidations = 0, syncs = 0;
  const document = {getElementById:id=>nodes[id], createElement:element, querySelector:()=>null};
  new Function('document','fetch','FormData','form','syncVenues','invalidate', script)(document, fetch, class {}, {querySelectorAll:()=>[select]}, ()=>syncs++, ()=>invalidations++);
  return {nodes, select, templateSelect, submit:()=>nodes['day-add-venue-form'].listeners.submit({preventDefault(){}}), invalidations:()=>invalidations, syncs:()=>syncs};
}

test('venue addition keeps editor in place and uses actual court labels in cards and band choices', async () => {
  const h = harness(async () => ({ok:true, json:async()=>({message:'Added.',venue:{id:9,name:'Club <courts>',court_labels:['Show court','8']}})}));
  await h.submit();
  const card = h.nodes['day-venue-list'].children[0].children[0];
  assert.equal(card.children[0].children[1].textContent, 'Club <courts>');
  assert.equal(card.children[0].children[0].checked, true);
  assert.deepEqual(card.children[3].children.map(label=>label.children[0].value), ['Show court','8']);
  assert.equal(h.select.children[0].value, 9);
  assert.equal(h.templateSelect.children[0].textContent, 'Club <courts>');
  assert.equal(h.invalidations(), 1);
  assert.equal(h.syncs(), 1);
  assert.equal(h.nodes['day-add-venue-submit'].disabled, false);
});

test('pending submit is protected and validation errors preserve input for retry', async () => {
  let resolve, calls = 0;
  const h = harness(()=>{calls++; return new Promise(done=>resolve=done);});
  h.nodes['day-new-venue'].value = 'Keep my name';
  const pending = h.submit();
  await h.submit();
  assert.equal(calls, 1);
  assert.equal(h.nodes['day-add-venue-submit'].disabled, true);
  resolve({ok:false,json:async()=>({errors:{courts:['Courts must be at least 1.']}})});
  await pending;
  assert.equal(h.nodes['day-add-venue-status'].textContent, 'Courts must be at least 1.');
  assert.equal(h.nodes['day-new-venue'].value, 'Keep my name');
  assert.equal(h.nodes['day-add-venue-submit'].disabled, false);
  assert.equal(h.invalidations(), 0);
});

test('new venue mode enables only the relevant input', () => {
  const h = harness();
  h.nodes['day-venue-mode'].value = 'new';
  h.nodes['day-venue-mode'].listeners.change();
  assert.equal(h.nodes['day-existing-venue'].disabled, true);
  assert.equal(h.nodes['day-new-venue'].disabled, false);
  assert.equal(h.nodes['day-existing-venue-panel'].hidden, true);
});
