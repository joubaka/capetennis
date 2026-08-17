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
/*!*****************************************!*\
  !*** ./resources/js/pages/adminShow.js ***!
  \*****************************************/
/*
 * Admin — Event Players & Order JS
 * Blade-matched, clean rebuild
 */

(function ($, window, document) {
  'use strict';

  console.log('✅ adminShow.js (players + order) loaded');
  function logXhrFail(label, xhr) {
    console.group("\u274C ".concat(label));
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }

  // =====================================================
  // GLOBALS
  // =====================================================
  var APP_URL = window.APP_URL || window.location.origin;
  var CSRF = $('meta[name="csrf-token"]').attr('content');
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });

  // =====================================================
  // API MAP
  // =====================================================
  var api = {
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
    var $select = $('#select2Region');
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
    var eventId = $('input[name="event_id"]').val();
    var regionId = $('#select2Region').val(); // ✅ FIX

    console.log('➕ Add region clicked', {
      eventId: eventId,
      regionId: regionId,
      rawSelect: $('#select2Region')[0],
      select2Data: $('#select2Region').select2('data')
    });
    if (!eventId || !regionId) {
      toastr.error('Please select a region');
      console.warn('⚠ Missing event_id or region_id');
      return;
    }
    var payload = {
      event_id: eventId,
      region_id: regionId
    };
    console.log('📦 POST payload', payload);
    console.log('➡ POST URL', api.addRegionToEvent);
    $.post(api.addRegionToEvent, payload).done(function (res) {
      console.log('✅ Region added successfully', res);
      toastr.success('Region added to event');
      bootstrap.Modal.getInstance(document.getElementById('modalToggle')).hide();
      location.reload();
    }).fail(function (xhr) {
      var _xhr$responseJSON;
      console.error('❌ Add region failed', xhr.responseText);
      toastr.error(((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || 'Failed to add region');
    });
  });
  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var pivotId = $btn.data('id');
    var $row = $btn.closest('[data-region-row], tr, .region-card');
    console.log('🗑️ removeRegionEvent click', {
      pivotId: pivotId,
      row: $row
    });
    if (!pivotId) {
      toastr.error('Missing pivot id');
      return;
    }
    Swal.fire({
      title: 'Remove region from event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Remove'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      var url = "".concat(APP_URL, "/backend/eventRegion/").concat(pivotId);
      console.log('➡️ DELETE', url);
      $.ajax({
        url: url,
        method: 'DELETE',
        data: {
          _token: CSRF
        }
      }).done(function (res) {
        console.log('✅ region removed', res);
        toastr.success('Region removed');

        // 🔥 Remove from UI without reload
        $row.fadeOut(200, function () {
          return $row.remove();
        });
      }).fail(function (xhr) {
        var _xhr$responseJSON2;
        logXhrFail('Remove region failed', xhr);
        toastr.error(((_xhr$responseJSON2 = xhr.responseJSON) === null || _xhr$responseJSON2 === void 0 ? void 0 : _xhr$responseJSON2.message) || 'Failed to remove region');
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
    var $btn = $(this);
    var teamId = $btn.data('id');
    var state = String($btn.data('state')); // "0" or "1"

    console.log('📣 publishTeam click', {
      teamId: teamId,
      state: state
    });
    var actionLabel = state === '1' ? 'Unpublish' : 'Publish';
    Swal.fire({
      title: "".concat(actionLabel, " team?"),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: actionLabel
    }).then(function (r) {
      if (!r.isConfirmed) return;
      var url = "".concat(APP_URL, "/backend/team/publishTeam/").concat(teamId);
      console.log('➡️ POST', url);
      $.post(url, {
        _token: CSRF
      }).done(function (res) {
        console.log('✅ publish toggled', res);
        var newState = state === '1' ? '0' : '1';
        $btn.data('state', newState);

        // 🔁 Update label + icon + class
        if (newState === '1') {
          $btn.removeClass('btn-success').addClass('btn-warning').html('<i class="ti ti-eye-off me-1"></i> Unpublish');
        } else {
          $btn.removeClass('btn-warning').addClass('btn-success').html('<i class="ti ti-eye me-1"></i> Publish');
        }
        toastr.success("Team ".concat(actionLabel.toLowerCase(), "ed"));
      }).fail(function (xhr) {
        var _xhr$responseJSON3;
        logXhrFail('Publish toggle failed', xhr);
        toastr.error(((_xhr$responseJSON3 = xhr.responseJSON) === null || _xhr$responseJSON3 === void 0 ? void 0 : _xhr$responseJSON3.message) || 'Publish toggle failed');
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
    var $btn = $(this);
    var url = $btn.data('url');
    var state = String($btn.data('state'));
    console.log('🟡 toggleNoProfile click', {
      url: url,
      state: state
    });
    var actionLabel = state === '1' ? 'Disable' : 'Enable';
    Swal.fire({
      title: "".concat(actionLabel, " NoProfile?"),
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: actionLabel
    }).then(function (r) {
      if (!r.isConfirmed) return;
      console.log('➡️ PATCH', url);
      $.ajax({
        url: url,
        method: 'PATCH',
        data: {
          _token: CSRF
        }
      }).done(function (res) {
        console.log('✅ noprofile toggled', res);
        var newState = state === '1' ? '0' : '1';
        $btn.data('state', newState);
        if (newState === '1') {
          $btn.removeClass('btn-success').addClass('btn-danger').html('<i class="ti ti-user-off me-1"></i> Disable NoProfile');
        } else {
          $btn.removeClass('btn-danger').addClass('btn-success').html('<i class="ti ti-user me-1"></i> Enable NoProfile');
        }
        toastr.success("NoProfile ".concat(actionLabel.toLowerCase(), "d"));
      }).fail(function (xhr) {
        var _xhr$responseJSON4;
        logXhrFail('NoProfile toggle failed', xhr);
        toastr.error(((_xhr$responseJSON4 = xhr.responseJSON) === null || _xhr$responseJSON4 === void 0 ? void 0 : _xhr$responseJSON4.message) || 'NoProfile toggle failed');
      });
    });
  });

  // =====================================================
  // PLAYER ORDER (SORTABLE)
  // =====================================================
  function initPlayerOrder() {
    if (typeof Sortable === 'undefined') return;
    $('tbody.sortablePlayers').each(function () {
      var $tbody = $(this);
      if ($tbody.data('init')) return;
      var teamId = $tbody.data('team-id');
      if (!teamId) return;
      console.log('↕️ Init order', teamId);
      $tbody.data('init', true);
      new Sortable(this, {
        animation: 150,
        handle: '.drag-handle',
        draggable: 'tr.drag-item',
        onEnd: function onEnd() {
          var debugRows = [];
          var mismatches = [];
          $tbody.find('tr.drag-item').each(function (i) {
            var $row = $(this);
            var position = i + 1;
            var id = $row.data('playerteamid');
            var teamPlayerId = $row.data('teamplayerid');
            var noProfileId = $row.data('noprofileid');
            var type = $row.data('type');
            var $rankBadge = $row.find('td').eq(1).find('.badge').first();
            var badgeBefore = $rankBadge.text().trim();
            $rankBadge.text(position);
            var profileOk = type === 'profile' ? id === teamPlayerId : true;
            var noProfileOk = type === 'noprofile' ? id === noProfileId : true;
            if (!profileOk || !noProfileOk) {
              mismatches.push({
                position: position,
                id: id,
                teamPlayerId: teamPlayerId,
                noProfileId: noProfileId,
                type: type
              });
            }
            debugRows.push({
              position: position,
              id: id,
              teamPlayerId: teamPlayerId,
              noProfileId: noProfileId,
              type: type,
              badgeBefore: badgeBefore
            });
          });
          var order = debugRows.map(function (row) {
            return {
              id: row.id,
              team_player_id: row.teamPlayerId,
              no_profile_id: row.noProfileId,
              type: row.type,
              position: row.position
            };
          });
          console.log('↕️ Drag debug rows', debugRows);
          if (mismatches.length) {
            console.warn('↕️ Drag ID mismatches', mismatches);
          }
          console.log('↕️ Save order', order);
          $.post(api.orderPlayers, {
            team_id: teamId,
            order: order
          }).done(function () {
            return toastr.success('Order saved');
          }).fail(function () {
            return toastr.error('Failed to save order');
          });
        }
      });
    });
  }
  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);
})(jQuery, window, document);
/******/ 	return __webpack_exports__;
/******/ })()
;
});