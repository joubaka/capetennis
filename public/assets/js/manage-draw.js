$(function () {
  const csrfToken = $('meta[name="csrf-token"]').attr('content');
  const APP_URL = $('meta[name="app-url"]').attr('content');

  // 🎯 Update settings preview
  function updatePreview() {
    $('#preview_format').text($('#draw_format_id option:selected').text());
    $('#preview_type').text($('#draw_type_id option:selected').text());
    $('#preview_boxes').text($('#boxes').val());
    $('#preview_playoff').text($('#playoff_size').val());
    $('#preview_sets').text($('#num_sets').val());
  }

  // 🛠️ Save settings via AJAX
  $('#drawSettingsForm select, #drawSettingsForm input').on('change', function () {
    updatePreview();

    const form = $('#drawSettingsForm');
    const drawId = form.data('draw-id');
    const url = `${APP_URL}/backend/draw/${drawId}/update-settings`;

    $.ajax({
      method: 'POST',
      url: url,
      data: form.serialize(),
      headers: { 'X-CSRF-TOKEN': csrfToken },
      success: function (data) {
        console.log('✅ Settings saved', data);
      },
      error: function (error) {
        console.error('❌ Failed to save settings', error);
      }
    });
  });

  updatePreview(); // On initial load




});

