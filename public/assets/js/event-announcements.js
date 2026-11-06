(function () {
  'use strict';

  document.querySelectorAll('[data-event-announcements]').forEach(function (root) {
    var reader = root.querySelector('[data-announcement-reader]');
    var toggle = root.querySelector('[data-announcement-toggle]');
    var status = root.querySelector('[data-announcement-status]');
    var viewAll = root.querySelector('[data-announcement-view-all]');
    var markAll = root.querySelector('[data-announcement-mark-all]');
    var menuEntry = document.querySelector('[data-announcement-menu-entry]');
    var menuLink = document.querySelector('[data-announcement-menu-link]');
    var menuBadges = document.querySelectorAll('[data-announcement-menu-badge]');
    var items = Array.from(root.querySelectorAll('[data-announcement]'));
    var viewed = new Set();
    var onlyNew = true;
    var recencyTimer;
    var hour = 60 * 60 * 1000;

    function mergeSavedReads() {
      try {
        var saved = JSON.parse(localStorage.getItem(root.dataset.storageKey) || '[]');
        if (Array.isArray(saved)) saved.forEach(function (revision) {
          if (typeof revision === 'string') viewed.add(revision);
        });
      } catch (_) { /* Browsing must still work when storage is unavailable. */ }
    }
    mergeSavedReads();

    function unread(item) { return !viewed.has(item.dataset.revision); }
    function refresh() {
      var unreadItems = items.filter(unread);
      var count = unreadItems.length;
      var now = Date.now();
      var recentUntil = unreadItems.reduce(function (latest, item) {
        var postedAt = Number(item.dataset.postedAt);
        return postedAt <= now && postedAt + hour > now ? Math.max(latest, postedAt + hour) : latest;
      }, 0);
      var recent = recentUntil > now;
      root.toggleAttribute('data-recent-unread', recent);
      clearTimeout(recencyTimer);
      if (recent) recencyTimer = setTimeout(refresh, recentUntil - now);

      toggle.textContent = count ? 'View ' + count + ' new announcement' + (count === 1 ? '' : 's') : 'View announcements';
      status.textContent = count ? count + ' unread' + (recent ? ' · Recent update' : '') : 'All read';
      viewAll.hidden = !count;
      markAll.hidden = !count;
      menuBadges.forEach(function (badge) {
        badge.classList.toggle('d-none', !count);
        badge.textContent = count;
        badge.setAttribute('aria-label', count + ' unread event announcement' + (count === 1 ? '' : 's'));
        badge.toggleAttribute('data-recent-unread', recent);
      });
      items.forEach(function (item) {
        var isNew = unread(item);
        item.hidden = onlyNew && count > 0 && !isNew;
        item.querySelector('[data-announcement-badge]').textContent = isNew ? 'New' : 'Read';
        item.querySelector('[data-announcement-viewed]').hidden = !isNew;
      });
    }
    function openAll() {
      onlyNew = false;
      refresh();
      reader.open = true;
      items[0].open = true;
    }
    function closeReader() {
      reader.open = false;
      onlyNew = true;
      refresh();
      toggle.focus();
    }
    function markRead(entries) {
      mergeSavedReads();
      entries.forEach(function (item) {
        viewed.add(item.dataset.revision);
        item.open = false;
      });
      try {
        // Persist only revisions still on the page; never store message content.
        localStorage.setItem(root.dataset.storageKey, JSON.stringify(items.filter(function (item) {
          return !unread(item);
        }).map(function (item) { return item.dataset.revision; })));
      } catch (_) { /* Keep the current page functional without persistence. */ }
      closeReader();
    }
    root.querySelector('[data-announcement-controls]').hidden = false;
    root.querySelector('[data-announcement-storage-note]').hidden = false;
    root.querySelector('[data-announcement-all]').addEventListener('click', openAll);
    viewAll.addEventListener('click', openAll);
    root.querySelector('[data-announcement-close]').addEventListener('click', closeReader);
    markAll.addEventListener('click', function () { markRead(items); });
    if (menuEntry && menuLink) {
      menuEntry.classList.remove('d-none');
      menuLink.addEventListener('click', function () {
        reader.open = true;
        toggle.focus();
      });
    }
    reader.addEventListener('toggle', function () {
      if (reader.open) {
        var first = items.find(function (item) { return !item.hidden; });
        if (first) first.open = true;
      } else {
        onlyNew = true;
        refresh();
      }
    });
    items.forEach(function (item) {
      item.querySelector('[data-announcement-viewed]').addEventListener('click', function () {
        markRead([item]);
      });
    });
    refresh();
  });
})();
