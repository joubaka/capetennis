$(function () {
  'use strict';

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Show modal
  $('.openDrawModal').on('click', function () {
    $('#generateDrawModal').modal('show');
  });

  // Rebuild Select2 when modal is fully shown
  $('#generateDrawModal').on('shown.bs.modal', function () {
    const $select = $(this).find('.select2');

    // ✅ Only destroy if already initialized
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }

    // ✅ Then safely re-initialize
    $select.select2({
      dropdownParent: $('#generateDrawModal'),
      width: '100%'
    });
  });

  $('#generateDrawModal').on('hidden.bs.modal', function () {
    const $form = $(this).find('form')[0];
    if ($form) $form.reset();

    const $select = $(this).find('.select2');

    // ✅ Again, check before destroy
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.val(null).trigger('change').select2('destroy');
    }
  });

  // Reset modal and Select2 on close
  $('#generateDrawModal').on('hidden.bs.modal', function () {
    const $form = $(this).find('form')[0];
    if ($form) $form.reset();
    $(this).find('.select2').val(null).trigger('change').select2('destroy');
  });
});
