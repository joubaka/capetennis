(function (window, document) {
  'use strict';
  var peers = [];
  function initialize(panel) {
  var drawId = panel.dataset.drawId;
  var scoped = panel.hasAttribute('data-draw-day-publication');
  if (scoped && !/^[1-9][0-9]*$/.test(drawId || '')) return;
  var eventDays = JSON.parse(panel.dataset.eventDays || '{}');
  var cards = panel.querySelector('[data-day-cards]');
  var template = panel.querySelector('[data-day-card-template]');
  var feedback = panel.querySelector('[data-day-feedback]');
  var retry = panel.querySelector('[data-day-status-retry]');
  var pending = false, unconfirmed = panel.dataset.initialUnconfirmed === 'true', activeDate = null, loaded = !scoped;
  function controls(busy) {
    panel.querySelectorAll('[data-day-action-form] button').forEach(function (button) {
      button.disabled = busy || unconfirmed || button.dataset.disabled === 'true';
    });
    retry.disabled = busy;
    panel.setAttribute('aria-busy', busy ? 'true' : 'false');
  }
  function show(message) {
    feedback.classList.remove('d-none');
    feedback.textContent = message;
  }
  function restoreFocus() {
    if (!activeDate) return;
    var button = panel.querySelector('[data-day-card][data-date="' + activeDate + '"] [data-day-toggle]');
    if (button && !button.disabled) button.focus();
    else { feedback.setAttribute('tabindex', '-1'); feedback.focus(); }
  }
  function render(result) {
    if (result.success !== true || Number(result.event_id) !== Number(panel.dataset.eventId) || !/^[a-f0-9]{64}$/.test(result.revision) || !Array.isArray(result.days)) throw new Error('Could not confirm the schedule status.');
    if (!scoped && result.draw_id !== undefined) throw new Error('Could not confirm the whole-event scope.');
    if (scoped && Number(result.draw_id) !== Number(drawId)) throw new Error('Could not confirm this draw scope.');
    var dates = new Set();
    result.days.forEach(function (day) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(day.date) || dates.has(day.date) || typeof day.label !== 'string'
          || ['Published', 'Partly published', 'Unpublished', 'Updates not published', 'Not scheduled'].indexOf(day.status) < 0
          || ['saved', 'published', 'matched', 'pending'].some(function (key) { return !Number.isInteger(day[key]) || day[key] < 0; })) throw new Error('Could not confirm all day statuses.');
      dates.add(day.date);
    });
    var daysByDate = new Map(result.days.map(function (day) { return [day.date, day]; }));
    Object.keys(eventDays).forEach(function (date) {
      if (!daysByDate.has(date)) daysByDate.set(date, { date: date, status: 'Not scheduled', saved: 0, published: 0, matched: 0, pending: 0 });
    });
    var updated = Array.from(daysByDate.keys()).sort().map(function (date) {
      var day = daysByDate.get(date);
      var card = template.content.firstElementChild.cloneNode(true);
      var published = day.status === 'Published';
      card.dataset.date = day.date;
      card.querySelector('[data-day-label]').textContent = eventDays[date] || day.label;
      card.querySelector('[data-day-status]').textContent = day.status;
      card.querySelector('[data-day-counts]').textContent = day.saved + ' saved times · ' + day.published + ' published times';
      card.querySelector('[data-day-pending]').textContent = day.pending ? day.pending + ' matches with unpublished changes' : '';
      card.querySelector('[data-day-review]').href = panel.dataset.calendarUrl + (panel.dataset.calendarUrl.indexOf('?') === -1 ? '?' : '&') + 'date=' + encodeURIComponent(day.date);
      var main = card.querySelector('[data-main-day-action]');
      main.action = published ? panel.dataset.hideUrl : panel.dataset.publishUrl;
      var button = card.querySelector('[data-day-toggle]');
      main.hidden = day.saved === 0 && day.published === 0;
      button.textContent = published ? 'Hide day' : (day.published > 0 || day.status === 'Updates not published' ? 'Publish updates' : 'Publish day');
      button.setAttribute('aria-pressed', published ? 'true' : 'false');
      button.classList.toggle('btn-outline-danger', published);
      button.classList.toggle('btn-success', !published);
      button.dataset.disabled = (published ? day.published === 0 : day.saved === 0) ? 'true' : 'false';
      card.querySelector('[data-day-hide-menu]').hidden = published || day.published === 0;
      card.querySelectorAll('[data-day-action-form]').forEach(function (form) {
        form.querySelector('[name="date"]').value = day.date;
        form.querySelector('[name="revision"]').value = result.revision;
        if (form !== main) form.querySelector('button').dataset.disabled = day.published === 0 ? 'true' : 'false';
      });
      return card;
    });
    cards.replaceChildren.apply(cards, updated);
    if (!updated.length) {
      var empty = document.createElement('p');
      empty.textContent = 'Not scheduled · No saved or published match times yet.';
      cards.appendChild(empty);
    }
    unconfirmed = false;
    loaded = true;
    retry.hidden = true;
    if (panel.dataset.workspaceDayPublication === 'true') {
      var hasPublished = result.days.some(function (day) { return day.published > 0; });
      document.querySelectorAll('[data-workspace-schedule-status]').forEach(function (badge) {
        badge.textContent = hasPublished ? 'Schedule: published times' : 'Schedule: unpublished';
        badge.classList.toggle('bg-label-success', hasPublished);
        badge.classList.toggle('bg-label-secondary', !hasPublished);
      });
      document.querySelectorAll('[data-workspace-public]').forEach(function (link) {
        link.dataset.workspaceSchedulePublished = hasPublished ? '1' : '0';
        link.hidden = link.dataset.workspaceDrawPublished !== '1' && !hasPublished;
      });
    }
  }
  async function readStatus() {
    var response = await window.fetch(panel.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var result = await response.json();
    if (!response.ok) throw new Error('Status check failed.');
    render(result);
  }
  panel.addEventListener('submit', async function (event) {
    var form = event.target;
    if (!form.matches('[data-day-action-form]')) return;
    event.preventDefault();
    if (pending || unconfirmed || form.querySelector('button').disabled) return;
    activeDate = form.querySelector('[name="date"]').value;
    pending = true;
    controls(true);
    show(scoped ? 'Updating this draw’s day publication…' : 'Updating this whole day’s schedule publication…');
    try {
      var response = await window.fetch(form.action, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(Object.assign({ date: form.querySelector('[name="date"]').value, revision: form.querySelector('[name="revision"]').value }, scoped ? { draw_id: Number(drawId) } : {})) });
      var result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.message || 'The publication request could not be confirmed.');
      render(result);
      window.dispatchEvent(new Event('draw-workflow-saved'));
      await Promise.all(peers.filter(function (peer) { return peer !== refreshPeer; }).map(function (peer) { return peer(); }));
      show(scoped ? (result.action === 'hide' ? 'This draw’s day times hidden.' : 'This draw’s day times published.') : (result.action === 'hide' ? 'Whole-day times hidden. All day statuses are current.' : 'Whole-day times published. All day statuses are current.'));
    } catch (error) {
      unconfirmed = true;
      show(error.message + ' Checking the current saved publication status…');
      try {
        await readStatus();
        show(error.message + ' Current day statuses have been refreshed. Review before another action.');
      } catch (_) {
        show('Publication status unconfirmed. Actions are paused; retry the status check before changing another day.');
        retry.hidden = false;
      }
    } finally {
      pending = false;
      controls(false);
      restoreFocus();
    }
  });
  retry.addEventListener('click', async function () {
    if (pending) return;
    pending = true;
    controls(true);
    try { await readStatus(); show('Current day statuses refreshed.'); }
    catch (_) { unconfirmed = true; show('Publication status unconfirmed. Retry the status check when the connection is available.'); }
    finally { pending = false; controls(false); restoreFocus(); }
  });
  async function refreshPeer() {
    if (!loaded || pending) return;
    pending = true;
    unconfirmed = true;
    controls(true);
    show('Refreshing day statuses after another schedule publication change…');
    try { await readStatus(); show('Current day statuses refreshed.'); }
    catch (_) { unconfirmed = true; retry.hidden = false; show('Publication status unconfirmed. Retry the day status check.'); }
    finally { pending = false; controls(false); }
  }
  peers.push(refreshPeer);
  controls(false);
  if (scoped) panel.addEventListener('toggle', async function () {
    if (!panel.open || pending) return;
    pending = true;
    unconfirmed = true;
    controls(true);
    show('Checking this draw’s saved and published days…');
    try { await readStatus(); show('Current day statuses refreshed.'); }
    catch (_) { unconfirmed = true; retry.hidden = false; show('Publication status unconfirmed. Retry the day status check.'); }
    finally { pending = false; controls(false); }
  });
  }
  document.querySelectorAll('[data-whole-day-publication], [data-draw-day-publication]').forEach(initialize);
})(window, document);
