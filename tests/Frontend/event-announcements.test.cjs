const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page(storage = new Map(), key = 'event:1:user:1', revisions = ['1:original', '2:original'], broken = false) {
  function element() {
    return {hidden: false, open: false, textContent: '', events: {}, addEventListener(name, fn) { this.events[name] = fn; }, focus() { this.focused = true; }};
  }
  const nodes = {};
  ['reader', 'toggle', 'status', 'view-all', 'controls', 'storage-note', 'all', 'close'].forEach(name => nodes[name] = element());
  const items = revisions.map(revision => {
    const item = element();
    item.dataset = {revision};
    item.badge = element();
    item.viewed = element();
    item.querySelector = selector => selector.includes('badge') ? item.badge : item.viewed;
    return item;
  });
  const root = {dataset: {storageKey: key}, querySelectorAll: () => items, querySelector(selector) { return nodes[selector.replace('[data-announcement-', '').replace(']', '')]; }};
  vm.runInNewContext(fs.readFileSync('public/assets/js/event-announcements.js', 'utf8'), {
    document: {querySelectorAll: () => [root]},
    localStorage: {getItem(k) {if (broken) throw Error('blocked'); return storage.get(k);}, setItem(k, v) {if (broken) throw Error('blocked'); storage.set(k, v);}}
  });
  return {nodes, items, storage};
}

test('closing preserves unread state; marking viewed closes and persists only that revision', () => {
  const p = page();
  assert.equal(p.nodes.toggle.textContent, 'View 2 new announcements');
  p.nodes.reader.open = true;
  p.nodes.close.events.click();
  assert.equal(p.nodes.reader.open, false);
  assert.equal(p.storage.size, 0);
  p.items[0].viewed.events.click();
  assert.equal(p.nodes.toggle.textContent, 'View 1 new announcement');
  assert.equal(p.nodes.reader.open, false);
  assert.equal(p.items[0].hidden, true);
  assert.equal(p.nodes.toggle.focused, true);
  const refreshed = page(p.storage);
  assert.equal(refreshed.nodes.toggle.textContent, 'View 1 new announcement');
  refreshed.nodes['view-all'].events.click();
  assert.equal(refreshed.items[0].hidden, false);
  assert.equal(refreshed.items[0].badge.textContent, 'Viewed');
});

test('event, account and edited-content revisions stay independent', () => {
  const p = page();
  p.items[0].viewed.events.click();
  assert.equal(page(p.storage, 'event:2:user:1').nodes.toggle.textContent, 'View 2 new announcements');
  assert.equal(page(p.storage, 'event:1:user:2').nodes.toggle.textContent, 'View 2 new announcements');
  assert.equal(page(p.storage, 'event:1:user:1', ['1:edited', '2:original']).nodes.toggle.textContent, 'View 2 new announcements');
});

test('all-viewed announcements remain accessible and storage failures do not break viewing', () => {
  const p = page();
  p.items.forEach(item => item.viewed.events.click());
  const refreshed = page(p.storage);
  assert.equal(refreshed.nodes.status.textContent, 'All viewed');
  assert.equal(refreshed.nodes.toggle.textContent, 'View announcements');
  assert.equal(refreshed.items.every(item => !item.hidden), true);
  for (const value of ['invalid json', '{}', '[null, 42]']) {
    assert.equal(page(new Map([['event:1:user:1', value]])).nodes.status.textContent, '2 new');
  }
  const blocked = page(new Map(), undefined, undefined, true);
  blocked.items[0].viewed.events.click();
  assert.equal(blocked.nodes.status.textContent, '1 new');
});
