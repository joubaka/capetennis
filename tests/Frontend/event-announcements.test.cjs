const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page(storage = new Map(), key = 'event:1:user:1', revisions = ['1:original', '2:original'], broken = false, postedAt = []) {
  function element() {
    const classes = new Set(['d-none']);
    return {
      hidden: false, open: false, textContent: '', events: {}, attributes: {},
      addEventListener(name, fn) { this.events[name] = fn; },
      focus() { this.focused = true; },
      toggleAttribute(name, value) { this.attributes[name] = value; },
      setAttribute(name, value) { this.attributes[name] = value; },
      classList: {
        toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); },
        remove(name) { classes.delete(name); },
        contains(name) { return classes.has(name); },
      },
    };
  }
  const nodes = {};
  ['reader', 'toggle', 'status', 'view-all', 'controls', 'storage-note', 'all', 'close', 'mark-all', 'menu-entry', 'menu-link'].forEach(name => nodes[name] = element());
  const items = revisions.map((revision, index) => {
    const item = element();
    item.dataset = {revision, postedAt: postedAt[index]};
    item.badge = element();
    item.viewed = element();
    item.querySelector = selector => selector.includes('badge') ? item.badge : item.viewed;
    return item;
  });
  const root = Object.assign(element(), {dataset: {storageKey: key}, querySelectorAll: () => items, querySelector(selector) { return nodes[selector.replace('[data-announcement-', '').replace(']', '')]; }});
  const menuBadges = [element(), element()];
  let now = 2 * 60 * 60 * 1000;
  let expiry;
  vm.runInNewContext(fs.readFileSync('public/assets/js/event-announcements.js', 'utf8'), {
    document: {querySelector: root.querySelector, querySelectorAll: selector => selector === '[data-event-announcements]' ? [root] : menuBadges},
    Date: {now: () => now},
    setTimeout(fn, delay) { expiry = () => { now += delay; fn(); }; },
    clearTimeout() { expiry = undefined; },
    localStorage: {getItem(k) {if (broken) throw Error('blocked'); return storage.get(k);}, setItem(k, v) {if (broken) throw Error('blocked'); storage.set(k, v);}}
  });
  return {nodes, items, storage, root, menuBadges, expire() { expiry(); }};
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
  assert.equal(refreshed.items[0].badge.textContent, 'Read');
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
  assert.equal(refreshed.nodes.status.textContent, 'All read');
  assert.equal(refreshed.nodes.toggle.textContent, 'View announcements');
  assert.equal(refreshed.items.every(item => !item.hidden), true);
  for (const value of ['invalid json', '{}', '[null, 42]']) {
    assert.equal(page(new Map([['event:1:user:1', value]])).nodes.status.textContent, '2 unread');
  }
  const blocked = page(new Map(), undefined, undefined, true);
  blocked.items[0].viewed.events.click();
  assert.equal(blocked.nodes.status.textContent, '1 unread');
});

test('recent unread announcements highlight the card and profile badges until the hour expires', () => {
  const p = page(undefined, undefined, undefined, false, [3600000, 3600001]);
  assert.equal(p.root.attributes['data-recent-unread'], true);
  assert.equal(p.nodes.status.textContent, '2 unread · Recent update');
  assert.equal(p.menuBadges.every(badge => badge.attributes['data-recent-unread']), true);
  p.expire();
  assert.equal(p.root.attributes['data-recent-unread'], false);
  assert.equal(p.nodes.status.textContent, '2 unread');
  assert.equal(page(undefined, undefined, undefined, false, [3600000, 7200001]).root.attributes['data-recent-unread'], false);
});

test('the profile menu opens the event reader and tracks individual and bulk reads', () => {
  const p = page(undefined, undefined, undefined, false, [7200000, 1000]);
  assert.equal(p.nodes['menu-entry'].classList.contains('d-none'), false);
  p.nodes['menu-link'].events.click();
  assert.equal(p.nodes.reader.open, true);
  p.items[0].viewed.events.click();
  assert.equal(p.root.attributes['data-recent-unread'], false);
  assert.equal(p.menuBadges.every(badge => badge.textContent === 1), true);
  p.nodes['mark-all'].events.click();
  assert.equal(p.menuBadges.every(badge => badge.classList.contains('d-none')), true);
  assert.equal(p.nodes['mark-all'].hidden, true);
  assert.equal(p.items.every(item => !item.hidden), true);
  assert.equal(page(p.storage).nodes.status.textContent, 'All read');
});

test('marking an announcement in an older tab preserves reads saved by another tab', () => {
  const first = page();
  const second = page(first.storage);
  first.nodes['mark-all'].events.click();
  second.items[0].viewed.events.click();
  assert.equal(page(first.storage).nodes.status.textContent, 'All read');
});
