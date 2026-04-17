$(function () {
  'use strict';

  // ===============================
  // GLOBALS
  // ===============================
  window.selectedBrackets = window.selectedBrackets || [];
  window.selectedRounds = window.selectedRounds || [];

  window.APP_URL = window.APP_URL || $('meta[name="app-url"]').attr('content');
  if (!window.APP_URL) window.APP_URL = ''; // fallback

  // Fallback loadData if not defined yet
  if (typeof loadData !== "function") {
    window.loadData = function () {
      console.warn("loadData() not defined yet");
    };
  }

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // Cached selectors
  var submitResultButton = $('#submit-result-button');
  var submitResultForm = $('#submit-result-form');


  // ===============================
  // EDIT RESULT
  // ===============================
  $(document).on('click', '.editResult', function () {
    var $this = $(this);

    $('#fixture_id').val($this.data('id'));
    $('#reg1name').text($this.data('reg1'));
    $('#reg2name').text($this.data('reg2'));

    updateScores($this.data('result'));

    // Remove old click handler, attach once
    submitResultButton.off('click').on('click', function () {

      var data = submitResultForm.serialize();

      $.get(APP_URL + '/backend/fixture/insertResult', data)
        .done(function (results) {
          clearScores('.score');
          $('#result-modal').modal('toggle');
          updateFixtureResult($this, results)
        })
        .fail(function (err) {
          console.log(err);
        });
    });
  });


  // ===============================
  // REMOVE PLAYER FROM DRAW
  // ===============================
  $(document).on('click', '.remove-from-draw-button', function () {
    var $btn = $(this);
    var id = $btn.data('id');
    var drawid = $btn.data('drawid');

    $.post(APP_URL + '/backend/draw/registration/removePlayer/' + id, {
      id: id,
      draw_id: drawid
    }).done(function () {
      $btn.closest('tr').remove();
      Swal.fire({ title: 'Player removed', icon: 'success' });
    });
  });


  // ===============================
  // UNLOCK DRAW
  // ===============================
  $(document).on('click', '.unlock-draw-button', function () {
    var id = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: "This will delete all fixtures and results!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, unlock!'
    }).then(result => {
      if (!result.value) return;

      $.post(APP_URL + '/backend/draw/unlock/' + id)
        .done(function () {
          location.reload();
        });
    });
  });


  // ===============================
  // DELETE DRAW
  // ===============================
  $(document).on('click', '.remove-draw-button', function () {
    var $btn = $(this);
    var id = $btn.data('id');

    Swal.fire({
      title: 'Delete draw?',
      icon: 'warning',
      showCancelButton: true
    }).then(result => {
      if (!result.value) return;

      $.ajax({
        url: APP_URL + '/backend/draw/' + id,
        type: 'DELETE'
      }).done(function () {
        $btn.closest('.list-group-item').remove();
        Swal.fire('Deleted!', '', 'success');
      });
    });
  });


  // ===============================
  // SEED SELECT
  // ===============================
  $(document).on('change', '.seed-select', function () {

    var $this = $(this);
    var regId = $this.data('registration');
    var drawId = $this.data('drawid');
    var val = $this.val();

    $.post(APP_URL + '/backend/draw/changeSeed/' + drawId, {
      reg: regId,
      seed: val
    });

    // Prevent duplicates
    var used = [];
    $(".seed-select").each(function () {
      var v = $(this).val();
      if (used.includes(v) && v > 0) {
        toastr.error("Seed already selected");
        $(this).val('');
      }
      used.push(v);
    });
  });


  // ===============================
  // SELECT2
  // ===============================
  $(".select2").each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent()
    });
  });


  // ===============================
  // VENUES — Multi-venue modal (headOffice pattern)
  // ===============================
  var $venuesModal = $('#venuesModal');
  var $venuesForm  = $('#venuesForm');
  var $venuesContainer = $('#venues-container');
  var csrfToken = $('meta[name="csrf-token"]').attr('content');

  var allVenuesCache = [];

  function venueRowTemplate(selectedId, numCourts) {
    selectedId = selectedId || '';
    numCourts  = numCourts || 1;
    var options = '<option value="">-- Select Venue --</option>';
    allVenuesCache.forEach(function (v) {
      var sel = (selectedId == v.id) ? 'selected' : '';
      options += '<option value="' + v.id + '" ' + sel + '>' + v.name + '</option>';
    });
    return '<div class="venue-row d-flex gap-2 mb-2">'
      + '<select name="venue_id[]" class="form-select venue-select" required>' + options + '</select>'
      + '<input type="number" name="num_courts[]" class="form-control" value="' + numCourts + '" min="1" required style="max-width:100px">'
      + '<button type="button" class="btn btn-danger btn-remove-row">&times;</button>'
      + '</div>';
  }

  function initVenueSelect2($row) {
    var $select = $row.find('.venue-select');
    if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
    $select.select2({ dropdownParent: $venuesModal, width: '100%' });
  }

  $(document).on('click', '.btn-add-venues', function () {
    var drawId   = $(this).data('draw-id');
    var drawName = $(this).data('draw-name') || 'Draw';

    var storeUrl = window.venueStoreBase.replace('DRAW_ID', drawId);
    var jsonUrl  = window.venueJsonBase.replace('DRAW_ID', drawId);

    $venuesForm.attr('action', storeUrl).data('draw-id', drawId);
    $venuesModal.find('.modal-title').text('Assign Venues to ' + drawName);
    $venuesContainer.empty();

    var venuesListUrl = (window.allVenuesUrl || '/backend/venues/json');

    $.when(
      $.get(jsonUrl),
      allVenuesCache.length ? $.Deferred().resolve(allVenuesCache) : $.get(venuesListUrl)
    ).done(function (existingResult, allResult) {
      var existing = Array.isArray(existingResult) ? existingResult : existingResult[0];
      var allVenues = Array.isArray(allResult) ? allResult : (allResult[0] || allResult);
      allVenuesCache = allVenues;

      if (existing && existing.length > 0) {
        existing.forEach(function (v) {
          var $row = $(venueRowTemplate(v.id, v.num_courts));
          $venuesContainer.append($row);
          initVenueSelect2($row);
        });
      } else {
        var $row = $(venueRowTemplate());
        $venuesContainer.append($row);
        initVenueSelect2($row);
      }

      $venuesModal.modal('show');
    }).fail(function () {
      toastr.error('Failed to load venues');
    });
  });

  $('#addVenueRow').on('click', function () {
    var $row = $(venueRowTemplate());
    $venuesContainer.append($row);
    initVenueSelect2($row);
  });

  $(document).on('click', '.btn-remove-row', function () {
    $(this).closest('.venue-row').remove();
  });

  $venuesForm.on('submit', function (e) {
    e.preventDefault();

    var url    = $(this).attr('action');
    var data   = $(this).serialize();
    var drawId = $(this).data('draw-id');

    $.post(url, data + '&_token=' + csrfToken)
      .done(function (response) {
        if (!response.success) {
          toastr.error('Could not save venues.');
          return;
        }

        toastr.success('Venues updated successfully.');
        $venuesModal.modal('hide');

        var $container = $('.draw-venues[data-draw-id="' + drawId + '"]');
        if (response.venues && response.venues.length) {
          var html = '<small class="text-muted me-1"><i class="ti ti-map-pin ti-xs"></i> Venues:</small> ';
          response.venues.forEach(function (v) {
            var courts = v.pivot ? v.pivot.num_courts : (v.num_courts || 1);
            html += '<span class="badge bg-label-primary me-1">'
              + v.name + ' <span class="text-muted">(' + courts + ' court' + (courts != 1 ? 's' : '') + ')</span>'
              + '</span>';
          });
          $container.html(html);
        } else {
          $container.html('<small class="text-muted"><i class="ti ti-map-pin-off ti-xs me-1"></i>No venues assigned</small>');
        }
      })
      .fail(function () {
        toastr.error('Error while saving venues.');
      });
  });


  // ===============================
  // RESET TRIALS
  // ===============================
  // ===============================
  // RESET TRIALS (FIXED ID)
  // ===============================
  // ===============================




  // ===============================
  // AUTO-SCHEDULE TRIALS
  // ===============================
  $(document).on('click', '#autoScheduleTrialsButton', function () {

    const drawId = $(this).data('id');

    const payload = {
      start: $('#startTime').val(),
      duration: $('#duration').val(),
      gap: $('#gap').val(),
      brackets: window.selectedBrackets,
      rounds: window.selectedRounds
    };

    $.post("/backend/draw/" + drawId + "/trials/auto-schedule", payload)
      .done(function () {
        toastr.success("Auto-scheduled");
        loadData();
      })
      .fail(function (xhr) {
        toastr.error(xhr.responseJSON?.error || "Error");
      });
  });


  // ===============================
  // DRAW BOX (SVG TEST)
  // ===============================
  var drawInstance = SVG();
  function drawBoxRnd1(height, width, x, y) {
    return drawInstance.rect(width, height).fill('#f06').attr({ x, y });
  }
});


/* -----------------------------------
   FUNCTIONS
-----------------------------------*/

function updateScores(results) {
  if (!results) return;
  $.each(results, function (i, s) {
    $('#reg1ScoreSet' + (i + 1)).val(s.registration1_score);
    $('#reg2ScoreSet' + (i + 1)).val(s.registration2_score);
  });
}

function clearScores(selector) {
  $(selector).val('');
}

function updateFixtureResult($btn, results) {
  let str = results.map(s =>
    `${s.registration1_score}-${s.registration2_score}`
  ).join(', ');

  $btn.closest('tr').find('.resultTd').html(str);
}
