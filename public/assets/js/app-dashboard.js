/**
 * Dashboard Page Script
 * Handles user table, player table, event table, series table, invoice table,
 * and includes AJAX edit modals for user and player management.
 */

'use strict';

$(function () {
  console.log('✅ Dashboard script loaded');

  const CSRF = $('meta[name="csrf-token"]').attr('content');
  const userId = $('#user').val();
  const url = APP_URL + '/backend/player';

  // ==========================================================
  // 🟦 USER EVENTS TABLE
  // ==========================================================
  const dt_project_table = $('.datatable-events');
  if (dt_project_table.length) {
    const dt_project = dt_project_table.DataTable({
      ordering: false,
      paging: false,
      ajax: APP_URL + '/events/ajax/userEvents/' + userId,
      columns: [
        { data: 'name' },
        { data: 'start_date' },
        { data: 'entryFee' },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 0,
          render: (data, type, full) => {
            const link = `${APP_URL}/events/${full.id}`;
            const label = `<a href="${link}" class="btn btn-warning btn-sm text-white">${full.name}</a>`;

            // ✅ FIX: use start_date (snake_case) + defensive check
            const eventDate = full.start_date ? new Date(full.start_date) : null;
            const isUpcoming =
              eventDate && eventDate > new Date()
                ? '<span class="badge rounded-pill bg-label-success ms-1">Upcoming</span>'
                : '';

            return label + isUpcoming;
          },
        },
        {
          targets: 2,
          render: (data, type, full) => 'R' + full.entryFee,
        },
        {
          targets: 3,
          render: (data, type, full) => full.registrations.length,
        },
        {
          targets: 4,
          render: (data, type, full) =>
            `<a href="${APP_URL}/backend/eventAdmin/${full.id}" class="btn btn-sm btn-secondary">Admin Page</a>`,
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 PLAYERS TABLE
  // ==========================================================
  const dt_players_table = $('.datatable-players');
  if (dt_players_table.length) {
    const dt_players = dt_players_table.DataTable({
      ordering: false,
      paging: false,
      ajax: url,
      columns: [
        { data: 'id' },
        { data: 'full_name' },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 2,
          render: (data, type, full) => {
            const profile = `${APP_URL}/backend/player/profile/${full.id}`;
            return `<a href="${profile}" class="btn btn-primary btn-sm">Profile</a>`;
          },
        },
        {
          targets: 3,
          render: (data, type, full) => {
            const results = `${APP_URL}/backend/player/results/${full.id}`;
            return `<a href="${results}" class="btn btn-secondary btn-sm">Results</a>`;
          },
        },
        {
          targets: 4,
          render: (data, type, full) => {
            const details = `${APP_URL}/backend/player/details/${full.id}`;
            return `<a href="${details}" class="btn btn-info btn-sm">Details</a>`;
          },
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 USER EVENTS TABLE
  // ==========================================================
  const dt_project_table = $('.datatable-events');
  if (dt_project_table.length) {
    const dt_project = dt_project_table.DataTable({
      ordering: false,
      paging: false,
      ajax: APP_URL + '/events/ajax/userEvents/' + userId,
      columns: [
        { data: 'name' },
        { data: 'start_date' },
        { data: 'entryFee' },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 0,
          render: (data, type, full) => {
            const link = `${APP_URL}/events/${full.id}`;
            const label = `<a href="${link}" class="btn btn-warning btn-sm text-white">${full.name}</a>`;
            const isUpcoming = new Date(full.start_date) > new Date()
              ? '<span class="badge rounded-pill bg-label-success">Upcoming</span>'
              : '';
            return label + ' ' + isUpcoming;
          },
        },
        {
          targets: 2,
          render: (data, type, full) => 'R' + full.entryFee,
        },
        {
          targets: 3,
          render: (data, type, full) => full.registrations.length,
        },
        {
          targets: 4,
          render: (data, type, full) => `<a href="${APP_URL}/backend/eventAdmin/${full.id}" class="btn btn-sm btn-secondary">Admin Page</a>`,
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 SERIES TABLE
  // ==========================================================
  const dt_series = $('.datatable-series');
  if (dt_series.length) {
    const dt_series_table = dt_series.DataTable({
      ordering: false,
      paging: false,
      ajax: APP_URL + '/events/ajax/series',
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 2,
          render: (data, type, full) =>
            `<a href="${APP_URL}/backend/ranking/settings/${full.id}" class="btn btn-sm btn-warning">Settings</a>`,
        },
        {
          targets: 3,
          render: (data, type, full) => {
            const btnClass = full.leaderboard_published ? 'btn-success' : 'btn-danger';
            const text = full.leaderboard_published ? 'Published' : 'Not Published';
            return `<div data-id="${full.id}" class="btn ${btnClass} btn-sm publishLeaderboard">${text}</div>`;
          },
        },
        {
          targets: 4,
          render: (data, type, full) =>
            `<a href="${APP_URL}/backend/ranking/${full.id}" class="btn btn-sm btn-secondary">Show</a>`,
        },
      ],
      initComplete: function () {
        $('.publishLeaderboard').on('click', function () {
          const id = $(this).data('id');
          const $btn = $(this);
          $.get(`${APP_URL}/backend/series/publishLeaderboard/${id}`, (data) => {
            if (data.leaderboard_published == 1) {
              $btn.removeClass('btn-danger').addClass('btn-success').text('Published');
            } else {
              $btn.removeClass('btn-success').addClass('btn-danger').text('Not Published');
            }
          });
        });
      },
    });
  }

  // ==========================================================
  // 🟦 PLAYER EDIT MODAL (AJAX)
  // ==========================================================
  $(document).ready(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const BASE_URL = APP_URL + '/backend/player';

    // 🟢 Fill modal helper (called from Blade button)
    window.fillModal = function (email, id, name, surname, cell = '') {
      console.group('🎾 [fillModal]');
      console.log('Player ID:', id);
      console.log('Name:', name);
      console.log('Surname:', surname);
      console.log('Email:', email);
      console.groupEnd();

      $('#player-id').val(id);
      $('#player-name').val(name);
      $('#player-surname').val(surname);
      $('#player-email').val(email);
      $('#player-cell').val(cell);
    };

    // 🟢 Handle form submit
    $(document).ready(function () {
      const CSRF = $('meta[name="csrf-token"]').attr('content');
      const BASE_URL = APP_URL + '/backend/player';

      // ==========================================================
      // 🟩 FILL MODAL HELPER
      // ==========================================================
      window.fillModal = function (email, id, name, surname, cell = '') {
        console.group('🎾 [fillModal]');
        console.log('Player ID:', id);
        console.log('Name:', name);
        console.log('Surname:', surname);
        console.log('Email:', email);
        console.groupEnd();

        $('#player-id').val(id);
        $('#player-name').val(name);
        $('#player-surname').val(surname);
        $('#player-email').val(email);
        $('#player-cell').val(cell);
      };

      // ==========================================================
      // 🟩 EDIT PLAYER FORM SUBMIT (AJAX)
      // ==========================================================
      $(document).ready(function () {
        const CSRF = $('meta[name="csrf-token"]').attr('content');
        const BASE_URL = APP_URL + '/backend/player';

        // ==========================================================
        // 🟢 Fill modal helper (called from Blade button)
        // ==========================================================
        window.fillModal = function (email, id, name, surname, cell = '') {
          $('#player-id').val(id);
          $('#player-name').val(name);
          $('#player-surname').val(surname);
          $('#player-email').val(email);
          $('#player-cell').val(cell);
        };

        // ==========================================================
        // 🟢 Handle Edit Player Submit
        // ==========================================================
        $(document).on('submit', '#playerEditForm', function (e) {
          e.preventDefault();

          const id = $('#player-id').val();
          const formData = $(this).serialize();
          const endpoint = `${BASE_URL}/update/${id}`;

          Swal.fire({
            title: 'Saving player...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
          });

          $.ajax({
            url: endpoint,
            type: 'POST',
            data: formData,
            headers: { 'X-CSRF-TOKEN': CSRF },
            success: function (response) {
              Swal.close();

              if (!response || !response.success) {
                toastr.warning('Player saved, but unexpected response.');
                return;
              }

              toastr.success(response.message || 'Player updated successfully.');
              $('#playerEditModal').modal('hide');

              const p = response.player;

              // ==========================================================
              // 🟩 Try to update sidebar live
              // ==========================================================
              const $li = $(`.removeProfileButton[data-id="${p.id}"]`).closest('li');

              if ($li.length) {
                console.log('✅ Updating player list item:', p);
                $li.find('a.btn-outline-primary')
                  .text(`${p.name} ${p.surname}`);
                $li.find('.text-muted.small')
                  .text(p.email || '');

                // Animation feedback
                $li.addClass('bg-success bg-opacity-25 animate__animated animate__flash');
                setTimeout(() => {
                  $li.removeClass('bg-success bg-opacity-25 animate__animated animate__flash');
                }, 1000);
              } else {
                console.warn('⚠️ Player element not found, reloading.');
                // fallback if player is not visible or dynamically rendered
                setTimeout(() => location.reload(), 800);
              }
            },
            error: function (xhr) {
              Swal.close();
              console.error('❌ AJAX Error:', xhr);
              const msg = xhr.responseJSON?.message || 'Failed to update player.';
              toastr.error(msg);
            },
          });
        });
      });

    });

  });


  // ==========================================================
  // 🟦 EDIT USER MODAL (AJAX)
  // ==========================================================
  console.log($('#editUser'))
  // ==========================================================
  // 🟦 EDIT USER MODAL (AJAX) — WITH DEBUG LOGGING
  // ==========================================================
  $(document).on('submit', '#editUserForm', function (e) {
    console.log('🟢 [editUserForm] SUBMIT triggered');
    e.preventDefault();
    e.stopPropagation();

    const form = $(this);
    const data = form.serialize();
    const endpoint = APP_URL + '/backend/user/update';
    const CSRF = $('meta[name="csrf-token"]').attr('content');

    console.group('🧩 Edit User Debug');
    console.log('📤 Form serialized data:', data);
    console.log('🌐 Endpoint:', endpoint);
    console.log('🧾 CSRF Token:', CSRF);
    console.groupEnd();

    $.ajax({
      type: 'POST',
      url: endpoint,
      data: data,
      headers: { 'X-CSRF-TOKEN': CSRF },
      beforeSend: () => {
        console.log('⏳ [AJAX] Request sending...');
        Swal.fire({
          title: 'Saving...',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading(),
        });
      },
      success: (response) => {
        console.group('✅ [AJAX SUCCESS]');
        console.log('🔹 Response object:', response);
        console.log('🔹 Message:', response.message);
        console.log('🔹 Updated user:', response.user);
        console.groupEnd();

        Swal.close();
        toastr.success(response.message || 'Profile updated successfully');
        $('#editUser').modal('hide');

        // 🔁 Update visible sidebar info (smooth fade effect)
        if (response.user) {
          console.log('🔄 Updating sidebar info...');
          const u = response.user;

          const nameEl = $('.card-body h4.mb-0');
          const emailEl = $('.card-body small.text-muted');
          const detailsEl = $('.list-unstyled');

          // Animate update with fade for better UX
          nameEl.fadeOut(200, function () {
            $(this).text(`${u.userName || ''} ${u.userSurname || ''}`).fadeIn(200);
          });
          emailEl.fadeOut(200, function () {
            $(this).text(u.email || '').fadeIn(200);
          });

          // Update details list
          detailsEl.find('li:contains("Name:")').html(`<span class="fw-semibold me-1">Name:</span> ${u.userName || '-'}`);
          detailsEl.find('li:contains("Surname:")').html(`<span class="fw-semibold me-1">Surname:</span> ${u.userSurname || '-'}`);
          detailsEl.find('li:contains("Email:")').html(`<span class="fw-semibold me-1">Email:</span> ${u.email || '-'}`);
          detailsEl.find('li:contains("Contact:")').html(`<span class="fw-semibold me-1">Contact:</span> ${u.cell_nr || '-'}`);

          // ✅ Highlight change visually
          detailsEl.find('li').addClass('bg-success bg-opacity-25');
          setTimeout(() => detailsEl.find('li').removeClass('bg-success bg-opacity-25'), 800);
        }
      },
      error: (xhr) => {
        console.group('❌ [AJAX ERROR]');
        console.error('🔹 Status:', xhr.status);
        console.error('🔹 Response Text:', xhr.responseText);
        console.error('🔹 Parsed JSON:', xhr.responseJSON);
        console.groupEnd();

        Swal.close();
        const msg = xhr?.responseJSON?.message || 'Failed to update profile';
        toastr.error(msg);
      },
    });
  });

  // ==========================================================
  // 🟦 DATATABLE RESPONSIVE FIX
  // ==========================================================
  setTimeout(() => {
    $('.dataTables_filter .form-control').removeClass('form-control-sm');
    $('.dataTables_length .form-select').removeClass('form-select-sm');
  }, 300);
});
