(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!***************************************!*\
  !*** ./resources/js/pages/regions.js ***!
  \***************************************/
/*
 * Admin — Regions & Teams JS
 */

(function ($, window, document) {
  'use strict';

  console.log('📍 regions.js loaded');
  function logXhrFail(label, xhr) {
    console.group("\u274C ".concat(label));
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }
  var APP_URL = window.APP_URL || window.location.origin;
  var CSRF = $('meta[name="csrf-token"]').attr('content');
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });
  var api = {
    addRegionToEvent: "".concat(APP_URL, "/backend/eventRegion") // ✅ Use direct URL
  };

  // Provide import URL (matches routes/web.php)
  window.importNoProfileUrl = "".concat(APP_URL, "/backend/team/import/action");
  console.log('🟢 Import URL:', window.importNoProfileUrl);

  // Timer state (optional small timer)
  var importTimerInterval = null;
  var importStartTime = null;
  function formatElapsed(ms) {
    var totalSeconds = Math.floor(ms / 1000);
    var minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
    var seconds = (totalSeconds % 60).toString().padStart(2, '0');
    return "".concat(minutes, ":").concat(seconds);
  }
  function startImportTimer() {
    importStartTime = Date.now();
    $('#import-timer').text('00:00');
    importTimerInterval = setInterval(function () {
      var elapsed = Date.now() - importStartTime;
      $('#import-timer').text(formatElapsed(elapsed));
    }, 250);
  }
  function stopImportTimer() {
    if (importTimerInterval) {
      clearInterval(importTimerInterval);
      importTimerInterval = null;
    }
    $('#import-timer').text('00:00');
  }

  // Import UI helpers: show/hide spinner + lock/unlock controls
  function showImportUI() {
    $('#import-status').show();
    $('#import-spinner').show();
    $('#import-message').text('Uploading and processing — please wait...');
    $('#import-submit-btn').prop('disabled', true);
    $('#import-file').prop('disabled', true);
    $('#import-cancel-btn').prop('disabled', true);
    startImportTimer();
  }
  function hideImportUI() {
    stopImportTimer();
    $('#import-spinner').hide();
    $('#import-status').hide();
    $('#import-message').text('Ready to import. Choose a file.');
    $('#import-submit-btn').prop('disabled', false);
    $('#import-file').prop('disabled', false);
    $('#import-cancel-btn').prop('disabled', false);
  }

  // Ensure import UI is reset when modal is hidden (user closed modal or after import)
  $('#import-noprofile-modal').on('hidden.bs.modal', function () {
    // reset file input + team fields
    $('#import-file').val('');
    $('#import-team-id').val('');
    $('#import-region-id').val('');
    $('#import-team-name').text('');
    hideImportUI();
  });

  // ===============================
  // Select2 – Add Region
  // ===============================
  function initRegionSelect2() {
    var $select = $('#select2Region');
    if (!$select.length) return;
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    console.log('🔽 Init Select2 (Region)');
    $select.select2({
      dropdownParent: $('#modalToggle'),
      width: '100%',
      placeholder: 'Select a region or type new one',
      allowClear: true,
      tags: true,
      tokenSeparators: [','],
      searching: true,
      minimumInputLength: 1,
      createTag: function createTag(params) {
        var term = $.trim(params.term);
        if (term === '' || term.length < 2) return null;
        var existingOption = $select.find("option:contains(\"".concat(term, "\")")).length > 0;
        if (existingOption) return null;
        return {
          id: term,
          text: term,
          isNew: true
        };
      },
      templateResult: function templateResult(data) {
        if (data.isNew) {
          return $('<span style="color: #28a745; font-weight: bold;">✨ Create: "' + data.text + '"</span>');
        }
        return data.text;
      },
      templateSelection: function templateSelection(data) {
        return data.isNew ? data.text : data.text;
      }
    });
    $select.on('change', function () {
      console.log('📍 Region selected:', $select.val());
    });
  }
  $('#modalToggle').on('shown.bs.modal', initRegionSelect2);

  // ===============================
  // Add Region to Event
  // ===============================
  $(document).on('click', '#addRegionToEventButton', function () {
    var eventId = $('input[name="event_id"]').val();
    var regionId = $('#select2Region').val();
    console.log('➕ Add region clicked', {
      eventId: eventId,
      regionId: regionId
    });
    if (!eventId || !regionId) {
      toastr.error('Please select a region');
      return;
    }
    $.post(api.addRegionToEvent, {
      event_id: eventId,
      region_id: regionId
    }).done(function (res) {
      var _bootstrap$Modal$getI;
      console.log('✅ Region added', res);
      console.log('Response keys:', Object.keys(res));
      console.log('ID:', res.id);
      console.log('Region Name:', res.region_name);
      console.log('Pivot ID:', res.pivot_id);
      $('.noRegions').remove();
      var html = "\n    <div class=\"accordion-item mb-2 border rounded\"\n         data-region-row\n         data-region-id=\"".concat(res.id, "\"\n         data-pivot-id=\"").concat(res.pivot_id, "\">\n\n      <h2 class=\"accordion-header\" id=\"heading-").concat(res.id, "\">\n        <button class=\"accordion-button collapsed fw-semibold\"\n                type=\"button\"\n                data-bs-toggle=\"collapse\"\n                data-bs-target=\"#collapse-").concat(res.id, "\">\n          <span class=\"badge bg-label-secondary me-2\">#").concat(res.id, "</span>\n          ").concat(res.region_name, "\n          <span class=\"ms-2 text-muted small\">(0 Teams)</span>\n        </button>\n      </h2>\n\n      <div id=\"collapse-").concat(res.id, "\" class=\"accordion-collapse collapse\"\n           data-bs-parent=\"#regionsAccordion\">\n        <div class=\"accordion-body pt-2\">\n\n          <div class=\"d-flex justify-content-end mb-2 gap-2\">\n            <a href=\"javascript:void(0)\"\n               class=\"text-danger removeRegionEvent\"\n               data-id=\"").concat(res.pivot_id, "\">\n              <i class=\"ti ti-trash me-1\"></i> Remove Region\n            </a>\n\n            <a href=\"javascript:void(0)\"\n               class=\"btn btn-sm btn-primary addTeam\"\n               data-regionid=\"").concat(res.id, "\"\n               data-bs-toggle=\"modal\"\n               data-bs-target=\"#addTeamModal\">\n              <i class=\"ti ti-plus me-1\"></i> Add Team\n            </a>\n          </div>\n\n          <div class=\"alert alert-light border text-center py-2\">\n            No teams in this region yet.\n          </div>\n\n        </div>\n      </div>\n    </div>\n  ");
      $('#regionsAccordion').prepend(html);
      (_bootstrap$Modal$getI = bootstrap.Modal.getInstance(document.getElementById('modalToggle'))) === null || _bootstrap$Modal$getI === void 0 ? void 0 : _bootstrap$Modal$getI.hide();
      toastr.success('Region added');
    }).fail(function (xhr) {
      console.error('❌ AJAX Error:');
      console.error('Status:', xhr.status);
      console.error('Response:', xhr.responseText);
      console.error('JSON:', xhr.responseJSON);
      logXhrFail('Add region failed', xhr);
      toastr.error('Failed to add region');
    });
  });

  // ===============================
  // Remove Region
  // ===============================
  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var pivotId = $btn.data('id');
    var $row = $btn.closest('[data-region-row]');
    console.log('🗑 removeRegionEvent', {
      pivotId: pivotId
    });
    Swal.fire({
      title: 'Remove region?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Remove'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.ajax({
        url: "".concat(APP_URL, "/backend/eventRegion/").concat(pivotId),
        method: 'DELETE',
        data: {
          _token: CSRF
        }
      }).done(function () {
        toastr.success('Region removed');
        $row.fadeOut(200, function () {
          return $row.remove();
        });
      }).fail(function (xhr) {
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
    var $btn = $(this);
    var teamId = $btn.data('id');
    var state = String($btn.data('state'));
    console.log('📣 publishTeam', {
      teamId: teamId,
      state: state
    });
    var action = state === '1' ? 'Unpublish' : 'Publish';
    Swal.fire({
      title: "".concat(action, " team?"),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.post("".concat(APP_URL, "/backend/team/publishTeam/").concat(teamId), {
        _token: CSRF
      }).done(function () {
        var newState = state === '1' ? '0' : '1';
        $btn.data('state', newState);
        $btn.toggleClass('btn-success btn-warning').html(newState === '1' ? '<i class="ti ti-eye-off me-1"></i> Unpublish' : '<i class="ti ti-eye me-1"></i> Publish');
        toastr.success("Team ".concat(action.toLowerCase(), "ed"));
      }).fail(function (xhr) {
        return logXhrFail('Publish toggle failed', xhr);
      });
    });
  });

  // ===============================
  // Toggle NoProfile
  // ===============================
  $(document).on('click', '.toggleNoProfile', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var url = $btn.data('url');
    var state = String($btn.data('state'));
    console.log('🟡 toggleNoProfile', {
      url: url,
      state: state
    });
    var action = state === '1' ? 'Disable' : 'Enable';
    Swal.fire({
      title: "".concat(action, " NoProfile?"),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.ajax({
        url: url,
        method: 'PATCH',
        data: {
          _token: CSRF
        }
      }).done(function () {
        var newState = state === '1' ? '0' : '1';
        $btn.data('state', newState);
        $btn.toggleClass('btn-danger btn-success').html(newState === '1' ? '<i class="ti ti-user-off me-1"></i> Disable NoProfile' : '<i class="ti ti-user me-1"></i> Enable NoProfile');
        toastr.success("NoProfile ".concat(action.toLowerCase(), "d"));
      }).fail(function (xhr) {
        return logXhrFail('NoProfile toggle failed', xhr);
      });
    });
  });

  // ===============================
  // Edit Team Category – Open Modal
  // ===============================
  var selectedTeamId = null;
  $(document).on('click', '.edit-team-category', function () {
    var teamData = $(this).data('team');
    selectedTeamId = (teamData === null || teamData === void 0 ? void 0 : teamData.id) || null;
    console.log('✏️ Edit category clicked', {
      teamId: selectedTeamId,
      teamData: teamData
    });

    // Set modal title
    $('#edit-team-category-title').text("Edit Category: ".concat((teamData === null || teamData === void 0 ? void 0 : teamData.name) || 'Team'));

    // Store team id in hidden input
    $('#edit-team-category-modal input[name="team"]').val(selectedTeamId);

    // Clear previous selection
    $('#edit-team-category-modal input[name="category"]').prop('checked', false);
  });

  // ===============================
  // Edit Team Category – Save (AJAX)
  // ===============================
  $(document).on('click', '#change-team-category-button', function (e) {
    e.preventDefault();
    var teamId = $('#edit-team-category-modal input[name="team"]').val();
    var categoryId = $('#edit-team-category-modal input[name="category"]:checked').val();
    console.log('💾 Save category clicked', {
      teamId: teamId,
      categoryId: categoryId
    });
    if (!teamId || !categoryId) {
      toastr.error('Please select a category');
      return;
    }
    $.ajax({
      url: "".concat(APP_URL, "/backend/team/category/change/").concat(teamId),
      method: 'POST',
      data: {
        _token: CSRF,
        team: teamId,
        data: categoryId
      }
    }).done(function (newCategoryName) {
      var _bootstrap$Modal$getI2;
      console.log('✅ Category updated', newCategoryName);

      // Update the category label in the team row
      $(".category-".concat(teamId)).html("\n          Category: <span class=\"fw-semibold text-primary\">".concat(newCategoryName, "</span>\n        "));

      // Close modal
      (_bootstrap$Modal$getI2 = bootstrap.Modal.getInstance(document.getElementById('edit-team-category-modal'))) === null || _bootstrap$Modal$getI2 === void 0 ? void 0 : _bootstrap$Modal$getI2.hide();
      toastr.success('Category updated');
    }).fail(function (xhr) {
      logXhrFail('Change category failed', xhr);
      toastr.error('Failed to update category');
    });
  });

  // =====================================================
  // DELETE TEAM (AJAX)
  // =====================================================
  $(document).on('click', '.removeTeam', function (e) {
    e.preventDefault();
    var $row = $(this).closest('[data-team-row]');
    var teamId = $(this).data('id');
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
    }).then(function (r) {
      if (!r.isConfirmed) return;
      var url = "".concat(APP_URL, "/backend/team/").concat(teamId);
      console.log('➡️ DELETE', url);
      $.ajax({
        url: url,
        method: 'DELETE',
        data: {
          _token: CSRF
        }
      }).done(function () {
        toastr.success('Team deleted');
        $row.fadeOut(200, function () {
          return $row.remove();
        });
      }).fail(function (xhr) {
        var _xhr$responseJSON;
        logXhrFail('Delete team failed', xhr);
        toastr.error(((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || 'Failed to delete team');
      });
    });
  });

  // ===============================
  // Add Team Button – Capture Region ID
  // ===============================
  $(document).on('click', '.addTeam', function () {
    // ❌ NO e.preventDefault() here!
    // Just capture and store the region ID

    var regionId = $(this).data('regionid');
    console.log('🎯 [.addTeam] Click detected');
    console.log('   Region ID:', regionId);
    if (!regionId) {
      console.error('❌ [.addTeam] No region ID found!');
      toastr.error('Region ID is missing');
      return false; // Prevent modal from opening if no region ID
    }

    // Set region_id in the modal form
    $('#region_id').val(regionId);
    console.log('✅ [.addTeam] Set region_id to:', $('#region_id').val());

    // Return true to allow Bootstrap to open the modal
    return true;
  });

  // ===============================
  // Create Team (Save to Region)
  // ===============================
  $(document).on('click', '#updateTeamButton', function (e) {
    e.preventDefault(); // Prevent the data-bs-dismiss from closing immediately
    console.log('🎯 [#updateTeamButton] Click detected');
    var teamName = $('input[name="team_name"]').val();
    var numPlayers = $('input[name="num_players"]').val();
    var year = $('input[name="year"]').val();
    var regionId = $('#region_id').val();
    var published = $('input[name="published"]').val();
    console.log('📋 [#updateTeamButton] Form data:');
    console.log('   Team Name:', teamName);
    console.log('   Region ID:', regionId);
    if (!teamName || !regionId) {
      console.error('❌ Missing required fields');
      toastr.error('Please enter team name');
      return;
    }
    console.log('✅ Sending POST request to create team...');

    // POST to create team in region
    $.post("".concat(APP_URL, "/backend/team"), {
      _token: CSRF,
      name: teamName,
      num_players: numPlayers,
      year: year,
      region_id: regionId,
      published: published
    }).done(function (res) {
      var _bootstrap$Modal$getI3;
      console.log('✅ Team created successfully');
      console.log('   Team ID:', res.id);

      // Clear form
      $('#teamForm')[0].reset();

      // Find region row and add team to it
      var $regionRow = $("[data-region-id=\"".concat(regionId, "\"]"));
      var $teamList = $regionRow.find('.list-group');
      if ($teamList.length === 0) {
        // Remove "No teams" alert and create list
        $regionRow.find('.alert-light').remove();
        var teamListHtml = "\n            <div class=\"list-group\">\n              <div class=\"list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom\" data-team-row data-team-id=\"".concat(res.id, "\">\n                <div>\n                  <div class=\"fw-medium\">").concat(res.name, "</div>\n                  <small class=\"text-muted d-block mb-1 category-").concat(res.id, "\">\n                    Category: <span class=\"fw-semibold text-primary\">None</span>\n                  </small>\n                  <button class=\"btn btn-xs bg-label-info edit-team-category\" data-team='").concat(JSON.stringify({
          id: res.id,
          name: res.name
        }), "' data-bs-toggle=\"modal\" data-bs-target=\"#edit-team-category-modal\">\n                    <i class=\"ti ti-edit me-25\"></i> Edit Category\n                  </button>\n                </div>\n                <div class=\"text-end\" style=\"min-width:180px\">\n                  <a href=\"javascript:void(0)\" class=\"publishTeam btn btn-xs w-100 mb-2 btn-success\" data-id=\"").concat(res.id, "\" data-state=\"0\">\n                    <i class=\"ti ti-eye me-1\"></i> Publish Team\n                  </a>\n                  <a href=\"javascript:void(0)\" class=\"toggleNoProfile btn btn-xs w-100 mb-2 btn-info\" data-url=\"").concat(APP_URL, "/backend/teams/toggle-noprofile/").concat(res.id, "\" data-state=\"0\">\n                    <i class=\"ti ti-user me-1\"></i> Enable NoProfile\n                  </a>\n                  <a href=\"javascript:void(0)\" class=\"text-danger small removeTeam\" data-id=\"").concat(res.id, "\">\n                    <i class=\"ti ti-trash me-25\"></i> Delete\n                  </a>\n                </div>\n              </div>\n            </div>\n          ");
        $regionRow.find('.accordion-body').html(teamListHtml);
      } else {
        // Add to existing team list
        var teamRowHtml = "\n            <div class=\"list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom\" data-team-row data-team-id=\"".concat(res.id, "\">\n              <div>\n                <div class=\"fw-medium\">").concat(res.name, "</div>\n                <small class=\"text-muted d-block mb-1 category-").concat(res.id, "\">\n                  Category: <span class=\"fw-semibold text-primary\">None</span>\n                </small>\n                <button class=\"btn btn-xs bg-label-info edit-team-category\" data-team='").concat(JSON.stringify({
          id: res.id,
          name: res.name
        }), "' data-bs-toggle=\"modal\" data-bs-target=\"#edit-team-category-modal\">\n                  <i class=\"ti ti-edit me-25\"></i> Edit Category\n                </button>\n              </div>\n              <div class=\"text-end\" style=\"min-width:180px\">\n                <a href=\"javascript:void(0)\" class=\"publishTeam btn btn-xs w-100 mb-2 btn-success\" data-id=\"").concat(res.id, "\" data-state=\"0\">\n                  <i class=\"ti ti-eye me-1\"></i> Publish Team\n                </a>\n                <a href=\"javascript:void(0)\" class=\"toggleNoProfile btn btn-xs w-100 mb-2 btn-info\" data-url=\"").concat(APP_URL, "/backend/teams/toggle-noprofile/").concat(res.id, "\" data-state=\"0\">\n                  <i class=\"ti ti-user me-1\"></i> Enable NoProfile\n                </a>\n                <a href=\"javascript:void(0)\" class=\"text-danger small removeTeam\" data-id=\"").concat(res.id, "\">\n                  <i class=\"ti ti-trash me-25\"></i> Delete\n                </a>\n              </div>\n            </div>\n          ");
        $teamList.append(teamRowHtml);
      }

      // Update team count
      var headerText = $regionRow.find('.ms-2.text-muted.small').text();
      var match = headerText.match(/\d+/);
      var currentCount = match ? parseInt(match[0]) : 0;
      $regionRow.find('.ms-2.text-muted.small').text("(".concat(currentCount + 1, " Teams)"));
      toastr.success('Team added to region');

      // Now close the modal
      (_bootstrap$Modal$getI3 = bootstrap.Modal.getInstance(document.getElementById('addTeamModal'))) === null || _bootstrap$Modal$getI3 === void 0 ? void 0 : _bootstrap$Modal$getI3.hide();
    }).fail(function (xhr) {
      console.error('❌ Team creation failed');
      console.error('   Status:', xhr.status);
      console.error('   Response:', xhr);
      toastr.error('Failed to create team');
    });
  });

  // When clicking import on a team row, populate modal
  $(document).on('click', '.import-noprofile-btn', function () {
    var regionId = $(this).data('region-id');
    var teamId = $(this).data('team-id');
    var teamName = $(this).data('team-name');
    // Populate modal fields
    $('#import-team-id').val(teamId);
    $('#import-region-id').val(regionId);
    $('#import-team-name').text(teamName);
    $('#import-file').val(''); // Clear file input
    // Reset status
    $('#import-message').text('Ready to import. Choose a file.');
    $('#import-status').hide();
    stopImportTimer();
  });

  // Handle import form submission - show spinner while importing
  $('#import-submit-btn').on('click', function () {
    var form = document.getElementById('import-noprofile-form');
    var formData = new FormData(form);
    var $file = $('#import-file');
    var $btn = $('#import-submit-btn');
    var $cancel = $('#import-cancel-btn');
    if (!$file.val()) {
      toastr.error('Please select a file to import.');
      return;
    }

    // UI: show spinner/status, disable controls, start small timer
    $('#import-status').show();
    $('#import-spinner').show();
    $btn.prop('disabled', true);
    $file.prop('disabled', true);
    $cancel.prop('disabled', true);
    $('#import-message').text('Uploading and processing — please wait...');
    startImportTimer();
    $.ajax({
      url: window.importNoProfileUrl,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      headers: {
        'X-CSRF-TOKEN': CSRF
      },
      success: function success(response) {
        stopImportTimer();
        $('#import-spinner').hide();
        $('#import-message').text(response.message || 'Import complete');
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        toastr.success(response.message || 'Import finished');
        // hide modal after short delay
        setTimeout(function () {
          var _bootstrap$Modal$getI4;
          (_bootstrap$Modal$getI4 = bootstrap.Modal.getInstance(document.getElementById('import-noprofile-modal'))) === null || _bootstrap$Modal$getI4 === void 0 ? void 0 : _bootstrap$Modal$getI4.hide();
          location.reload();
        }, 900);
      },
      error: function error(xhr) {
        var _xhr$responseJSON2;
        stopImportTimer();
        $('#import-spinner').hide();
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        var msg = ((_xhr$responseJSON2 = xhr.responseJSON) === null || _xhr$responseJSON2 === void 0 ? void 0 : _xhr$responseJSON2.message) || 'Import failed. Please check the file format.';
        $('#import-message').text(msg);
        toastr.error(msg);
        console.error('Import failed', xhr);
      }
    });
  });

  // ===============================
  // Extra confirm dialog for leave
  // ===============================
  window.addEventListener('beforeunload', function (e) {
    var confirmationMessage = 'You have unsaved changes. Are you sure you want to leave?';
    e.returnValue = confirmationMessage; // Gecko + WebKit browsers
    return confirmationMessage; // Gecko + WebKit browsers
  });
})(jQuery, window, document);
/******/ 	return __webpack_exports__;
/******/ })()
;
});