(function (window, document) {
  'use strict';
  var panel = document.querySelector('[data-event-draw-publication]');
  if (!panel) return;
  var cards = Array.from(document.querySelectorAll('[data-quick-publication-card]'));
  var summary = panel.querySelector('[data-draw-publication-summary]');
  var unknown = false;
  function lock(busy) {
    cards.forEach(function (card) {
      var quick = card.querySelector('[data-quick-draw-publication]');
      if (!quick) return;
      var disabled = busy || unknown || quick.dataset.lockedPublished === 'true';
      quick.disabled = disabled;
      card.querySelectorAll('.toggle-publish').forEach(function (button) { button.disabled = disabled; });
    });
    panel.querySelectorAll('[data-bulk-draw-action]').forEach(function (button) { button.disabled = busy || unknown; });
  }
  function show(card, text) {
    var feedback = card.querySelector('[data-quick-draw-feedback]');
    feedback.classList.remove('d-none');
    feedback.textContent = text;
  }
  function applyState(result) {
    if (Number(result.event_id) !== Number(panel.dataset.eventId) || !Array.isArray(result.draw_states) || !result.draw_summary) throw new Error('Could not confirm event draw publication status.');
    var states = new Map();
    result.draw_states.forEach(function (state) {
      if (!Number.isInteger(state.id) || states.has(state.id) || typeof state.published !== 'boolean' || typeof state.locked !== 'boolean' || typeof state.oop_published !== 'boolean') throw new Error('Could not confirm every draw status.');
      states.set(state.id, state);
    });
    if (cards.some(function (card) { return !states.has(Number(card.dataset.drawId)); })) throw new Error('Some draw statuses could not be confirmed.');
    cards.forEach(function (card) {
      var state = states.get(Number(card.dataset.drawId));
      if (state.scoring && window.TeamDrawScoringReadiness) window.TeamDrawScoringReadiness.apply(state.id, state.scoring, state.published, state.locked);
      var quick = card.querySelector('[data-quick-draw-publication]');
      if (quick) {
        quick.dataset.published = state.published ? 'true' : 'false';
        quick.dataset.lockedPublished = state.published && state.locked ? 'true' : 'false';
        quick.textContent = state.published ? 'Unpublish' : 'Publish';
        quick.setAttribute('aria-pressed', state.published ? 'true' : 'false');
        quick.setAttribute('aria-label', (state.published ? 'Unpublish' : 'Publish') + ' ' + card.querySelector('h6').textContent.trim());
        quick.title = state.published && state.locked ? 'Locked draws cannot be unpublished.' : '';
        quick.classList.toggle('btn-outline-danger', state.published);
        quick.classList.toggle('btn-success', !state.published);
      }
      card.querySelectorAll('.event-draw-status').forEach(function (badge) {
        badge.textContent = state.published ? 'Draw published' : 'Draw hidden';
        badge.classList.toggle('bg-label-success', state.published);
        badge.classList.toggle('bg-label-warning', !state.published);
      });
      card.querySelectorAll('.toggle-publish').forEach(function (button) {
        button.dataset.status = state.published ? '1' : '0';
        if (window.jQuery) window.jQuery(button).data('status', state.published ? 1 : 0);
        button.textContent = state.published ? 'Hide draw' : 'Publish draw';
      });
      card.querySelectorAll('.event-schedule-status').forEach(function (badge) {
        badge.textContent = state.oop_published ? (state.published ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden';
        badge.classList.toggle('bg-label-success', state.oop_published);
        badge.classList.toggle('bg-label-secondary', !state.oop_published);
      });
      card.querySelectorAll('.toggle-publish-schedule').forEach(function (button) {
        button.dataset.status = state.oop_published ? '1' : '0';
        if (window.jQuery) window.jQuery(button).data('status', state.oop_published ? 1 : 0);
        button.textContent = state.oop_published ? 'Hide schedule' : 'Publish schedule';
      });
      var oop = card.querySelector('.event-oop-summary');
      if (oop) {
        oop.textContent = 'Order of play: ' + (state.oop_published ? (state.published ? 'Published' : 'Preview only') : (oop.dataset.created === '1' ? 'Created' : 'Not done'));
        oop.classList.toggle('bg-label-success', state.oop_published);
        oop.classList.toggle('bg-label-info', !state.oop_published && oop.dataset.created === '1');
        oop.classList.toggle('bg-label-secondary', !state.oop_published && oop.dataset.created !== '1');
      }
      card.querySelector('[data-quick-draw-status-retry]').hidden = true;
    });
    var published = result.draw_states.filter(function (state) { return state.published; }).length;
    var hidden = result.draw_states.length - published;
    var label = !result.draw_states.length ? 'No draws' : (!hidden ? 'All published' : (!published ? 'Unpublished' : 'Partly published'));
    summary.textContent = label + ' · ' + published + ' published · ' + hidden + ' unpublished';
    unknown = false;
    window.HeadOfficeDrawPublicationUnconfirmed = false;
  }
  async function readStatus() {
    var response = await window.fetch(panel.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var result = await response.json();
    if (!response.ok || result.success !== true) throw new Error('Status check failed.');
    applyState(result);
  }
  window.HeadOfficeDrawPublicationUI = {
    apply: function (result) { applyState(result); lock(Boolean(window.HeadOfficeDrawPublicationPending)); },
    pause: function () {
      unknown = true; window.HeadOfficeDrawPublicationUnconfirmed = true;
      summary.textContent = 'Publication status unconfirmed. Refresh or retry the status check before changing a draw.';
      var card = cards.find(function (item) { return item.querySelector('[data-quick-draw-publication]'); });
      if (card) card.querySelector('[data-quick-draw-status-retry]').hidden = false;
      lock(true);
    },
    reconcile: async function () { await readStatus(); lock(Boolean(window.HeadOfficeDrawPublicationPending)); },
    unlock: function () { lock(false); }
  };
  cards.forEach(function (card) {
    var quick = card.querySelector('[data-quick-draw-publication]');
    if (quick) quick.dataset.lockedPublished = quick.disabled ? 'true' : 'false';
    card.querySelector('[data-quick-draw-status-retry]').addEventListener('click', async function (event) {
      event.preventDefault(); event.stopPropagation();
      if (window.HeadOfficeDrawPublicationPending) return;
      window.HeadOfficeDrawPublicationPending = true;
      lock(true);
      try { await readStatus(); show(card, 'Current draw publication statuses confirmed.'); }
      catch (_) { show(card, 'Publication status unconfirmed. Retry the status check before changing a draw.'); }
      finally { window.HeadOfficeDrawPublicationPending = false; lock(false); }
    });
  });
  lock(false);
  document.addEventListener('click', async function (event) {
    var clicked = event.target.closest('[data-quick-draw-publication], [data-quick-publication-card] .toggle-publish');
    if (!clicked) return;
    var card = clicked.closest('[data-quick-publication-card]');
    var quick = card && card.querySelector('[data-quick-draw-publication]');
    if (!quick) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (clicked.disabled || quick.disabled || unknown || window.HeadOfficeDrawPublicationPending) return;
    window.HeadOfficeDrawPublicationPending = true;
    lock(true);
    show(card, 'Updating draw publication…');
    var action = quick.dataset.published === 'true' ? 'unpublish' : 'publish';
    try {
      var response = await window.fetch(panel.dataset.url, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ operation: 'draws', action: action, draw_ids: [Number(card.dataset.drawId)] }) });
      var result = await response.json();
      if (!response.ok || !Array.isArray(result.failed)) throw new Error(result.message || 'The request could not be confirmed.');
      applyState(result);
      if (result.failed.length) show(card, result.failed.map(function (failure) { return failure.message; }).join('; '));
      else if (result.success === true) show(card, quick.dataset.published === 'true' ? 'Draw published. Match times have separate publication controls.' : 'Draw unpublished.');
      else throw new Error('The request outcome could not be confirmed.');
    } catch (error) {
      unknown = true;
      window.HeadOfficeDrawPublicationUnconfirmed = true;
      summary.textContent = 'Publication status unconfirmed. Checking current counts…';
      try { await readStatus(); show(card, error.message + ' Current statuses have been checked; review before another action.'); }
      catch (_) {
        summary.textContent = 'Publication status unconfirmed. Retry the status check before changing a draw.';
        show(card, 'The request outcome is unconfirmed. Publication actions are paused; retry the read-only status check.');
        card.querySelector('[data-quick-draw-status-retry]').hidden = false;
      }
    } finally {
      window.HeadOfficeDrawPublicationPending = false;
      lock(false);
    }
  }, true);
})(window, document);
