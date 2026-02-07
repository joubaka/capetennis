/*
 * Admin — Event Page JS
 * (teams, regions, ranks, nominations, wallet, DT, select2, quill)
 *
 * Player Order logic REMOVED
 * Cleaned on 2026-02-05
 */

(function ($, window, document) {
  'use strict';

  // ==============================
  // Bootstrap / globals
  // ==============================
  const CSRF = $('meta[name="csrf-token"]').attr('content');
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });

  if (typeof APP_URL === 'undefined') {
    console.error('APP_URL is not defined');
  }

  // ==============================
  // Central API map (NO Player Order endpoints)
  // ==============================
  const api = {
    // --- Email & Admin ---
    sendMail: APP_URL + '/backend/email/send',
    registerAdmin: APP_URL + '/register/registerAdmin',
    saveCategories: APP_URL + '/backend/event/saveCategories',

    // --- Wallet ---
    refundWallet: APP_URL + '/backend/wallet/refund',
    refundWalletBulk: APP_URL + '/backend/wallet/refund/bulk',
    refreshWalletBalance: id => `${APP_URL}/backend/wallet/balance/${id}`,

    // --- Team actions ---
    publishTeam: id => `${APP_URL}/backend/team/publishTeam/${id}`,
    teamStore: `${APP_URL}/backend/team`,
    teamDestroy: id => `${APP_URL}/backend/team/${id}`,
    addTeamToRegion: `${APP_URL}/backend/team/addToRegion`,

    // --- Results ---
    resetResult: `${APP_URL}/backend/result/reset`,
    saveOrder: catId => `${APP_URL}/backend/result/saveOrder/${catId}`,

    // --- Event & Region ---
    eventCategoryData: `${APP_URL}/backend/eventAdmin/eventCategory/data`,
    eventRegion: `${APP_URL}/backend/eventRegion`,
    eventRegionId: id => `${APP_URL}/backend/eventRegion/${id}`,
    region: `${APP_URL}/backend/region`,

    // --- Nominations ---
    nomination: {
      delete: `${APP_URL}/backend/nominate/delete`,
      togglePublish: id => `${APP_URL}/backend/nomination/publish/toggle/${id}`,
      playersForCat: id => `${APP_URL}/backend/nomination/players/category/${id}`,
      submit: `${APP_URL}/backend/nominate`,
      add: `${APP_URL}/backend/nomination/add`,
      remove: `${APP_URL}/backend/nomination/remove`
    },

    // --- Event helpers ---
    eventCategoriesForEvent: id => `${APP_URL}/backend/event/getEventCategories/${id}`,
    nominationPlayersForCat: id => `${APP_URL}/backend/nomination/players/category/${id}`,

    // --- Add player to category ---
    addPlayerToCategory: `${APP_URL}/backend/registration/addPlayerToCategory`,

    // --- Email lists ---
    emailPlayers: id => `${APP_URL}/backend/email/players/${id}`,
    emailTeams: id => `${APP_URL}/backend/email/teams/${id}`,
    emailRegions: id => `${APP_URL}/backend/email/regions/${id}`,

    // --- Templates ---
    emailTemplates: `${APP_URL}/backend/email/templates`,
    emailTemplate: id => `${APP_URL}/backend/email/templates/${id}`,
    editRoster: APP_URL + '/backend/team/roster/edit',
    updateRoster: APP_URL + '/backend/team/roster/update',
    changePayStatus: APP_URL + '/backend/team/change-pay-status'
  };

  window.api = api;

  // ==============================
  // Notify helpers
  // ==============================
  const notify = {
    ok: m => toastr.success(m || 'Done'),
    err: m => toastr.error(m || 'Something went wrong'),
    warn: m => toastr.warning(m || 'Check details'),
    info: m => toastr.info(m || 'Working...')
  };
  function lockRecipient(label, value) {
    const $sel = $('#emailRecipientSelect');

    // Backup original options once
    if (!$sel.data('original-options')) {
      $sel.data('original-options', $sel.html());
    }

    // Replace with single option
    $sel.html(`<option value="${value}" selected>${label}</option>`);

    // Refresh Select2
    $sel.trigger('change.select2');
  }

  // ==============================
  // Global AJAX loader
  // ==============================
  $(document).ajaxStart(() => $('body').addClass('loading'));
  $(document).ajaxStop(() => $('body').removeClass('loading'));

  if (!$('#ajaxLoaderStyle').length) {
    $('head').append(`
      <style id="ajaxLoaderStyle">
        body.loading::after {
          content: 'Saving...';
          position: fixed;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: rgba(0,0,0,.65);
          color: #fff;
          padding: 10px 20px;
          border-radius: 6px;
          z-index: 9999;
          font-weight: 600;
        }
      </style>
    `);
  }

  // ==============================
  // DataTables defaults
  // ==============================
  if ($.fn.DataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
      pageLength: 25,
      order: [],
      responsive: true,
      language: {
        search: '',
        searchPlaceholder: 'Search…'
      }
    });

    $('.playerTable, #orderItemsTable').each(function () {
      if (!$.fn.dataTable.isDataTable(this)) {
        $(this).DataTable();
      }
    });
  }

  // ==============================
  // Persist tabs / accordion
  // ==============================
  const STATE_KEY = 'event-admin-state-v2';

  const saveState = (k, v) => {
    const s = JSON.parse(localStorage.getItem(STATE_KEY) || '{}');
    s[k] = v;
    localStorage.setItem(STATE_KEY, JSON.stringify(s));
  };

  const getState = k =>
    (JSON.parse(localStorage.getItem(STATE_KEY) || '{}'))[k];

  $(document).on('shown.bs.tab', 'a[data-bs-toggle="tab"]', e => {
    saveState('lastTab', $(e.target).attr('href'));
  });

  const lastTab = getState('lastTab');
  if (lastTab) {
    const $t = $(`a[data-bs-toggle="tab"][href="${lastTab}"]`);
    if ($t.length) new bootstrap.Tab($t[0]).show();
  }

  // ==============================
  // Quill (Email)
  // ==============================
  let mailEditor = null;
  if ($('#messageEditor').length) {
    mailEditor = new Quill('#messageEditor', {
      theme: 'snow',
      placeholder: 'Write your email message here...',
      modules: {
        toolbar: [
          [{ header: [1, 2, false] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'clean']
        ]
      }
    });
  }



  // ==============================
  // RESULTS & RANKINGS
  // ==============================
  $(document).on('click', '.save-results-order', function () {
    const catId = $(this).data('category');
    const order = $(`#results-table-${catId} tbody tr`)
      .map((i, tr) => ({
        id: $(tr).data('id'),
        position: i + 1
      }))
      .get();

    $.post(api.saveOrder(catId), { order })
      .done(() => notify.ok('Order saved'))
      .fail(() => notify.err('Order save failed'));
  });

  $(document).on('click', '.reset-results', function () {
    const catId = $(this).data('category');

    Swal.fire({
      title: 'Reset all results?',
      icon: 'warning',
      showCancelButton: true
    }).then(r => {
      if (!r.isConfirmed) return;
      $.post(api.resetResult, { category_event_id: catId })
        .done(() => location.reload())
        .fail(() => notify.err('Reset failed'));
    });
  });

  // ==============================
  // ADD PLAYER TO CATEGORY
  // ==============================
  $('#addPlayerToCategory').on('shown.bs.modal', function () {
    const $sel = $('#select2AddPlayer');
    if (!$sel.hasClass('select2-hidden-accessible')) {
      $sel.select2({
        dropdownParent: $('#addPlayerToCategory'),
        width: '100%',
        placeholder: 'Select a player...'
      });
    }
  });

  // ==============================
  // WITHDRAW PLAYER
  // ==============================
  $(document).on('click', '.withdraw-player-btn', function () {
    const btn = $(this);
    const id = btn.data('id');
    const categoryEvent = btn.data('categoryevent');

    Swal.fire({
      title: 'Withdraw player?',
      icon: 'warning',
      showCancelButton: true
    }).then(r => {
      if (!r.isConfirmed) return;

      $.post(`${APP_URL}/backend/registration/delete`, {
        _token: CSRF,
        id,
        categoryEvent
      })
        .done(() => {
          btn.closest('tr').fadeOut(300, function () {
            $(this).remove();
          });
          notify.ok('Player withdrawn');
        })
        .fail(() => notify.err('Withdraw failed'));
    });
  });

  // ==============================
  // NO-PROFILE EDIT
  // ==============================
  $(document).on('click', '.edit-noprofile-btn', function () {
    $('#noProfileId').val($(this).data('id'));
    $('#noProfileName').val($(this).data('name'));
    $('#noProfileSurname').val($(this).data('surname'));
    $('#editNoProfileModal').modal('show');
  });

  $('#editNoProfileForm').on('submit', function (e) {
    e.preventDefault();

    $.ajax({
      url: `${APP_URL}/backend/team/noprofile/update/${$('#noProfileId').val()}`,
      type: 'PATCH',
      data: {
        name: $('#noProfileName').val(),
        surname: $('#noProfileSurname').val(),
        _token: CSRF
      }
    })
      .done(() => {
        notify.ok('Dummy player updated');
        $('#editNoProfileModal').modal('hide');
      })
      .fail(() => notify.err('Update failed'));
  });

  $(function () {

    // ==============================
    // EMAIL — SINGLE PLAYER
    // ==============================
    $(document).on('click', '.emailPlayer', function () {
      const playerId = $(this).data('playerid');
      const name = $(this).data('name');

      if (!playerId) {
        toastr.error('No email available for this player');
        return;
      }

      $('#sendMailForm')[0].reset();

      $('#target_type').val('player');
      $('#emailPlayerId').val(playerId);

      // 🔒 LOCK recipient to PLAYER
      lockRecipient(name, 'Player');

      $('#emailTeamSelect').val(null).trigger('change');
      $('#emailRegionSelect').val(null).trigger('change');
      $('#emailCategorySelect').val(null).trigger('change');

      mailEditor.setText('');

      $('#sendMailLabel').html(
        `<i class="ti ti-mail me-50"></i> Send Email – ${name}`
      );

      $('#sendMailModal').modal('show');
    });

    // ==============================
    // EMAIL — TEAM
    // ==============================
    $(document).on('click', '.emailTeamBtn', function () {
      const teamId = $(this).data('teamid');
      const teamName = $(this).data('teamname');
      console.log('EMAIL TEAM CLICKED', { teamId, teamName });
      if (!teamId) {
        toastr.error('Team ID missing');
        return;
      }

      $('#sendMailForm')[0].reset();

      $('#target_type').val('team');
      $('#emailTeamId').val(teamId);
    

      $('#emailTeamSelect').val(teamId).trigger('change');
      $('#emailRegionSelect').val(null).trigger('change');
      $('#emailCategorySelect').val(null).trigger('change');

      mailEditor.setText('');

      $('#sendMailLabel').html(
        `<i class="ti ti-mail me-50"></i> Send Email – ${teamName}`
      );

      $('#sendMailModal').modal('show');
    });


    $('#sendMailForm').on('submit', function (e) {
      e.preventDefault();

      console.log('📨 [SendMail] submit triggered');

      const targetType = $('#target_type').val();
      const subject = $('#emailSubject').val();
      const html = mailEditor.root.innerHTML.trim();
      const text = mailEditor.getText().trim();

      console.log('📌 targetType:', targetType);
      console.log('📌 subject:', subject);
      console.log('📌 message length:', text.length);

      if (!subject) {
        toastr.warning('Please enter a subject');
        return;
      }

      if (!text || html === '<p><br></p>') {
        toastr.warning('Please type your message');
        return;
      }

      $('#emailMessage').val(html);

      const $btn = $('#sendMailForm button[type="submit"]');
      $btn.prop('disabled', true);

      toastr.info('Sending email…');

      let url = api.sendMail;
      let data;

      /* ==========================
         SINGLE PLAYER
      ========================== */
      if (targetType === 'player') {
        const playerId = $('#emailPlayerId').val();

        if (!playerId) {
          toastr.error('Missing player ID');
          $btn.prop('disabled', false);
          return;
        }

        data = {
          _token: CSRF,
          to: playerId,
          emailSubject: subject,
          message: html,
          replyTo: $('#replyTo').val(),
          fromName: $('#fromName').val(),
          bcc: $('#bcc').is(':checked') ? 1 : 0
        };

        console.log('➡️ Payload (player):', data);
      }

      /* ==========================
         TEAM / BULK
      ========================== */
      else if (targetType === 'team') {
        const teamId = $('#emailTeamId').val();

        console.log('Selected team ID:', teamId);

        if (!teamId) {
          toastr.error('Missing team ID');
          $btn.prop('disabled', false);
          return;
        }

        data = {
          _token: CSRF,
          target_type: 'team',        // ✅ REQUIRED
          team_id: teamId,            // ✅ REQUIRED
          event_id: $('#event_id').val(),
          emailSubject: subject,
          message: html,
          replyTo: $('#replyTo').val(),
          fromName: $('#fromName').val(),
          bcc: $('#bcc').is(':checked') ? 1 : 0
        };

        console.log('➡️ Payload (team):', data);
      }


      console.log('➡️ POST URL:', url);

      $.post(url, data)
        .done(resp => {
          console.log('✅ Mail success response:', resp);

          toastr.success(
            resp?.result?.message ||
            resp?.message ||
            'Email sent successfully'
          );

          $('#sendMailModal').modal('hide');
        })
        .fail(xhr => {
          console.error('❌ Mail failed', xhr);

          toastr.error(
            xhr.responseJSON?.message || 'Failed to send email'
          );
        })
        .always(() => {
          $btn.prop('disabled', false);
        });
    });


    $('#sendMailModal').on('hidden.bs.modal', function () {
      const $sel = $('#emailRecipientSelect');
      const original = $sel.data('original-options');

      if (original) {
        $sel.html(original);
        $sel.trigger('change.select2');
      }
    });

    

    /* =====================================================
   EDIT ROSTER (HEADER BUTTON)
===================================================== */
    $(document).on('click', '.editRosterBtn', function () {

      console.log('🟢 EDIT ROSTER CLICKED');
      console.log('➡️ Button data:', $(this).data());

      const teamId = $(this).data('teamid');
      console.log('➡️ Extracted teamId:', teamId);

      if (!teamId) {
        console.error('❌ Team ID missing');
        toastr.error('Team ID missing');
        return;
      }

      console.log('🟡 Showing modal #edit-roster-modal');
      $('#rosterEditor').html('Loading…');
      $('#edit-roster-modal').modal('show');

      console.log('🌐 Sending AJAX GET to:', api.editRoster, { team_id: teamId });

      $.get(api.editRoster, { team_id: teamId })

        .done(res => {
          console.log('✅ AJAX SUCCESS');
          console.log('📦 Full response:', res);

          if (!res.team || !res.slots || !res.players) {
            console.error('❌ Response missing expected keys', res);
            toastr.error('Invalid roster response');
            return;
          }

          console.log('👥 Team:', res.team);
          console.log('📋 Slots:', res.slots.length);
          console.log('🧍 Players:', res.players.length);

          let html = `
        <input type="hidden" id="rosterTeamId" value="${res.team.id}">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Player</th>
            </tr>
          </thead>
          <tbody>
      `;

          res.slots.forEach(slot => {
            console.log('🔹 Slot:', slot);

            html += `
          <tr>
            <td width="80">${slot.rank}</td>
            <td>
              <select class="form-select rosterPlayerSelect"
                      data-slot="${slot.id}">
                <option value="0">— Empty —</option>
        `;

            res.players.forEach(p => {
              const selected = (String(p.id) === String(slot.player_id)) ? 'selected' : '';

              html += `
            <option value="${p.id}" ${selected}>
              ${p.name} ${p.surname}
            </option>
          `;
            });

            html += `
              </select>
            </td>
          </tr>
        `;
          });

          html += `
          </tbody>
        </table>
      `;

          console.log('🧱 Injecting roster HTML');
          $('#rosterEditor').html(html);

          console.log('🎛 Initialising Select2');
          $('.rosterPlayerSelect').select2({
            dropdownParent: $('#edit-roster-modal'),
            width: '100%'
          });

          console.log('✅ Roster modal fully rendered');
        })

        .fail((xhr, status, error) => {
          console.error('❌ AJAX FAILED');
          console.error('Status:', status);
          console.error('Error:', error);
          console.error('Response:', xhr.responseText);

          toastr.error('Failed to load roster');
          $('#edit-roster-modal').modal('hide');
        });
    });




   


    /* =====================================================
       SAVE ROSTER
    ===================================================== */
    $('#saveRosterBtn').on('click', function () {

      const teamId = $('#rosterTeamId').val();
      if (!teamId) {
        toastr.error('Team ID missing');
        return;
      }

      const slots = {};
      $('.rosterPlayerSelect').each(function () {
        slots[$(this).data('slot')] = $(this).val();
      });

      $.post(api.updateRoster, {
        team_id: teamId,
        slots: slots
      })

        .done(res => {
          toastr.success('Roster updated');
          $('#edit-roster-modal').modal('hide');
          location.reload();
        })
        .fail(() => toastr.error('Roster save failed'));
    });


    /* =====================================================
       CHANGE PAY STATUS (ROW ACTION)
    ===================================================== */
    $(document).on('click', '.changePayStatus', function () {

      const pivotId = $(this).data('pivot');
      const $row = $(`tr[data-playerteamid="${pivotId}"]`);
      const $cell = $row.find('.payStatus');

      $cell.html('<i class="ti ti-loader ti-spin"></i>');

      $.post(api.changePayStatus, {
        pivot_id: pivotId
      })

        .done(res => {
          const paid = !!res.pay_status;
          $cell.html(`
        <span class="badge ${paid ? 'bg-label-success' : 'bg-label-danger'}">
          ${paid ? 'Paid' : 'Unpaid'}
        </span>
      `);
          toastr.success('Pay status updated');
        })
        .fail(() => toastr.error('Failed to update pay status'));
    });


    /* =====================================================
       REFUND TO WALLET (ROW ACTION)
    ===================================================== */
    $(document).on('click', '.refundToWallet', function (e) {
      e.preventDefault();

      const pivotId = $(this).data('pivot');
      const $btn = $(this);

      $btn.prop('disabled', true);

      $.post(api.refundWallet, {
        team_player_id: pivotId
      })
        .done(() => toastr.success('Refunded to wallet'))
        .fail(() => toastr.error('Refund failed'))
        .always(() => $btn.prop('disabled', false));
    });

  });


})(jQuery, window, document);
