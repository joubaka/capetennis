/**
 * bracket-score.js
 * Handles score submission and deletion for bracket fixtures via the canonical API.
 *
 * Expected globals: AdminCore (from admin/core/index.js), window.drawId
 * Emits: custom event `bracket:scoreUpdated` after a successful write.
 */
import AdminCore from '../core/index.js';

const BracketScore = (() => {
  function _scoreUrl(fixtureId) {
    return `/api/draws/${window.drawId}/fixtures/${fixtureId}/score`;
  }

  /**
   * Submit a score for a bracket fixture.
   *
   * @param {number}   fixtureId
   * @param {string[]} sets      e.g. ['6-3', '4-6', '7-5']
   * @param {Function} [onSuccess]
   */
  function save(fixtureId, sets, onSuccess) {
    AdminCore.loading.show();

    $.ajax({
      url: _scoreUrl(fixtureId),
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      contentType: 'application/json',
      data: JSON.stringify({ sets }),
    })
      .done((resp) => {
        if (!resp.success) {
          AdminCore.toast.error(resp.message || 'Score save failed.');
          return;
        }
        AdminCore.toast.success('Score saved.');
        $(document).trigger('bracket:scoreUpdated', [fixtureId, resp]);
        if (typeof onSuccess === 'function') onSuccess(resp);
      })
      .fail((xhr) => {
        const msg = xhr.responseJSON?.message || 'Error saving score.';
        AdminCore.toast.error(msg);
      })
      .always(() => AdminCore.loading.hide());
  }

  /**
   * Delete the score for a bracket fixture (with confirmation).
   *
   * @param {number}   fixtureId
   * @param {Function} [onSuccess]
   */
  function remove(fixtureId, onSuccess) {
    AdminCore.confirm.show(
      'Delete Score',
      'Are you sure you want to delete this score? This will roll back bracket progression.',
      () => {
        AdminCore.loading.show();

        $.ajax({
          url: _scoreUrl(fixtureId),
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
          .done((resp) => {
            if (!resp.success) {
              AdminCore.toast.error(resp.message || 'Score delete failed.');
              return;
            }
            AdminCore.toast.success('Score deleted.');
            $(document).trigger('bracket:scoreUpdated', [fixtureId, resp]);
            if (typeof onSuccess === 'function') onSuccess(resp);
          })
          .fail((xhr) => {
            const msg = xhr.responseJSON?.message || 'Error deleting score.';
            AdminCore.toast.error(msg);
          })
          .always(() => AdminCore.loading.hide());
      }
    );
  }

  return { save, remove };
})();

export default BracketScore;
