/*
 * Admin — Event Page JS (teams, regions, ranks, nominations, wallet, DT, select2, sortable)
 * Requires: jQuery, toastr, SweetAlert2, SortableJS, DataTables, Select2, Quill
 * Consolidated + rebuilt on 2025-10-23 from earlier working version
 */

(function ($, window, document) {
  'use strict';

  // ==============================
  // Bootstrap / globals
  // ==============================
  const CSRF = $('meta[name="csrf-token"]').attr('content');
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });

  if (typeof APP_URL === 'undefined') {
    console.error('APP_URL is not defined on window');
  }

  // Central API map (keep endpoints here only)
  const api = {
    // --- Email & Admin ---
    sendMail: APP_URL + '/backend/email/send',
    registerAdmin: APP_URL + '/register/registerAdmin',
    saveCategories: APP_URL + '/backend/event/saveCategories',

    // --- Wallet & Payment ---
    refundWallet: APP_URL + '/backend/wallet/refund',
    refundWalletBulk: APP_URL + '/backend/wallet/refund/bulk',
    changePayStatus: APP_URL + '/backend/team/change/payStatus',
    refreshWalletBalance: id => `${APP_URL}/backend/wallet/balance/${id}`,

    // --- Team actions ---
    publishTeam: id => `${APP_URL}/backend/team/publishTeam/${id}`,
    orderPlayerList: `${APP_URL}/backend/team/orderPlayerList`,
    insertPlayer: `${APP_URL}/backend/team/insertPlayer`,
    replacePlayer: `${APP_URL}/backend/team/replacePlayer`,
    teamStore: `${APP_URL}/backend/team`,
    teamDestroy: id => `${APP_URL}/backend/team/${id}`,
    addTeamToRegion: `${APP_URL}/backend/team/addToRegion`,

    // --- Results management ---
    resetResult: `${APP_URL}/backend/result/reset`,
    saveOrder: catId => `${APP_URL}/backend/result/saveOrder/${catId}`,

    // --- Event & Region management ---
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

    // --- Event helper endpoints ---
    eventCategoriesForEvent: id => `${APP_URL}/backend/event/getEventCategories/${id}`,
    nominationPlayersForCat: id => `${APP_URL}/backend/nomination/players/category/${id}`,

    // --- Add Player to Category (from today's modal) ---
    addPlayerToCategory: `${APP_URL}/backend/registration/addPlayerToCategory`,


    // --- Email lists ---
    emailPlayers: id => `${APP_URL}/backend/email/players/${id}`,
    emailTeams: id => `${APP_URL}/backend/email/teams/${id}`,
    emailRegions: id => `${APP_URL}/backend/email/regions/${id}`,

    // --- Templates ---
    emailTemplates: `${APP_URL}/backend/email/templates`,
    emailTemplate: id => `${APP_URL}/backend/email/templates/${id}`
  };
  window.api = api; // expose for areas out of closure (Sortable callbacks etc.)

  // ==============================
  // Helpers / Notify / Loader
  // ==============================
  const notify = {
    ok: (m = 'Done') => toastr.success(m),
    err: (m = 'Something went wrong') => toastr.error(m),
    warn: (m = 'Check details') => toastr.warning(m),
    info: (m = 'Working...') => toastr.info(m)
  };

  $(document).ajaxStart(() => $('body').addClass('loading'));
  $(document).ajaxStop(() => $('body').removeClass('loading'));

  if (!$('style#ajaxLoaderStyle').length) {
    $('head').append(`
      <style id="ajaxLoaderStyle">
        body.loading::after {
          content: 'Saving...';
          position: fixed;
          top: 50%; left: 50%;
          transform: translate(-50%, -50%);
          background: rgba(0,0,0,0.6);
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
  // DataTables Defaults & init
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
      if (!$.fn.dataTable.isDataTable(this)) $(this).DataTable();
    });
  }

  // ==============================
  // Persist tab/accordion state
  // ==============================
  const STATE_KEY = 'event-admin-state-v2';
  const saveState = (k, v) => {
    const s = JSON.parse(localStorage.getItem(STATE_KEY) || '{}');
    s[k] = v; localStorage.setItem(STATE_KEY, JSON.stringify(s));
  };
  const getState = k => (JSON.parse(localStorage.getItem(STATE_KEY) || '{}'))[k];

  // Tabs
  $(document).on('shown.bs.tab', 'a[data-bs-toggle="tab"]', function (e) {
    saveState('lastTab', $(e.target).attr('href'));
  });
  const lastTab = getState('lastTab');
  if (lastTab) {
    const $t = $(`a[data-bs-toggle="tab"][href="${lastTab}"]`);
    if ($t.length) new bootstrap.Tab($t[0]).show();
  }

  // Accordion (regions)
  $(document).on('shown.bs.collapse', '.accordion-collapse', function () {
    const id = $(this).attr('id');
    saveState('regionOpen', id);
  });
  const openId = getState('regionOpen');
  if (openId && document.getElementById(openId)) {
    new bootstrap.Collapse(document.getElementById(openId), { toggle: true });
    setTimeout(() => document.getElementById(openId)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
  }

  // ==============================
  // Quill Editors (Mail)
  // ==============================
  let fullEditor = null, mailEditor = null;
  const fullToolbar = [
    [{ font: [] }, { size: [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ script: 'super' }, { script: 'sub' }],
    [{ header: '1' }, { header: '2' }, 'blockquote', 'code-block'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
    ['link', 'image', 'video'],
    ['clean']
  ];
  if (document.querySelector('#full-editor')) {
    fullEditor = new Quill('#full-editor', {
      bounds: '#full-editor',
      placeholder: 'Type Something...',
      modules: { toolbar: fullToolbar },
      theme: 'snow'
    });
  }
  if (document.getElementById('messageEditor')) {
    mailEditor = new Quill('#messageEditor', {
      theme: 'snow',
      placeholder: 'Write your email message here...',
      modules: {
        toolbar: [
          [{ header: [1, 2, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'clean']
        ]
      }
    });
  }

  // ==============================
  // Email – open modal & populate lists
  // ==============================
  $(document).on('click', '.sendMailBtn', function () {
    const type = $(this).data('type');
    let eventId = $(this).data('eventid') || $('#event_id').val() || window.EVENT_ID || null;
    const regionId = $(this).data('regionid') || null;

    console.groupCollapsed('%c[SendMail] Button Clicked', 'color:#28a745; font-weight:bold;');
    console.log('🟡 Type:', type);
    console.log('🟢 Event ID:', eventId);
    console.log('🟣 Region ID:', regionId);
    console.groupEnd();

    if (!eventId) {
      toastr.error('Event ID missing — cannot load recipients.');
      console.error('[SendMail] ❌ Missing Event ID.');
      return;
    }

    $('#target_type').val(type);
    $('.selector-wrapper').addClass('d-none'); // hide all selectors first

    const openModal = () => {
      console.log('[SendMail] 🟢 Opening modal...');
      $('#sendMailModal').modal('show');
    };

    // 🧩 Load Players
    if (type === 'player') {
      console.log('[SendMail] Loading players for event:', eventId);
      $('#playerSelectWrapper').removeClass('d-none');
      $('#emailPlayerSelect').empty().append('<option>Loading players...</option>');

      $.get(api.emailPlayers(eventId))
        .done(data => {
          console.log('[SendMail] ✅ Players loaded:', data);
          $('#emailPlayerSelect').empty().append('<option value="">Select Player</option>');
          data.forEach(p => $('#emailPlayerSelect').append(`<option value="${p.id}">${p.text}</option>`));
          openModal();
        })
        .fail(err => {
          console.error('[SendMail] ❌ Failed to load players:', err);
          toastr.error('Failed to load players');
        });
    }

    // 🧩 Load Teams
    if (type === 'team') {
      console.log('[SendMail] Loading teams for event:', eventId);
      $('#teamSelectWrapper').removeClass('d-none');
      $('#emailTeamSelect').empty().append('<option>Loading teams...</option>');

      $.get(api.emailTeams(eventId))
        .done(data => {
          console.log('[SendMail] ✅ Teams loaded:', data);
          $('#emailTeamSelect').empty().append('<option value="">Select Team</option>');
          data.forEach(t => $('#emailTeamSelect').append(`<option value="${t.id}">${t.text}</option>`));
          openModal();
        })
        .fail(err => {
          console.error('[SendMail] ❌ Failed to load teams:', err);
          toastr.error('Failed to load teams');
        });
    }

    // 🧩 Load Regions
    if (type === 'region') {
      console.log('[SendMail] Loading regions for event:', eventId);
      $('#regionSelectWrapper').removeClass('d-none');
      $('#emailRegionSelect').empty().append('<option>Loading regions...</option>');

      $.get(api.emailRegions(eventId))
        .done(data => {
          console.log('[SendMail] ✅ Regions loaded:', data);
          $('#emailRegionSelect').empty().append('<option value="">Select Region</option>');
          data.forEach(r => $('#emailRegionSelect').append(`<option value="${r.id}">${r.text}</option>`));

          if (regionId) {
            console.log('[SendMail] 🔵 Preselecting region:', regionId);
            $('#emailRegionSelect').val(regionId).trigger('change');
          }

          openModal();
        })
        .fail(err => {
          console.error('[SendMail] ❌ Failed to load regions:', err);
          toastr.error('Failed to load regions');
        });
    }
  });

  // 🧩 Email Template Loader
  $(document).on('show.bs.modal', '#sendMailModal', function () {
    console.groupCollapsed('%c[SendMail] Modal Opened — Loading Templates', 'color:#007bff; font-weight:bold;');
    const $ddl = $('#emailTemplateSelect');
    if (!$ddl.length) {
      console.warn('[SendMail] ⚠️ No email template dropdown found.');
      console.groupEnd();
      return;
    }

    $ddl.prop('disabled', true).html('<option>Loading templates…</option>');
    console.log('[SendMail] Fetching templates from:', api.emailTemplates);

    $.get(api.emailTemplates)
      .done(list => {
        console.log('[SendMail] ✅ Templates loaded:', list);
        $ddl.empty().append('<option value="">— Select a template —</option>');
        list.forEach(t => $ddl.append(`<option value="${t.id}">${t.name}</option>`));
        $ddl.prop('disabled', false);
        console.groupEnd();
      })
      .fail(err => {
        console.error('[SendMail] ❌ Error loading templates:', err);
        $ddl.html('<option>Error loading templates</option>');
        console.groupEnd();
      });
  });

  // Email – apply template
  $(document).on('change', '#emailTemplateSelect', function () {
    const id = $(this).val();
    if (!id) return;
    $.get(api.emailTemplate(id))
      .done(t => {
        $('input[name="subject"]').val(t.subject || '');
        if (mailEditor) mailEditor.root.innerHTML = t.html || '';
      })
      .fail(() => notify.err('Could not load template'));
  });

  // ==============================
  // Email – show/hide selectors based on recipient dropdown (and lazy-load options)
  // ==============================
  $(document).on('change', '#emailRecipientSelect', function () {
    const val = ($(this).val() || '').toLowerCase();
    const eventId = $('#event_id').val();

    // Hide everything first
    $('.selector-wrapper, #regionSelectWrapper, #teamSelectWrapper, #categorySelectWrapper').addClass('d-none');

    // Region selector needed?
    if (val.includes('region')) {
      $('#regionSelectWrapper').removeClass('d-none');
      // Lazy-load if empty (or just has placeholder)
      if (!$('#emailRegionSelect option').length || $('#emailRegionSelect option').length === 1) {
        $('#emailRegionSelect').html('<option value="">Loading regions…</option>');
        $.get(api.emailRegions(eventId))
          .done(data => {
            $('#emailRegionSelect').empty().append('<option value="">Select Region</option>');
            data.forEach(r => $('#emailRegionSelect').append(`<option value="${r.id}">${r.text}</option>`));
          })
          .fail(() => toastr.error('Failed to load regions'));
      }
    }

    // Team selector needed?
    if (val.includes('team')) {

      $('#teamSelectWrapper').removeClass('d-none');
      if (!$('#emailTeamSelect option').length || $('#emailTeamSelect option').length === 1) {
        $('#emailTeamSelect').html('<option value="">Loading teams…</option>');
        $.get(api.emailTeams(eventId))
          .done(data => {
            $('#emailTeamSelect').empty().append('<option value="">Select Team</option>');
            data.forEach(t => $('#emailTeamSelect').append(`<option value="${t.id}">${t.text}</option>`));
          })
          .fail(() => toastr.error('Failed to load teams'));
      }
    }

    // Category selector needed?
    if (val.includes('category')) {
      $('#categorySelectWrapper').removeClass('d-none');
      if (!$('#emailCategorySelect option').length || $('#emailCategorySelect option').length === 1) {
        $('#emailCategorySelect').html('<option value="">Loading categories…</option>');
        $.get(api.eventCategoriesForEvent(eventId))
          .done(rows => {
            $('#emailCategorySelect').empty().append('<option value="">Select Category</option>');
            rows.forEach(r => $('#emailCategorySelect').append(`<option value="${r.id}">${r.category_name || r.name}</option>`));
          })
          .fail(() => toastr.error('Failed to load categories'));
      }
    }
  });

  // Email – submit
  // Email – submit
  $('#sendMailForm').on('submit', function (e) {
   
    e.preventDefault();

    const type = $('#target_type').val();
    const subject = $.trim($('#emailSubject').val());
    const message = mailEditor ? mailEditor.root.innerHTML.trim() : $('#emailMessage').val();
    $('#emailMessage').val(message); // ✅ keep hidden textarea in sync

    const fromName = $.trim($('#fromName').val()) || 'Cape Tennis Admin';
    const fromEmail = $.trim($('#fromEmail').val()) || 'capetennis@capetennis.co.za';
    const replyTo = $.trim($('#replyTo').val()) || fromEmail;

    // Build data payload
    const data = {
      emailSubject: subject,
      message,
      fromName,
      fromEmail,
      replyTo,
      bcc: $('#bcc').is(':checked') ? 1 : 0,
      event_id: $('#event_id').val()
    };

    // 🟢 Standard recipient types
    if (type === 'player') data.to = $('#emailPlayerSelect').val();
    if (type === 'team') { data.team_id = $('#emailTeamSelect').val(); data.to = 'All players in team'; }
    if (type === 'region') { data.region_id = $('#emailRegionSelect').val(); data.to = 'All players in region'; }

    // 🟠 New unregistered types (selected in dropdown)
    const recipient = $('#emailRecipientSelect').val();
    if (recipient === 'All Unregistered players in Event') data.to = 'All Unregistered players in Event';
    if (recipient === 'All Unregistered players in Region') {
      data.region_id = $('#emailRegionSelect').val();
      data.to = 'All Unregistered players in Region';
    }
    if (recipient === 'All Unregistered players in Team') {
      data.team_id = $('#emailTeamSelect').val();
      data.to = 'All Unregistered players in Team';
    }

    // 🧠 Fallback if selected directly from dropdown without type
    if (!data.to && recipient) data.to = recipient;

    $.post(api.sendMail, data)
      .done(res => {
        toastr.success(res.message || 'Email sent successfully!');
        $('#sendMailModal').modal('hide');
        $('#sendMailForm')[0].reset();
        mailEditor && mailEditor.setContents([]);
      })
      .fail(err => {
        toastr.error(err.responseJSON?.message || 'Failed to send email');
        console.error('❌ Email send failed:', err);
      });
  });

  // ==============================
  // Region add / select
  // ==============================
  $('#modalToggle').on('shown.bs.modal', function () {
    const $select2Region = $('.select2Region');
    $select2Region.select2({
      dropdownParent: $('#modalToggle'),
      placeholder: 'Select or add a region',
      allowClear: true,
      tags: true,
      createTag: params => {
        const term = $.trim(params.term);
        if (!term) return null;
        return { id: term, text: `➕ Add new region: ${term}`, newTag: true };
      }
    });

    $select2Region.on('select2:select', function (e) {
      const data = e.params.data;
      if (data.newTag) {
        $.post(api.region, { region_name: data.id })
          .done(res => {
            const newOpt = new Option(res.region_name, res.id, true, true);
            $select2Region.append(newOpt).trigger('change');
            notify.ok(`Region "${res.region_name}" added`);
          })
          .fail(() => notify.err('Could not add new region'));
      }
    });
  });

  $(document).on('click', '#addRegionToEventButton', function () {
    const data = $('#regionEventForm').serialize();

    $.post(api.eventRegion, data)
      .done(res => {
        if (!res.id || !res.region_name) { toastr.error('Invalid response from server.'); return; }
        $('.noRegions').remove();

        let $accordion = $('#regionsAccordion');
        if (!$accordion.length) {
          $('.card-body').append('<div class="accordion" id="regionsAccordion"></div>');
          $accordion = $('#regionsAccordion');
        }

        const html = `
        <div class="accordion-item mb-2 border rounded" id="region-${res.id}">
          <h2 class="accordion-header" id="heading-${res.id}">
            <button class="accordion-button collapsed fw-semibold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-${res.id}"
                    aria-expanded="false" aria-controls="collapse-${res.id}">
              <span class="me-2 badge bg-label-secondary">#${res.id}</span>
              ${res.region_name}
              <span class="ms-2 text-muted small">(0 Teams)</span>
            </button>
          </h2>
          <div id="collapse-${res.id}" class="accordion-collapse collapse"
               aria-labelledby="heading-${res.id}" data-bs-parent="#regionsAccordion">
            <div class="accordion-body pt-2">
              <div class="d-flex justify-content-end mb-2">
                <a href="javascript:void(0)" class="text-danger removeRegionEvent me-2" data-id="${res.pivot_id}">
                  <i class="ti ti-trash me-1"></i> Remove Region
                </a>
                <a data-regionid="${res.id}" data-bs-target="#addTeamModal" data-bs-toggle="modal" href="javascript:void(0)" class="btn btn-sm btn-primary addTeam">
                  <i class="ti ti-plus me-1"></i> Add Team
                </a>
              </div>
              <div class="alert alert-light border text-center py-2">No teams in this region yet.</div>
            </div>
          </div>
        </div>`;

        $accordion.append(html);
        const $newCollapse = $(`#collapse-${res.id}`);
        new bootstrap.Collapse($newCollapse[0], { toggle: true });
        toastr.success(`Region "${res.region_name}" added successfully`);
        $('#modalToggle').modal('hide');
      })
      .fail(err => {
        console.error('❌ AJAX Error:', err.responseText || err);
        toastr.error('Failed to link region');
      });
  });

  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();

    const pivotId = $(this).data('id');
    const $regionItem = $(this).closest('.accordion-item');

    Swal.fire({
      title: 'Remove Region?',
      text: 'This will remove the region and all its linked teams from this event.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, remove it',
      cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-secondary' },
      buttonsStyling: false
    }).then(result => {
      if (!result.isConfirmed) return;

      $.ajax({ url: api.eventRegionId(pivotId), type: 'DELETE' })
        .done(() => {
          $regionItem.fadeOut(300, function () {
            $(this).remove();
            if ($('#regionsAccordion .accordion-item').length === 0) {
              $('#regionsAccordion').replaceWith(`
                <div class="alert alert-primary noRegions text-center" role="alert">
                  <i class="ti ti-info-circle me-1"></i> No regions added to this event yet.
                </div>`);
            }
          });
          toastr.success('Region removed successfully');
        })
        .fail(xhr => {
          console.error('❌ Remove Region Error:', xhr.responseJSON || xhr);
          toastr.error('Failed to remove region');
        });
    });
  });

  // ==============================
  // Team Add / Category edit modal
  // ==============================
  // Store region id when opening Add Team
  $(document).on('click', '.addTeam', function () {
    const regionId = $(this).data('regionid');
    $('#addTeamModal #region_id').val(regionId);
  });

  // Submit Add Team
  $(document).on('click', '#updateTeamButton', function (e) {
    e.preventDefault();
    const form = $('#teamForm');
    const data = form.serialize();

    $.ajax({ url: api.addTeamToRegion, method: 'POST', data })
      .done(function (res) {
        $('#addTeamModal').modal('hide');
        form[0].reset();
        toastr.success(`Team "${res.team.name}" added successfully!`);

        const $regionBody = $(`#collapse-${res.region_id} .accordion-body`);
        if ($regionBody.length) {
          let $listGroup = $regionBody.find('.list-group');
          $regionBody.find('.alert-light').remove();
          if (!$listGroup.length) $listGroup = $('<div class="list-group"></div>').appendTo($regionBody);

          $listGroup.append(`
          <div class="list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom">
            <div>
              <div class="fw-medium">${res.team.name}</div>
              <small class="text-muted d-block mb-1 category-${res.team.id}">Category: <span class="fw-semibold text-primary">None</span></small>
              <button class="btn btn-xs bg-label-info edit-team-category" data-team='{"id":${res.team.id},"name":"${res.team.name}"}' data-bs-toggle="modal" data-bs-target="#edit-team-category-modal">
                <i class="ti ti-edit me-25"></i> Edit Category
              </button>
            </div>
            <div class="text-end">
              <a href="javascript:void(0)" class="publishTeam d-block mb-2" data-state="0" data-id="${res.team.id}">
                <span class="badge bg-label-success">Publish Team</span>
              </a>
              <a href="javascript:void(0)" class="toggleTeam toggleNoProfile d-block mb-2" data-url="${APP_URL}/backend/teams/${res.team.id}/toggle-noprofile" data-state="0">
                <span class="badge bg-label-info">Enable NoProfile</span>
              </a>
              <a href="javascript:void(0)" class="text-danger removeTeam small" data-id="${res.team.id}">
                <i class="ti ti-trash me-25"></i> Delete
              </a>
            </div>
          </div>`);
        }
      })
      .fail(function (xhr) {
        console.error('❌ AJAX Error:', xhr.responseJSON || xhr);
        toastr.error(xhr.responseJSON?.message || 'Failed to add team');
      });
  });

  // Open Edit Team Category modal
  $(document).on('click', '.edit-team-category', function () {
    const data = $(this).data('team');
    $('#edit-team-category-modal [name="team_id"]').val(data.id);
    $('#edit-team-category-modal .team-name').text(data.name);

    // load event categories for this event
    const eventId = $('#event_id').val();
    const $ddl = $('#edit-team-category-modal select[name="category_event_id"]');
    $ddl.prop('disabled', true).html('<option>Loading…</option>');
    $.get(api.eventCategoriesForEvent(eventId))
      .done(rows => {
        $ddl.empty().append('<option value="">— Select —</option>');
        rows.forEach(r => $ddl.append(`<option value="${r.id}">${r.category_name}</option>`));
        $ddl.prop('disabled', false);
      })
      .fail(() => $ddl.html('<option>Error</option>'));
  });

  // Save Team Category
  $(document).on('submit', '#edit-team-category-form', function (e) {
    e.preventDefault();
    const $f = $(this);
    $.post(APP_URL + '/backend/team/update/category', $f.serialize())
      .done(res => {
        toastr.success('Team category updated');
        $('#edit-team-category-modal').modal('hide');
        $(`.category-${res.team_id} span`).text(res.category_name || 'None');
      })
      .fail(() => toastr.error('Update failed'));
  });

  // Delete Team
  $(document).on('click', '.removeTeam', function (e) {
    e.preventDefault();
    const teamId = $(this).data('id');
    const $teamItem = $(this).closest('.list-group-item');

    Swal.fire({
      title: 'Delete Team?',
      text: 'Are you sure you want to remove this team?',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Yes, delete it', cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-secondary' },
      buttonsStyling: false
    }).then(result => {
      if (!result.isConfirmed) return;
      $.ajax({ url: api.teamDestroy(teamId), method: 'DELETE', data: { _token: CSRF } })
        .done(res => {
          toastr.success('Team deleted successfully.');
          $teamItem.fadeOut(300, function () {
            $(this).remove();
            const $regionBody = $(this).closest('.accordion-body');
            const teamCount = $regionBody.find('.list-group-item').length;
            const $header = $regionBody.closest('.accordion-item').find('.accordion-button .text-muted small');
            if (teamCount === 0) {
              $regionBody.find('.list-group').remove();
              $regionBody.append('<div class="alert alert-light border text-center py-2">No teams in this region yet.</div>');
              $header.text('(0 Teams)');
            } else {
              $header.text(`(${teamCount} Teams)`);
            }
          });
        })
        .fail(xhr => {
          console.error('❌ Delete Error:', xhr.responseJSON || xhr);
          toastr.error(xhr.responseJSON?.message || 'Failed to delete team.');
        });
    });
  });

  // Toggle NoProfile
  $(document).on('click', '.toggleTeam', function () {
    const $btn = $(this);
    const url = $btn.data('url');
    const currentState = parseInt($btn.data('state'), 10);
    const oldHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="badge bg-label-secondary">Updating…</span>');

    $.ajax({ url, type: 'POST', data: { _method: 'PATCH', _token: CSRF } })
      .done(res => {
        const newState = res.state ?? (currentState === 1 ? 0 : 1);
        const html = res.html ?? (newState
          ? '<span class="badge bg-label-warning">Disable NoProfile</span>'
          : '<span class="badge bg-label-info">Enable NoProfile</span>');
        $btn.html(html).data('state', newState).prop('disabled', false)
          .removeClass('btn-info btn-warning')
          .addClass(newState ? 'btn-warning' : 'btn-info');
        toastr.success(newState ? 'NoProfile enabled' : 'NoProfile disabled');
      })
      .fail(xhr => {
        console.error('🔴 [ToggleNoProfile] AJAX Error:', xhr);
        toastr.error('Toggle failed');
        $btn.html(oldHtml).prop('disabled', false);
      });
  });

  // Publish / Unpublish Team
  $(document).on('click', '.publishTeam', function () {
    const $btn = $(this);
    const id = $btn.data('id');
    const currentState = parseInt($btn.data('state'), 10);

    $btn.prop('disabled', true).html('<span class="badge bg-label-secondary">Updating…</span>');

    $.ajax({ url: api.publishTeam(id), type: 'POST', data: { _token: CSRF } })
      .done(res => {
        const newState = res.state ?? (currentState === 1 ? 0 : 1);
        const html = newState ? '<span class="badge bg-label-danger">Unpublish Team</span>' : '<span class="badge bg-label-success">Publish Team</span>';
        $btn.html(html).data('state', newState).prop('disabled', false)
          .removeClass('btn-success btn-danger').addClass(newState ? 'btn-danger' : 'btn-success');
        toastr.success(res.message || (newState ? 'Team published' : 'Team unpublished'));
      })
      .fail(xhr => {
        console.error('🔴 [PublishTeam] AJAX Error:', xhr);
        toastr.error('Publish toggle failed');
        $btn.html('<span class="badge bg-label-danger">Error</span>').prop('disabled', false);
      });
  });

  // ==============================
  // Player Order (Sortable) + Replace/Insert + Pay/Wallet
  // ==============================
  // Sortable players
  $('tbody.sortablePlayers').each(function () {
    const $tbody = $(this);
    new Sortable(this, {
      animation: 150,
      handle: '.drag-item',
      onEnd: () => {
        const order = $tbody.find('tr.drag-item').map(function (idx) {
          return { id: $(this).data('playerteamid'), type: $(this).data('type'), position: idx + 1 };
        }).get();
        $tbody.addClass('opacity-50');
        $.post(api.orderPlayerList, { team_id: $tbody.data('team-id'), order })
          .done(res => {
            if (!res || !res.players) { notify.err('Invalid response'); $tbody.removeClass('opacity-50'); return; }
            const rows = res.players;
            const tpl = (v, idx) => {
              const isProfile = v.type === 'profile';
              const playerObj = isProfile ? v.player : v;
              const name = [playerObj?.name, playerObj?.surname].filter(Boolean).join(' ') || '—';
              const email = playerObj?.email || '—';
              const cellNr = playerObj?.cellNr || '—';
              const paid = Number(v.pay_status ?? 0) === 1;
              return `
                <tr class="row-${v.id} drag-item" data-playerteamid="${v.id}" data-type="${v.type}">
                  <td><span class="badge bg-label-primary">${idx + 1}</span></td>
                  <td class="name">${name}</td>
                  <td class="email">${email}</td>
                  <td class="cellNr">${cellNr}</td>
                  <td class="payStatus"><span class="badge ${paid ? 'bg-label-success' : 'bg-label-danger'}">${paid ? 'Paid' : 'Not Paid'}</span></td>
                  <td>
                    <div class="dropdown listDropdown">
                      <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                      <div class="dropdown-menu">
                        <a class="dropdown-item insertPlayer" data-pivot="${v.id}" data-position="${idx + 1}" data-teamid="${$tbody.data('team-id')}" data-bs-toggle="modal" data-bs-target="#insert-player-team-modal">
                          <i class="ti ti-insert me-1"></i> Replace Player
                        </a>
                        <a class="dropdown-item changePayStatus" data-pivot="${v.id}"><i class="ti ti-credit-card me-1"></i> Change Pay Status</a>
                        <a class="dropdown-item refundToWallet" data-pivot="${v.id}"><i class="ti ti-cash me-1"></i> Refund to Wallet</a>
                      </div>
                    </div>
                  </td>
                </tr>`;
            };
            $tbody.html(rows.map(tpl).join('')).removeClass('opacity-50').addClass('table-success');
            setTimeout(() => $tbody.removeClass('table-success'), 1200);
            notify.ok('Player order updated successfully');
          })
          .fail(() => { notify.err('Order save failed'); $tbody.removeClass('opacity-50'); });
      }
    });
  });

  // Insert Player modal open: stash pivot and position
  $(document).on('click', '.insertPlayer', function () {
    $('#playerPosition').text('Insert player into position ' + $(this).data('position'));
    $('#teamId').val($(this).data('teamid'));
    $('#position').val($(this).data('position'));
    $('#pivot').val($(this).data('pivot'));
  });

  // Select2 inside Insert Player modal
  $('#insert-player-team-modal').on('shown.bs.modal', function () {
    const $sel = $('#select2PlayerBasic');
    if (!$sel.hasClass('select2-hidden-accessible')) {
      $sel.select2({ dropdownParent: $('#insert-player-team-modal'), placeholder: 'Select player...', allowClear: true, width: '100%' });
    }
  });

  // Perform insert/replace
  $('#playerPosition').on('click', function () {
    const playerSel = $('#select2PlayerBasic').select2('data');
    const selectedPlayerId = playerSel[0]?.id || null;
    if (!selectedPlayerId) { toastr.warning('Please select a player before inserting.'); return; }

    const formData = { player: selectedPlayerId, team_id: $('#teamId').val(), position: $('#position').val(), pivot: $('#pivot').val(), _token: CSRF };
    const $btn = $(this); $btn.prop('disabled', true).addClass('opacity-50');

    $.post(api.insertPlayer, formData)
      .done(res => {
        if (!res || !res.id || !res.player) { toastr.error('Unexpected response structure.'); $btn.prop('disabled', false).removeClass('opacity-50'); return; }
        const player = res.player; const payStatus = res.pay_status ?? 0; const badgeClass = payStatus ? 'bg-label-success' : 'bg-label-danger'; const badgeText = payStatus ? 'Paid' : 'Not Paid';
        const updateRow = ($row) => {
          const $nameCell = $row.find('.name').length ? $row.find('.name') : $row.find('td').eq(1);
          $nameCell.text(`${player.name} ${player.surname}`);
          $row.find('.email').text(player.email || '—');
          $row.find('.cellNr').text(player.cellNr || '—');
          $row.find('.payStatus').html(`<span class="badge ${badgeClass}">${badgeText}</span>`);
          $row.addClass('table-success'); setTimeout(() => $row.removeClass('table-success'), 1000);
        };
        const $orderRow = $(`tr[data-playerteamid="${res.id}"]`); if ($orderRow.length) updateRow($orderRow);
        const $playersRow = $(`#tab-players tr[data-playerteamid="${res.id}"]`); if ($playersRow.length) updateRow($playersRow);
        toastr.success(`Player replaced → ${player.name} ${player.surname}`);
        $('#insert-player-team-modal').modal('hide');
      })
      .fail(xhr => { console.error('❌ Replace Player AJAX Error:', xhr.responseJSON || xhr); toastr.error('Failed to replace player.'); })
      .always(() => $btn.prop('disabled', false).removeClass('opacity-50'));
  });

  // Change Pay Status
  $(document).on('click', '.changePayStatus', function (e) {
    e.preventDefault();
    const pivotId = $(this).data('pivot');
    const $row = $(this).closest('tr');
    const $cell = $row.find('.payStatus');
    $cell.html('<span class="text-muted"><i class="ti ti-loader ti-spin"></i></span>');
    $.post(api.changePayStatus, { teamPlayer_id: pivotId })
      .done(res => {
        if (!res || res.success === false) { notify.err(res?.message || 'Update failed'); return; }
        const isPaid = !!res.pay_status; const badgeHtml = `<span class="badge ${isPaid ? 'bg-label-success' : 'bg-label-danger'}">${isPaid ? 'Paid' : 'Not Paid'}</span>`;
        $cell.html(badgeHtml); $row.addClass('table-success'); setTimeout(() => $row.removeClass('table-success'), 1000);
        notify.ok(res.message || 'Pay status updated');
      })
      .fail(() => { notify.err('Could not change pay status'); $cell.html('<span class="badge bg-label-danger">Error</span>'); });
  });

  // Refund to Wallet (single)
  $(document).on('click', '.refundToWallet', function (e) {
    e.preventDefault();
    const pivotId = $(this).data('pivot');
    const $btn = $(this);
    $btn.prop('disabled', true).addClass('opacity-50');
    $.post(api.refundWallet, { team_player_id: pivotId })
      .done(() => notify.ok('Refunded to wallet'))
      .fail(() => notify.err('Refund failed'))
      .always(() => $btn.prop('disabled', false).removeClass('opacity-50'));
  });

  // Refund to Wallet (bulk by team)
  $(document).on('click', '.refund-wallet-bulk', function () {
    const teamId = $(this).data('team-id');
    Swal.fire({ title: 'Refund all players in team?', icon: 'warning', showCancelButton: true })
      .then(res => {
        if (!res.isConfirmed) return;
        $.post(api.refundWalletBulk, { team_id: teamId })
          .done(r => { toastr.success(r.message || 'Bulk refund done'); })
          .fail(() => toastr.error('Bulk refund failed'));
      });
  });

  // ==============================
  // Nominations
  // ==============================
  // Load players for category (nomination panel)
  $(document).on('change', '.nomination-category-select', function () {
    const catId = $(this).val();
    const $list = $('#nomination-player-list');
    $list.html('<div class="text-muted small">Loading players…</div>');
    $.get(api.nomination.playersForCat(catId))
      .done(rows => {
        if (!rows.length) { $list.html('<div class="text-muted small">No players found.</div>'); return; }
        const html = rows.map(p => `
          <div class="form-check mb-1">
            <input class="form-check-input nomination-player" type="checkbox" value="${p.id}" id="np-${p.id}">
            <label class="form-check-label" for="np-${p.id}">${p.name} ${p.surname}</label>
          </div>`).join('');
        $list.html(html);
      })
      .fail(() => $list.html('<div class="text-danger small">Load failed</div>'));
  });

  // Submit nomination selection
  $(document).on('click', '#nomination-submit-btn', function () {
    const catId = $('.nomination-category-select').val();
    const players = $('.nomination-player:checked').map(function () { return this.value; }).get();
    if (!catId) return toastr.warning('Select a category first');
    if (!players.length) return toastr.warning('Select at least one player');
    $.post(api.nomination.submit, { category_event_id: catId, players })
      .done(res => toastr.success(res.message || 'Nominations saved'))
      .fail(() => toastr.error('Save failed'));
  });

  // Toggle publish nomination item
  $(document).on('click', '.nomination-toggle-publish', function () {
    const id = $(this).data('id');
    const $btn = $(this);
    $btn.prop('disabled', true);
    $.post(api.nomination.togglePublish(id))
      .done(res => { toastr.success(res.message || 'Toggled'); $btn.toggleClass('btn-success btn-warning'); })
      .fail(() => toastr.error('Toggle failed'))
      .always(() => $btn.prop('disabled', false));
  });

  // Remove nomination
  // Remove nomination
  $(document).on('click', '.nomination-remove', function () {
    const nomination_id = $(this).data('id');
    const $row = $(this).closest('tr');

    $.post(api.nomination.remove, {
      nomination_id, // ✅ match controller field name
      _token: $('meta[name="csrf-token"]').attr('content')
    })
      .done(() => {
        toastr.success('Removed');
        $row.fadeOut(200, function () { $(this).remove(); });
      })
      .fail(xhr => {
        console.error('❌ Remove failed:', xhr.responseText);
        toastr.error('Remove failed');
      });
  });


  // ==============================
  // Results & Rankings per Category
  // ==============================
  // Save visible order (e.g., drag within category results table)
  $(document).on('click', '.save-results-order', function () {
    const catId = $(this).data('category');
    const order = $(`#results-table-${catId} tbody tr`).map(function (idx) { return { id: $(this).data('id'), position: idx + 1 }; }).get();
    $.post(api.saveOrder(catId), { order })
      .done(() => toastr.success('Order saved'))
      .fail(() => toastr.error('Order save failed'));
  });

  // Reset results
  $(document).on('click', '.reset-results', function () {
    const catId = $(this).data('category');
    Swal.fire({ title: 'Reset all results for this category?', icon: 'warning', showCancelButton: true })
      .then(r => {
        if (!r.isConfirmed) return;
        $.post(api.resetResult, { category_event_id: catId })
          .done(() => { toastr.success('Results reset'); location.reload(); })
          .fail(() => toastr.error('Reset failed'));
      });
  });

  // ==============================
  // Event Categories Save (checkbox/multi-select form)
  // ==============================
  $(document).on('submit', '#event-categories-form', function (e) {
    e.preventDefault();
    const $f = $(this);
    $.post(api.saveCategories, $f.serialize())
      .done(res => toastr.success(res.message || 'Categories saved'))
      .fail(() => toastr.error('Save failed'));
  });

  // 🟢 Add Player to Category — Full Debug + Dynamic Update
  /* ============================================================
   * ADD PLAYER TO CATEGORY — FINAL PATCHED VERSION
   * ============================================================ */
  // ============================================================
  // Add Player Modal — Set category_event_id
  // ============================================================
  $(document).on('click', '.addPlayerC', function () {
    const catId = $(this).data('categoryeventid');
    console.log("🟢 Opening Add Player Modal — category_event_id:", catId);
    $('#categoryEvent').val(catId);
    $('#addPlayerToCategory').modal('show');
  });



  // ✅ Trigger form submit when clicking Add Player button
  $(document).on('click', '#addPlayerToCategoryButton', function () {
    console.log('🟢 Add Player button clicked');
    $('#addPlayerToCategoryForm').trigger('submit');
  });

  // ✅ Main submit handler (with console debug)
  $(document).on('submit', '#addPlayerToCategoryForm', function (e) {
    e.preventDefault();
    console.group('🟢 Submitting Add Player Form');

    const $form = $(this);
    const $btn = $('#addPlayerToCategoryButton');
    const playerId = $('#select2AddPlayer').val();
    const playerName = $('#select2AddPlayer option:selected').text().trim();
    const categoryEventId = $('#categoryEvent').val();
    const eventId = $('#event_id').val();

    console.table({
      'Player ID': playerId,
      'Player Name': playerName,
      'Category Event ID': categoryEventId,
      'Event ID': eventId
    });

    if (!playerId) {
      toastr.warning('Please select a player.');
      console.warn('⚠️ No player selected');
      console.groupEnd();
      return;
    }

    const payload = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      player_id: playerId,
      category_event_id: categoryEventId,
      event_id: eventId
    };

    console.log('📦 Sending payload:', payload);
    console.log('➡️ Endpoint:', api.addPlayerToCategory);

    $btn.prop('disabled', true).addClass('opacity-50');
    toastr.info('Adding player...');

    $.ajax({
      url: api.addPlayerToCategory,
      type: 'POST',
      data: payload,
      success: function (res) {
        console.group('✅ AJAX Success');
        console.log('Server response:', res);

        if (!res.success) {
          toastr.error(res.message || 'Player already exists');
          console.warn('⚠️ addPlayerToCategory returned failure');
          console.groupEnd();
          return;
        }

        toastr.success(`Player ${playerName} added successfully!`);
        $('#addPlayerToCategory').modal('hide');

        // 🧩 Find category section table dynamically and append row
        const $tableBody = $(`.card:has(button[data-categoryeventid="${categoryEventId}"]) table tbody`);
        console.log('Target table found:', $tableBody.length > 0);

        if ($tableBody.length) {
          const rowCount = $tableBody.find('tr').length + 1;
          console.log('Appending new player row at position', rowCount);

          $tableBody.append(`
          <tr>
            <td>${rowCount}</td>
            <td>${playerName}</td>
            <td>${res.email ?? '—'}</td>
            <td>${res.cellNr ?? '—'}</td>
            <td>
              <button class="btn btn-sm btn-danger withdraw-player-btn"
                data-id="${res.registration_id}"
                data-categoryevent="${categoryEventId}"
                data-name="${playerName}">
                <i class="ti ti-user-x me-1"></i> Withdraw
              </button>
            </td>
          </tr>
        `);
        } else {
          console.warn('❌ No target table found for CategoryEvent ID:', categoryEventId);
        }

        console.groupEnd();
      },
      error: function (xhr) {
        console.group('❌ AJAX Error');
        console.error('Status:', xhr.status);
        console.error('Response:', xhr.responseText);
        toastr.error(xhr.responseJSON?.message || 'Server error');
        console.groupEnd();
      },
      complete: function () {
        console.log('✅ AJAX request completed.');
        $btn.prop('disabled', false).removeClass('opacity-50');
        console.groupEnd();
      }
    });
  });
// ==============================
// Nomination Modal — Select2 init
// ==============================
$('#nominatePlayerModal').on('shown.bs.modal', function () {
  console.group('🟢 Nomination Modal Shown');
  const $sel = $('#nominateSelect2');
  console.log('Found select:', $sel.length > 0);

  if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
    console.log('Initializing Select2 on #nominateSelect2');
    $sel.select2({
      dropdownParent: $('#nominatePlayerModal'),
      width: '100%',
      placeholder: 'Select players…',
      allowClear: true
    });
  } else {
    console.log('Select2 already initialized or not found');
  }

  console.groupEnd();
});
  // ==============================
  // Preselect nominated players when modal opens
  // ==============================
  // ==============================
  // Nomination Modal — Select2 handling
  // ==============================
  $(document).on('click', '.openNominateModal', function () {
    const categoryId = $(this).data('categoryeventid');
    console.group('🟢 Opening Nominate Modal');
    console.log('Category ID:', categoryId);

    const $sel = $('#nominateSelect2');
    $('#category').val(categoryId);

    // destroy previous Select2 if any
    if ($sel.hasClass('select2-hidden-accessible')) {
      $sel.select2('destroy');
    }

    // re-init Select2
    $sel.select2({
      dropdownParent: $('#nominatePlayerModal'),
      width: '100%',
      placeholder: 'Select players…',
      allowClear: true
    });

    // fetch nominated players only
    $.get(`${APP_URL}/backend/nomination/selected/${categoryId}`)
      .done(selectedIds => {
        console.log('🎯 Already nominated IDs:', selectedIds);
        $sel.val(selectedIds).trigger('change.select2');
      })
      .fail(() => toastr.error('Could not load nominated players'));

    console.groupEnd();
  });
  // ==============================
  // Save Nominations (AJAX + Live Update)
  // ==============================
  // ==============================
  // Save Nominations (AJAX + live refresh)
  // ==============================
  $(document).on('click', '#submitNomination', function () {
    const categoryId = $('#category').val();
    const selectedPlayers = $('#nominateSelect2').val() || [];
    const eventId = $('#event_id').val();

    if (!categoryId || !eventId) {
      toastr.warning('Missing event or category.');
      return;
    }

    const payload = {
      event_id: eventId,
      category_event_id: categoryId,
      players: selectedPlayers,
      _token: $('meta[name="csrf-token"]').attr('content')
    };

    const $btn = $(this);
    $btn.prop('disabled', true).text('Saving...');

    $.ajax({
      url: `${APP_URL}/backend/nominate`,
      method: 'POST',
      data: payload
    })
      .done(res => {
        console.log('📦 Raw server response:', res);

        toastr.success(res.message || 'Nominations saved successfully!');
        $('#nominatePlayerModal').modal('hide');

        // ✅ Find the correct nominations table
        const $table = $(`#nomination-table-${categoryId} tbody`);
        if ($table.length) {
          $table.empty(); // clear old rows
          var key = 0;
          if (res.nominations.length) {
            res.nominations.forEach(n => {
              key += 1;
              $table.append(`
              <tr data-nominationid="${n.id}">
                <td><strong>${key}</strong></td>
                <td>${n.name} ${n.surname}</td>
                <td>${n.email}</td>
                <td><span class="badge bg-label-primary me-1">${n.cellNr}</span></td>
                <td>
                  <span class="btn btn-sm btn-secondary sendEmail"
                        data-bs-target="#createEmail"
                        data-bs-toggle="modal"
                        data-email="${n.email}"
                        data-totype="one">
                    <i class="ti ti-pencil me-1"></i>Email Player
                  </span>
                  <span class="btn btn-sm btn-danger nomination-remove"
                        data-id="${n.id}"
                        data-player="${n.name} ${n.surname}"
                        data-categoryeventid="${categoryId}">
                    <i class="ti ti-trash me-1"></i>Remove
                  </span>
                </td>
              </tr>
            `);
            });
          } else {
            $table.append(`<tr><td colspan="5" class="text-center text-muted">No nominations yet</td></tr>`);
          }
        } else {
          console.warn(`⚠️ No table found for category ${categoryId}`);
        }
      })
      .fail(xhr => {
        console.error('❌ Save failed:', xhr.responseText);
        toastr.error(xhr.responseJSON?.message || 'Failed to save nominations.');
      })
      .always(() => {
        $btn.prop('disabled', false).text('Save changes');
      });
  });
  // ==============================
  // Publish / Unpublish nominations
  // ==============================
  $(document).on('click', '.nominationPublish', function () {
    const $btn = $(this);
    const categoryId = $btn.data('id');
    const isCurrentlyPublished = $btn.hasClass('btn-danger'); // red = published

    console.group('🟣 Publish Toggle');
    console.log('Category ID:', categoryId);
    console.log('Currently published:', isCurrentlyPublished);

    $btn.prop('disabled', true).text('Processing...');

    $.ajax({
      url: `${APP_URL}/backend/nomination/publish/${categoryId}`,
      method: 'PATCH',
      data: {
        published: !isCurrentlyPublished,
        _token: $('meta[name="csrf-token"]').attr('content')
      }
    })
      .done(res => {
        console.log('✅ Publish response:', res);
        toastr.success(res.message || 'Publish status updated.');

        // 🔄 Toggle button style + text
        if (res.published) {
          $btn.removeClass('btn-success').addClass('btn-danger').text('Unpublish list');
        } else {
          $btn.removeClass('btn-danger').addClass('btn-success').text('Publish list');
        }
      })
      .fail(xhr => {
        console.error('❌ Publish toggle failed:', xhr.responseText);
        toastr.error(xhr.responseJSON?.message || 'Failed to update publish status.');
      })
      .always(() => {
        $btn.prop('disabled', false);
        console.groupEnd();
      });
  });

  // helper for consistent init (fallback)
  function initSelect2() {
    const $sel = $('#nominateSelect2');
    if (!$sel.hasClass('select2-hidden-accessible')) {
      $sel.select2({
        dropdownParent: $('#nominatePlayerModal'),
        width: '100%',
        placeholder: 'Select players…',
        allowClear: true
      });
    }
  }

  // ==============================
  // Withdraw Player (Entries tab)
  // ==============================
  $(document).on('click', '.withdraw-player-btn', function () {
    const btn = $(this);
    const id = btn.data('id');
    const categoryEvent = btn.data('categoryevent');
    const url = `${APP_URL}/backend/registration/delete`;
    const player_name = btn.data('name');

    Swal.fire({ title: 'Withdraw player?', text: 'Are you sure you want to withdraw this player from the event?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, withdraw', cancelButtonText: 'Cancel' })
      .then((result) => {
        if (!result.isConfirmed) return;
        $.ajax({ url: url, type: 'POST', data: { _token: CSRF, id, categoryEvent } })
          .done(function (response) {
            btn.closest('tr').fadeOut(400, function () { $(this).remove(); });
            const withdrawTable = $('#navs-justified-link-preparing tbody').first();
            withdrawTable.append(`<tr><td><strong>#</strong></td><td>${player_name}</td><td colspan="3"><span class="badge bg-warning">Withdrawn</span></td></tr>`);
            toastr.success(`Player ${player_name} withdrawn successfully.`);
            Swal.fire({ icon: 'success', title: 'Withdrawal successful', text: `${player_name} has been withdrawn.`, timer: 2000, showConfirmButton: false });
          })
          .fail(function (xhr) { console.error('❌ Withdraw error:', xhr); toastr.error(xhr.responseJSON?.message || 'Error withdrawing player'); });
      });
  });
  $(document).on('click', '.withdrawButton', function () {
    const btn = $(this);

    const registrationId = btn.data('registrationid');
    const categoryEventId = btn.data('categoryeventid');
    const playerName = btn.data('player');

    const url = `${APP_URL}/backend/registration/delete`;

    Swal.fire({
      title: 'Withdraw player?',
      text: `Are you sure you want to withdraw ${playerName}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, withdraw',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (!result.isConfirmed) return;

      $.ajax({
        url: url,
        type: 'POST',
        data: {
          _token: CSRF,
          id: registrationId,
          categoryEvent: categoryEventId
        }
      })
        .done((response) => {

          // Remove from Confirmed tab
          btn.closest('tr').fadeOut(400, function () {
            $(this).remove();
          });

          // Add to Withdrawals tab
          const tableBody = $(`#navs-justified-link-preparing tbody`)
            .eq(btn.closest('.card').index());

          tableBody.append(`
                    <tr>
                        <td><strong>#</strong></td>
                        <td>${playerName}</td>
                        <td colspan="3">
                            <span class="badge bg-warning">Withdrawn</span>
                        </td>
                    </tr>
                `);

          toastr.success(`${playerName} withdrawn successfully.`);

          Swal.fire({
            icon: 'success',
            title: 'Player withdrawn',
            text: `${playerName} has been moved to withdrawals.`,
            timer: 2000,
            showConfirmButton: false
          });
        })
        .fail((xhr) => {
          console.error('❌ Withdrawal error:', xhr);
          toastr.error(xhr.responseJSON?.message || 'Error withdrawing player.');
        });
    });
  });

  // ==============================
  // Edit NoProfile Player
  // ==============================
  $(document).on('click', '.edit-noprofile-btn', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const surname = $(this).data('surname');
    $('#noProfileId').val(id);
    $('#noProfileName').val(name);
    $('#noProfileSurname').val(surname);
    $('#editNoProfileModal').modal('show');
  });

  $('#editNoProfileForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#noProfileId').val();
    const name = $('#noProfileName').val().trim();
    const surname = $('#noProfileSurname').val().trim();
    if (!name || !surname) { toastr.warning('Please enter both name and surname'); return; }
    $.ajax({ url: `${APP_URL}/backend/team/noprofile/update/${id}`, type: 'PATCH', data: { name, surname, _token: CSRF } })
      .done(function (res) {
        if (res.success) {
          toastr.success('Dummy player updated');
          const $btn = $(`.edit-noprofile-btn[data-id="${id}"]`);
          const $cell = $btn.closest('td');
          $cell.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith(`${name} ${surname} `);
          $btn.data('name', name).data('surname', surname);
          $('#editNoProfileModal').modal('hide');
        } else toastr.error(res.message || 'Update failed');
      })
      .fail(function (xhr) { console.error('❌ AJAX update failed', xhr); toastr.error('Could not update dummy player'); });
  });

  // ==============================
  // Misc (close buttons refresh etc.)
  // ==============================
  $('.btn-close').on('click', () => location.reload());
  // ==============================
  // Legacy helper – changeRecipants() for email modals
  // ==============================
  window.changeRecipants = function (type, id) {
    console.group('%c[changeRecipants] called', 'color:#28a745; font-weight:bold;');
    console.log('Type:', type);
    console.log('ID:', id);
    console.groupEnd();

    // Store the type & ID for later
    $('#target_type').val(type);
    $('#event_id').val(id);

    // Optional: open modal immediately
    if (type === 'event') {
      $('#emailRecipientSelect').val('All players in Event').trigger('change');
    } else if (type === 'category') {
      $('#emailRecipientSelect').val('All players in Category').trigger('change');
    }

    // Show modal if it exists
    const $modal = $('#sendMailModal, #createEmail');
    if ($modal.length) {
      console.log('Opening email modal...');
      $modal.modal('show');
    } else {
      console.warn('Email modal not found.');
    }
  };

})(jQuery, window, document);
