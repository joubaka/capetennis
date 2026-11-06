const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page(storage = new Map(), key = 'event:1:user:1', revisions = ['1:original', '2:original'], broken = false, postedAt = [], posts, hash = '') {
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
  ['reader', 'toggle', 'status', 'view-all', 'controls', 'storage-note', 'all', 'close', 'mark-all'].forEach(name => nodes[name] = element());
  const items = revisions.map((revision, index) => {
    const item = element();
    item.id = 'event-announcement-' + (index + 1);
    item.scrollIntoView = () => { item.scrolled = true; };
    item.dataset = {revision, postedAt: postedAt[index]};
    item.badge = element();
    item.viewed = element();
    item.querySelector = selector => selector.includes('badge') ? item.badge : item.viewed;
    return item;
  });
  const root = Object.assign(element(), {dataset: {storageKey: key}, querySelectorAll: () => items, querySelector(selector) { return nodes[selector.replace('[data-announcement-', '').replace(']', '')]; }});
  const menuBadges = [element(), element()];
  const inbox = {};
  ['status', 'clear', 'list', 'item', 'close'].forEach(name => inbox[name] = element());
  inbox.list.replaceChildren = () => { inbox.list.rows = []; };
  inbox.list.append = row => inbox.list.rows.push(row);
  inbox.item.content = {cloneNode() {
    const fields = {'[data-inbox-link]': element(), '[data-inbox-event]': element()};
    return {querySelector: selector => fields[selector]};
  }};
  const modal = {dataset: {accountId: '1', announcementInbox: '/announcement-inbox'}, querySelector(selector) {
    return selector.includes('data-bs-dismiss') ? inbox.close : inbox[selector.slice(12, -1)];
  }};
  let now = 2 * 60 * 60 * 1000;
  let expiry;
  vm.runInNewContext(fs.readFileSync('public/assets/js/event-announcements.js', 'utf8'), {
    document: {querySelector: () => posts ? modal : null, querySelectorAll: selector => selector === '[data-event-announcements]' ? (revisions.length ? [root] : []) : menuBadges},
    window: {location: {hash}, addEventListener() {}},
    fetch: async () => ({ok: true, json: async () => posts}),
    Date: {now: () => now},
    setTimeout(fn, delay) { expiry = () => { now += delay; fn(); }; },
    clearTimeout() { expiry = undefined; },
    localStorage: {getItem(k) {if (broken) throw Error('blocked'); return storage.get(k);}, setItem(k, v) {if (broken) throw Error('blocked'); storage.set(k, v);}}
  });
  return {nodes, items, storage, root, menuBadges, inbox, expire() { expiry(); }};
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

test('recent unread announcements highlight the card until the hour expires', () => {
  const p = page(undefined, undefined, undefined, false, [3600000, 3600001]);
  assert.equal(p.root.attributes['data-recent-unread'], true);
  assert.equal(p.nodes.status.textContent, '2 unread · Recent update');
  p.expire();
  assert.equal(p.root.attributes['data-recent-unread'], false);
  assert.equal(p.nodes.status.textContent, '2 unread');
  assert.equal(page(undefined, undefined, undefined, false, [3600000, 7200001]).root.attributes['data-recent-unread'], false);
});

test('the event deep link opens the reader and event bulk reads preserve the archive', () => {
  const p = page(undefined, undefined, undefined, false, [7200000, 1000], undefined, '#event-announcement-2');
  assert.equal(p.nodes.reader.open, true);
  assert.equal(p.items[0].open, false);
  assert.equal(p.items[1].open, true);
  assert.equal(p.items[1].scrolled, true);
  p.items[0].viewed.events.click();
  assert.equal(p.root.attributes['data-recent-unread'], false);
  p.nodes['mark-all'].events.click();
  assert.equal(p.nodes['mark-all'].hidden, true);
  assert.equal(p.items.every(item => !item.hidden), true);
  assert.equal(page(p.storage).nodes.status.textContent, 'All read');
});

test('global inbox works off event pages and clears each event for the signed-in account', async () => {
  const posts = [
    {eventId: 1, eventName: 'Event one', title: 'Time change', revision: '1:original', url: '/events/1#event-announcements', postedAt: 7200000},
    {eventId: 2, eventName: 'Event two', title: 'Venue change', revision: '2:original', url: '/events/2#event-announcements', postedAt: 1000},
  ];
  const p = page(undefined, undefined, [], false, [], posts);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(p.menuBadges.every(badge => badge.textContent === 2 && badge.attributes['data-recent-unread']), true);
  assert.equal(p.inbox.list.rows[1].querySelector('[data-inbox-link]').href, posts[1].url);
  assert.equal(p.inbox.list.rows[1].querySelector('[data-inbox-event]').textContent, posts[1].eventName);
  p.expire();
  assert.equal(p.menuBadges.every(badge => !badge.attributes['data-recent-unread']), true);
  p.inbox.clear.events.click();
  assert.equal(p.inbox.clear.disabled, true);
  assert.equal(p.menuBadges.every(badge => badge.classList.contains('d-none')), true);
  assert.equal(page(p.storage, 'ct:announcements:1:1', ['1:original']).nodes.status.textContent, 'All read');
  assert.equal(page(p.storage, 'ct:announcements:2:1', ['2:original']).nodes.status.textContent, 'All read');
  assert.equal(page(p.storage, 'ct:announcements:2:2', ['2:original']).nodes.status.textContent, '1 unread');
});

test('marking an announcement in an older tab preserves reads saved by another tab', () => {
  const first = page();
  const second = page(first.storage);
  first.nodes['mark-all'].events.click();
  second.items[0].viewed.events.click();
  assert.equal(page(first.storage).nodes.status.textContent, 'All read');
});
