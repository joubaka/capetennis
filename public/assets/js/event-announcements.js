(function () {
  'use strict';

  var reads = new Map();
  var hour = 60 * 60 * 1000;

  function savedReads(key) {
    if (!reads.has(key)) reads.set(key, new Set());
    var viewed = reads.get(key);
    try {
      var saved = JSON.parse(localStorage.getItem(key) || '[]');
      if (Array.isArray(saved)) saved.forEach(function (revision) {
        if (typeof revision === 'string') viewed.add(revision);
      });
    } catch (_) { /* Read controls still work in memory when storage is unavailable. */ }
    return viewed;
  }

  function saveReads(key, revisions) {
    var viewed = savedReads(key);
    revisions.forEach(function (revision) { viewed.add(revision); });
    try {
      localStorage.setItem(key, JSON.stringify(Array.from(viewed)));
    } catch (_) { /* Keep the current page functional without persistence. */ }
  }

  function recentUntil(posts, postedAt) {
    var now = Date.now();
    return posts.reduce(function (latest, post) {
      var timestamp = Number(postedAt(post));
      return timestamp <= now && timestamp + hour > now ? Math.max(latest, timestamp + hour) : latest;
    }, 0);
  }

  var refreshInbox = function () {};
  var refreshEvent = function () {};
  var openEvent = function () {};
  document.querySelectorAll('[data-event-announcements]').forEach(function (root) {
    var reader = root.querySelector('[data-announcement-reader]');
    var toggle = root.querySelector('[data-announcement-toggle]');
    var status = root.querySelector('[data-announcement-status]');
    var viewAll = root.querySelector('[data-announcement-view-all]');
    var markAll = root.querySelector('[data-announcement-mark-all]');
    var items = Array.from(root.querySelectorAll('[data-announcement]'));
    var key = root.dataset.storageKey;
    var viewed = savedReads(key);
    var onlyNew = true;
    var recencyTimer;

    function unread(item) { return !viewed.has(item.dataset.revision); }
    function refresh() {
      var unreadItems = items.filter(unread);
      var count = unreadItems.length;
      var now = Date.now();
      var expiry = recentUntil(unreadItems, function (item) { return item.dataset.postedAt; });
      var recent = expiry > now;
      root.toggleAttribute('data-recent-unread', recent);
      clearTimeout(recencyTimer);
      if (recent) recencyTimer = setTimeout(refresh, expiry - now);

      toggle.textContent = count ? 'View ' + count + ' new announcement' + (count === 1 ? '' : 's') : 'View announcements';
      status.textContent = count ? count + ' unread' + (recent ? ' · Recent update' : '') : 'All read';
      viewAll.hidden = !count;
      markAll.hidden = !count;
      items.forEach(function (item) {
        var isNew = unread(item);
        item.hidden = onlyNew && count > 0 && !isNew;
        item.querySelector('[data-announcement-badge]').textContent = isNew ? 'New' : 'Read';
        item.querySelector('[data-announcement-viewed]').hidden = !isNew;
      });
    }
    function openAll(target) {
      onlyNew = false;
      refresh();
      reader.open = true;
      (target || items[0]).open = true;
    }
    function closeReader() {
      reader.open = false;
      onlyNew = true;
      refresh();
      toggle.focus();
    }
    function markRead(entries) {
      saveReads(key, entries.map(function (item) { return item.dataset.revision; }));
      entries.forEach(function (item) { item.open = false; });
      closeReader();
      refreshInbox();
    }
    root.querySelector('[data-announcement-controls]').hidden = false;
    root.querySelector('[data-announcement-storage-note]').hidden = false;
    root.querySelector('[data-announcement-all]').addEventListener('click', function () { openAll(); });
    viewAll.addEventListener('click', function () { openAll(); });
    root.querySelector('[data-announcement-close]').addEventListener('click', closeReader);
    markAll.addEventListener('click', function () { markRead(items); });
    reader.addEventListener('toggle', function () {
      if (reader.open) {
        var first = items.find(function (item) { return !item.hidden; });
        if (first && !items.some(function (item) { return !item.hidden && item.open; })) first.open = true;
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
    function openFromLink(hash) {
      if (hash === '#event-announcements') {
        openAll();
        return;
      }
      var target = items.find(function (item) { return '#' + item.id === hash; });
      if (target) {
        openAll(target);
        target.scrollIntoView({block: 'start'});
      }
    }
    window.addEventListener('hashchange', function () { openFromLink(window.location.hash); });
    openFromLink(window.location.hash);
    openEvent = openFromLink;
    refreshEvent = refresh;
    refresh();
  });

  var inbox = document.querySelector('[data-announcement-inbox]');
  if (!inbox) return;

  var badges = document.querySelectorAll('[data-announcement-menu-badge]');
  var list = inbox.querySelector('[data-inbox-list]');
  var template = inbox.querySelector('[data-inbox-item]');
  var status = inbox.querySelector('[data-inbox-status]');
  var clear = inbox.querySelector('[data-inbox-clear]');
  var posts = [];
  var timer;
  var loaded = false;

  function eventKey(post) { return 'ct:announcements:' + post.eventId + ':' + inbox.dataset.accountId; }

  function unreadPosts() {
    var eventReads = new Map();
    return posts.filter(function (post) {
      var key = eventKey(post);
      if (!eventReads.has(key)) eventReads.set(key, savedReads(key));
      return !eventReads.get(key).has(post.revision);
    });
  }

  refreshInbox = function (recencyOnly) {
    if (!loaded) return;
    var unread = unreadPosts();
    var count = unread.length;
    var delay = recentUntil(unread, function (post) { return post.postedAt; }) - Date.now();
    clearTimeout(timer);
    if (delay > 0) timer = setTimeout(function () { refreshInbox(true); }, delay);
    badges.forEach(function (badge) {
      badge.classList.toggle('d-none', !count);
      badge.textContent = count;
      badge.setAttribute('aria-label', count + ' unread event announcement' + (count === 1 ? '' : 's'));
      badge.toggleAttribute('data-recent-unread', delay > 0);
    });
    if (recencyOnly) return;
    status.textContent = count ? count + ' unread in the latest 100 public event announcements.' : 'You’re caught up on recent event announcements.';
    clear.disabled = !count;
    list.replaceChildren();
    unread.forEach(function (post) {
      var row = template.content.cloneNode(true);
      var link = row.querySelector('[data-inbox-link]');
      link.href = post.url;
      link.textContent = post.title;
      row.querySelector('[data-inbox-event]').textContent = post.eventName;
      list.append(row);
    });
  };

  list.addEventListener('click', function (event) {
    var link = event.target.closest('[data-inbox-link]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (link.origin === window.location.origin && link.pathname === window.location.pathname) openEvent(link.hash);
    window.bootstrap?.Modal.getInstance(inbox)?.hide();
  });
  clear.addEventListener('click', function () {
    var byEvent = new Map();
    unreadPosts().forEach(function (post) {
      var key = eventKey(post);
      if (!byEvent.has(key)) byEvent.set(key, []);
      byEvent.get(key).push(post.revision);
    });
    byEvent.forEach(function (revisions, key) { saveReads(key, revisions); });
    refreshEvent();
    refreshInbox();
    inbox.querySelector('[data-bs-dismiss="modal"]').focus();
  });

  fetch(inbox.dataset.announcementInbox, {headers: {Accept: 'application/json'}})
    .then(function (response) {
      if (!response.ok) throw new Error('Announcement inbox unavailable');
      return response.json();
    })
    .then(function (result) {
      posts = result;
      loaded = true;
      refreshInbox();
    })
    .catch(function () { status.textContent = 'Announcements could not be loaded. Reload the page to try again.'; });
})();
