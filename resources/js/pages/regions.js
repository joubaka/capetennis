/*
 * Admin — Regions & Teams JS
 */

(function ($, window, document) {
  'use strict';

  console.log('📍 regions.js loaded');

  function logXhrFail(label, xhr) {
    console.group(`❌ ${label}`);
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }

  const APP_URL = window.APP_URL || window.location.origin;
  const CSRF = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  });

  const api = {
    addRegionToEvent: window.routes?.addRegionToEvent
  };

  // ===============================
  // Select2 – Add Region
  // ===============================
  function initRegionSelect2() {
    const $select = $('#select2Region');
    if (!$select.length) return;

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }

    console.log('🔽 Init Select2 (Region)');

    $select.select2({
      dropdownParent: $('#modalToggle'),
      width: '100%',
      placeholder: 'Select a region',
      allowClear: true
    });

    $select.on('change', () => {
      console.log('📍 Region selected:', $select.val());
    });
  }

  $('#modalToggle').on('shown.bs.modal', initRegionSelect2);

  // ===============================
  // Add Region to Event
  // ===============================
  $(document).on('click', '#addRegionToEventButton', function () {
    const eventId = $('input[name="event_id"]').val();
    const regionId = $('#select2Region').val();

    console.log('➕ Add region clicked', { eventId, regionId });

    if (!eventId || !regionId) {
      toastr.error('Please select a region');
      return;
    }

    $.post(api.addRegionToEvent, { event_id: eventId, region_id: regionId })
      .done(res => {
        console.log('✅ Region added', res);

        $('.noRegions').remove();

        const html = `
    <div class="accordion-item mb-2 border rounded"
         data-region-row
         data-region-id="${res.id}"
         data-pivot-id="${res.pivot_id}">

      <h2 class="accordion-header" id="heading-${res.id}">
        <button class="accordion-button collapsed fw-semibold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse-${res.id}">
          <span class="badge bg-label-secondary me-2">#${res.id}</span>
          ${res.region_name}
          <span class="ms-2 text-muted small">(0 Teams)</span>
        </button>
      </h2>

      <div id="collapse-${res.id}" class="accordion-collapse collapse"
           data-bs-parent="#regionsAccordion">
        <div class="accordion-body pt-2">

          <div class="d-flex justify-content-end mb-2 gap-2">
            <a href="javascript:void(0)"
               class="text-danger removeRegionEvent"
               data-id="${res.pivot_id}">
              <i class="ti ti-trash me-1"></i> Remove Region
            </a>

            <a href="javascript:void(0)"
               class="btn btn-sm btn-primary addTeam"
               data-regionid="${res.id}"
               data-bs-toggle="modal"
               data-bs-target="#addTeamModal">
              <i class="ti ti-plus me-1"></i> Add Team
            </a>
          </div>

          <div class="alert alert-light border text-center py-2">
            No teams in this region yet.
          </div>

        </div>
      </div>
    </div>
  `;

        $('#regionsAccordion').prepend(html);

        bootstrap.Modal
          .getInstance(document.getElementById('modalToggle'))
          ?.hide();

        toastr.success('Region added');
      })

      .fail(xhr => {
        logXhrFail('Add region failed', xhr);
        toastr.error('Failed to add region');
      });
  });

  // ===============================
  // Remove Region
  // ===============================
  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const pivotId = $btn.data('id');
    const $row = $btn.closest('[data-region-row]');

    console.log('🗑 removeRegionEvent', { pivotId });

    Swal.fire({
      title: 'Remove region?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Remove'
    }).then(r => {
      if (!r.isConfirmed) return;

      $.ajax({
        url: `${APP_URL}/backend/eventRegion/${pivotId}`,
        method: 'DELETE',
        data: { _token: CSRF }
      })
        .done(() => {
          toastr.success('Region removed');
          $row.fadeOut(200, () => $row.remove());
        })
        .fail(xhr => {
          logXhrFail('Remove region failed', xhr);
          toastr.error('Failed to remove region');
        });
    });
  });

  // ===============================
  // Publish / Unpublish Team
  // ===============================
  $(document).on('click', '.publishTeam', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const teamId = $btn.data('id');
    const state = String($btn.data('state'));

    console.log('📣 publishTeam', { teamId, state });

    const action = state === '1' ? 'Unpublish' : 'Publish';

    Swal.fire({
      title: `${action} team?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(r => {
      if (!r.isConfirmed) return;

      $.post(`${APP_URL}/backend/team/publishTeam/${teamId}`, { _token: CSRF })
        .done(() => {
          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          $btn.toggleClass('btn-success btn-warning')
            .html(newState === '1'
              ? '<i class="ti ti-eye-off me-1"></i> Unpublish'
              : '<i class="ti ti-eye me-1"></i> Publish');

          toastr.success(`Team ${action.toLowerCase()}ed`);
        })
        .fail(xhr => logXhrFail('Publish toggle failed', xhr));
    });
  });

  // ===============================
  // Toggle NoProfile
  // ===============================
  $(document).on('click', '.toggleNoProfile', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const url = $btn.data('url');
    const state = String($btn.data('state'));

    console.log('🟡 toggleNoProfile', { url, state });

    const action = state === '1' ? 'Disable' : 'Enable';

    Swal.fire({
      title: `${action} NoProfile?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(r => {
      if (!r.isConfirmed) return;

      $.ajax({ url, method: 'PATCH', data: { _token: CSRF } })
        .done(() => {
          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          $btn.toggleClass('btn-danger btn-success')
            .html(newState === '1'
              ? '<i class="ti ti-user-off me-1"></i> Disable NoProfile'
              : '<i class="ti ti-user me-1"></i> Enable NoProfile');

          toastr.success(`NoProfile ${action.toLowerCase()}d`);
        })
        .fail(xhr => logXhrFail('NoProfile toggle failed', xhr));
    });
  });
  // =====================================================
  // DELETE TEAM (AJAX)
  // =====================================================
  $(document).on('click', '.removeTeam', function (e) {
    e.preventDefault();

    const $row = $(this).closest('[data-team-row]');
    const teamId = $(this).data('id');

    console.log('🗑 Delete team clicked', teamId);

    if (!teamId) {
      toastr.error('Missing team id');
      return;
    }

    Swal.fire({
      title: 'Delete team?',
      text: 'This will permanently delete the team.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Delete'
    }).then(r => {
      if (!r.isConfirmed) return;

      const url = `${APP_URL}/backend/team/${teamId}`;
      console.log('➡️ DELETE', url);

      $.ajax({
        url,
        method: 'DELETE',
        data: { _token: CSRF }
      })
        .done(() => {
          toastr.success('Team deleted');
          $row.fadeOut(200, () => $row.remove());
        })
        .fail(xhr => {
          logXhrFail('Delete team failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Failed to delete team');
        });
    });
  });

})(jQuery, window, document);
