/*
 * Admin — Regions & Teams JS
 */

(function ($, window, document) {
  'use strict';

  console.log('📍 regions.js loaded');

  function logXhrFail(label, xhr) {
    console.group(`❌ ${label}`);
    console.log('status:', xhr.status);
    console.log('responseText:', xhr.responseText);
    console.log('responseJSON:', xhr.responseJSON);
    console.groupEnd();
  }

  const APP_URL = window.APP_URL || window.location.origin;
  const CSRF = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  });

  const api = {
    addRegionToEvent: `${APP_URL}/backend/eventRegion`  // ✅ Use direct URL
  };

  // Provide import URL (matches routes/web.php)
window.importNoProfileUrl = window.importNoProfileUrl || null;
  let bulkTeamImportUrl = null;

  // Timer state (optional small timer)
  let importTimerInterval = null;
  let importStartTime = null;
  function formatElapsed(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');
    return `${minutes}:${seconds}`;
  }
  function startImportTimer() {
    importStartTime = Date.now();
    $('#import-timer').text('00:00');
    importTimerInterval = setInterval(() => {
      const elapsed = Date.now() - importStartTime;
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
    const $select = $('#select2Region');
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
      createTag: function (params) {
        const term = $.trim(params.term);
        if (term === '' || term.length < 2) return null;
        const existingOption = $select.find(`option:contains("${term}")`).length > 0;
        if (existingOption) return null;
        return { id: term, text: term, isNew: true };
      },
      templateResult: function (data) {
        if (data.isNew) {
          return $('<span style="color: #28a745; font-weight: bold;">✨ Create: "' + data.text + '"</span>');
        }
        return data.text;
      },
      templateSelection: function (data) { return data.isNew ? data.text : data.text; }
    });

    $select.on('change', () => { console.log('📍 Region selected:', $select.val()); });
  }

  $('#modalToggle').on('shown.bs.modal', initRegionSelect2);

  // ===============================
  // Add Region to Event
  // ===============================
  $(document).on('click', '#addRegionToEventButton', function () {
    const eventId = $('input[name="event_id"]').val();
    const regionId = $('#select2Region').val();

    console.log('➕ Add region clicked', { eventId, regionId });

    if (!eventId || !regionId) {
      toastr.error('Please select a region');
      return;
    }

    const $button = $(this);
    $button.prop('disabled', true);

    $.post(api.addRegionToEvent, { event_id: eventId, region_id: regionId })
      .done(res => {
        console.log('✅ Region added', res);
        console.log('Response keys:', Object.keys(res));
        console.log('ID:', res.id);
        console.log('Region Name:', res.region_name);
        console.log('Pivot ID:', res.pivot_id);

        $('.noRegions').remove();

        const html = `
    <div class="accordion-item mb-2 border rounded"
         data-region-row
         data-region-id="${res.id}"
         data-pivot-id="${res.pivot_id}">

      <h2 class="accordion-header" id="heading-${res.id}">
        <button class="accordion-button collapsed fw-semibold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse-${res.id}">
          <span class="badge bg-label-secondary me-2">#${res.id}</span>
          ${res.region_name}
          <span class="ms-2 text-muted small">(0 Teams)</span>
        </button>
      </h2>

      <div id="collapse-${res.id}" class="accordion-collapse collapse"
           data-bs-parent="#regionsAccordion">
        <div class="accordion-body pt-2">

          <div class="d-flex justify-content-end mb-2 gap-2">
            <a href="javascript:void(0)"
               class="text-danger removeRegionEvent"
               data-id="${res.pivot_id}">
              <i class="ti ti-trash me-1"></i> Remove Region
            </a>

            <button type="button"
                    class="btn btn-sm btn-success publishRegionTeams"
                    data-url="${APP_URL}/backend/event/${eventId}/region/${res.id}/teams/publish"
                    data-team-count="0"
                    data-unpublished-count="0"
                    disabled>
              <i class="ti ti-eye me-1"></i> Publish All Teams
            </button>

            <a href="javascript:void(0)"
               class="btn btn-sm btn-outline-primary import-region-teams-btn"
               data-region-name="${escapeHtml(res.region_name)}"
               data-team-prefix="${escapeHtml(res.region_name)}"
               data-import-url="${APP_URL}/backend/event/${eventId}/region/${res.id}/external-teams/import"
               data-bs-toggle="modal"
               data-bs-target="#import-region-teams-modal">
              <i class="ti ti-file-spreadsheet me-1"></i> Import Teams
            </a>

            <a href="javascript:void(0)"
               class="btn btn-sm btn-primary addTeam"
               data-regionid="${res.id}"
               data-bs-toggle="modal"
               data-bs-target="#addTeamModal">
              <i class="ti ti-plus me-1"></i> Add Team
            </a>
          </div>

          <div class="teams-container">
            <div class="alert alert-light border text-center py-2 no-teams-alert">
              No teams in this region yet.
            </div>
          </div>

        </div>
      </div>
    </div>
  `;

        // Remove the "no regions" alert if present, then prepend new region
        $('#regionsAccordion .noRegions').remove();
        $('#regionsAccordion').prepend(html);

        bootstrap.Modal
          .getInstance(document.getElementById('modalToggle'))
          ?.hide();

        toastr.success('Region added');
      })
      .fail(xhr => {
        console.error('❌ AJAX Error:');
        console.error('Status:', xhr.status);
        console.error('Response:', xhr.responseText);
        console.error('JSON:', xhr.responseJSON);

        logXhrFail('Add region failed', xhr);
        toastr.error(xhr.responseJSON?.message || 'Failed to add region');
      })
      .always(() => {
        $button.prop('disabled', false);
      });
  });

  // ===============================
  // Remove Region
  // ===============================
  $(document).on('click', '.removeRegionEvent', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const pivotId = $btn.data('id');
    const $row = $btn.closest('[data-region-row]');

    console.log('🗑 removeRegionEvent', { pivotId });

    Swal.fire({
      title: 'Remove region?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Remove'
    }).then(r => {
      if (!r.isConfirmed) return;

      $.ajax({
        url: `${APP_URL}/backend/eventRegion/${pivotId}`,
        method: 'DELETE',
        data: { _token: CSRF }
      })
        .done(() => {
          toastr.success('Region removed');
          $row.fadeOut(200, () => $row.remove());
        })
        .fail(xhr => {
          logXhrFail('Remove region failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Failed to remove region');
        });
    });
  });

  // ===============================
  // Publish / Unpublish Team
  // ===============================
  function renderTeamPublicationButton($btn, published) {
    const state = published ? '1' : '0';
    $btn.data('state', state)
      .attr('data-state', state)
      .toggleClass('btn-warning', published)
      .toggleClass('btn-success', !published)
      .html(published
        ? '<i class="ti ti-eye-off me-1"></i> Unpublish Team'
        : '<i class="ti ti-eye me-1"></i> Publish Team');
  }

  function syncRegionPublicationButton($regionRow) {
    const $bulkBtn = $regionRow.find('.publishRegionTeams').first();
    if (!$bulkBtn.length) return;

    const $teamButtons = $regionRow.find('.publishTeam');
    const unpublishedCount = $teamButtons.filter(function () {
      return String($(this).data('state')) !== '1';
    }).length;

    $bulkBtn
      .data('team-count', $teamButtons.length)
      .attr('data-team-count', $teamButtons.length)
      .data('unpublished-count', unpublishedCount)
      .attr('data-unpublished-count', unpublishedCount)
      .prop('disabled', $teamButtons.length === 0 || unpublishedCount === 0)
      .html(unpublishedCount === 0 && $teamButtons.length > 0
        ? '<i class="ti ti-check me-1"></i> All Teams Published'
        : '<i class="ti ti-eye me-1"></i> Publish All Teams');
  }

  $(document).on('click', '.publishTeam', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const teamId = $btn.data('id');
    const state = String($btn.data('state'));

    console.log('📣 publishTeam', { teamId, state });

    const action = state === '1' ? 'Unpublish' : 'Publish';
    const targetState = state !== '1';

    Swal.fire({
      title: `${action} team?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(r => {
      if (!r.isConfirmed) return;

      $btn.prop('disabled', true);

      $.post($btn.data('url') || `${APP_URL}/backend/team/publishTeam/${teamId}`, {
        _token: CSRF,
        published: targetState ? 1 : 0
      })
        .done(res => {
          renderTeamPublicationButton($btn, !!res.published);
          syncRegionPublicationButton($btn.closest('[data-region-row]'));
          toastr.success(res.message || `Team ${action.toLowerCase()}ed.`);
        })
        .fail(xhr => {
          logXhrFail('Publish update failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Could not update the team publication status.');
        })
        .always(() => $btn.prop('disabled', false));
    });
  });

  // ===============================
  // Publish every team in one event region
  // ===============================
  $(document).on('click', '.publishRegionTeams', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const $regionRow = $btn.closest('[data-region-row]');
    const unpublishedCount = Number($btn.data('unpublished-count')) || 0;

    if ($btn.prop('disabled') || unpublishedCount === 0) return;

    Swal.fire({
      title: 'Publish all teams in this region?',
      text: `${unpublishedCount} unpublished ${unpublishedCount === 1 ? 'team' : 'teams'} will become visible.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Publish all'
    }).then(r => {
      if (!r.isConfirmed) return;

      $btn.prop('disabled', true);

      $.post($btn.data('url'), { _token: CSRF })
        .done(res => {
          $regionRow.find('.publishTeam').each(function () {
            renderTeamPublicationButton($(this), true);
          });
          syncRegionPublicationButton($regionRow);
          toastr.success(res.message || 'All teams in this region are published.');
        })
        .fail(xhr => {
          logXhrFail('Publish region teams failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Could not publish the teams in this region.');
          syncRegionPublicationButton($regionRow);
        });
    });
  });

  // ===============================
  // Toggle NoProfile
  // ===============================
  $(document).on('click', '.toggleNoProfile', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const url = $btn.data('url');
    const state = String($btn.data('state'));

    console.log('🟡 toggleNoProfile', { url, state });

    const action = state === '1' ? 'Disable' : 'Enable';

    Swal.fire({
      title: `${action} NoProfile?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(r => {
      if (!r.isConfirmed) return;

      $.ajax({ url, method: 'PATCH', data: { _token: CSRF } })
        .done(() => {
          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          $btn.toggleClass('btn-danger btn-success')
            .html(newState === '1'
              ? '<i class="ti ti-user-off me-1"></i> Disable NoProfile'
              : '<i class="ti ti-user me-1"></i> Enable NoProfile');


          toastr.success(`NoProfile ${action.toLowerCase()}d`);
        })
        .fail(xhr => logXhrFail('NoProfile toggle failed', xhr));
    });
  });

  // ===============================
  // Edit Team Category – Open Modal
  // ===============================
  let selectedTeamId = null;

  $(document).on('click', '.edit-team-category', function () {
    const teamData = $(this).data('team');
    selectedTeamId = teamData?.id || null;

    console.log('✏️ Edit category clicked', { teamId: selectedTeamId, teamData });

    // Set modal title
    $('#edit-team-category-title').text(`Edit Category: ${teamData?.name || 'Team'}`);

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

    const teamId = $('#edit-team-category-modal input[name="team"]').val();
    const categoryId = $('#edit-team-category-modal input[name="category"]:checked').val();

    console.log('💾 Save category clicked', { teamId, categoryId });

    if (!teamId || !categoryId) {
      toastr.error('Please select a category');
      return;
    }

    $.ajax({
      url: `${APP_URL}/backend/team/category/change/${teamId}`,
      method: 'POST',
      data: {
        _token: CSRF,
        team: teamId,
        data: categoryId
      }
    })
      .done(newCategoryName => {
        console.log('✅ Category updated', newCategoryName);

        // Update the category label in the team row
        $(`.category-${teamId}`).html(`
          Category: <span class="fw-semibold text-primary">${newCategoryName}</span>
        `);

        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('edit-team-category-modal'))?.hide();

        toastr.success('Category updated');
      })
      .fail(xhr => {
        logXhrFail('Change category failed', xhr);
        toastr.error('Failed to update category');
      });
  });

  // =====================================================
  // DELETE TEAM (AJAX)
  // =====================================================
  $(document).on('click', '.removeTeam', function (e) {
    e.preventDefault();

    const $row = $(this).closest('[data-team-row]');
    const teamId = $(this).data('id');

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
    }).then((r) => {
      if (!r.isConfirmed) return;

      const url = `${APP_URL}/backend/team/${teamId}`;
      console.log('➡️ DELETE', url);

      $.ajax({
        url,
        method: 'DELETE',
        data: { _token: CSRF }
      })
        .done(() => {
          toastr.success('Team deleted');
          $row.fadeOut(200, () => $row.remove());
        })
        .fail(xhr => {
          logXhrFail('Delete team failed', xhr);
          toastr.error(xhr.responseJSON?.message || 'Failed to delete team');
        });
    });
  });

  // ===============================
  // Add Team Button – Capture Region ID
  // ===============================
  $(document).on('click', '.addTeam', function () {
    // ❌ NO e.preventDefault() here!
    // Just capture and store the region ID

    const regionId = $(this).data('regionid');
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

    const teamName = $('input[name="team_name"]').val();
    const numPlayers = $('input[name="num_players"]').val();
    const year = $('input[name="year"]').val();
    const regionId = $('#region_id').val();
    const published = $('input[name="published"]').val();

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
    $.post(`${APP_URL}/backend/team`, {
      _token: CSRF,
      name: teamName,
      num_players: numPlayers,
      year: year,
      region_id: regionId,
      published: published
    })
      .done(res => {
        console.log('✅ Team created successfully');
        console.log('   Team ID:', res.id);

        // Clear form
        $('#teamForm')[0].reset();

        // Find region row's teams container
        const $regionRow = $(`[data-region-id="${regionId}"]`);
        const $teamsContainer = $regionRow.find('.teams-container');

        // Remove "no teams" alert
        $teamsContainer.find('.no-teams-alert').remove();

        const teamRowHtml = `
            <div class="list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom" data-team-row data-team-id="${res.id}">
              <div>
                <div class="fw-medium">${res.name}</div>
                <small class="text-muted d-block mb-1 category-${res.id}">
                  Category: <span class="fw-semibold text-primary">None</span>
                </small>
                <button class="btn btn-xs bg-label-info edit-team-category" data-team='${JSON.stringify({id: res.id, name: res.name})}' data-bs-toggle="modal" data-bs-target="#edit-team-category-modal">
                  <i class="ti ti-edit me-25"></i> Edit Category
                </button>
              </div>
              <div class="text-end" style="min-width:180px">
                <button type="button" class="publishTeam btn btn-xs w-100 mb-2 btn-success" data-id="${res.id}" data-url="${APP_URL}/backend/team/publishTeam/${res.id}" data-state="0">
                  <i class="ti ti-eye me-1"></i> Publish Team
                </button>
                <a href="javascript:void(0)" class="toggleNoProfile btn btn-xs w-100 mb-2 btn-info" data-url="${APP_URL}/backend/teams/toggle-noprofile/${res.id}" data-state="0">
                  <i class="ti ti-user me-1"></i> Enable NoProfile
                </a>
                <a href="javascript:void(0)" class="text-danger small removeTeam" data-id="${res.id}">
                  <i class="ti ti-trash me-25"></i> Delete
                </a>
              </div>
            </div>
          `;

        let $teamList = $teamsContainer.find('.list-group');
        if ($teamList.length === 0) {
          $teamsContainer.append('<div class="list-group"></div>');
          $teamList = $teamsContainer.find('.list-group');
        }
        $teamList.append(teamRowHtml);
        syncRegionPublicationButton($regionRow);

        // Update team count
        const headerText = $regionRow.find('.ms-2.text-muted.small').text();
        const match = headerText.match(/\d+/);
        const currentCount = match ? parseInt(match[0]) : 0;
        $regionRow.find('.ms-2.text-muted.small').text(`(${currentCount + 1} Teams)`);

        toastr.success('Team added to region');

        // Now close the modal
        bootstrap.Modal.getInstance(document.getElementById('addTeamModal'))?.hide();
      })
      .fail(xhr => {
        console.error('❌ Team creation failed');
        console.error('   Status:', xhr.status);
        console.error('   Response:', xhr);
        toastr.error('Failed to create team');
      });
  });

  // When clicking import on a team row, populate modal
  $(document).on('click', '.import-noprofile-btn', function () {
    const regionId = $(this).data('region-id');
    const teamId = $(this).data('team-id');
    const teamName = $(this).data('team-name');
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
    const form = document.getElementById('import-noprofile-form');
    const formData = new FormData(form);
    const $file = $('#import-file');
    const $btn = $('#import-submit-btn');
    const $cancel = $('#import-cancel-btn');

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
      headers: { 'X-CSRF-TOKEN': CSRF },
      success: function (response) {
        stopImportTimer();
        $('#import-spinner').hide();
        $('#import-message').text(response.message || 'Import complete');
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        if (response.requires_confirmation) {
          const rows = response.preview || [];
          $('#import-preview-body').html(rows.map(row => `
            <tr>
              <td>${row.rank}</td>
              <td>${$('<div>').text(`${row.name} ${row.surname}`).html()}</td>
              <td>${row.date_of_birth || '—'}</td>
              <td><span class="badge ${row.candidate_count ? 'bg-label-warning' : 'bg-label-secondary'}">${row.candidate_count}</span></td>
            </tr>
          `).join(''));
          $('#import-preview').removeClass('d-none');
          $('#import-confirmed').val('1');
          $btn.text(`Confirm import of ${response.row_count} players`);
          toastr.info('Review the roster, then confirm the import.');
          return;
        }

        toastr.success(response.message || 'Import finished');
        setTimeout(() => location.reload(), 700);
      },
      error: function (xhr) {
        stopImportTimer();
        $('#import-spinner').hide();
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        const payload = xhr.responseJSON || {};
        const msg = payload.message || 'Import failed. Please check the file format.';
        $('#import-message').text(msg);
        const rawErrors = payload.errors || [];
        const errors = Array.isArray(rawErrors) ? rawErrors : Object.values(rawErrors).flat();
        if (errors.length) {
          $('#import-errors').removeClass('d-none').html(`<strong>Nothing was imported.</strong><ul class="mb-0 mt-1">${errors.map(error => `<li>${$('<div>').text(error).html()}</li>`).join('')}</ul>`);
        }
        toastr.error(msg);
        console.error('Import failed', xhr);
      }
    });
  });

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function resetBulkImportPreview(clearSheets = false) {
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
    $('#bulk-import-submit, #bulk-import-cancel, #bulk-import-file, #bulk-import-prefix, #bulk-import-expected, #bulk-import-sheet, #bulk-import-fill-missing')
      .prop('disabled', busy);

    if (!busy) {
      updateBulkImportActionButton();
    }
  }

  function renderWorkbookSheets(sheets, selectedSheet) {
    const $select = $('#bulk-import-sheet');
    const currentValue = selectedSheet || $select.val() || '';
    $select.html('<option value="">Auto-detect the best worksheet</option>');

    (sheets || []).forEach(sheet => {
      const label = `${sheet.name} — ${sheet.complete_team_count}/${sheet.team_count} complete teams`;
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
      const hasWorkbook = Boolean(document.getElementById('bulk-import-file')?.files?.length);
      $('#bulk-import-submit').text('Preview teams').prop('disabled', !hasWorkbook);
      return;
    }

    const selected = $('.bulk-team-select:checked').length;
    $('#bulk-import-submit')
      .text(selected ? `Confirm import of ${selected} team${selected === 1 ? '' : 's'}` : 'Select a complete team')
      .prop('disabled', selected === 0);
  }

  function showBulkImportErrors(message, rawErrors = []) {
    const errors = [...new Set((Array.isArray(rawErrors) ? rawErrors : Object.values(rawErrors).flat())
      .filter(error => error && error !== message))];

    $('#bulk-import-errors').removeClass('d-none').html(
      `<strong>${escapeHtml(message)}</strong>` +
      (errors.length ? `<ul class="mb-0 mt-1">${errors.map(error => `<li>${escapeHtml(error)}</li>`).join('')}</ul>` : '')
    );
  }

  function renderBulkImportTeams(response) {
    const rows = (response.teams || []).map(team => {
      const validation = team.placeholder_count
        ? `<span class="badge bg-label-warning">Ready with ${team.placeholder_count} placeholder${team.placeholder_count === 1 ? '' : 's'}</span>`
        : team.errors.length
        ? `<ul class="small text-danger mb-0 ps-3">${team.errors.map(error => `<li>${escapeHtml(error)}</li>`).join('')}</ul>`
        : '<span class="badge bg-label-success">Complete</span>';
      const players = team.players.map(player =>
        `<li class="${player.is_placeholder ? 'text-warning' : ''}"><span class="text-muted">${player.rank}.</span> ${escapeHtml(player.name)} ${escapeHtml(player.surname)}${player.is_placeholder ? ' <span class="badge bg-label-warning ms-1">Placeholder</span>' : ''}</li>`
      ).join('');

      return `
        <tr>
          <td class="text-center">
            <input class="form-check-input bulk-team-select" type="checkbox"
                   name="selected_team_keys[]" value="${escapeHtml(team.key)}"
                   ${team.selectable ? 'checked' : 'disabled'}>
          </td>
          <td>
            <div class="fw-medium">${escapeHtml(team.category)}</div>
            <div class="small text-muted">Source: ${escapeHtml(team.source_heading)}</div>
          </td>
          <td>${escapeHtml(team.team_name)}</td>
          <td class="text-center">
            <details>
              <summary>${team.player_count}</summary>
              <ol class="small text-start mb-0 mt-1 ps-3">${players}</ol>
            </details>
          </td>
          <td>${escapeHtml(team.action)}</td>
          <td>${validation}</td>
        </tr>`;
    }).join('');

    $('#bulk-import-preview-body').html(rows);
    $('#bulk-import-summary').text(
      `${response.selected_sheet}: ${response.complete_team_count} teams ready, ${response.complete_player_count} roster slots` +
      (response.placeholder_player_count ? `, including ${response.placeholder_player_count} placeholder${response.placeholder_player_count === 1 ? '' : 's'}.` : '.')
    );
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
    bulkTeamImportUrl = null;
    $('#bulk-team-import-form')[0]?.reset();
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
    const form = document.getElementById('bulk-team-import-form');

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
    const formData = new FormData(form);
    setBulkImportBusy(true);
    $('#bulk-import-errors').addClass('d-none').empty();

    $.ajax({
      url: bulkTeamImportUrl,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      success: function (response) {
        renderWorkbookSheets(response.sheets, response.selected_sheet);
        if (response.requires_confirmation) {
          renderBulkImportTeams(response);
          toastr.info(response.message);
          return;
        }

        toastr.success(response.message || 'Teams imported.');
        setTimeout(() => location.reload(), 700);
      },
      error: function (xhr) {
        const payload = xhr.responseJSON || {};
        renderWorkbookSheets(payload.sheets, null);
        const message = payload.message || 'Import failed. Nothing was imported.';
        showBulkImportErrors(message, payload.errors || []);
        toastr.error(message);
      },
      complete: function () {
        setBulkImportBusy(false);
        updateBulkImportActionButton();
      }
    });
  });

  // ===============================
  // Extra confirm dialog for leave
  // ===============================
  window.addEventListener('beforeunload', function (e) {
    const confirmationMessage = 'You have unsaved changes. Are you sure you want to leave?';
    e.returnValue = confirmationMessage; // Gecko + WebKit browsers
    return confirmationMessage;         // Gecko + WebKit browsers
  });
})(jQuery, window, document);
