'use strict';
$(function () {
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
  const table = $('#photo-list').DataTable({ language: { emptyTable: 'No photos or folders yet. Add a folder, then upload photos inside it.' } });
  const selectedIds = () => table.$('.dt-checkboxes:checked', { page: 'all' }).map(function () { return this.value; }).get();
  const refreshSelection = () => {
    const count = selectedIds().length;
    $('#photo-selection-count').text(`${count} photos selected`);
    $('#deleteSelected, #moveSelected').prop('disabled', count === 0);
  };
  $('#photo-list').on('change', '.dt-checkboxes', refreshSelection);
  table.on('draw', refreshSelection);
  refreshSelection();
  $('#photo-list').on('click', '.preview', function () {
    const photo = $(this).data('image');
    $('#frame').empty().append($('<img>', { src: APP_URL + '/storage/photoFolder/' + photo.path, alt: photo.name, class: 'img-fluid' }));
  });
  $('#photo-list').on('click', '.edit-folder-button', function () {
    const folder = $(this).data('id');
    $('#folder-name').val(folder.name);
    $('#edit-folder-form').attr('action', APP_URL + '/backend/photoFolder/' + encodeURIComponent(folder.id));
  });
  $('#deleteSelected').on('click', function () {
    const ids = selectedIds();
    if (!ids.length || !window.confirm(`Delete ${ids.length} selected photos? This action cannot be undone.`)) return;
    $(this).prop('disabled', true);
    $.post(APP_URL + '/backend/photo/deleteSelected', { data: ids })
      .done(() => location.reload())
      .fail(() => { window.alert('Photos could not be deleted. Please try again.'); refreshSelection(); });
  });
  $('#moveSelected').on('click', function () {
    $('#photos').val(selectedIds());
    $('#folder').val('');
    $('#photo-action-status').text('');
    $('#submit-move-button').prop('disabled', true);
  });
  $('#folder').on('change', function () {
    $('#submit-move-button').prop('disabled', !this.value || selectedIds().length === 0);
  });
  $('#submit-move-button').on('click', function () {
    const ids = selectedIds();
    const folder = $('#folder').val();
    if (!ids.length || !folder) return;
    const button = $(this).prop('disabled', true);
    $.post(APP_URL + '/backend/photo/moveSelected', { data: ids, folder_id: folder })
      .done(() => location.reload())
      .fail(() => { $('#photo-action-status').text('Photos could not be moved. Check the destination and try again.'); button.prop('disabled', false); });
  });
});
