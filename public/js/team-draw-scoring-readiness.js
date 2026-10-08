(function (window, document) {
  'use strict';
  var paused = new Set();
  function buttons(id) { return document.querySelectorAll('[data-enable-scoring][data-draw-id="' + id + '"]'); }
  function apply(id, state, published, locked) {
    if (!state || typeof state.ready !== 'boolean' || typeof published !== 'boolean' || typeof locked !== 'boolean') throw new Error('Scoring status could not be confirmed.');
    document.querySelectorAll('[data-scoring-status][data-draw-id="' + id + '"]').forEach(function (badge) {
      badge.textContent = state.ready ? 'Scoring ready' : 'Scoring not ready';
      badge.classList.toggle('bg-label-success', state.ready);
      badge.classList.toggle('bg-label-warning', !state.ready);
    });
    buttons(id).forEach(function (button) {
      button.textContent = 'Enable scoring';
      button.hidden = !published || state.ready;
      button.disabled = Boolean(locked);
      button.dataset.locked = locked ? 'true' : 'false';
    });
    paused.delete(Number(id));
  }
  window.TeamDrawScoringReadiness = { apply: apply };
  document.addEventListener('click', function (event) {
    if (window.HeadOfficeDrawPublicationPending && event.target.closest('.toggle-publish, .toggle-publish-schedule, [data-quick-draw-publication], [data-bulk-draw-action]')) {
      event.preventDefault(); event.stopImmediatePropagation();
    }
  }, true);
  if (window.jQuery) window.jQuery(document).ajaxSuccess(function (_, xhr, settings, result) {
    if (settings.url.indexOf('/draw/publishToggle/') !== -1 && result.success && result.scoring) {
      apply(result.id, result.scoring, result.published, result.locked);
    }
  });
  document.addEventListener('click', async function (event) {
    var button = event.target.closest('[data-enable-scoring]');
    if (!button) return;
    event.preventDefault(); event.stopPropagation();
    if (button.disabled || window.HeadOfficeDrawPublicationPending || window.HeadOfficeDrawPublicationUnconfirmed) return;
    var id = Number(button.dataset.drawId);
    var feedback = document.querySelector('[data-scoring-feedback][data-draw-id="' + id + '"]');
    buttons(id).forEach(function (item) { item.disabled = true; });
    window.HeadOfficeDrawPublicationPending = true;
    feedback.textContent = 'Validating team ties…';
    try {
      if (paused.has(id)) {
        var check = await window.fetch(button.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        var current = await check.json();
        if (!check.ok || !current.success || Number(current.id) !== id) throw new Error('Scoring status could not be confirmed.');
        apply(id, current.scoring, current.published, current.locked);
        button.textContent = 'Enable scoring';
        feedback.textContent = 'Current scoring status confirmed. Review before enabling scoring.';
        return;
      }
      var response = await window.fetch(button.dataset.url, { method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
      var result = await response.json();
      if (response.status === 422 && result.success === false) {
        feedback.textContent = result.message || 'Review team ties before enabling scoring.';
        return;
      }
      if (!response.ok || !result.success || Number(result.id) !== id) throw new Error(result.message || 'Could not enable scoring.');
      apply(id, result.scoring, result.published, result.locked);
      feedback.textContent = result.message;
    } catch (error) {
      try {
        var status = await window.fetch(button.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        var fresh = await status.json();
        if (!status.ok || !fresh.success || Number(fresh.id) !== id) throw new Error('Status unavailable.');
        apply(id, fresh.scoring, fresh.published, fresh.locked);
        feedback.textContent = error.message + ' Current scoring status checked; review before another action.';
      } catch (_) {
        paused.add(id);
        button.textContent = 'Check scoring status';
        feedback.textContent = 'Outcome unconfirmed. Check scoring status before another action.';
      }
    } finally {
      window.HeadOfficeDrawPublicationPending = false;
      buttons(id).forEach(function (item) { item.disabled = item.dataset.locked === 'true'; });
    }
  });
})(window, document);
