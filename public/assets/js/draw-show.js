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
  // ADD VENUE MODAL (fills hidden drawId)
  // ===============================
  $(document).on("click", ".addVenues", function () {
    const drawId = $(this).data("id");
    $("#drawIdInput").val(drawId);

    // Build dynamic store route
    window.storeVenueRoute = window.venueStoreBase.replace("DRAW_ID", drawId);
  });


  // ===============================
  // SAVE VENUE
  // ===============================
  $(document).on("click", "#save-draw-venue-button", function (e) {
    e.preventDefault();

    const drawId = $("#drawIdInput").val();
    const venueId = $("#venueDrawSelect2").val();
    const courts = $("#numCourtsInput").val();

    if (!venueId) return toastr.error("Select a venue");
    if (!courts || courts <= 0) return toastr.error("Invalid court count");

    $.post(window.storeVenueRoute, {
      "venue_id[]": venueId,
      "num_courts[]": courts
    }).done(function () {
      toastr.success("Venue added");
      $("#basicModal").modal("hide");
      loadData();
    }).fail(function () {
      toastr.error("Failed to save venue");
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
