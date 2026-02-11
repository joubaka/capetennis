/*
 * Admin — Players / Roster JS
 * FINAL STABLE VERSION (EMAIL + ROSTER + QUILL + SELECT2)
 */

(function ($, window, document) {
  'use strict';

  // =====================================================
  // GLOBAL SETUP
  // =====================================================
  const CSRF = $('meta[name="csrf-token"]').attr('content');
  const APP_URL = window.APP_URL || window.location.origin;

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });

  console.log('🧍 players.js loaded');

  function logXhrFail(label, xhr) {
    console.group(`❌ ${label}`);
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }

  // =====================================================
  // API MAP
  // =====================================================
  const api = {
    sendMail: APP_URL + '/backend/email/send',
    loadRoster: APP_URL + '/backend/team/roster/edit',
    saveRoster: APP_URL + '/backend/team/roster/update',
    changePayStatus: APP_URL + '/backend/team/change/payStatus' // ✅ correct route
  };
  // =====================================================
  // PAY STATUS — TOGGLE
  // =====================================================
  // =====================================================
  // PAY STATUS — CHANGE
  // =====================================================
  // =====================================================
  // PAY STATUS — CHANGE
  // =====================================================
  $(document).on('click', '.changePayStatus', function (e) {
    e.preventDefault();

    const pivotId = $(this).data('pivot');
    const $row = $(this).closest('tr');
    const $badge = $row.find('.payStatus .badge');

    console.log('🟡 ChangePayStatus clicked');
    console.log('Pivot ID:', pivotId);

    if (!pivotId) {
      toastr.error('Missing pivot ID');
      return;
    }

    toastr.info('Updating pay status…');

    $.post(api.changePayStatus, { pivot_id: pivotId }) // ✅ MATCH CONTROLLER
      .done(res => {

        console.log('🟢 Server response:', res);

        if (!res.success) {
          toastr.error(res.message || 'Update failed');
          return;
        }

        const paid = Number(res.pay_status) === 1;

        $badge
          .removeClass('bg-label-success bg-label-danger')
          .addClass(paid ? 'bg-label-success' : 'bg-label-danger')
          .text(paid ? 'Paid' : 'Unpaid');

        toastr.success(res.message);
      })
      .fail(xhr => {
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
        toolbar: [
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link'],
          ['clean']
        ]
      }
    });
  }

  $('#sendMailModal').on('shown.bs.modal', function () {
    initQuillOnce();
    setTimeout(() => window.emailQuill.focus(), 50);
  });

  // =====================================================
  // EMAIL — SINGLE PLAYER
  // =====================================================
  $(document).on('click', '.emailPlayer', function () {
    resetEmailForm();
    hideRecipientSelector();

    const playerId = $(this).data('playerid');
    const name = $(this).data('name');

    $('#target_type').val('player');
    $('#emailToHidden').val(playerId);
    $('#emailPlayerId').val(playerId);

    $('#sendMailLabel').html(
      `<i class="ti ti-mail me-50"></i> Email Player: ${name}`
    );

    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // EMAIL — TEAM
  // =====================================================
  $(document).on('click', '.emailTeamBtn', function () {
    resetEmailForm();
    hideRecipientSelector();

    $('#target_type').val('');
    $('#emailToHidden').val('All players in team');
    $('#emailTeamId').val($(this).data('teamid'));

    $('#sendMailLabel').html(
      `<i class="ti ti-mail me-50"></i> Email Team: ${$(this).data('teamname')}`
    );

    bootstrap.Modal.getOrCreateInstance('#sendMailModal').show();
  });

  // =====================================================
  // EMAIL — REGION
  // =====================================================
  $(document).on('click', '.emailRegionBtn', function () {
    resetEmailForm();
    hideRecipientSelector();

    const regionId = $(this).data('regionid');
    const regionName = $(this).data('regionname');

    $('#emailToHidden').val('All players in region');
    $('#regionSelectWrapper').removeClass('d-none');

    const $regionSelect = $('#emailRegionSelect');

    if ($regionSelect.hasClass('select2-hidden-accessible')) {
      $regionSelect.select2('destroy');
    }

    $regionSelect
      .html(`<option value="${regionId}" selected>${regionName}</option>`)
      .select2({
        width: '100%',
        dropdownParent: $('#sendMailModal')
      });

    $('#sendMailLabel').html(
      `<i class="ti ti-mail me-50"></i> Email Region: ${regionName}`
    );

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

    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);

    toastr.info('Sending email…');

    $.post(api.sendMail, $(this).serialize())
      .done(res => {
        toastr.success(res.message || 'Email sent');
        bootstrap.Modal.getInstance(
          document.getElementById('sendMailModal')
        )?.hide();
      })
      .fail(xhr => {
        toastr.error(xhr.responseJSON?.message || 'Failed to send email');
        logXhrFail('Send email failed', xhr);
      })
      .always(() => {
        $btn.prop('disabled', false);
      });
  });

  // =====================================================
  // ROSTER — EDIT
  // =====================================================
  $(document).on('click', '.editRosterBtn', function (e) {
    e.preventDefault();

    const teamId = $(this).data('teamid');
    if (!teamId) return toastr.error('Missing team ID');

    toastr.info('Loading roster…');

    $.get(api.loadRoster, { team_id: teamId })
      .done(res => {
        const { team, slots, players } = res;

        let html = `
          <form id="editRosterForm">
            <input type="hidden" name="team_id" value="${team.id}">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:60px">#</th>
                  <th>Player</th>
                  <th style="width:120px">Pay</th>
                </tr>
              </thead>
              <tbody>
        `;

        slots.forEach(slot => {
          html += `
            <tr>
              <td class="text-center">
                <span class="badge bg-label-primary">${slot.rank}</span>
              </td>
              <td>
                <select class="form-select roster-player-select"
                        name="slots[${slot.id}]">
                  <option value="0">— Empty —</option>
          `;

          players.forEach(p => {
            const selected = slot.player_id === p.id ? 'selected' : '';
            html += `<option value="${p.id}" ${selected}>
              ${p.surname}, ${p.name}
            </option>`;
          });

          html += `
                </select>
              </td>
              <td class="text-center">
                <span class="badge ${slot.pay_status ? 'bg-label-success' : 'bg-label-danger'}">
                  ${slot.pay_status ? 'Paid' : 'Unpaid'}
                </span>
              </td>
            </tr>
          `;
        });

        html += `
              </tbody>
            </table>

            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox"
                     name="preserve_payments" value="1" id="preservePayments">
              <label class="form-check-label" for="preservePayments">
                Preserve payment status
              </label>
            </div>

            <div class="text-end mt-3">
              <button type="submit" class="btn btn-primary">Save Roster</button>
            </div>
          </form>
        `;

        $('#replaceRosterModalBody').html(html);
        $('#replaceRosterModalLabel').text(`Edit Roster — ${team.name}`);

        // 🔥 INIT SELECT2 (ROSTER)
        $('#replaceRosterModalBody .roster-player-select').each(function () {
          const $sel = $(this);
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
      })
      .fail(xhr => {
        toastr.error('Failed to load roster');
        logXhrFail('Load roster failed', xhr);
      });
  });

  // =====================================================
  // ROSTER — SAVE
  // =====================================================
  $(document).on('submit', '#editRosterForm', function (e) {
    e.preventDefault();

    const $form = $(this);
    const $btn = $form.find('button[type="submit"]');
    const teamId = $form.find('input[name="team_id"]').val();

    $btn.prop('disabled', true);
    toastr.info('Saving roster…');

    $.post(api.saveRoster, $form.serialize())
      .done(res => {
        toastr.success('Roster updated');

        // 🔥 UPDATE TABLE INLINE
        res.slots.forEach(slot => {
          const $row = $(`tr[data-playerteamid="${slot.id}"]`);
          if (!$row.length) return;

          const name = slot.player
            ? `${slot.player.name} ${slot.player.surname}`
            : '—';

          $row.find('td:eq(1)').text(name);
          $row.find('td:eq(2)').text(slot.player?.email || '—');
          $row.find('td:eq(3)').text(slot.player?.cell || '—');

          const $badge = $row.find('.payStatus .badge');
          $badge
            .toggleClass('bg-label-success', slot.pay_status === 1)
            .toggleClass('bg-label-danger', slot.pay_status === 0)
            .text(slot.pay_status ? 'Paid' : 'Unpaid');
        });

        bootstrap.Modal.getInstance(
          document.getElementById('replaceRosterModal')
        )?.hide();
      })
      .fail(xhr => {
        toastr.error('Failed to save roster');
        logXhrFail('Save roster failed', xhr);
      })
      .always(() => {
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

    const pivotId = $(this).data('slotid');
    const teamId = $(this).data('teamid');

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
    })
      .done(html => {

        const $modal = $('#replacePlayerModal');
        const $body = $('#replacePlayerModalBody');

        $body.html(html);

        const $select = $body.find('.select2ReplacePlayer');

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
      })
      .fail(xhr => {
        toastr.error('Failed to load replace form');
        logXhrFail('Replace form load failed', xhr);
      });
  });

  // =====================================================
  // REPLACE PLAYER — SAVE
  // =====================================================
  $(document).on('submit', '#replacePlayerForm', function (e) {
    e.preventDefault();

    const $form = $(this);

    toastr.info('Replacing player…');

    $.post(window.routes.replacePlayer, $form.serialize())
      .done(res => {

        console.log('🔁 Replace response:', res);

        if (!res.success) {
          toastr.error(res.message || 'Replace failed');
          return;
        }

        const slot = res.slot;

        const $row = $(`tr[data-playerteamid="${slot.pivot_id}"]`);

        if (!$row.length) {
          console.warn('Row not found for pivot:', slot.pivot_id);
          return;
        }

        // Update name
        $row.find('td:eq(1)').text(
          slot.player.name + ' ' + slot.player.surname
        );

        // Update email + cell
        $row.find('td:eq(2)').text(slot.player.email || '—');
        $row.find('td:eq(3)').text(slot.player.cell || '—');

        // Reset pay badge (since controller sets pay_status null)
        const paid = Number(slot.pay_status) === 1;

        $row.find('.payStatus .badge')
          .removeClass('bg-label-success bg-label-danger')
          .addClass(paid ? 'bg-label-success' : 'bg-label-danger')
          .text(paid ? 'Paid' : 'Unpaid');

        bootstrap.Modal.getInstance(
          document.getElementById('replacePlayerModal')
        )?.hide();

        toastr.success(res.message);
      })
      .fail(xhr => {
        toastr.error('Replace failed');
        logXhrFail('Replace save failed', xhr);
      });
  });



})(jQuery, window, document);
