/* Event draw overview. Mutation rules remain in the existing endpoints. */
$(function () {
  const config = window.headOfficeDraws;
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const error = (xhr, fallback) => toastr.error(xhr.responseJSON?.message || fallback);

  const selectedRows = () => $('.draw-select:checked').closest('.draw-overview-row');
  const visibleSelectors = () => $('.draw-overview-row:not([hidden]) .draw-select');
  function updateBulkActions() {
    const $selected = selectedRows();
    const $visible = visibleSelectors();
    const visibleSelected = $visible.filter(':checked').length;
    const selectAll = document.getElementById('draw-select-all');
    if (selectAll) {
      selectAll.checked = $visible.length > 0 && visibleSelected === $visible.length;
      selectAll.indeterminate = visibleSelected > 0 && visibleSelected < $visible.length;
    }
    $('#draw-selection-count').text($selected.length + ' selected');
    $('#schedule-selected').prop('disabled', !$selected.filter('[data-schedulable="1"]').length);
    $('#publish-selected-draws').prop('disabled', !$selected.filter('[data-published="0"]').length);
    $('#unpublish-selected-draws').prop('disabled', !$selected.filter('[data-published="1"]').length);
    $('#publish-selected-times').prop('disabled', !$selected.filter('[data-has-schedule="1"][data-schedule="0"]').length);
    $('#unpublish-selected-times').prop('disabled', !$selected.filter('[data-schedule="1"]').length);
  }

  function filterDraws() {
    const search = String($('#draw-search').val() || '').trim().toLocaleLowerCase();
    const status = String($('#draw-status').val() || '');
    let visible = 0;
    $('.draw-overview-row').each(function () {
      const matches = (!search || (this.dataset.name + ' ' + (this.dataset.format || '')).toLocaleLowerCase().includes(search))
        && (!status || this.dataset.published === status);
      this.hidden = !matches;
      if (matches) visible++;
    });
    $('#draw-filter-count').text('Showing ' + visible + ' of ' + $('.draw-overview-row').length + ' draws');
    $('#draw-no-results').prop('hidden', visible !== 0);
    $('[data-draw-filter]').each(function () { this.setAttribute('aria-pressed', String(this.dataset.drawFilter === status)); });
    updateBulkActions();
  }
  $('#draw-search').on('input', filterDraws);
  $('#draw-status').on('change', filterDraws);
  $('[data-draw-filter]').on('click', function () { $('#draw-status').val(this.dataset.drawFilter); filterDraws(); });
  $('#draw-clear-filters').on('click', function () {
    $('#draw-search, #draw-status').val('');
    filterDraws();
    $('#draw-search').trigger('focus');
  });
  $('#draw-select-all').on('change', function () {
    visibleSelectors().prop('checked', this.checked);
    updateBulkActions();
  });
  $(document).on('change', '.draw-select', updateBulkActions);

  $('#schedule-selected').on('click', function () {
    const ids = selectedRows().filter('[data-schedulable="1"]').map(function () { return this.dataset.drawId; }).get();
    if (!ids.length) { toastr.warning('Select at least one draft, unlocked draw to schedule.'); return; }
    const url = new URL(config.venueScheduleUrl, window.location.origin);
    ids.forEach(id => url.searchParams.append('draw_ids[]', id));
    window.location.assign(url.toString());
  });

  function bulkPublish(operation, action) {
    const schedule = operation === 'schedules';
    const publishing = action === 'publish';
    const selector = schedule
      ? (publishing ? '[data-has-schedule="1"][data-schedule="0"]' : '[data-schedule="1"]')
      : (publishing ? '[data-published="0"]' : '[data-published="1"]');
    const $rows = selectedRows().filter(selector);
    const ids = $rows.map(function () { return Number(this.dataset.drawId); }).get();
    if (!ids.length) {
      toastr.warning(schedule
        ? 'Select at least one ' + (publishing ? 'prepared, unpublished schedule.' : 'published schedule.')
        : 'Select at least one ' + (publishing ? 'unpublished draw.' : 'published draw.'));
      return;
    }
    const noun = schedule ? 'match times' : 'draws';
    const verb = publishing ? 'Publish' : 'Unpublish';
    Swal.fire({
      title: verb + ' selected ' + noun + '?',
      text: publishing
        ? (schedule
          ? ids.length + ' selected schedules will expose their times, venues and courts. Draft draws remain available only in the authorized preview.'
          : ids.length + ' selected draws will become visible on their public links.')
        : (schedule
          ? ids.length + ' selected schedules will hide their times, venues and courts from the public.'
          : ids.length + ' selected draws will no longer be visible on their public links.'),
      icon: 'question', showCancelButton: true, confirmButtonText: verb + ' ' + noun,
    }).then(result => {
      if (!result.isConfirmed) return;
      const $buttons = $('.draws-bulk-buttons button').prop('disabled', true).attr('aria-busy', 'true');
      $.post(config.bulkPublicationUrl, {_token: csrf, operation: operation, action: action, draw_ids: ids})
        .done(function (response) {
          if (!response.failed?.length) { window.location.reload(); return; }
          const details = response.failed.map(item => item.name + ': ' + item.message).join('\n');
          Swal.fire({
            title: response.changed.length ? 'Some items could not be updated' : 'Nothing was changed',
            text: details, icon: 'warning', confirmButtonText: response.changed.length ? 'Refresh overview' : 'Close',
          }).then(() => { if (response.changed.length) window.location.reload(); });
        })
        .fail(xhr => error(xhr, 'Could not ' + action + ' the selected ' + noun + '.'))
        .always(() => { $buttons.prop('disabled', false).removeAttr('aria-busy'); updateBulkActions(); });
    });
  }
  $('#publish-selected-draws').on('click', () => bulkPublish('draws', 'publish'));
  $('#unpublish-selected-draws').on('click', () => bulkPublish('draws', 'unpublish'));
  $('#publish-selected-times').on('click', () => bulkPublish('schedules', 'publish'));
  $('#unpublish-selected-times').on('click', () => bulkPublish('schedules', 'unpublish'));
  updateBulkActions();

  $('#drawSettingsForm').on('submit', function (event) {
    event.preventDefault();
    const $form = $(this);
    const $submit = $form.find('[type="submit"]').prop('disabled', true).attr('aria-busy', 'true');
    $.post(config.drawSettingsUrl, $form.serialize())
      .done(function (response) {
        toastr.success(response.message || 'Draw settings updated.');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('drawSettingsModal')).hide();
      })
      .fail(xhr => error(xhr, 'Could not update the draw settings.'))
      .always(() => $submit.prop('disabled', false).removeAttr('aria-busy'));
  });

  $('#createDrawForm').on('submit', function (event) {
    event.preventDefault();
    if (!$('#drawName').val().trim()) { toastr.error('Please enter a draw name.'); return; }
    const $submit = $(this).find('[type="submit"]').prop('disabled', true);
    $.post(config.createUrl, $(this).serialize())
      .done(() => location.reload())
      .fail(xhr => error(xhr, 'Could not create draw.'))
      .always(() => $submit.prop('disabled', false));
  });

  $(document).on('click', '.toggle-publish', function () {
    const $button = $(this);
    const published = String($button.data('published')) === '1';
    const verb = published ? 'Unpublish' : 'Publish';
    const payload = {_token: csrf};
    if ($button.is('[data-revision]')) {
      payload.revision = Number($button.attr('data-revision'));
      payload.published = published ? 0 : 1;
    }

    Swal.fire({
      title: verb + ' draw?',
      text: published
        ? $button.data('draw-name') + ' will no longer be visible on the public draw link.'
        : $button.data('draw-name') + ' will become visible on its public draw link.',
      icon: 'question', showCancelButton: true, confirmButtonText: verb + ' draw',
    }).then(result => {
      if (!result.isConfirmed) return;
      $button.prop('disabled', true).attr('aria-busy', 'true');
      $.post($button.data('url'), payload)
        .done(function (response) {
          if (response.success === false) { toastr.error(response.message || 'Could not update publication.'); return; }
          location.reload(); // Refresh status, counts, filters and permitted actions together.
        })
        .fail(xhr => error(xhr, 'Could not update publication.'))
        .always(() => $button.prop('disabled', false).removeAttr('aria-busy'));
    });
  });
  $(document).on('click', '.toggle-schedule-publication', function () {
    const $button = $(this);
    const published = String($button.data('published')) === '1';
    const drawPublished = String($button.data('draw-published')) === '1';
    const verb = published ? 'Unpublish' : 'Publish';
    Swal.fire({
      title: verb + ' match times?',
      text: published
        ? $button.data('draw-name') + ' times, venues and courts will be hidden from the public.'
        : drawPublished
          ? $button.data('draw-name') + ' times, venues and courts will become public.'
          : $button.data('draw-name') + ' times can be checked in the authorized front-page preview. The draw remains hidden from the public.',
      icon: 'question', showCancelButton: true, confirmButtonText: verb + ' times',
    }).then(result => {
      if (!result.isConfirmed) return;
      $button.prop('disabled', true).attr('aria-busy', 'true');
      $.post($button.data('url'), {_token: csrf})
        .done(function (response) {
          if (response.success === false) { toastr.error(response.message || 'Could not update match times.'); return; }
          location.reload();
        })
        .fail(xhr => error(xhr, 'Could not update match times.'))
        .always(() => $button.prop('disabled', false).removeAttr('aria-busy'));
    });
  });
  $(document).on('click', '.btn-delete-draw', function () {
    const $button = $(this);
    Swal.fire({
      title: 'Delete draw?',
      text: 'Delete ' + $button.data('draw-name') + ' and its fixtures and results? This cannot be undone.',
      icon: 'warning', showCancelButton: true,
      confirmButtonColor: '#d33', confirmButtonText: 'Delete draw',
    }).then(result => {
      if (!result.isConfirmed) return;
      $button.prop('disabled', true);
      $.ajax({url: $button.data('url'), type: 'DELETE', data: {_token: csrf}})
        .done(function (response) {
          if (response.success) location.reload(); // Refresh the overview, empty state and print selector.
          else toastr.error(response.message || 'Could not delete draw.');
        })
        .fail(xhr => error(xhr, 'Could not delete draw.'))
        .always(() => $button.prop('disabled', false));
    });
  });
});
