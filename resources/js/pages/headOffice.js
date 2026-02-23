/*
  HeadOffice — team-event-show.js
  Requires:
  window.HeadOffice = {
      venues,
      previewUrl,
      createUrl,
      backendDrawVenuesStoreTemplate,
      backendDrawVenuesJsonTemplate
  }
*/

(function (window, $) {
  'use strict';

  const data = window.HeadOffice || {};
  const venues = data.venues || [];
  const csrfToken = $('meta[name="csrf-token"]').attr('content');

  // =====================================================
  // Loading Helpers
  // =====================================================
  function showLoading() {
    $('#loading-overlay').removeClass('d-none').addClass('d-flex');
  }

  function hideLoading() {
    $('#loading-overlay').removeClass('d-flex').addClass('d-none');
  }

  // =====================================================
  // CREATE DRAW — Auto-name
  // =====================================================

  function updateDrawName() {
    let selectedType = $('input[name="draw_type_id"]:checked');
    let typeText = selectedType.closest('.switch').find('.switch-label').text().trim();

    let selectedCat = $('input[name="category_choice"]:checked');
    let catText = selectedCat.data('age')
      || selectedCat.closest('.switch').find('.switch-label').text().trim();

    let name = '';

    if (selectedCat.length) name += catText;

    if (selectedType.val() == "3" && selectedCat.length) {
      name += ' – ' + typeText + ' (Boys & Girls)';
    } else if (typeText) {
      name += ' – ' + typeText;
    }

    $('#drawName').val(name);
  }

  // =====================================================
  // CREATE DRAW — Toggle category sections
  // =====================================================

  $(document).on('change', 'input[name="draw_type_id"]', function () {
    let selectedVal = $(this).val();
    let isMixed = $(this).data('mixed') == 1;

    if (selectedVal == "3") {
      $('#categorySection').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#type3Categories').removeClass('d-none');
      $('input[name="category_choice"]').prop('checked', false);

    } else if (isMixed) {
      $('#type3Categories').addClass('d-none');
      $('#categorySection').addClass('d-none');
      $('#mixedPlaceholder').removeClass('d-none');
      $('input[name="category_choice"]').prop('checked', false);

    } else {
      $('#type3Categories').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#categorySection').removeClass('d-none');
    }

    updateDrawName();
  });

  $(document).on('change', 'input[name="category_choice"]', updateDrawName);

  // =====================================================
  // CREATE DRAW — Form submit
  // =====================================================

  $('#createDrawForm').on('submit', function (e) {
    e.preventDefault();

    const drawName = $('#drawName').val().trim();
    if (!drawName) { toastr.error('Please enter a draw name'); return; }

    const $selectedType = $('input[name="draw_type_id"]:checked');
    const $selectedCat  = $('input[name="category_choice"]:checked');

    if (!$selectedType.length) { toastr.error('Please select a draw type'); return; }
    if (!$selectedCat.length)  { toastr.error('Please select a category'); return; }

    // Build category_ids[] from data attributes
    let categoryIds = [];

    if ($selectedType.val() == "3") {
      // Type 3 (mixed): data-ids is a JSON array [boysPivot, girlsPivot]
      categoryIds = $selectedCat.data('ids') || [];
    } else {
      // Standard: data-pivot-id is a single category_events.id
      const pivotId = $selectedCat.data('pivot-id');
      if (pivotId) categoryIds = [pivotId];
    }

    if (!categoryIds.length) { toastr.error('No category selected'); return; }

    // Build POST payload with category_ids[] (what the controller expects)
    let postData = {
      _token: csrfToken,
      draw_type_id: $selectedType.val(),
      drawName: drawName
    };

    categoryIds.forEach(function (id, i) {
      postData['category_ids[' + i + ']'] = id;
    });

    $.post(data.createUrl, postData)
      .done(function (response) {
        toastr.success(response.message);
        $('#createDrawModal').modal('hide');
        location.reload();
      })
      .fail(function (xhr) {
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          let msgs = Object.values(xhr.responseJSON.errors).flat();
          msgs.forEach(function (m) { toastr.error(m); });
        } else {
          toastr.error('Error creating draw');
        }
      });
  });

  // =====================================================
  // RECREATE FIXTURES
  // =====================================================

  $(document).off('click.recreate', '.btn-recreate-fixtures')
    .on('click.recreate', '.btn-recreate-fixtures', function () {

      const $btn = $(this);

      Swal.fire({
        title: 'Recreate Fixtures?',
        html: `This will <strong>delete and rebuild</strong> all fixtures for <b>${$btn.data('draw-name')}</b>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, recreate',
        confirmButtonColor: '#28a745'
      }).then((result) => {
        if (!result.isConfirmed) return;

        showLoading();

        $.post($btn.data('url'), { _token: csrfToken })
          .done(function (response) {
            if (response.success) {
              toastr.success(response.message);
              setTimeout(() => location.reload(), 1200);
            } else {
              toastr.error(response.message);
            }
          })
          .fail(function () {
            toastr.error('Failed to recreate fixtures.');
          })
          .always(hideLoading);
      });
    });

  // =====================================================
  // GENERATE ALL FIXTURES
  // =====================================================

  $('#generate-fixtures-btn').on('click', function (e) {
    e.preventDefault();

    let url = $(this).data('url');
    if (!url) { toastr.error('No fixture generation URL'); return; }

    showLoading();

    $.post(url, { _token: csrfToken })
      .done(function (response) {
        toastr.success(response.message || 'Fixtures generated');
        location.reload();
      })
      .fail(function () {
        toastr.error('Error generating fixtures');
      })
      .always(hideLoading);
  });

  // =====================================================
  // VENUES MODAL
  // =====================================================

  const $venuesModal = $('#venuesModal');
  const $venuesForm = $('#venuesForm');
  const $venuesContainer = $('#venues-container');

  function venueRowTemplate(selectedId = "", numCourts = 1) {
    let options = '<option value="">-- Select Venue --</option>';

    venues.forEach(v => {
      let selected = selectedId == v.id ? 'selected' : '';
      options += `<option value="${v.id}" ${selected}>${v.name}</option>`;
    });

    return `
      <div class="venue-row d-flex gap-2 mb-2">
        <select name="venue_id[]" class="form-select venue-select" required>
          ${options}
        </select>
        <input type="number" name="num_courts[]" class="form-control" value="${numCourts}" min="1" required>
        <button type="button" class="btn btn-danger btn-remove-row">&times;</button>
      </div>
    `;
  }

  function initSelect2($row) {
    const $select = $row.find('.venue-select');

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }

    $select.select2({
      dropdownParent: $venuesModal,
      width: '100%'
    });
  }

  // =====================================================
  // OPEN VENUES MODAL
  // =====================================================

  $(document).off('click.venues', '.btn-add-venues')
    .on('click.venues', '.btn-add-venues', function () {

      const drawId = $(this).data('draw-id');
      const drawName = $(this).data('draw-name');

      const storeUrl = data.backendDrawVenuesStoreTemplate.replace('__ID__', drawId);
      const jsonUrl = data.backendDrawVenuesJsonTemplate.replace('__ID__', drawId);

      $venuesForm.attr('action', storeUrl).data('draw-id', drawId);
      $venuesModal.find('.modal-title').text('Assign Venues to ' + drawName);
      $venuesContainer.empty();

      $.get(jsonUrl).done(existing => {

        if (existing.length > 0) {
          existing.forEach(v => {
            const $row = $(venueRowTemplate(v.id, v.num_courts));
            $venuesContainer.append($row);
            initSelect2($row);
          });
        } else {
          const $row = $(venueRowTemplate());
          $venuesContainer.append($row);
          initSelect2($row);
        }

        $venuesModal.modal('show');
      });
    });

  // =====================================================
  // ADD ROW
  // =====================================================

  $('#addVenueRow').off('click.venues')
    .on('click.venues', function () {

      const $row = $(venueRowTemplate());
      $venuesContainer.append($row);
      initSelect2($row);
    });

  // =====================================================
  // REMOVE ROW
  // =====================================================

  $(document).off('click.venues', '.btn-remove-row')
    .on('click.venues', '.btn-remove-row', function () {
      $(this).closest('.venue-row').remove();
    });

  // =====================================================
  // SAVE VENUES
  // =====================================================

  $venuesForm.off('submit.venues')
    .on('submit.venues', function (e) {

      e.preventDefault();

      const url = $(this).attr('action');
      const formData = $(this).serialize();
      const drawId = $(this).data('draw-id');

      $.post(url, formData + '&_token=' + csrfToken)
        .done(response => {

          if (!response.success) {
            toastr.error('Could not save venues.');
            return;
          }

          toastr.success('Venues updated successfully.');
          $venuesModal.modal('hide');

          const $container = $('.draw-venues[data-draw-id="' + drawId + '"]');

          if (response.venues && response.venues.length) {

            let html = '<small class="text-muted">Venues:</small> ';

            response.venues.forEach(v => {
              html += `
                <span class="badge bg-label-primary me-1">
                  ${v.name} 
                  <span class="text-muted">(${v.pivot.num_courts})</span>
                </span>
              `;
            });

            $container.html(html);
          } else {
            $container.empty();
          }

        })
        .fail(() => {
          toastr.error('Error while saving venues.');
        });
    });

  // =====================================================
  // TOGGLE PUBLISH
  // =====================================================

  $(document).off('click.publish', '.toggle-publish')
    .on('click.publish', '.toggle-publish', function () {

      const $btn = $(this);
      const url = $btn.data('url');
      const currentStatus = $btn.data('status');

      $btn.prop('disabled', true);

      $.post(url, { _token: csrfToken, status: currentStatus })
        .done(response => {

          if (!response.success) {
            toastr.error('Could not update publish status.');
            return;
          }

          const newStatus = response.published ? 1 : 0;
          $btn.data('status', newStatus);

          if (newStatus === 1) {
            $btn.html('<i class="ti ti-eye-off me-2"></i> Unpublish');
            toastr.success('Draw published.');
          } else {
            $btn.html('<i class="ti ti-eye me-2"></i> Publish');
            toastr.info('Draw unpublished.');
          }

        })
        .fail(() => {
          toastr.error('Error while toggling publish.');
        })
        .always(() => {
          $btn.prop('disabled', false);
        });
    });

  // =====================================================
  // DELETE DRAW
  // =====================================================

  $(document).off('click.delete', '.btn-delete-draw')
    .on('click.delete', '.btn-delete-draw', function () {

      const $btn = $(this);
      const url = $btn.data('url');
      const drawName = $btn.data('draw-name');

      Swal.fire({
        title: 'Delete Draw?',
        html: `Are you sure you want to delete <strong>${drawName}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        confirmButtonColor: '#d33'
      }).then(result => {

        if (!result.isConfirmed) return;

        $.ajax({
          url: url,
          type: 'DELETE',
          data: { _token: csrfToken },
          success: response => {

            if (!response.success) {
              toastr.error(response.message);
              return;
            }

            toastr.success(response.message);
            $btn.closest('.list-group-item')
              .fadeOut(300, function () { $(this).remove(); });
          },
          error: () => {
            toastr.error('Error while deleting draw.');
          }
        });

      });
    });

})(window, jQuery);
