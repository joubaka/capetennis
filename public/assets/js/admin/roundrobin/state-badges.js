/**
 * RR State Badges module — draw lock/publish status banner and destructive
 * button guard.
 *
 * Listens for AdminState events and keeps the badge UI in sync.
 * Also provides the lock/unlock toggle button behavior.
 *
 * Depends on: AdminApi, AdminToast, AdminModal, AdminConfirm, AdminState,
 *             AdminLoading, AdminRoutes
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  // ─── Apply badge state ────────────────────────────────────────────
  function applyDrawState(locked, published) {
    var $lb = $('#badge-locked');
    if (locked) {
      $lb.removeClass('d-none').addClass('bg-danger')
         .html('<i class="ti ti-lock me-1"></i>Locked');
    } else {
      $lb.addClass('d-none').removeClass('bg-danger').html('');
    }

    var $pb = $('#badge-published');
    if (published) {
      $pb.removeClass('d-none').addClass('bg-primary')
         .html('<i class="ti ti-eye me-1"></i>Published');
    } else {
      $pb.addClass('d-none').removeClass('bg-primary').html('');
    }

    var disable = locked || published;
    $('[data-rr-destructive]').prop('disabled', disable).toggleClass('disabled', disable);
  }

  // ─── Lock toggle button ───────────────────────────────────────────
  function _toggleLock() {
    var $btn     = $('#btn-toggle-lock');
    var isLocked = $btn.hasClass('btn-danger');
    var action   = isLocked ? 'unlock' : 'lock';

    var confirmFn = isLocked ? AdminConfirm.unlock : AdminConfirm.lock;
    var html = isLocked
      ? '<p>Unlocking allows changes to groups, fixtures and scores.</p>'
      : '<p>Locking prevents changes to groups, fixtures and scores.</p>';

    confirmFn(
      (isLocked ? 'Unlock' : 'Lock') + ' Draw?',
      html
    ).then(function (ok) {
      if (!ok) return;

      var restore = AdminLoading.button($btn, (isLocked ? 'Unlocking…' : 'Locking…'));

      AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/toggle-lock'), {
        _token: $('meta[name="csrf-token"]').attr('content')
      }).then(function (res) {
        if (!res.success) { AdminToast.error(res.message || 'Failed to toggle lock.'); return; }

        AdminToast.success(res.message);

        if (res.locked) {
          $btn.removeClass('btn-outline-warning').addClass('btn-danger');
          $btn.find('i').removeClass('ti-lock-open').addClass('ti-lock');
          $('#lock-label').text('Locked');
        } else {
          $btn.removeClass('btn-danger').addClass('btn-outline-warning');
          $btn.find('i').removeClass('ti-lock').addClass('ti-lock-open');
          $('#lock-label').text('Unlocked');
        }

        AdminState.setLocked(res.locked);
      }).catch(function (err) {
        AdminToast.error(err.message || 'Error toggling lock.');
      }).then(function () { restore(); });
    });
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    $(document).on('click', '#btn-toggle-lock', _toggleLock);

    AdminState.on('rr:draw:locked',     function (e) { applyDrawState(e.detail.locked, e.detail.published); });
    AdminState.on('rr:draw:published',  function (e) { applyDrawState(e.detail.locked, e.detail.published); });
  }

  function init(drawId) {
    DRAW_ID = drawId;
    bind();
    applyDrawState(AdminState.isLocked(), AdminState.isPublished());

    // Keep legacy shim working
    root.rrApplyDrawState = applyDrawState;
  }

  root.RRStateBadges = { init: init, apply: applyDrawState };

}(jQuery, window));
