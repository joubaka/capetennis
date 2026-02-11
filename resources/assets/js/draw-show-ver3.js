$(function () {
  'use strict';

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  var unlockDrawButton = $('.unlock-draw-button'),
    removeDrawButton = $('.remove-draw-button'),
    applyScheduleSettingsButton = $('#applySchedule'),
    submitResultButton = $('#submit-result-button'),
    submitResultForm = $('#submit-result-form'),
    createDrawButton = $('#create-draw-button'),
    editTime = $('.timeVenue'),
    removeFromDrawButton = $('.remove-from-draw-button'),
    editResult = $('.editResult'),
    insertResult = $('.insertResult'),
    publishOOPButton = $('#publishOOP'),
    unpublishOOPButton = $('#unpublishOOP'),
    seedSelect = $('.seed-select');

  var flatpickrDateTime = $(".flatpickr-datetime");

  /* ============================================================
        PLAYER RANKING – SORT, DRAG, UPDATE SEED + UI
     ============================================================ */

  // ------------------------------
  // Sort players by seed on load
  // ------------------------------
  function sortPlayerListBySeed() {
    let list = $('#handle-list-1');
    let items = list.children('li').get();

    console.log("%c--- SORTING PLAYER LIST BY SEED ---", "color:#0af;font-weight:bold;");

    items.sort(function (a, b) {

      let regA = $(a).data('id');
      let regB = $(b).data('id');

      let seedA = parseInt($(a).data('seed')) || 9999;
      let seedB = parseInt($(b).data('seed')) || 9999;

      console.log(`COMPARE → Reg ${regA} (seed ${seedA}) vs Reg ${regB} (seed ${seedB})`);

      return seedA - seedB;
    });

    $.each(items, function (_, li) {
      list.append(li);
    });
  }

  // ------------------------------
  // Update seed label
  // ------------------------------
  function updateSeedLabel(regId, newSeed) {
    console.log(`%cUPDATE LABEL → Reg ${regId} now Seed ${newSeed}`, "color:#9b59b6;font-weight:bold;");
    $('.seed-label[data-registration="' + regId + '"]').text('Seed ' + newSeed);
  }

  // ------------------------------
  // Refresh all labels after sorting
  // ------------------------------
  function refreshAllSeedLabels() {
    console.log("%c--- REFRESH ALL SEED LABELS ---", "color:#0af;font-weight:bold;");

    $('#handle-list-1 li').each(function (index) {
      let regId = $(this).data('id');
      let seed = index + 1;

      console.log(`Label Refresh → Reg ${regId} = Seed ${seed}`);

      updateSeedLabel(regId, seed);
    });
  }

  // INITIALIZE ORDER + LABELS
  sortPlayerListBySeed();
  refreshAllSeedLabels();

  // ------------------------------
  // DRAG & DROP SORTING
  // ------------------------------
  if (document.getElementById('handle-list-1')) {

    let list = document.getElementById('handle-list-1');
    let drawId = list.dataset.drawId;

    let sortable = new Sortable(list, {
      animation: 150,
      handle: '.drag-handle',
      onEnd: function () {
        saveNewRankingOrder();
      }
    });

    function saveNewRankingOrder() {

      console.log("%c=== SAVE NEW RANKING ORDER ===", "color:#e67e22;font-weight:bold;");

      $('#handle-list-1 li').each(function (index) {

        let regId = $(this).data('id');
        let newSeed = index + 1;

        let playerName = $(this).find('span').text().trim();

        console.log(`Assigning NEW SEED → Reg ${regId}, Name: "${playerName}", New Seed: ${newSeed}`);

        // Update backend
        $.post(APP_URL + '/backend/draw/changeSeed/' + drawId, {
          reg: regId,
          seed: newSeed
        });

        // Update dropdown
        $('.seed-select[data-registration="' + regId + '"]').val(newSeed);

        // Update label
        updateSeedLabel(regId, newSeed);

        // Update internal seed attribute
        $(this).data('seed', newSeed);
      });

      toastr.success('Seed order updated');
    }
  }

  /* ============================================================
         SEED DROPDOWN CHANGE HANDLING
     ============================================================ */

  seedSelect.on('change', function () {
    var reg_id = $(this).data('registration');
    var val = $(this).val();
    var draw_id = $(this).data('drawid');
    var url = APP_URL + '/backend/draw/changeSeed/' + draw_id;

    console.log(`%cSEED DROPDOWN CHANGED → Reg ${reg_id}, New Seed ${val}`, "color:#c0392b;font-weight:bold;");

    $.post(url, { reg: reg_id, seed: val });

    // prevent duplicate seeds
    var used = [];
    var duplicateFound = false;

    seedSelect.each(function () {
      let value = $(this).val();
      if (value > 0) {
        if (used.includes(value)) {
          console.log(`%cDUPLICATE FOUND: Seed ${value}`, "color:red;font-weight:bold;");
          duplicateFound = true;
        }
        used.push(value);
      }
    });

    if (duplicateFound) {
      toastr.error('Seed already selected');
      $(this).val('');
      return;
    }

    updateSeedLabel(reg_id, val);

    sortPlayerListBySeed();
    refreshAllSeedLabels();
  });

  /* ============================================================
              FIXTURE TIME EDIT
     ============================================================ */

  editTime.on('click', function () {

    $('#typeFixture').val('Individual')
    let oop = $(this).data('id');

    $('#player1name-modal').html($(this).closest('tr').find('.p1').html());
    $('#player2name-modal').html($(this).closest('tr').find('.p2').html());
    $('#oopId').val(oop.id);
  });

  /* ============================================================
             DELETE FIXTURE RESULT
     ============================================================ */

  $('.deleteFixture').on('click', function () {

    var $fixtureId = $(this).data('id');

    $.ajax({
      url: APP_URL + '/backend/fixture/deleteIndResult/' + $fixtureId,
      type: 'post',
      success: function () {
        location.reload();
      }
    });
  });

  /* ============================================================
             INSERT RESULT BUTTON CLICK
     ============================================================ */

  insertResult.on('click', function () {
    var fix = $(this).data('id').id;

    $('input[name="fixture_id"]').val(fix);

    $('#registration-1-name').val($(this).data('reg1'));
    $('#registration-2-name').val($(this).data('reg2'));

    $('#result-modal').on('hidden.bs.modal', function () {
      $('#myModalForm')[0].reset();
    });
  });

  $('#tennisResultForm').on('submit', function (e) {
    e.preventDefault();

    let formData = $(this).serialize();

    $.ajax({
      type: 'GET',
      url: APP_URL + '/backend/fixture/insertResult',
      data: formData,
      success: function (response) {
        $('#tennisResultModal').modal('hide');
        $('#tennisResultForm')[0].reset();
        updateFixtureResult(response);
      }
    });
  });

  /* ============================================================
            PUBLISH / UNPUBLISH OOP
     ============================================================ */

  unpublishOOPButton.on('click', function () {
    $.post(APP_URL + '/backend/draw/publishToggleSchedule/' + $(this).data('id'), function () {
      location.reload();
    });
  });

  publishOOPButton.on('click', function () {
    $.post(APP_URL + '/backend/draw/publishToggleSchedule/' + $(this).data('id'), function () {
      location.reload();
    });
  });

  /* ============================================================
            FLATPICKR SETUP
     ============================================================ */

  flatpickrDateTime.each(function (_, item) {
    item.flatpickr({
      enableTime: true,
      dateFormat: "Y-m-d H:i"
    });
  });

  /* ============================================================
             APPLY SCHEDULE SETTINGS
     ============================================================ */

  applyScheduleSettingsButton.on('click', function () {

    var duration = $('input[name="duration"]').val();
    var numcourts = $('input[name="numcourts"]').val();
    var startTime = $('input[name="firstMatchTime"]').val();
    var endTime = $('input[name="lastMatchTime"]').val();
    var venueId = $('select[name="venue"]').val();
    var venueName = $('#venueSelect option:selected').text();

    $('#duration').html(duration + ' minutes');
    $('#numcourts').html(numcourts);
    $('#startTime').html(startTime);
    $('#endTime').html(endTime);
    $('#venue').html(venueName);
    $('input[name="venueId"]').val(venueId);
  });

  /* ============================================================
              EDIT RESULT (EXISTING LOGIC)
     ============================================================ */

  editResult.on('click', function () {
    var $this = $(this);

    var results = $this.data('result');
    $('#fixture_id').val($this.data('id'));
    updateScores(results);

    $('#reg2name').html($this.data('reg2'));
    $('#reg1name').html($this.data('reg1'));

    submitResultButton.on('click', function () {

      var data = submitResultForm.serialize();

      $.get(APP_URL + '/backend/fixture/insertResult', data, function (results) {
        clearScores($('.score'));
        $('#result-modal').modal('toggle');
        updateFixtureResult($this, results['results']);
      });

      submitResultButton.off('click');
    });
  });

  /* ============================================================
             REMOVE PLAYER FROM DRAW
     ============================================================ */

  removeFromDrawButton.on('click', function () {
    var id = $(this).data('id');
    var drawid = $(this).data('drawid');

    $.ajax({
      url: APP_URL + '/backend/draw/registration/removePlayer/' + id,
      type: 'post',
      data: { id: id, draw_id: drawid },
      success: function () {
        Swal.fire({ title: 'Player removed', customClass: { confirmButton: 'btn btn-primary' } });
      }
    });
  });

  /* ============================================================
                UNLOCK DRAW
     ============================================================ */

  unlockDrawButton.on('click', function () {
    var id = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: "This will delete all fixtures!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      customClass: {
        confirmButton: 'btn btn-primary me-1',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {

      if (result.value) {
        $.ajax({
          url: APP_URL + '/backend/draw/unlock/' + id,
          type: 'post',
          success: function () {
            location.reload();
          }
        });
      }
    });
  });

  /* ============================================================
                DELETE DRAW
     ============================================================ */

  removeDrawButton.on('click', function () {
    var id = $(this).data('id');
    var $this = $(this);

    Swal.fire({
      title: 'Delete draw?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      customClass: {
        confirmButton: 'btn btn-primary me-1',
        cancelButton: 'btn btn-label-secondary'
      }
    }).then(function (result) {

      if (result.value) {
        $this.parents().closest('.list-group-item').remove();

        $.ajax({
          url: APP_URL + '/backend/draw/' + id,
          type: 'DELETE',
          success: function () {
            Swal.fire({
              title: 'Draw deleted',
              customClass: { confirmButton: 'btn btn-primary' }
            });
          }
        });
      }
    });
  });

  /* ============================================================
                SELECT2 INITIALIZATION
     ============================================================ */

  $(".select2").each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',
    });
  });

});

/* ============================================================
                HELPER FUNCTIONS
   ============================================================ */

function updateScores(results) {
  $.each(results, function (key, item) {
    $('#reg1ScoreSet' + (key + 1)).val(item.registration1_score);
    $('#reg2ScoreSet' + (key + 1)).val(item.registration2_score);
  });
}

function clearScores(myClass) {
  $(myClass).each(function (_, item) {
    $(item).val('');
  });
}

function updateFixtureResult(response) {

  var result = '';
  var sets = response.results.length;

  $.each(response.results, function (key, item) {
    result += item.registration1_score + '-' + item.registration2_score;
    if (key < sets - 1) result += ', ';
  });

  var tr = $('#' + response.id);
  let reg1 = tr.find('.registration1').data('id');

  if (response.winner === reg1) {
    tr.find('.registration1').addClass('bg-label-success');
    tr.find('.registration2').addClass('bg-label-danger');
  } else {
    tr.find('.registration1').addClass('bg-label-danger');
    tr.find('.registration2').addClass('bg-label-success');
  }

  tr.find('.resultTd').html(result);
}

/* ============================================================
           SVG DRAW CODE (UNCHANGED)
   ============================================================ */

var drawSize = 32;
var draw = SVG().addTo('#draw');

var height = 100;
var width = 100;
var boxTopPoint = 0;
var boxLeftPoint = 0;

drawBoxRnd1();

function drawBoxRnd1(height, width, boxLeftPoint, boxTopPoint) {
  var rect = draw.rect(width, height).fill('#f06')
  rect.attr({ x: boxLeftPoint, y: boxTopPoint })

  return rect;
}
