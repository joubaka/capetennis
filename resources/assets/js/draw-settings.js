$(function () {
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const tabKey = 'activeDrawTab';
  // Show draw with hidden names initially

  const setLoading = (btn, loading = true) => {
    if (loading) {
      btn
        .prop('disabled', true)
        .append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
    } else {
      btn.prop('disabled', false).find('.spinner-border').remove();
    }
  };

  // Restore last selected tab
  const storedTab = localStorage.getItem(tabKey);
  if (storedTab) {
    const triggerEl = document.querySelector(`#settingsTabs button[data-bs-target='${storedTab}']`);
    if (triggerEl) new bootstrap.Tab(triggerEl).show();
  }

  // Store active tab on change
  $('#settingsTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
    localStorage.setItem(tabKey, $(event.target).data('bs-target'));
  });

  // Add selected players to draw
  $('#add-any-player').on('click', function () {
    const drawId = $(this).data('draw-id');
    const selected = $('#add-player-select').val();
    const btn = $(this);

    if (!selected || !selected.length) {
      alert('Please select at least one player.');
      return;
    }

    setLoading(btn, true);

    $.ajax({
      url: APP_URL + '/backend/admin/draws/add-player',
      method: 'POST',
      contentType: 'application/json',
      headers: { 'X-CSRF-TOKEN': csrf },
      data: JSON.stringify({ draw_id: drawId, player_ids: selected.map(Number) }),
      success: function (data) {
        alert(data.message);
        refreshDrawPlayers(drawId);
        $('#add-player-select').val(null).trigger('change');
      },
      error: function (error) {
        console.error(error);
        alert('Error adding players.');
      },
      complete: function () {
        setLoading(btn, false);
      }
    });
  });

  // Import players from selected category
  $('#import-category-players').on('click', function () {
    const drawId = $(this).data('draw-id');
    const categoryId = $('#select-category').val();
    const btn = $(this);

    if (!categoryId) {
      alert('Please select a category.');
      return;
    }

    setLoading(btn, true);

    $.post(
      APP_URL + '/backend/admin/draws/add-category-players',
      {
        draw_id: drawId,
        category_id: categoryId,
        _token: csrf
      },
      function (data) {
        console.log('dta', data);
        refreshDrawPlayers(drawId);
      }
    ).always(function () {
      setLoading(btn, false);
    });
  });

  // Delete a single registration from draw
  $(document).on('click', '.remove-player', function () {
    const regId = $(this).data('reg-id');
    const drawId = $(this).data('draw-id');
    console.log(regId, drawId);
    if (!confirm('Remove this player from draw?')) return;

    $.ajax({
      url: APP_URL + '/backend/admin/draws/remove-player',
      method: 'post',
      headers: { 'X-CSRF-TOKEN': csrf },
      data: { registration_id: regId, draw_id: drawId },
      success: function (data) {
        console.log('remove players data', data);
        refreshDrawPlayers(drawId);
      },
      error: function (error) {
        console.log('error', error);
        alert('Failed to remove player.');
      }
    });
  });

  // Delete all registrations from draw
  $(document).on('click', '#clear-draw-players', function () {
    const drawId = $(this).data('draw-id');

    if (!confirm('Remove all players from this draw?')) return;

    const url = APP_URL + '/backend/draws/clear-all-players';

    $.ajax({
      url: url,
      method: 'GET',
      headers: { 'X-CSRF-TOKEN': csrf },
      data: { draw_id: drawId },
      success: function (data) {
        console.log('Cleared:', data);
        refreshDrawPlayers(drawId);
      },
      error: function (error) {
        console.log('Error clearing:', error);
        alert('Failed to clear players.');
      }
    });
  });

  // Enable/disable button based on selection
  $('#add-player-select').on('change', function () {
    const selected = $(this).val();
    $('#add-any-player').prop('disabled', !selected || !selected.length);
  });

  // Init Select2
  $('#add-player-select').select2({
    placeholder: 'Select players...',
    width: '100%'
  });

  function refreshDrawPlayers(drawId) {
    $.get(APP_URL + '/backend/admin/draws/' + drawId + '/players', function (data) {
      console.log('refresh hit', data);
      // update UI with new player list
      $('#draw-player-list').html(data); // or whatever your target element is
    });
  }

  // Initialize button disabled state
  $('#add-any-player').prop('disabled', true);

  // Load format-dependent fields dynamically
  $('#draw_format_id').on('change', function () {
    const formatId = $(this).val();
    const $fieldContainer = $('#format-dependent-fields');

    if (!formatId) {
      $fieldContainer.empty();
      return;
    }

    $.get(APP_URL + '/admin/draws/format-options/' + formatId, function (data) {
      $fieldContainer.empty();

      const $settingsData = $('#draw-settings-data');
      const boxes = $settingsData.data('boxes');
      const playoff = $settingsData.data('playoff');
      const sets = $settingsData.data('sets');

      // BOXES
      if (data.supports_boxes) {
        $fieldContainer.append(`
      <div class="col-md-6 mb-3">
        <label for="boxes" class="form-label">Number of Boxes</label>
        <input type="number" class="form-control" name="boxes" id="boxes" value="${boxes ?? data.default_boxes ?? ''}">
      </div>
    `);
      } else {
        // Clear box preview if format doesn't support boxes
        $('#box-preview').html('');
      }

      // PLAYOFF SIZE
      if (data.supports_playoff) {
        $fieldContainer.append(`
      <div class="col-md-6 mb-3">
        <label for="playoff_size" class="form-label">Playoff Size</label>
        <input type="number" class="form-control" name="playoff_size" value="${
          playoff ?? data.default_playoff_size ?? ''
        }">
      </div>
    `);
      }

      // NUMBER OF SETS (always show)
      $fieldContainer.append(`
    <div class="col-md-6 mb-3">
      <label for="num_sets" class="form-label">Number of Sets</label>
      <input type="number" class="form-control" name="num_sets" value="${sets ?? data.default_num_sets ?? ''}">
    </div>
  `);
    });
  });

  // If format is already selected, trigger on page load
  if ($('#draw_format_id').val()) {
    $('#draw_format_id').trigger('change');
  }
  function refreshPreview(drawId) {
    $.get(APP_URL + '/backend/admin/draws/' + drawId + '/preview', function (html) {
      $('.draw-preview-area').html(html);
    });
  }

  $('#format-dependent-fields').on('change', '#boxes', function () {
    const drawId = $('select[name="draw_format_id"]').data('draw-id');
    const boxes = $(this).val();
    console.log('boxes', boxes);
    $.ajax({
      url: APP_URL + `/backend/admin/draws/${drawId}/split-boxes`,
      type: 'GET',
      data: { boxes: boxes },
      success: function (html) {
        console.log('html', html);

        $('.box-preview').html(html);
      },
      error: function () {
        $('.box-preview').html('<p class="text-danger">Could not load box preview.</p>');
      }
    });
  });

  const seededList = document.getElementById('seeded-player-list');

  if (seededList) {
    new Sortable(document.getElementById('seeded-player-list'), {
      animation: 150,
      onEnd: function () {
        const drawId = $('#draw_format_id').data('draw-id');
        const seedData = [];

        $('#seeded-player-list .list-group-item').each(function (index) {
          const regId = $(this).data('registration-id');
          seedData.push({ registration_id: regId, seed: index + 1 });

          // 🔁 Update seed badge
          $(this)
            .find('.seed-badge')
            .text(`Seed ${index + 1}`);
        });

        if (!seedData.length) {
          alert('No seeds to update.');
          return;
        }

        $.ajax({
          url: `${APP_URL}/backend/admin/draws/${drawId}/update-seeds`,
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf },
          contentType: 'application/json',
          data: JSON.stringify({ ordered_seeds: seedData }),
          success: function () {
            console.log('Seeds updated');
            $.post(
              `${APP_URL}/backend/admin/draws/${drawId}/assign-boxes`,
              {
                _token: csrf,
                boxes: $('#boxes').val() || 2
              },
              function () {
                refreshBoxes(drawId, $('#boxes').val());
                refreshFixtures(drawId);
              }
            );
          },
          error: function (xhr) {
            console.error(xhr.responseText);
            alert('Error updating seeds.');
          }
        });
      }
    });
  }
  $(document).on('click', '#generate-roundrobin', function () {
    const drawId = $(this).data('draw-id');
    const csrf = $('meta[name="csrf-token"]').attr('content');

    generateFixtures(drawId, csrf);
  });

  function refreshBoxes(drawId, boxCount) {
    const url = `${APP_URL}/backend/admin/draws/${drawId}/split-boxes?boxes=${boxCount}`;
    console.log(url);
    // Refresh Settings tab preview
    $.get(url, function (html) {
      console.log('html', html);
      $('.box-preview').html(html);
    });

    // Refresh Draw tab preview
    $.get(url, function (html) {
      console.log('html', html);
      $('.box-preview').html(html);
    });
  }

  function refreshFixtures(drawId) {
    $.get(`${APP_URL}/backend/admin/draws/${drawId}/preview`, function (html) {
      console.log('html', html);
      $('.fixtures-preview-area').html(html);
    });
  }
  function generateFixtures(drawId, csrf) {
    $.ajax({
      url: `${APP_URL}/backend/admin/draws/${drawId}/generate-roundrobin`,
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf
      },
      success: function (data) {
        refreshFixtures(drawId);
        location.reload(true);
      },
      error: function (xhr) {
        console.error(xhr.responseText);
        alert('Failed to generate round robin fixtures.');
      }
    });
  }

  $(document).on('click', '.set-result-btn', function () {
    const fixtureId = $(this).data('fixture-id');
    $('#fixture_id').val(fixtureId);
    $('#set_scores').val('');
    $('#setResultModal').modal('show');
  });

  $('#setResultForm').on('submit', function (e) {
    e.preventDefault();
    const fixtureId = $('#fixture_id').val();
    const scores = $('#set_scores').val();

    $.ajax({
      url: APP_URL + '/backend/admin/draws/save-result',
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      data: {
        fixture_id: fixtureId,
        scores: scores
      },
      success: function (response) {
        $('#setResultModal').modal('hide');
        alert(response.message);
        location.reload(); // or refreshFixtures(drawId)
      },
      error: function (err) {
        console.error(err);
        alert('Error saving result.');
      }
    });
  });
  $(document).on('click', '.set-result-btn', function () {
    const fixtureId = $(this).data('fixture-id');
    const player1 = $(this).data('player1');
    const player2 = $(this).data('player2');
    console.log(fixtureId, player1);
    $('#modalFixtureId').val(fixtureId);
    $('#modalPlayer1').val(player1);
    $('#modalPlayer2').val(player2);
  });

  $(document).on('click', '.set-result-btn', function () {
    const fixtureId = $(this).data('fixture-id');
    const player1 = $(this).closest('tr').find('td:nth-child(5)').text().trim();
    const player2 = $(this).closest('tr').find('td:nth-child(6)').text().trim();
    console.log(player1, player2);
    console.log(fixtureId);
    $('#modalFixtureId').val(fixtureId);
    $('#modalPlayer1').val(player1);
    $('#modalPlayer2').val(player2);

    // $('#tennisResultModal').modal('show');
  });

  $('#insertResultFormButton').on('click', function () {
    const form = $('#insertResultForm');
    const formData = form.serialize();

    $.ajax({
      url: APP_URL + '/backend/fixture/insertResult',
      method: 'GET',
      data: formData,
      success: function (response) {
        console.log(APP_URL + '/backend/fixture/insertResult', response);
        if (response.results && response.id) {
          const row = $('tr').filter(function () {
            return $(this).find('td:first').text().trim() == response.id;
          });

          // Format result string
          let formatted = response.results
            .map(function (r) {
              return r.registration1_score + '-' + r.registration2_score;
            })
            .join(', ');

          // Inject result and update status
          row.find('td:last').html(formatted);
          row.find('td').eq(6).text('finished');

          // Hide modal
          $('#tennisResultModal').modal('hide');

          // 🆕 Reload box SVG matrix
          const boxNumber = row.find('td').eq(3).text().trim();
          const drawId = $('#insertResultForm').data('draw-id');

          console.log(response.fixture);
          if (response.fixture.stage == 'RR') {
            refreshMatrixBox(drawId, boxNumber);
          } else {
            console.log('🔁 Draw preview reloaded after score update, still have to work on this.');
            // ✅ Refresh draw preview (SVG or HTML)
            console.log('drawid', drawId);
            refreshDrawPlayoff(drawId);
          }
        }
      },
      error: function () {
        alert('Something went wrong when saving the result.');
      }
    });
  });
  // Remember active tab across page loads
  $('#settingsTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
    localStorage.setItem('activeDrawSettingsTab', $(event.target).data('bs-target'));
  });

  const savedTab = localStorage.getItem('activeDrawSettingsTab');
  if (savedTab) {
    const triggerEl = document.querySelector(`#settingsTabs button[data-bs-target="${savedTab}"]`);
    if (triggerEl) {
      new bootstrap.Tab(triggerEl).show();
    }
  }
  function toggleDrawLock() {
    const $btn = $('#progress-draw-btn');
    const drawId = $btn.data('draw-id');
    const isLocked = $btn.data('locked') === true || $btn.data('locked') === 'true';

    const newLockState = !isLocked;
    // 🔁 Fetch and log group standings

    $.ajax({
      url: APP_URL + '/backend/draw/' + drawId + '/lock',
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: { lock: newLockState },
      success: function (data) {
        console.log(data);
        $btn.data('locked', newLockState);
        $btn.toggleClass('btn-success btn-warning').text(newLockState ? 'Unlock Draw' : 'Lock Draw');
      },
      error: function (error) {
        alert('Failed to update draw lock state.');
        console.log(error);
      }
    });
  }

  $('#progress-draw-btn').on('click', toggleDrawLock);

  function refreshMatrixBox(drawId, boxNumber) {
    if (!boxNumber || !drawId) {
      console.warn('Missing drawId or boxNumber for matrix update.');
      return;
    }

    const url = `${APP_URL}/backend/draws/box-matrix/${drawId}/${boxNumber}`;
    console.log('🔄 Fetching matrix from:', url);

    $.ajax({
      url: url,
      method: 'GET',
      dataType: 'html',
      success: function (html) {
        console.log('rr',html)
        const selector = `#box-matrix-${boxNumber}`;
        console.log(`✅ Matrix loaded for box ${boxNumber}`);
        $(selector).replaceWith(html);
      },
      error: function (xhr, status, error) {
        console.error('❌ Error loading matrix:');
        console.error('Status:', status);
        console.error('Error:', error);
        console.error('Response:', xhr.responseText);
      }
    });
  }

/**
 * Reload the playoff draw after a score update.
 * GETs fresh HTML from /backend/draw/{id}/preview
 * and swaps the content inside #draw-container.
 */
/**
 * Pull fresh fixture data and redraw the bracket.
 * Call this right after a result is saved.
 */
function refreshDrawPlayoff(drawId) {
  $.ajax({
    url: `${APP_URL}/backend/draw/${drawId}/json`,
    method: 'GET',
    dataType: 'json',
    cache: false,
    success: function (data) {
      window.fixtureMap   = data.fixtureMap;
      window.isDrawLocked = data.isDrawLocked;
      buildAllDraws();           // will now use latest data
    },
    error: function (xhr, status, error) {
      console.log('error',error)
      console.error(`❌ Reload failed: ${status} – ${error}`);
      if (xhr.responseText) {
        console.error('Server response:', xhr.responseText);
      }
    }
  });
}




});
