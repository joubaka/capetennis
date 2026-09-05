/* Event draw overview. Mutation rules remain in the existing endpoints. */
$(function () {
  const config = window.headOfficeDraws;
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const error = (xhr, fallback) => toastr.error(xhr.responseJSON?.message || fallback);
  const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

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
  }
  $('#draw-search').on('input', filterDraws);
  $('#draw-status').on('change', filterDraws);
  $('[data-draw-filter]').on('click', function () { $('#draw-status').val(this.dataset.drawFilter); filterDraws(); });
  $('#draw-clear-filters').on('click', function () {
    $('#draw-search, #draw-status').val('');
    filterDraws();
    $('#draw-search').trigger('focus');
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

  const $venuesForm = $('#venuesForm');
  const $container = $('#venues-container');
  function addVenueRow(id = '', courts = 1) {
    const $row = $('<div class="venue-row gap-2 mb-2"></div>');
    const $select = $('<select name="venue_id[]" class="form-select" aria-label="Venue" required></select>');
    $select.append(new Option('Select venue', ''));
    config.venues.forEach(v => $select.append(new Option(v.name, v.id, false, String(id) === String(v.id))));
    $row.append($select);
    $row.append($('<input type="number" name="num_courts[]" class="form-control" min="1" aria-label="Number of courts" required>').val(courts));
    $row.append('<button type="button" class="btn btn-outline-danger btn-remove-row" aria-label="Remove venue">&times;</button>');
    $container.append($row);
    $select.select2({dropdownParent: $('#venuesModal'), width: '100%'});
  }
  $(document).on('click', '.btn-add-venues', function () {
    const $button = $(this).prop('disabled', true);
    const id = $button.data('draw-id');
    $venuesForm.attr('action', config.venueStoreUrl.replace('__ID__', id)).data('draw-id', id);
    $('#venuesModal .modal-title').text('Venues · ' + $button.data('draw-name'));
    $container.find('select').each(function () { $(this).select2('destroy'); });
    $container.empty();
    $.get(config.venueJsonUrl.replace('__ID__', id))
      .done(function (venues) {
        if (venues.length) venues.forEach(v => addVenueRow(v.id, v.num_courts));
        else addVenueRow();
        modal('venuesModal').show();
      })
      .fail(xhr => error(xhr, 'Could not load venues.'))
      .always(() => $button.prop('disabled', false));
  });
  $('#addVenueRow').on('click', () => addVenueRow());
  $(document).on('click', '.btn-remove-row', function () {
    $(this).closest('.venue-row').find('select').select2('destroy');
    $(this).closest('.venue-row').remove();
  });
  $venuesForm.on('submit', function (event) {
    event.preventDefault();
    const $submit = $(this).find('[type="submit"]').prop('disabled', true);
    $.post($(this).attr('action'), $(this).serialize())
      .done(function (response) {
        if (!response.success) { toastr.error(response.message || 'Could not save venues.'); return; }
        const names = response.venues.map(v => {
          const courts = v.pivot?.num_courts ?? v.num_courts;
          return v.name + ' (' + courts + ' ' + (Number(courts) === 1 ? 'court' : 'courts') + ')';
        });
        $('.draw-venues[data-draw-id="' + $venuesForm.data('draw-id') + '"]').text(names.join(', ') || 'Venues not set');
        modal('venuesModal').hide();
        toastr.success('Venues updated.');
      })
      .fail(xhr => error(xhr, 'Could not save venues.'))
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
