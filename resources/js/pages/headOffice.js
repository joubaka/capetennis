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

  function getCategoryLabel($input) {
    if (!$input.length) {
      return '';
    }

    return ($input.data('age') || $('label[for="' + $input.attr('id') + '"]').text().trim() || '').toString().trim();
  }

  function updateDrawName() {
    var selectedType = $('input[name="draw_type_id"]:checked');
    var typeText = '';
    if (selectedType.length) {
      typeText = $('label[for="' + selectedType.attr('id') + '"]').text().trim();
    }

    var name = '';

    if (selectedType.val() == "3") {
      var selectedBoy = $('input[name="category_choice_boys"]:checked').first();
      var selectedGirl = $('input[name="category_choice_girls"]:checked').first();
      var catText = getCategoryLabel(selectedBoy) || getCategoryLabel(selectedGirl);

      if (catText) {
        name += catText;
      }

      if (typeText) {
        name += (name ? ' – ' : '') + typeText;
      }
    } else {
      var selectedCat = $('input[name="category_choice"]:checked');
      var catText = getCategoryLabel(selectedCat);

      if (catText) name += catText;
      if (typeText) name += (name ? ' – ' : '') + typeText;
    }

    $('#drawName').val(name);
  }

  function setMixedVisibility(isMixed) {
    if (isMixed) {
      $('#categorySection').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#type3Categories').removeClass('d-none');
      $('input[name="category_choice"]').prop('checked', false);
    } else {
      $('#type3Categories').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#categorySection').removeClass('d-none');
      $('input[name="category_choice_boys"], input[name="category_choice_girls"]').prop('checked', false);
    }
  }

  // =====================================================
  // CREATE DRAW — Toggle category sections
  // =====================================================

  $(document).on('change', 'input[name="draw_type_id"]', function () {
    let selectedVal = $(this).val();
    let isMixed = $(this).data('mixed') == 1;

    setMixedVisibility(selectedVal == "3" || isMixed);
    updateDrawName();
  });

  $(document).on('change', 'input[name="category_choice"], input[name="category_choice_boys"], input[name="category_choice_girls"]', updateDrawName);

  // Auto-populate draw name when modal opens (based on default-checked radios)
  $(document).on('shown.bs.modal', '#createDrawModal', function () {
    updateDrawName();
  });

  // =====================================================
  // CREATE DRAW — Form submit
  // =====================================================

  $('#createDrawForm').on('submit', function (e) {
    e.preventDefault();

    const drawName = $('#drawName').val().trim();
    if (!drawName) { toastr.error('Please enter a draw name'); return; }

    const $selectedType = $('input[name="draw_type_id"]:checked');
    const isMixedDraw = $selectedType.val() == "3";
    const $selectedCat  = $('input[name="category_choice"]:checked');
    const $selectedBoy  = $('input[name="category_choice_boys"]:checked');
    const $selectedGirl = $('input[name="category_choice_girls"]:checked');

    if (!$selectedType.length) { toastr.error('Please select a draw type'); return; }

    // Build category_ids[] from data attributes
    let categoryIds = [];

    if (isMixedDraw) {
      if (!$selectedBoy.length || !$selectedGirl.length) {
        toastr.error('Please select both a boys category and a girls category');
        return;
      }

      const boyId = $selectedBoy.data('pivot-id');
      const girlId = $selectedGirl.data('pivot-id');

      if (boyId) categoryIds.push(boyId);
      if (girlId && categoryIds.indexOf(girlId) === -1) categoryIds.push(girlId);
    } else {
      if (!$selectedCat.length) { toastr.error('Please select a category'); return; }

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
  const $venueFeedback = $('<div class="alert alert-danger d-none" role="alert"></div>');
  $venuesContainer.before($venueFeedback);

  function venueSaveError(xhr) {
    const status = xhr.status || 0;
    const payload = xhr.responseJSON || {};
    if (status === 422 && payload.errors) {
      const messages = [...new Set(Object.values(payload.errors).flat().filter(message => typeof message === 'string'))];
      if (messages.length) return messages.join(' ');
    }
    if (status === 419 || status === 401) return 'Your session has expired. Refresh the page and sign in before saving venues again.';
    if (status === 403) return 'You cannot edit venues for this draw. Check your event permissions and whether the draw is locked or published.';
    if (status === 404) return 'This draw is no longer available. Refresh the page before assigning venues.';
    if (status >= 500) return 'The server could not confirm the venue save (HTTP ' + status + '). Refresh and check this draw and its age-group defaults before retrying. If it continues, contact support with the event, draw and time of the attempt.';
    if (!status) return 'Could not confirm the venue save because the connection was interrupted. Check your connection, then refresh and check the saved venues before retrying.';
    return payload.message || 'Could not save venues. Check the selected venues and court counts, then try again.';
  }

  function showVenueError(message) {
    $venueFeedback.text(message).removeClass('d-none');
    toastr.error(message, 'Venues could not be saved', { escapeHtml: true, timeOut: 10000 });
  }

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
      $venueFeedback.text('').addClass('d-none');

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
      const $save = $venuesForm.find('[type="submit"]');
      if ($save.prop('disabled')) return;
      $save.prop('disabled', true);
      $venueFeedback.text('').addClass('d-none');

      $.post(url, formData + '&_token=' + csrfToken)
        .done(response => {

          if (!response.success) {
            showVenueError('Could not save venues. Check the selected venues and court counts, then try again.');
            return;
          }

          toastr.success('Venues updated successfully.');
          $venuesModal.modal('hide');

          const $container = $('.draw-venues[data-draw-id="' + drawId + '"]');
          const $summary = $container.closest('.event-draw-publication-card').find('.event-draw-venue-summary').empty();
          if (response.venues && response.venues.length) {
            response.venues.forEach(v => {
              $('<span>').addClass('badge bg-label-primary')
                .text(`${v.name} (${v.pivot.num_courts})`).appendTo($summary);
            });
          } else {
            $('<span>').addClass('badge bg-label-secondary').text('No venues assigned').appendTo($summary);
          }

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
        .fail(xhr => {
          showVenueError(venueSaveError(xhr));
        })
        .always(() => {
          $save.prop('disabled', false);
        });
    });

  // =====================================================
  // TOGGLE PUBLISH
  // =====================================================

  $(document).off('click.publish', '.toggle-publish, .toggle-publish-schedule')
    .on('click.publish', '.toggle-publish, .toggle-publish-schedule', function () {

      const $btn = $(this);
      const url = $btn.data('url');
      const currentStatus = $btn.data('status');
      const isSchedule = $btn.hasClass('toggle-publish-schedule');
      const subject = isSchedule ? 'schedule' : 'draw';
      const $card = $btn.closest('.event-draw-publication-card');

      $btn.prop('disabled', true);

      $.post(url, { _token: csrfToken, status: currentStatus })
        .done(response => {

          if (!response.success) {
            toastr.error(response.message || 'Could not update publish status.');
            return;
          }

          const newStatus = (isSchedule ? response.oop_published : response.published) ? 1 : 0;
          $btn.data('status', newStatus);

          $btn.html(`<i class="ti ti-${newStatus ? 'eye-off' : 'eye'} me-1"></i>${newStatus ? 'Hide' : 'Publish'} ${subject}`);
          const drawPublished = isSchedule ? Number($card.find('.toggle-publish').data('status')) === 1 : newStatus === 1;
          const schedulePublished = isSchedule ? newStatus === 1 : Number($card.find('.toggle-publish-schedule').data('status')) === 1;
          $card.find('.event-draw-status')
            .removeClass('bg-label-success bg-label-warning')
            .addClass(drawPublished ? 'bg-label-success' : 'bg-label-warning')
            .text(drawPublished ? 'Draw published' : 'Draw hidden');
          $card.find('.event-schedule-status')
            .removeClass('bg-label-success bg-label-secondary')
            .addClass(schedulePublished ? 'bg-label-success' : 'bg-label-secondary')
            .text(schedulePublished ? (drawPublished ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden');
          $card.find('.event-publication-note').text(schedulePublished && !drawPublished ? 'Publish the draw to make these times public.' : 'Draws and match times are published separately.');
          $card.find('.event-oop-summary').each(function () {
            const created = Number($(this).data('created')) === 1;
            $(this).removeClass('bg-label-success bg-label-info bg-label-secondary')
              .addClass(schedulePublished ? 'bg-label-success' : (created ? 'bg-label-info' : 'bg-label-secondary'))
              .text(`Order of play: ${schedulePublished ? (drawPublished ? 'Published' : 'Preview only') : (created ? 'Created' : 'Not done')}`);
          });
          toastr.success(isSchedule && newStatus && response.preview_only
            ? 'Schedule ready for preview. Publish the draw to make these times public.'
            : `${isSchedule ? 'Schedule' : 'Draw'} ${newStatus ? 'published' : 'unpublished'}.`);

        })
        .fail(xhr => {
          toastr.error(xhr.responseJSON?.message || `Could not update ${subject} publication.`);
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
            $btn.closest('.event-draw-publication-card')
              .fadeOut(300, function () { $(this).remove(); });
          },
          error: () => {
            toastr.error('Error while deleting draw.');
          }
        });

      });
    });

})(window, jQuery);
