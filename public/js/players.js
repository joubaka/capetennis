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
  !*** ./resources/js/pages/players.js ***!
  \***************************************/
/*
 * Admin — Players / Roster JS
 * FINAL STABLE VERSION (EMAIL + ROSTER + QUILL + SELECT2 + ENHANCED TOAST)
 */

(function ($, window, document) {
  'use strict';

  // =====================================================
  // GLOBAL SETUP
  // =====================================================
  var CSRF = $('meta[name="csrf-token"]').attr('content');
  var APP_URL = window.APP_URL || window.location.origin;
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });
  console.log('🧍 players.js loaded');
  function logXhrFail(label, xhr) {
    console.group("\u274C ".concat(label));
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }
  var api = {
    sendMail: APP_URL + '/backend/email/send',
    loadRoster: APP_URL + '/backend/team/roster/edit',
    saveRoster: APP_URL + '/backend/team/roster/update',
    changePayStatus: APP_URL + '/backend/team/change/payStatus'
  };

  // =====================================================
  // PAY STATUS
  // =====================================================
  $(document).on('click', '.changePayStatus', function (e) {
    e.preventDefault();
    var pivotId = $(this).data('pivot');
    var $row = $(this).closest('tr');
    var $badge = $row.find('.payStatus .badge');
    console.log('🟡 ChangePayStatus clicked');
    console.log('Pivot ID:', pivotId);
    if (!pivotId) {
      toastr.error('Missing pivot ID');
      return;
    }
    toastr.info('Updating pay status…');
    $.post(api.changePayStatus, {
      pivot_id: pivotId
    }) // ✅ MATCH CONTROLLER
    .done(function (res) {
      console.log('🟢 Server response:', res);
      if (!res.success) {
        toastr.error(res.message || 'Update failed');
        return;
      }
      var paid = Number(res.pay_status) === 1;
      $badge.removeClass('bg-label-success bg-label-danger').addClass(paid ? 'bg-label-success' : 'bg-label-danger').text(paid ? 'Paid' : 'Unpaid');
      toastr.success(res.message);
    }).fail(function (xhr) {
      toastr.error('Failed to update pay status');
      logXhrFail('Change pay failed', xhr);
    });
  });

  // =====================================================
  // EMAIL HELPERS
  // =====================================================
  function resetEmailForm() {
    $('#sendMailForm')[0].reset();
    $('#emailPlayerId, #emailTeamId, #catEvent, #emailToHidden').val('');
    $('#emailSubject, #emailMessage').val('');
    $('#emailRecipientSelect').closest('.mb-3').removeClass('d-none');
    $('#regionSelectWrapper, #teamSelectWrapper, #categorySelectWrapper').addClass('d-none');
    if (window.emailQuill) {
      window.emailQuill.setText('');
    }
  }
  function hideRecipientSelector() {
    $('#emailRecipientSelect').closest('.mb-3').addClass('d-none');
  }

  // =====================================================
  // QUILL — INIT ONCE
  // =====================================================
  window.emailQuill = null;
  function initQuillOnce() {
    if (window.emailQuill) return;
    window.emailQuill = new Quill('#messageEditor', {
      theme: 'snow',
      placeholder: 'Type your message here…',
      modules: {
        toolbar: [['bold', 'italic', 'underline'], [{
          list: 'ordered'
        }, {
          list: 'bullet'
        }], ['link'], ['clean']]
      }
    });
  }
  $('#sendMailModal').on('shown.bs.modal', function () {
    initQuillOnce();
    setTimeout(function () {
      return window.emailQuill.focus();
    }, 50);
  });

  // =====================================================
  // EMAIL — SINGLE PLAYER
  // =====================================================
  $(document).on('click', '.emailPlayer', function () {
    resetEmailForm();
    hideRecipientSelector();
    var playerId = $(this).data('playerid');
    var name = $(this).data('name');
    $('#target_type').val('player');
    $('#emailToHidden').val(playerId);
    $('#emailPlayerId').val(playerId);
    $('#sendMailLabel').html("<i class=\"ti ti-mail me-50\"></i> Email Player: ".concat(name));
    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // EMAIL — TEAM
  // =====================================================
  $(document).on('click', '.emailTeamBtn', function () {
    resetEmailForm();
    hideRecipientSelector();
    $('#target_type').val('team'); // ✅ FIX: Set target_type
    $('#emailToHidden').val('All players in team');
    $('#emailTeamId').val($(this).data('teamid'));
    $('#sendMailLabel').html("<i class=\"ti ti-mail me-50\"></i> Email Team: ".concat($(this).data('teamname')));
    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // EMAIL — REGION
  // =====================================================
  $(document).on('click', '.emailRegionBtn', function () {
    resetEmailForm();
    hideRecipientSelector();
    var regionId = $(this).data('regionid');
    var regionName = $(this).data('regionname');
    $('#target_type').val('region'); // ✅ ADD: Set target_type for explicit routing
    $('#emailToHidden').val('All players in region');
    $('#regionSelectWrapper').removeClass('d-none');
    var $regionSelect = $('#emailRegionSelect');
    if ($regionSelect.hasClass('select2-hidden-accessible')) {
      $regionSelect.select2('destroy');
    }
    $regionSelect.html("<option value=\"".concat(regionId, "\" selected>").concat(regionName, "</option>")).select2({
      width: '100%',
      dropdownParent: $('#sendMailModal')
    });
    $('#sendMailLabel').html("<i class=\"ti ti-mail me-50\"></i> Email Region: ".concat(regionName));
    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // EMAIL — UNPAID PLAYERS IN REGION
  // =====================================================
  $(document).on('click', '.emailUnpaidRegionBtn', function () {
    resetEmailForm();
    hideRecipientSelector();
    var regionId = $(this).data('regionid');
    var regionName = $(this).data('regionname');
    $('#emailToHidden').val('All Unregistered players in Region');
    $('#regionSelectWrapper').removeClass('d-none');
    var $regionSelect = $('#emailRegionSelect');
    if ($regionSelect.hasClass('select2-hidden-accessible')) {
      $regionSelect.select2('destroy');
    }
    $regionSelect.html("<option value=\"".concat(regionId, "\" selected>").concat(regionName, "</option>")).select2({
      width: '100%',
      dropdownParent: $('#sendMailModal')
    });
    $('#sendMailLabel').html("<i class=\"ti ti-mail me-50\"></i> Email Unpaid Players \u2014 ".concat(regionName));
    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // SEND EMAIL
  // =====================================================
  $('#sendMailForm').on('submit', function (e) {
    e.preventDefault();
    if (window.emailQuill) {
      $('#emailMessage').val(window.emailQuill.root.innerHTML);
    }
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    toastr.info('Sending email…');
    $.post(api.sendMail, $(this).serialize()).done(function (res) {
      var _res$result, _bootstrap$Modal$getI;
      var message = '';
      var title = 'Email Queued';
      if (res.count !== undefined) {
        message = "\uD83D\uDCEC ".concat(res.count, " recipient(s)\nMailer: ").concat(res.mailer);
      } else if ((_res$result = res.result) !== null && _res$result !== void 0 && _res$result.message) {
        message = "".concat(res.result.message, "\nMailer: ").concat(res.mailer);
      } else {
        message = "Email queued successfully\nMailer: ".concat(res.mailer);
      }
      toastr.success(message, title, {
        timeOut: 6000,
        extendedTimeOut: 2000,
        closeButton: true,
        progressBar: true,
        escapeHtml: false
      });
      (_bootstrap$Modal$getI = bootstrap.Modal.getInstance(document.getElementById('sendMailModal'))) === null || _bootstrap$Modal$getI === void 0 ? void 0 : _bootstrap$Modal$getI.hide();
    }).fail(function (xhr) {
      var _xhr$responseJSON, _xhr$responseJSON2, _xhr$responseJSON2$re;
      var msg = ((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || ((_xhr$responseJSON2 = xhr.responseJSON) === null || _xhr$responseJSON2 === void 0 ? void 0 : (_xhr$responseJSON2$re = _xhr$responseJSON2.result) === null || _xhr$responseJSON2$re === void 0 ? void 0 : _xhr$responseJSON2$re.message) || 'Failed to send email';
      toastr.error(msg, 'Email Failed', {
        timeOut: 8000,
        closeButton: true,
        progressBar: true
      });
      logXhrFail('Send email failed', xhr);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // =====================================================
  // ROSTER — EDIT
  // =====================================================
  $(document).on('click', '.editRosterBtn', function (e) {
    e.preventDefault();
    var teamId = $(this).data('teamid');
    if (!teamId) return toastr.error('Missing team ID');
    toastr.info('Loading roster…');
    $.get(api.loadRoster, {
      team_id: teamId
    }).done(function (res) {
      var team = res.team,
        slots = res.slots,
        players = res.players;
      var html = "\n          <form id=\"editRosterForm\">\n            <input type=\"hidden\" name=\"team_id\" value=\"".concat(team.id, "\">\n            <table class=\"table table-sm table-bordered align-middle\">\n              <thead class=\"table-light\">\n                <tr>\n                  <th style=\"width:60px\">#</th>\n                  <th>Player</th>\n                  <th style=\"width:120px\">Pay</th>\n                </tr>\n              </thead>\n              <tbody>\n        ");
      slots.forEach(function (slot) {
        html += "\n            <tr>\n              <td class=\"text-center\">\n                <span class=\"badge bg-label-primary\">".concat(slot.rank, "</span>\n              </td>\n              <td>\n                <select class=\"form-select roster-player-select\"\n                        name=\"slots[").concat(slot.id, "]\">\n                  <option value=\"0\">\u2014 Empty \u2014</option>\n          ");
        players.forEach(function (p) {
          var selected = slot.player_id === p.id ? 'selected' : '';
          html += "<option value=\"".concat(p.id, "\" ").concat(selected, ">\n              ").concat(p.surname, ", ").concat(p.name, "\n            </option>");
        });
        html += "\n                </select>\n              </td>\n              <td class=\"text-center\">\n                <span class=\"badge ".concat(slot.pay_status ? 'bg-label-success' : 'bg-label-danger', "\">\n                  ").concat(slot.pay_status ? 'Paid' : 'Unpaid', "\n                </span>\n              </td>\n            </tr>\n          ");
      });
      html += "\n              </tbody>\n            </table>\n\n            <div class=\"form-check mt-2\">\n              <input class=\"form-check-input\" type=\"checkbox\"\n                     name=\"preserve_payments\" value=\"1\" id=\"preservePayments\">\n              <label class=\"form-check-label\" for=\"preservePayments\">\n                Preserve payment status\n              </label>\n            </div>\n\n            <div class=\"text-end mt-3\">\n              <button type=\"submit\" class=\"btn btn-primary\">Save Roster</button>\n            </div>\n          </form>\n        ";
      $('#replaceRosterModalBody').html(html);
      $('#replaceRosterModalLabel').text("Edit Roster \u2014 ".concat(team.name));

      // 🔥 INIT SELECT2 (ROSTER)
      $('#replaceRosterModalBody .roster-player-select').each(function () {
        var $sel = $(this);
        if ($sel.hasClass('select2-hidden-accessible')) {
          $sel.select2('destroy');
        }
        $sel.select2({
          width: '100%',
          dropdownParent: $('#replaceRosterModal'),
          placeholder: 'Select player',
          allowClear: true
        });
      });
      bootstrap.Modal.getOrCreateInstance('#replaceRosterModal').show();
    }).fail(function (xhr) {
      toastr.error('Failed to load roster');
      logXhrFail('Load roster failed', xhr);
    });
  });

  // =====================================================
  // ROSTER — SAVE
  // =====================================================
  $(document).on('submit', '#editRosterForm', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    var teamId = $form.find('input[name="team_id"]').val();
    $btn.prop('disabled', true);
    toastr.info('Saving roster…');
    $.post(api.saveRoster, $form.serialize()).done(function (res) {
      var _bootstrap$Modal$getI2;
      toastr.success('Roster updated');

      // 🔥 UPDATE TABLE INLINE
      res.slots.forEach(function (slot) {
        var _slot$player, _slot$player2;
        var $row = $("tr[data-playerteamid=\"".concat(slot.id, "\"]"));
        if (!$row.length) return;
        var name = slot.player ? "".concat(slot.player.name, " ").concat(slot.player.surname) : '—';
        $row.find('td:eq(1)').text(name);
        $row.find('td:eq(2)').text(((_slot$player = slot.player) === null || _slot$player === void 0 ? void 0 : _slot$player.email) || '—');
        $row.find('td:eq(3)').text(((_slot$player2 = slot.player) === null || _slot$player2 === void 0 ? void 0 : _slot$player2.cell) || '—');
        var $badge = $row.find('.payStatus .badge');
        $badge.toggleClass('bg-label-success', slot.pay_status === 1).toggleClass('bg-label-danger', slot.pay_status === 0).text(slot.pay_status ? 'Paid' : 'Unpaid');
      });
      (_bootstrap$Modal$getI2 = bootstrap.Modal.getInstance(document.getElementById('replaceRosterModal'))) === null || _bootstrap$Modal$getI2 === void 0 ? void 0 : _bootstrap$Modal$getI2.hide();
    }).fail(function (xhr) {
      toastr.error('Failed to save roster');
      logXhrFail('Save roster failed', xhr);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });
  // =====================================================
  // REPLACE PLAYER — OPEN ROSTER MODAL
  // =====================================================
  // =====================================================
  // REPLACE PLAYER — LOAD MODAL
  // =====================================================
  // =====================================================
  // =====================================================
  // REPLACE PLAYER — LOAD FORM
  // =====================================================
  $(document).on('click', '.replacePlayerBtn', function (e) {
    e.preventDefault();
    var pivotId = $(this).data('slotid');
    var teamId = $(this).data('teamid');
    console.log('🔁 Replace Player clicked');
    console.log('Pivot ID:', pivotId);
    if (!pivotId) {
      toastr.error('Missing slot ID');
      return;
    }
    toastr.info('Loading replace form…');
    console.log('Requesting form with pivot_id:', pivotId, 'team_id:', teamId);
    console.log('Form URL:', window.routes.replaceForm);
    $.get(window.routes.replaceForm, {
      pivot_id: pivotId,
      team_id: teamId
    }).done(function (html) {
      var $modal = $('#replacePlayerModal');
      var $body = $('#replacePlayerModalBody');
      $body.html(html);
      var $select = $body.find('.select2ReplacePlayer');

      // Destroy if already initialized
      if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
      }
      $select.select2({
        dropdownParent: $modal,
        width: '100%',
        placeholder: 'Search player...',
        allowClear: true
      });
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    }).fail(function (xhr) {
      toastr.error('Failed to load replace form');
      logXhrFail('Replace form load failed', xhr);
    });
  });

  // =====================================================
  // REPLACE PLAYER — SAVE
  // =====================================================
  $(document).on('submit', '#replacePlayerForm', function (e) {
    e.preventDefault();
    var $form = $(this);
    toastr.info('Replacing player…');
    $.post(window.routes.replacePlayer, $form.serialize()).done(function (res) {
      var _bootstrap$Modal$getI3;
      console.log('🔁 Replace response:', res);
      if (!res.success) {
        toastr.error(res.message || 'Replace failed');
        return;
      }
      var slot = res.slot;
      var $row = $("tr[data-playerteamid=\"".concat(slot.pivot_id, "\"]"));
      if (!$row.length) {
        console.warn('Row not found for pivot:', slot.pivot_id);
        return;
      }

      // Update name
      $row.find('td:eq(1)').text(slot.player.name + ' ' + slot.player.surname);

      // Update email + cell
      $row.find('td:eq(2)').text(slot.player.email || '—');
      $row.find('td:eq(3)').text(slot.player.cell || '—');

      // Reset pay badge (since controller sets pay_status null)
      var paid = Number(slot.pay_status) === 1;
      $row.find('.payStatus .badge').removeClass('bg-label-success bg-label-danger').addClass(paid ? 'bg-label-success' : 'bg-label-danger').text(paid ? 'Paid' : 'Unpaid');
      (_bootstrap$Modal$getI3 = bootstrap.Modal.getInstance(document.getElementById('replacePlayerModal'))) === null || _bootstrap$Modal$getI3 === void 0 ? void 0 : _bootstrap$Modal$getI3.hide();
      toastr.success(res.message);
    }).fail(function (xhr) {
      toastr.error('Replace failed');
      logXhrFail('Replace save failed', xhr);
    });
  });
})(jQuery, window, document);
/******/ 	return __webpack_exports__;
/******/ })()
;
});