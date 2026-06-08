/**
 * RR Schedule module — render schedule table and manage venue add/remove.
 *
 * Depends on: AdminApi, AdminToast, AdminModal, AdminConfirm, AdminLoading, AdminRoutes
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  // ─── Schedule table ───────────────────────────────────────────────
  function renderScheduleTable() {
    var oop  = AdminState.getOop();
    var $body = $('#rr-schedule-body');
    if (!$body.length) return;

    if (!oop || !oop.length) {
      $body.html('<tr><td colspan="7" class="text-center text-muted py-3">No fixtures found.</td></tr>');
      return;
    }

    var html = '';
    oop.forEach(function (fx) {
      var venue  = fx.venue_name || '';
      var court  = fx.court || '';
      var time   = fx.time  || '';
      html += '<tr>' +
        '<td>' + (fx.match_nr || fx.id) + '</td>' +
        '<td>' + (fx.home || '---') + '</td>' +
        '<td class="text-center">vs</td>' +
        '<td>' + (fx.away || '---') + '</td>' +
        '<td class="text-center">' + (venue ? '<span class="badge bg-label-primary">' + venue + '</span>' : '<span class="text-muted">—</span>') + '</td>' +
        '<td class="text-center">' + (court || '<span class="text-muted">—</span>') + '</td>' +
        '<td class="text-center">' + (time  || '<span class="text-muted">—</span>') + '</td>' +
        '</tr>';
    });
    $body.html(html);
  }

  // ─── Venues list ─────────────────────────────────────────────────
  function refreshVenuesUI() {
    AdminApi.get(AdminRoutes.appUrl() + '/backend/draw/' + DRAW_ID + '/venues/json')
      .then(function (venues) {
        var $list = $('#rr-venues-list');
        if (!$list.length) return;

        if (!venues || !venues.length) {
          $list.html(
            '<div class="text-muted text-center py-3">' +
            '<i class="ti ti-map-pin-off fs-3 d-block mb-2"></i>' +
            'No venues assigned. Add a venue to enable scheduling.</div>'
          );
          return;
        }

        var html = '';
        venues.forEach(function (v) {
          var courts = v.num_courts || 1;
          var vName  = $('<div>').text(v.name).html();
          html += '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">' +
            '<div><strong>' + vName + '</strong>' +
            '<span class="badge bg-label-info ms-2">' + courts + ' court' + (courts !== 1 ? 's' : '') + '</span></div>' +
            '<button class="btn btn-sm btn-outline-danger deleteVenue"' +
            ' data-id="' + DRAW_ID + '" data-venue="' + v.id + '">' +
            '<i class="ti ti-trash me-1"></i> Remove</button></div>';
        });
        $list.html(html);
      })
      .catch(function () { AdminToast.warning('Could not refresh venues.'); });
  }

  // ─── Add venue ────────────────────────────────────────────────────
  function _saveVenue() {
    var drawId   = $('#drawIdInput').val() || DRAW_ID;
    var venueId  = $('#venueDrawSelect2').val();
    var numCourts = $('#numCourtsInput').val();

    AdminApi.post(AdminRoutes.appUrl() + '/backend/draw/' + drawId + '/venues', {
      _token:     $('meta[name="csrf-token"]').attr('content'),
      venue_id:   venueId,
      num_courts: numCourts
    }).then(function (res) {
      AdminToast.success(res.message || 'Venue added');
      $('#basicModal').modal('hide');
      refreshVenuesUI();
    }).catch(function (err) {
      AdminToast.error(err.message || 'Failed to add venue');
    });
  }

  // ─── Remove venue ────────────────────────────────────────────────
  function _deleteVenue() {
    var $btn    = $(this);
    var drawId  = $btn.data('id');
    var venueId = $btn.data('venue');

    AdminConfirm.destructive('Remove venue?').then(function (ok) {
      if (!ok) return;

      AdminApi.request({
        url:    AdminRoutes.appUrl() + '/backend/draw/' + drawId + '/venues/' + venueId,
        method: 'POST',
        data: {
          _token:   $('meta[name="csrf-token"]').attr('content'),
          _method:  'DELETE'
        }
      }).then(function (res) {
        AdminToast.success(res.message || 'Venue removed');
        $btn.closest('.d-flex').fadeOut(300, function () { $(this).remove(); });
      }).catch(function (err) {
        AdminToast.error(err.message || 'Failed to remove venue');
      });
    });
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    $(document).on('click', '.addVenues', function () {
      $('#drawIdInput').val($(this).data('id'));
    });

    $(document).on('click', '#save-draw-venue-button', _saveVenue);
    $(document).on('click', '.deleteVenue', _deleteVenue);

    // Tab activation
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
      if ($(e.target).attr('id') === 'schedule-tab') {
        renderScheduleTable();
      }
    });

    // React to OOP updates
    AdminState.on('rr:oop:updated', function () {
      // Only re-render if schedule tab is visible
      if ($('#schedule-pane').hasClass('active') || $('#schedule-pane').hasClass('show')) {
        renderScheduleTable();
      }
    });
  }

  function init(drawId) {
    DRAW_ID = drawId;
    bind();
    // Expose for legacy shims
    root.refreshVenuesUI = refreshVenuesUI;
  }

  root.RRSchedule = { init: init, refresh: refreshVenuesUI, renderTable: renderScheduleTable };

}(jQuery, window));
