/*
 * Admin — Event Players & Order JS
 * Blade-matched, clean rebuild
 */

(function ($, window, document) {
  'use strict';

  console.log('✅ adminShow.js (players + order) loaded');
  function logXhrFail(label, xhr) {
    console.group(`❌ ${label}`);
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }

  // =====================================================
  // GLOBALS
  // =====================================================
  const APP_URL = window.APP_URL || window.location.origin;
  const CSRF = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });

  // =====================================================
  // API MAP
  // =====================================================
  const api = {
    // Regions
    addRegionToEvent: window.routes.addRegionToEvent,

    // Roster
    loadRoster: APP_URL + '/backend/team/roster',
    saveRoster: APP_URL + '/backend/team/roster/save',

    // Players
    availablePlayers: APP_URL + '/backend/team/availablePlayers',
    replacePlayer: APP_URL + '/backend/team/replacePlayer',

    // Finance
    togglePayStatus: APP_URL + '/backend/team/togglePayStatus',
    refundWallet: APP_URL + '/backend/wallet/refund',

    // Email
    sendMail: APP_URL + '/backend/email/send',

    // Order
    orderPlayers: APP_URL + '/backend/team/orderPlayerList'
  };

  // =====================================================
  // SELECT2 — ADD REGION MODAL
  // =====================================================
  function initRegionSelect2() {
    const $select = $('#select2Region');
    if (!$select.length) return;

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }

    console.log('🔽 Initialising Select2: region selector');

    $select.select2({
      dropdownParent: $('#modalToggle'),
      width: '100%',
      placeholder: 'Select a region',
      allowClear: true
    });

    $select.on('change', function () {
      console.log('📍 Region selected:', $(this).val());
    });
  }

  $('#modalToggle').on('shown.bs.modal', function () {
    console.log('🟢 Add Region modal opened');
    initRegionSelect2();
  });

  // =====================================================
  // ADD REGION TO EVENT
  // =====================================================
  $(document).on('click', '#addRegionToEventButton', function () {
    const eventId = $('input[name="event_id"]').val();
    const regionId = $('#select2Region').val(); // ✅ FIX

    console.log('➕ Add region clicked', {
      eventId,
      regionId,
      rawSelect: $('#select2Region')[0],
      select2Data: $('#select2Region').select2('data')
    });

    if (!eventId || !regionId) {
      toastr.error('Please select a region');
      console.warn('⚠ Missing event_id or region_id');
      return;
    }

    const payload = {
      event_id: eventId,
      region_id: regionId
    };

    console.log('📦 POST payload', payload);
    console.log('➡ POST URL', api.addRegionToEvent);

    $.post(api.addRegionToEvent, payload)
      .done(res => {
        console.log('✅ Region added successfully', res);
        toastr.success('Region added to event');

        bootstrap.Modal.getInstance(
          document.getElementById('modalToggle')
        ).hide();

        location.reload();
      })
      .fail(xhr => {
        console.error('❌ Add region failed', xhr.responseText);
        toastr.error(xhr.responseJSON?.message || 'Failed to add region');
      });
  });

  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const pivotId = $btn.data('id');
    const $row = $btn.closest('[data-region-row], tr, .region-card');

    console.log('🗑️ removeRegionEvent click', { pivotId, row: $row });

    if (!pivotId) {
      toastr.error('Missing pivot id');
      return;
    }

    Swal.fire({
      title: 'Remove region from event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Remove'
    }).then(r => {
      if (!r.isConfirmed) return;

      const url = `${APP_URL}/backend/eventRegion/${pivotId}`;
      console.log('➡️ DELETE', url);

      $.ajax({
        url,
        method: 'DELETE',
        data: { _token: CSRF }
      })
        .done(res => {
          console.log('✅ region removed', res);
          toastr.success('Region removed');

          // 🔥 Remove from UI without reload
          $row.fadeOut(200, () => $row.remove());
        })
        .fail(xhr => {
          logXhrFail('Remove region failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Failed to remove region');
        });
    });
  });


  // -----------------------------
  // 2) PUBLISH / UNPUBLISH TEAM
  // Blade: <a class="publishTeam" data-id="{{ $team->id }}" data-state="0|1">
  // Route: POST backend/team/publishTeam/{id}
  // -----------------------------
  $(document).on('click', '.publishTeam', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const teamId = $btn.data('id');
    const state = String($btn.data('state')); // "0" or "1"

    console.log('📣 publishTeam click', { teamId, state });

    const actionLabel = state === '1' ? 'Unpublish' : 'Publish';

    Swal.fire({
      title: `${actionLabel} team?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: actionLabel
    }).then(r => {
      if (!r.isConfirmed) return;

      const url = `${APP_URL}/backend/team/publishTeam/${teamId}`;
      console.log('➡️ POST', url);

      $.post(url, { _token: CSRF })
        .done(res => {
          console.log('✅ publish toggled', res);

          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          // 🔁 Update label + icon + class
          if (newState === '1') {
            $btn
              .removeClass('btn-success')
              .addClass('btn-warning')
              .html('<i class="ti ti-eye-off me-1"></i> Unpublish');
          } else {
            $btn
              .removeClass('btn-warning')
              .addClass('btn-success')
              .html('<i class="ti ti-eye me-1"></i> Publish');
          }

          toastr.success(`Team ${actionLabel.toLowerCase()}ed`);
        })
        .fail(xhr => {
          logXhrFail('Publish toggle failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Publish toggle failed');
        });
    });
  });


  // -----------------------------
  // 3) ENABLE / DISABLE NOPROFILE
  // Blade: <a class="toggleTeam toggleNoProfile" data-url="..." data-state="0|1">
  // Route: PATCH backend/teams/{id}/toggle-noprofile  (already in Blade via data-url)
  // -----------------------------
  $(document).on('click', '.toggleNoProfile', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const url = $btn.data('url');
    const state = String($btn.data('state'));

    console.log('🟡 toggleNoProfile click', { url, state });

    const actionLabel = state === '1' ? 'Disable' : 'Enable';

    Swal.fire({
      title: `${actionLabel} NoProfile?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: actionLabel
    }).then(r => {
      if (!r.isConfirmed) return;

      console.log('➡️ PATCH', url);

      $.ajax({
        url,
        method: 'PATCH',
        data: { _token: CSRF }
      })
        .done(res => {
          console.log('✅ noprofile toggled', res);

          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          if (newState === '1') {
            $btn
              .removeClass('btn-success')
              .addClass('btn-danger')
              .html('<i class="ti ti-user-off me-1"></i> Disable NoProfile');
          } else {
            $btn
              .removeClass('btn-danger')
              .addClass('btn-success')
              .html('<i class="ti ti-user me-1"></i> Enable NoProfile');
          }

          toastr.success(`NoProfile ${actionLabel.toLowerCase()}d`);
        })
        .fail(xhr => {
          logXhrFail('NoProfile toggle failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'NoProfile toggle failed');
        });
    });
  });

  // =====================================================
  // PLAYER ORDER (SORTABLE)
  // =====================================================
  function initPlayerOrder() {
    if (typeof Sortable === 'undefined') return;

    $('tbody.sortablePlayers').each(function () {
      const $tbody = $(this);
      if ($tbody.data('init')) return;

      const teamId = $tbody.data('team-id');
      if (!teamId) return;

      console.log('↕️ Init order', teamId);
      $tbody.data('init', true);

      new Sortable(this, {
        animation: 150,
        handle: '.drag-handle',
        draggable: 'tr.drag-item',
        onEnd() {
          const order = $tbody.find('tr.drag-item').map(function (i) {
            return {
              id: $(this).data('playerteamid'),
              type: $(this).data('type'),
              position: i + 1
            };
          }).get();

          console.log('↕️ Save order', order);

          $.post(api.orderPlayers, {
            team_id: teamId,
            order
          })
            .done(() => toastr.success('Order saved'))
            .fail(() => toastr.error('Failed to save order'));
        }
      });
    });
  }

  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);

})(jQuery, window, document);
