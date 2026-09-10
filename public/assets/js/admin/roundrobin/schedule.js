/**
 * RR Schedule module — render schedule table and manage venue add/remove.
 *
 * Depends on: AdminApi, AdminToast, AdminModal, AdminConfirm, AdminLoading, AdminRoutes
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;
  var ALL_VENUES = [];

  function venueRow(selectedId, numCourts) {
    var $row = $('<div class="venue-row d-flex gap-2 mb-2"></div>');
    var $select = $('<select name="venue_id[]" class="form-select venue-select" required></select>')
      .append($('<option></option>').val('').text('-- Select Venue --'));

    ALL_VENUES.forEach(function (venue) {
      $select.append($('<option></option>').val(venue.id).text(venue.name));
    });
    if (selectedId) $select.val(String(selectedId));

    var $courts = $('<input type="number" name="num_courts[]" class="form-control" min="1" required>')
      .val(numCourts || 1)
      .css('max-width', '100px');
    var $remove = $('<button type="button" class="btn btn-danger btn-remove-row" aria-label="Remove venue">&times;</button>');

    $row.append($select, $courts, $remove);
    $('#venues-container').append($row);

    if ($.fn.select2) {
      $select.select2({ dropdownParent: $('#venuesModal'), width: '100%' });
    }
  }

  function showVenueModal() {
    var element = document.getElementById('venuesModal');
    if (!element) {
      AdminToast.error('The venue form is unavailable. Refresh the page and try again.');
      return;
    }
    bootstrap.Modal.getOrCreateInstance(element).show();
  }

  function hideVenueModal() {
    var element = document.getElementById('venuesModal');
    if (!element) return;
    var modal = bootstrap.Modal.getInstance(element);
    if (modal) modal.hide();
  }

  function resetCreateVenue() {
    $('#new-draw-venue-name').val('');
    $('#new-draw-venue-courts').val(1);
    $('#new-draw-venue-ball').val('standard');
    $('#create-draw-venue-status').removeClass('text-danger text-success').text('');
  }

  function _createVenue() {
    var name = $.trim($('#new-draw-venue-name').val());
    var courts = Number($('#new-draw-venue-courts').val());
    var $button = $('#create-draw-venue');
    var $status = $('#create-draw-venue-status').removeClass('text-danger text-success');

    if (!name) {
      $status.addClass('text-danger').text('Enter a venue name.');
      $('#new-draw-venue-name').trigger('focus');
      return;
    }
    if (!Number.isInteger(courts) || courts < 1 || courts > 100) {
      $status.addClass('text-danger').text('Enter between 1 and 100 courts.');
      $('#new-draw-venue-courts').trigger('focus');
      return;
    }

    $button.prop('disabled', true);
    $status.text('Creating venue…');
    AdminApi.postJson(AdminRoutes.get('venueCreate'), {
      name: name,
      courts: courts,
      ball_type: $('#new-draw-venue-ball').val()
    }).then(function (response) {
      var venue = response.venue;
      if (!venue || !venue.id) throw { message: 'The venue was created but could not be selected. Refresh and try again.' };

      if (!ALL_VENUES.some(function (item) { return String(item.id) === String(venue.id); })) {
        ALL_VENUES.push({ id: venue.id, name: venue.name });
        ALL_VENUES.sort(function (a, b) { return a.name.localeCompare(b.name); });
      }

      $('#venues-container .venue-select').each(function () {
        if (!$(this).find('option[value="' + venue.id + '"]').length) {
          $(this).append($('<option></option>').val(venue.id).text(venue.name));
        }
      });

      var $blank = $('#venues-container .venue-select').filter(function () { return !$(this).val(); }).first();
      if ($blank.length) {
        $blank.val(String(venue.id)).trigger('change');
        $blank.closest('.venue-row').find('input[name="num_courts[]"]').val(venue.num_courts || courts);
      } else {
        venueRow(venue.id, venue.num_courts || courts);
      }

      resetCreateVenue();
      $status.addClass('text-success').text((response.message || 'Venue created.') + ' It is selected above; click Save to assign it to this draw.');
    }).catch(function (error) {
      $status.addClass('text-danger').text(error.message || 'Could not create the venue.');
    }).then(function () { $button.prop('disabled', false); });
  }

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

  // ─── Add/update venues ────────────────────────────────────────────
  function _openVenues() {
    var $button = $(this).prop('disabled', true);
    var $container = $('#venues-container').empty();
    $('#venuesForm')
      .attr('action', AdminRoutes.drawUrl(DRAW_ID, '/venues'))
      .data('draw-id', DRAW_ID);
    $('#venuesModal .modal-title').text('Assign Venues to ' + ($button.data('draw-name') || 'Draw'));

    AdminApi.get(AdminRoutes.drawUrl(DRAW_ID, '/venues/json'))
      .then(function (venues) {
        (venues && venues.length ? venues : [{ id: '', num_courts: 1 }]).forEach(function (venue) {
          venueRow(venue.id, venue.num_courts);
        });
        showVenueModal();
      })
      .catch(function (err) {
        $container.empty();
        AdminToast.error(err.message || 'Failed to load venues');
      })
      .then(function () { $button.prop('disabled', false); });
  }

  function _saveVenues(event) {
    event.preventDefault();
    var venueIds = $('#venues-container .venue-select').map(function () { return $(this).val(); }).get();
    var numCourts = $('#venues-container input[name="num_courts[]"]').map(function () { return $(this).val(); }).get();
    var uniqueIds = venueIds.filter(Boolean).filter(function (id, index, values) { return values.indexOf(id) === index; });

    if (uniqueIds.length !== venueIds.filter(Boolean).length) {
      AdminToast.error('Each venue can only be assigned once.');
      return;
    }

    var $submit = $('#venuesForm button[type="submit"]').prop('disabled', true);
    AdminApi.postJson(AdminRoutes.drawUrl(DRAW_ID, '/venues'), {
      venue_id: venueIds,
      num_courts: numCourts
    }).then(function (res) {
      AdminToast.success(res.message || 'Venues updated');
      hideVenueModal();
      refreshVenuesUI();
    }).catch(function (err) {
      AdminToast.error(err.message || 'Failed to update venues');
    }).then(function () { $submit.prop('disabled', false); });
  }

  // ─── Remove venue ────────────────────────────────────────────────
  function _deleteVenue() {
    var $btn    = $(this);
    var drawId  = $btn.data('id');
    var venueId = $btn.data('venue');

    AdminConfirm.destructive('Remove venue?').then(function (ok) {
      if (!ok) return;

      AdminApi.get(AdminRoutes.drawUrl(drawId, '/venues/json')).then(function (venues) {
        var remaining = venues.filter(function (venue) { return String(venue.id) !== String(venueId); });
        return AdminApi.postJson(AdminRoutes.drawUrl(drawId, '/venues'), {
          venue_id: remaining.map(function (venue) { return venue.id; }),
          num_courts: remaining.map(function (venue) { return venue.num_courts || 1; })
        });
      }).then(function (res) {
        AdminToast.success(res.message || 'Venue removed');
        refreshVenuesUI();
      }).catch(function (err) {
        AdminToast.error(err.message || 'Failed to remove venue');
      });
    });
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    $(document).on('click', '.addVenues', _openVenues);
    $(document).on('click', '#addVenueRow', function () { venueRow('', 1); });
    $(document).on('click', '#toggle-create-venue', function () {
      var $panel = $('#create-venue-panel');
      var opening = $panel.hasClass('d-none');
      $panel.toggleClass('d-none', !opening);
      $(this).attr('aria-expanded', opening ? 'true' : 'false');
      if (opening) $('#new-draw-venue-name').trigger('focus');
    });
    $(document).on('click', '#create-draw-venue', _createVenue);
    $(document).on('click', '#venuesModal .btn-remove-row', function () {
      var $row = $(this).closest('.venue-row');
      var $select = $row.find('.venue-select');
      if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
      $row.remove();
    });
    $(document).on('submit', '#venuesForm', _saveVenues);
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
    ALL_VENUES = root.RR_ALL_VENUES || [];
    bind();
    // Expose for legacy shims
    root.refreshVenuesUI = refreshVenuesUI;
  }

  root.RRSchedule = { init: init, refresh: refreshVenuesUI, renderTable: renderScheduleTable };

}(jQuery, window));
