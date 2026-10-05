/******/ (function() { // webpackBootstrap
/*!***************************************!*\
  !*** ./resources/js/pages/regions.js ***!
  \***************************************/
function _toConsumableArray(r) { return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _iterableToArray(r) { if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r); }
function _arrayWithoutHoles(r) { if (Array.isArray(r)) return _arrayLikeToArray(r); }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
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
  window.importNoProfileUrl = window.importNoProfileUrl || null;
  var bulkTeamImportUrl = null;

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
    $('#import-confirmed').val('0');
    $('#import-submit-btn').text('Preview roster');
    $('#import-preview').addClass('d-none');
    $('#import-preview-body').empty();
    $('#import-errors').addClass('d-none').empty();
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
          return $('<span style="color: #28a745; font-weight: bold;">✨ Create: "' + escapeHtml(data.text) + '"</span>');
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
  $('#modalToggle').on('shown.bs.modal', initRegionSelect2).on('hidden.bs.modal', function () {
    return $('#regionShortName').val('');
  });

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
    var $button = $(this);
    $button.prop('disabled', true);
    $.post(api.addRegionToEvent, {
      event_id: eventId,
      region_id: regionId,
      short_name: $('#regionShortName').val()
    }).done(function (res) {
      var _bootstrap$Modal$getI;
      console.log('✅ Region added', res);
      console.log('Response keys:', Object.keys(res));
      console.log('ID:', res.id);
      console.log('Region Name:', res.region_name);
      console.log('Pivot ID:', res.pivot_id);
      $('.noRegions').remove();
      var html = "\n    <div class=\"accordion-item mb-2 border rounded\"\n         data-region-row\n         data-region-id=\"".concat(res.id, "\"\n         data-pivot-id=\"").concat(res.pivot_id, "\">\n\n      <h2 class=\"accordion-header\" id=\"heading-").concat(res.id, "\">\n        <button class=\"accordion-button collapsed fw-semibold\"\n                type=\"button\"\n                data-bs-toggle=\"collapse\"\n                data-bs-target=\"#collapse-").concat(res.id, "\">\n          <span class=\"badge bg-label-secondary me-2\">#").concat(res.id, "</span>\n          <span class=\"region-name\">").concat(escapeHtml(res.region_name), "</span><span class=\"region-short-name ms-2 text-muted\">(").concat(escapeHtml(res.abbreviation), ")</span>\n          <span class=\"ms-2 text-muted small\">(0 Teams)</span>\n        </button>\n      </h2>\n\n      <div id=\"collapse-").concat(res.id, "\" class=\"accordion-collapse collapse\"\n           data-bs-parent=\"#regionsAccordion\">\n        <div class=\"accordion-body pt-2\">\n\n          <div class=\"d-flex flex-wrap justify-content-end mb-2 gap-2\">\n            <button type=\"button\"\n                    class=\"btn btn-sm btn-outline-secondary renameRegionEvent\"\n                    data-id=\"").concat(res.pivot_id, "\"\n                    data-name=\"").concat(escapeHtml(res.region_name), "\"\n                    data-short-name=\"").concat(escapeHtml(res.short_name || ''), "\" data-event-count=\"").concat(res.event_count || 1, "\">\n              <i class=\"ti ti-edit me-1\"></i> Edit Region\n            </button>\n\n            <a href=\"javascript:void(0)\"\n               class=\"text-danger removeRegionEvent\"\n               data-id=\"").concat(res.pivot_id, "\">\n              <i class=\"ti ti-trash me-1\"></i> Remove Region\n            </a>\n\n            <button type=\"button\"\n                    class=\"btn btn-sm btn-success publishRegionTeams\"\n                    data-url=\"").concat(APP_URL, "/backend/event/").concat(eventId, "/region/").concat(res.id, "/teams/publish\"\n                    data-team-count=\"0\"\n                    data-unpublished-count=\"0\"\n                    disabled>\n              <i class=\"ti ti-eye me-1\"></i> Publish All Teams\n            </button>\n\n            <a href=\"javascript:void(0)\"\n               class=\"btn btn-sm btn-outline-primary import-region-teams-btn\"\n               data-region-name=\"").concat(escapeHtml(res.region_name), "\"\n               data-team-prefix=\"").concat(escapeHtml(res.short_name || res.region_name), "\"\n               data-import-url=\"").concat(APP_URL, "/backend/event/").concat(eventId, "/region/").concat(res.id, "/external-teams/import\"\n               data-bs-toggle=\"modal\"\n               data-bs-target=\"#import-region-teams-modal\">\n              <i class=\"ti ti-file-spreadsheet me-1\"></i> Import Teams\n            </a>\n\n            <a href=\"javascript:void(0)\"\n               class=\"btn btn-sm btn-primary addTeam\"\n               data-regionid=\"").concat(res.id, "\"\n               data-bs-toggle=\"modal\"\n               data-bs-target=\"#addTeamModal\">\n              <i class=\"ti ti-plus me-1\"></i> Add Team\n            </a>\n          </div>\n\n          <div class=\"teams-container\">\n            <div class=\"alert alert-light border text-center py-2 no-teams-alert\">\n              No teams in this region yet.\n            </div>\n          </div>\n\n        </div>\n      </div>\n    </div>\n  ");

      // Remove the "no regions" alert if present, then prepend new region
      $('#regionsAccordion .noRegions').remove();
      if (!$('#regionsAccordion [data-region-row]').filter(function () {
        return String($(this).attr('data-region-id')) === String(res.id);
      }).length) $('#regionsAccordion').prepend(html);
      (_bootstrap$Modal$getI = bootstrap.Modal.getInstance(document.getElementById('modalToggle'))) === null || _bootstrap$Modal$getI === void 0 || _bootstrap$Modal$getI.hide();
      $('#regionShortName').val('');
      toastr.success('Region added');
    }).fail(function (xhr) {
      var _xhr$responseJSON;
      console.error('❌ AJAX Error:');
      console.error('Status:', xhr.status);
      console.error('Response:', xhr.responseText);
      console.error('JSON:', xhr.responseJSON);
      logXhrFail('Add region failed', xhr);
      toastr.error(((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || 'Failed to add region');
    }).always(function () {
      $button.prop('disabled', false);
    });
  });

  // ===============================
  // Edit Region
  // ===============================
  $(document).on('click', '.renameRegionEvent', function (e) {
    e.preventDefault();
    var $button = $(this);
    var pivotId = $button.data('id');
    var currentName = String($button.attr('data-name') || '');
    var eventCount = Number($button.data('event-count') || 1);
    var sharedWarning = eventCount > 1 ? "<div class=\"alert alert-warning py-2 mt-3 mb-0\">This shared region is used by ".concat(eventCount, " events. Renaming it changes the name in all of them.</div>") : '<div class="text-muted small mt-2">Teams, clothing and event links will stay unchanged.</div>';
    Swal.fire({
      title: 'Edit region',
      showDenyButton: false,
      didOpen: function didOpen() {
        var _Swal$getDenyButton;
        (_Swal$getDenyButton = Swal.getDenyButton()) === null || _Swal$getDenyButton === void 0 || _Swal$getDenyButton.remove();
      },
      html: "<label for=\"editRegionName\" class=\"form-label\">Region name</label><input id=\"editRegionName\" class=\"form-control\" maxlength=\"255\" value=\"".concat(escapeHtml(currentName), "\"><label for=\"editRegionShortName\" class=\"form-label mt-3\">Short name (optional)</label><input id=\"editRegionShortName\" class=\"form-control\" maxlength=\"20\" value=\"").concat(escapeHtml($button.attr('data-short-name') || ''), "\"><div class=\"text-muted small mt-2\">Leave blank to use an automatic abbreviation.</div>").concat(sharedWarning),
      showCancelButton: true,
      confirmButtonText: 'Save region',
      showLoaderOnConfirm: true,
      preConfirm: function preConfirm() {
        var regionName = $('#editRegionName').val().trim();
        if (!regionName) {
          Swal.showValidationMessage('Enter a region name.');
          return false;
        }
        return $.ajax({
          url: "".concat(APP_URL, "/backend/eventRegion/").concat(pivotId),
          method: 'PATCH',
          data: {
            _token: CSRF,
            region_name: regionName,
            short_name: $('#editRegionShortName').val().trim()
          }
        })["catch"](function (xhr) {
          var _xhr$responseJSON2;
          Swal.showValidationMessage(((_xhr$responseJSON2 = xhr.responseJSON) === null || _xhr$responseJSON2 === void 0 ? void 0 : _xhr$responseJSON2.message) || 'Failed to save region.');
        });
      },
      allowOutsideClick: function allowOutsideClick() {
        return !Swal.isLoading();
      }
    }).then(function (result) {
      if (!result.isConfirmed || !result.value) return;
      var response = result.value;
      var name = response.region_name;
      var $row = $button.closest('[data-region-row]');
      $row.find('.region-name').first().text(name);
      $row.find('.renameRegionEvent').attr('data-name', name).attr('data-short-name', response.short_name || '');
      $row.find('.region-short-name').text('(' + response.abbreviation + ')');
      $row.find('.import-region-teams-btn').attr('data-region-name', name).attr('data-team-prefix', response.short_name || name).data('region-name', name).data('team-prefix', response.short_name || name);
      toastr.success(response.message || 'Region renamed.');
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
        var _xhr$responseJSON3;
        logXhrFail('Remove region failed', xhr);
        toastr.error(((_xhr$responseJSON3 = xhr.responseJSON) === null || _xhr$responseJSON3 === void 0 ? void 0 : _xhr$responseJSON3.message) || 'Failed to remove region');
      });
    });
  });

  // ===============================
  // Publish / Unpublish Team
  // ===============================
  function renderTeamPublicationButton($btn, published) {
    var state = published ? '1' : '0';
    $btn.data('state', state).attr('data-state', state).toggleClass('btn-warning', published).toggleClass('btn-success', !published).html(published ? '<i class="ti ti-eye-off me-1"></i> Unpublish Team' : '<i class="ti ti-eye me-1"></i> Publish Team');
  }
  function syncRegionPublicationButton($regionRow) {
    var $bulkBtn = $regionRow.find('.publishRegionTeams').first();
    if (!$bulkBtn.length) return;
    var $teamButtons = $regionRow.find('.publishTeam');
    var unpublishedCount = $teamButtons.filter(function () {
      return String($(this).data('state')) !== '1';
    }).length;
    $bulkBtn.data('team-count', $teamButtons.length).attr('data-team-count', $teamButtons.length).data('unpublished-count', unpublishedCount).attr('data-unpublished-count', unpublishedCount).prop('disabled', $teamButtons.length === 0 || unpublishedCount === 0).html(unpublishedCount === 0 && $teamButtons.length > 0 ? '<i class="ti ti-check me-1"></i> All Teams Published' : '<i class="ti ti-eye me-1"></i> Publish All Teams');
  }
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
    var targetState = state !== '1';
    Swal.fire({
      title: "".concat(action, " team?"),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $btn.prop('disabled', true);
      $.post($btn.data('url') || "".concat(APP_URL, "/backend/team/publishTeam/").concat(teamId), {
        _token: CSRF,
        published: targetState ? 1 : 0
      }).done(function (res) {
        renderTeamPublicationButton($btn, !!res.published);
        syncRegionPublicationButton($btn.closest('[data-region-row]'));
        toastr.success(res.message || "Team ".concat(action.toLowerCase(), "ed."));
      }).fail(function (xhr) {
        var _xhr$responseJSON4;
        logXhrFail('Publish update failed', xhr);
        toastr.error(((_xhr$responseJSON4 = xhr.responseJSON) === null || _xhr$responseJSON4 === void 0 ? void 0 : _xhr$responseJSON4.message) || 'Could not update the team publication status.');
      }).always(function () {
        return $btn.prop('disabled', false);
      });
    });
  });

  // ===============================
  // Publish every team in one event region
  // ===============================
  $(document).on('click', '.publishRegionTeams', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $regionRow = $btn.closest('[data-region-row]');
    var unpublishedCount = Number($btn.data('unpublished-count')) || 0;
    if ($btn.prop('disabled') || unpublishedCount === 0) return;
    Swal.fire({
      title: 'Publish all teams in this region?',
      text: "".concat(unpublishedCount, " unpublished ").concat(unpublishedCount === 1 ? 'team' : 'teams', " will become visible."),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Publish all'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $btn.prop('disabled', true);
      $.post($btn.data('url'), {
        _token: CSRF
      }).done(function (res) {
        $regionRow.find('.publishTeam').each(function () {
          renderTeamPublicationButton($(this), true);
        });
        syncRegionPublicationButton($regionRow);
        toastr.success(res.message || 'All teams in this region are published.');
      }).fail(function (xhr) {
        var _xhr$responseJSON5;
        logXhrFail('Publish region teams failed', xhr);
        toastr.error(((_xhr$responseJSON5 = xhr.responseJSON) === null || _xhr$responseJSON5 === void 0 ? void 0 : _xhr$responseJSON5.message) || 'Could not publish the teams in this region.');
        syncRegionPublicationButton($regionRow);
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
      (_bootstrap$Modal$getI2 = bootstrap.Modal.getInstance(document.getElementById('edit-team-category-modal'))) === null || _bootstrap$Modal$getI2 === void 0 || _bootstrap$Modal$getI2.hide();
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
        var _xhr$responseJSON6;
        logXhrFail('Delete team failed', xhr);
        toastr.error(((_xhr$responseJSON6 = xhr.responseJSON) === null || _xhr$responseJSON6 === void 0 ? void 0 : _xhr$responseJSON6.message) || 'Failed to delete team');
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

      // Find region row's teams container
      var $regionRow = $("[data-region-id=\"".concat(regionId, "\"]"));
      var $teamsContainer = $regionRow.find('.teams-container');

      // Remove "no teams" alert
      $teamsContainer.find('.no-teams-alert').remove();
      var teamRowHtml = "\n            <div class=\"list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom\" data-team-row data-team-id=\"".concat(res.id, "\">\n              <div>\n                <div class=\"fw-medium\">").concat(res.name, "</div>\n                <small class=\"text-muted d-block mb-1 category-").concat(res.id, "\">\n                  Category: <span class=\"fw-semibold text-primary\">None</span>\n                </small>\n                <button class=\"btn btn-xs bg-label-info edit-team-category\" data-team='").concat(JSON.stringify({
        id: res.id,
        name: res.name
      }), "' data-bs-toggle=\"modal\" data-bs-target=\"#edit-team-category-modal\">\n                  <i class=\"ti ti-edit me-25\"></i> Edit Category\n                </button>\n              </div>\n              <div class=\"text-end\" style=\"min-width:180px\">\n                <button type=\"button\" class=\"publishTeam btn btn-xs w-100 mb-2 btn-success\" data-id=\"").concat(res.id, "\" data-url=\"").concat(APP_URL, "/backend/team/publishTeam/").concat(res.id, "\" data-state=\"0\">\n                  <i class=\"ti ti-eye me-1\"></i> Publish Team\n                </button>\n                <a href=\"javascript:void(0)\" class=\"toggleNoProfile btn btn-xs w-100 mb-2 btn-info\" data-url=\"").concat(APP_URL, "/backend/teams/toggle-noprofile/").concat(res.id, "\" data-state=\"0\">\n                  <i class=\"ti ti-user me-1\"></i> Enable NoProfile\n                </a>\n                <a href=\"javascript:void(0)\" class=\"text-danger small removeTeam\" data-id=\"").concat(res.id, "\">\n                  <i class=\"ti ti-trash me-25\"></i> Delete\n                </a>\n              </div>\n            </div>\n          ");
      var $teamList = $teamsContainer.find('.list-group');
      if ($teamList.length === 0) {
        $teamsContainer.append('<div class="list-group"></div>');
        $teamList = $teamsContainer.find('.list-group');
      }
      $teamList.append(teamRowHtml);
      syncRegionPublicationButton($regionRow);

      // Update team count
      var headerText = $regionRow.find('.ms-2.text-muted.small').text();
      var match = headerText.match(/\d+/);
      var currentCount = match ? parseInt(match[0]) : 0;
      $regionRow.find('.ms-2.text-muted.small').text("(".concat(currentCount + 1, " Teams)"));
      toastr.success('Team added to region');

      // Now close the modal
      (_bootstrap$Modal$getI3 = bootstrap.Modal.getInstance(document.getElementById('addTeamModal'))) === null || _bootstrap$Modal$getI3 === void 0 || _bootstrap$Modal$getI3.hide();
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
    window.importNoProfileUrl = $(this).data('import-url');
    // Populate modal fields
    $('#import-team-id').val(teamId);
    $('#import-region-id').val(regionId);
    $('#import-team-name').text(teamName);
    $('#import-template-link').attr('href', $(this).data('template-url'));
    $('#import-confirmed').val('0');
    $('#import-submit-btn').text('Preview roster');
    $('#import-preview').addClass('d-none');
    $('#import-preview-body').empty();
    $('#import-errors').addClass('d-none').empty();
    $('#import-file').val(''); // Clear file input
    // Reset status
    $('#import-message').text('Ready to import. Choose a file.');
    $('#import-status').hide();
    stopImportTimer();
  });
  $('#import-file').on('change', function () {
    $('#import-confirmed').val('0');
    $('#import-submit-btn').text('Preview roster');
    $('#import-preview').addClass('d-none');
    $('#import-preview-body').empty();
    $('#import-errors').addClass('d-none').empty();
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
        if (response.requires_confirmation) {
          var rows = response.preview || [];
          $('#import-preview-body').html(rows.map(function (row) {
            return "\n            <tr>\n              <td>".concat(row.rank, "</td>\n              <td>").concat($('<div>').text("".concat(row.name, " ").concat(row.surname)).html(), "</td>\n              <td>").concat(row.date_of_birth || '—', "</td>\n              <td><span class=\"badge ").concat(row.candidate_count ? 'bg-label-warning' : 'bg-label-secondary', "\">").concat(row.candidate_count, "</span></td>\n            </tr>\n          ");
          }).join(''));
          $('#import-preview').removeClass('d-none');
          $('#import-confirmed').val('1');
          $btn.text("Confirm import of ".concat(response.row_count, " players"));
          toastr.info('Review the roster, then confirm the import.');
          return;
        }
        toastr.success(response.message || 'Import finished');
        setTimeout(function () {
          return location.reload();
        }, 700);
      },
      error: function error(xhr) {
        stopImportTimer();
        $('#import-spinner').hide();
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        var payload = xhr.responseJSON || {};
        var msg = payload.message || 'Import failed. Please check the file format.';
        $('#import-message').text(msg);
        var rawErrors = payload.errors || [];
        var errors = Array.isArray(rawErrors) ? rawErrors : Object.values(rawErrors).flat();
        if (errors.length) {
          $('#import-errors').removeClass('d-none').html("<strong>Nothing was imported.</strong><ul class=\"mb-0 mt-1\">".concat(errors.map(function (error) {
            return "<li>".concat($('<div>').text(error).html(), "</li>");
          }).join(''), "</ul>"));
        }
        toastr.error(msg);
        console.error('Import failed', xhr);
      }
    });
  });
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function resetBulkImportPreview() {
    var clearSheets = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
    $('#bulk-import-confirmed').val('0');
    $('#bulk-import-preview').addClass('d-none');
    $('#bulk-import-preview-body').empty();
    $('#bulk-import-errors').addClass('d-none').empty();
    if (clearSheets) {
      $('#bulk-import-sheet').html('<option value="">Auto-detect the best worksheet</option>');
      $('#bulk-import-sheet-wrap').addClass('d-none');
    }
    updateBulkImportActionButton();
  }
  function setBulkImportBusy(busy) {
    $('#bulk-import-status').toggleClass('d-none', !busy);
    $('#bulk-import-submit, #bulk-import-cancel, #bulk-import-file, #bulk-import-prefix, #bulk-import-expected, #bulk-import-sheet, #bulk-import-fill-missing').prop('disabled', busy);
    if (!busy) {
      updateBulkImportActionButton();
    }
  }
  function renderWorkbookSheets(sheets, selectedSheet) {
    var $select = $('#bulk-import-sheet');
    var currentValue = selectedSheet || $select.val() || '';
    $select.html('<option value="">Auto-detect the best worksheet</option>');
    (sheets || []).forEach(function (sheet) {
      var label = "".concat(sheet.name, " \u2014 ").concat(sheet.complete_team_count, "/").concat(sheet.team_count, " complete teams");
      $('<option>').val(sheet.name).text(label).appendTo($select);
    });
    if ((sheets || []).length > 1) {
      $('#bulk-import-sheet-wrap').removeClass('d-none');
    }
    if (selectedSheet) {
      $select.val(currentValue);
    }
  }
  function updateBulkImportActionButton() {
    if ($('#bulk-import-confirmed').val() !== '1') {
      var _document$getElementB;
      var hasWorkbook = Boolean((_document$getElementB = document.getElementById('bulk-import-file')) === null || _document$getElementB === void 0 || (_document$getElementB = _document$getElementB.files) === null || _document$getElementB === void 0 ? void 0 : _document$getElementB.length);
      $('#bulk-import-submit').text('Preview teams').prop('disabled', !hasWorkbook);
      return;
    }
    var selected = $('.bulk-team-select:checked').length;
    $('#bulk-import-submit').text(selected ? "Confirm import of ".concat(selected, " team").concat(selected === 1 ? '' : 's') : 'Select a complete team').prop('disabled', selected === 0);
  }
  function showBulkImportErrors(message) {
    var rawErrors = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [];
    var errors = _toConsumableArray(new Set((Array.isArray(rawErrors) ? rawErrors : Object.values(rawErrors).flat()).filter(function (error) {
      return error && error !== message;
    })));
    $('#bulk-import-errors').removeClass('d-none').html("<strong>".concat(escapeHtml(message), "</strong>") + (errors.length ? "<ul class=\"mb-0 mt-1\">".concat(errors.map(function (error) {
      return "<li>".concat(escapeHtml(error), "</li>");
    }).join(''), "</ul>") : ''));
  }
  function renderBulkImportTeams(response) {
    var rows = (response.teams || []).map(function (team) {
      var validation = team.placeholder_count ? "<span class=\"badge bg-label-warning\">Ready with ".concat(team.placeholder_count, " placeholder").concat(team.placeholder_count === 1 ? '' : 's', "</span>") : team.errors.length ? "<ul class=\"small text-danger mb-0 ps-3\">".concat(team.errors.map(function (error) {
        return "<li>".concat(escapeHtml(error), "</li>");
      }).join(''), "</ul>") : '<span class="badge bg-label-success">Complete</span>';
      var players = team.players.map(function (player) {
        return "<li class=\"".concat(player.is_placeholder ? 'text-warning' : '', "\"><span class=\"text-muted\">").concat(player.rank, ".</span> ").concat(escapeHtml(player.name), " ").concat(escapeHtml(player.surname)).concat(player.is_placeholder ? ' <span class="badge bg-label-warning ms-1">Placeholder</span>' : '', "</li>");
      }).join('');
      return "\n        <tr>\n          <td class=\"text-center\">\n            <input class=\"form-check-input bulk-team-select\" type=\"checkbox\"\n                   name=\"selected_team_keys[]\" value=\"".concat(escapeHtml(team.key), "\"\n                   ").concat(team.selectable ? 'checked' : 'disabled', ">\n          </td>\n          <td>\n            <div class=\"fw-medium\">").concat(escapeHtml(team.category), "</div>\n            <div class=\"small text-muted\">Source: ").concat(escapeHtml(team.source_heading), "</div>\n          </td>\n          <td>").concat(escapeHtml(team.team_name), "</td>\n          <td class=\"text-center\">\n            <details>\n              <summary>").concat(team.player_count, "</summary>\n              <ol class=\"small text-start mb-0 mt-1 ps-3\">").concat(players, "</ol>\n            </details>\n          </td>\n          <td>").concat(escapeHtml(team.action), "</td>\n          <td>").concat(validation, "</td>\n        </tr>");
    }).join('');
    $('#bulk-import-preview-body').html(rows);
    $('#bulk-import-summary').text("".concat(response.selected_sheet, ": ").concat(response.complete_team_count, " teams ready, ").concat(response.complete_player_count, " roster slots") + (response.placeholder_player_count ? ", including ".concat(response.placeholder_player_count, " placeholder").concat(response.placeholder_player_count === 1 ? '' : 's', ".") : '.'));
    $('#bulk-import-preview').removeClass('d-none');
    $('#bulk-import-confirmed').val('1');
    updateBulkImportActionButton();
  }
  $(document).on('click', '.import-region-teams-btn', function () {
    bulkTeamImportUrl = $(this).data('import-url');
    $('#bulk-import-region-name').text($(this).data('region-name'));
    $('#bulk-import-prefix').val($(this).data('team-prefix'));
    $('#bulk-import-expected').val('8');
    $('#bulk-import-file').val('');
    resetBulkImportPreview(true);
  });
  $('#import-region-teams-modal').on('hidden.bs.modal', function () {
    var _$$;
    bulkTeamImportUrl = null;
    (_$$ = $('#bulk-team-import-form')[0]) === null || _$$ === void 0 || _$$.reset();
    setBulkImportBusy(false);
    resetBulkImportPreview(true);
  });
  $('#bulk-import-file, #bulk-import-prefix, #bulk-import-expected, #bulk-import-fill-missing').on('change input', function () {
    resetBulkImportPreview($(this).is('#bulk-import-file'));
  });
  $('#bulk-import-sheet').on('change', function () {
    resetBulkImportPreview(false);
  });
  $(document).on('change', '.bulk-team-select', updateBulkImportActionButton);
  $('#bulk-import-select-complete').on('click', function () {
    $('.bulk-team-select:not(:disabled)').prop('checked', true);
    updateBulkImportActionButton();
  });
  $('#bulk-import-submit').on('click', function () {
    var form = document.getElementById('bulk-team-import-form');
    if (!bulkTeamImportUrl) {
      toastr.error('Choose a region before importing teams.');
      return;
    }
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    if ($('#bulk-import-confirmed').val() === '1' && $('.bulk-team-select:checked').length === 0) {
      toastr.error('Select at least one team ready to import.');
      return;
    }

    // Disabled form controls are omitted from FormData. Capture the upload before
    // locking the controls so the selected workbook and import settings are sent.
    var formData = new FormData(form);
    setBulkImportBusy(true);
    $('#bulk-import-errors').addClass('d-none').empty();
    $.ajax({
      url: bulkTeamImportUrl,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json'
      },
      success: function success(response) {
        renderWorkbookSheets(response.sheets, response.selected_sheet);
        if (response.requires_confirmation) {
          renderBulkImportTeams(response);
          toastr.info(response.message);
          return;
        }
        toastr.success(response.message || 'Teams imported.');
        setTimeout(function () {
          return location.reload();
        }, 700);
      },
      error: function error(xhr) {
        var payload = xhr.responseJSON || {};
        renderWorkbookSheets(payload.sheets, null);
        var message = payload.message || 'Import failed. Nothing was imported.';
        showBulkImportErrors(message, payload.errors || []);
        toastr.error(message);
      },
      complete: function complete() {
        setBulkImportBusy(false);
        updateBulkImportActionButton();
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
/******/ })()
;