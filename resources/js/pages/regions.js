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
  window.importNoProfileUrl = `${APP_URL}/backend/team/import/action`;
  console.log('🟢 Import URL:', window.importNoProfileUrl);

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

            <a href="javascript:void(0)"
               class="btn btn-sm btn-primary addTeam"
               data-regionid="${res.id}"
               data-bs-toggle="modal"
               data-bs-target="#addTeamModal">
              <i class="ti ti-plus me-1"></i> Add Team
            </a>
          </div>

          <div class="alert alert-light border text-center py-2">
            No teams in this region yet.
          </div>

        </div>
      </div>
    </div>
  `;

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
        toastr.error('Failed to add region');
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
          toastr.error('Failed to remove region');
        });
    });
  });

  // ===============================
  // Publish / Unpublish Team
  // ===============================
  $(document).on('click', '.publishTeam', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const teamId = $btn.data('id');
    const state = String($btn.data('state'));

    console.log('📣 publishTeam', { teamId, state });

    const action = state === '1' ? 'Unpublish' : 'Publish';

    Swal.fire({
      title: `${action} team?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: action
    }).then(r => {
      if (!r.isConfirmed) return;

      $.post(`${APP_URL}/backend/team/publishTeam/${teamId}`, { _token: CSRF })
        .done(() => {
          const newState = state === '1' ? '0' : '1';
          $btn.data('state', newState);

          $btn.toggleClass('btn-success btn-warning')
            .html(newState === '1'
              ? '<i class="ti ti-eye-off me-1"></i> Unpublish'
              : '<i class="ti ti-eye me-1"></i> Publish');

          toastr.success(`Team ${action.toLowerCase()}ed`);
        })
        .fail(xhr => logXhrFail('Publish toggle failed', xhr));
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

        // Find region row and add team to it
        const $regionRow = $(`[data-region-id="${regionId}"]`);
        const $teamList = $regionRow.find('.list-group');

        if ($teamList.length === 0) {
          // Remove "No teams" alert and create list
          $regionRow.find('.alert-light').remove();

          const teamListHtml = `
            <div class="list-group">
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
                  <a href="javascript:void(0)" class="publishTeam btn btn-xs w-100 mb-2 btn-success" data-id="${res.id}" data-state="0">
                    <i class="ti ti-eye me-1"></i> Publish Team
                  </a>
                  <a href="javascript:void(0)" class="toggleNoProfile btn btn-xs w-100 mb-2 btn-info" data-url="${APP_URL}/backend/teams/toggle-noprofile/${res.id}" data-state="0">
                    <i class="ti ti-user me-1"></i> Enable NoProfile
                  </a>
                  <a href="javascript:void(0)" class="text-danger small removeTeam" data-id="${res.id}">
                    <i class="ti ti-trash me-25"></i> Delete
                  </a>
                </div>
              </div>
            </div>
          `;
          
          $regionRow.find('.accordion-body').html(teamListHtml);
        } else {
          // Add to existing team list
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
                <a href="javascript:void(0)" class="publishTeam btn btn-xs w-100 mb-2 btn-success" data-id="${res.id}" data-state="0">
                  <i class="ti ti-eye me-1"></i> Publish Team
                </a>
                <a href="javascript:void(0)" class="toggleNoProfile btn btn-xs w-100 mb-2 btn-info" data-url="${APP_URL}/backend/teams/toggle-noprofile/${res.id}" data-state="0">
                  <i class="ti ti-user me-1"></i> Enable NoProfile
                </a>
                <a href="javascript:void(0)" class="text-danger small removeTeam" data-id="${res.id}">
                  <i class="ti ti-trash me-25"></i> Delete
                </a>
              </div>
            </div>
          `;
          
          $teamList.append(teamRowHtml);
        }

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
        toastr.success(response.message || 'Import finished');
        // hide modal after short delay
        setTimeout(() => {
          bootstrap.Modal.getInstance(document.getElementById('import-noprofile-modal'))?.hide();
          location.reload();
        }, 900);
      },
      error: function (xhr) {
        stopImportTimer();
        $('#import-spinner').hide();
        $btn.prop('disabled', false);
        $file.prop('disabled', false);
        $cancel.prop('disabled', false);
        const msg = xhr.responseJSON?.message || 'Import failed. Please check the file format.';
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
    const confirmationMessage = 'You have unsaved changes. Are you sure you want to leave?';
    e.returnValue = confirmationMessage; // Gecko + WebKit browsers
    return confirmationMessage;         // Gecko + WebKit browsers
  });
})(jQuery, window, document);









