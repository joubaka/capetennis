(function () {
  'use strict';

  document.querySelectorAll('[data-event-announcements]').forEach(function (root) {
    var reader = root.querySelector('[data-announcement-reader]');
    var toggle = root.querySelector('[data-announcement-toggle]');
    var status = root.querySelector('[data-announcement-status]');
    var viewAll = root.querySelector('[data-announcement-view-all]');
    var items = Array.from(root.querySelectorAll('[data-announcement]'));
    var viewed = new Set();
    var onlyNew = true;

    try {
      var saved = JSON.parse(localStorage.getItem(root.dataset.storageKey) || '[]');
      if (Array.isArray(saved)) viewed = new Set(saved.filter(function (value) { return typeof value === 'string'; }));
    } catch (_) { /* Browsing must still work when storage is unavailable. */ }

    function unread(item) { return !viewed.has(item.dataset.revision); }
    function refresh() {
      var count = items.filter(unread).length;
      toggle.textContent = count ? 'View ' + count + ' new announcement' + (count === 1 ? '' : 's') : 'View announcements';
      status.textContent = count ? count + ' new' : 'All viewed';
      viewAll.hidden = !count;
      items.forEach(function (item) {
        var isNew = unread(item);
        item.hidden = onlyNew && count > 0 && !isNew;
        item.querySelector('[data-announcement-badge]').textContent = isNew ? 'New' : 'Viewed';
        item.querySelector('[data-announcement-viewed]').hidden = !isNew;
      });
    }
    function openAll() {
      onlyNew = false;
      refresh();
      reader.open = true;
      items[0].open = true;
    }
    root.querySelector('[data-announcement-controls]').hidden = false;
    root.querySelector('[data-announcement-storage-note]').hidden = false;
    root.querySelector('[data-announcement-all]').addEventListener('click', openAll);
    viewAll.addEventListener('click', openAll);
    root.querySelector('[data-announcement-close]').addEventListener('click', function () {
      reader.open = false;
      toggle.focus();
    });
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
        viewed.add(item.dataset.revision);
        try {
          // Persist only revisions still on the page; never store message content.
          localStorage.setItem(root.dataset.storageKey, JSON.stringify(items.map(function (entry) { return entry.dataset.revision; }).filter(function (revision) { return viewed.has(revision); })));
        } catch (_) { /* Keep the current page functional without persistence. */ }
        item.open = false;
        reader.open = false;
        onlyNew = true;
        refresh();
        toggle.focus();
      });
    });
    refresh();
  });
})();
