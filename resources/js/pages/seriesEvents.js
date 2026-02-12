import $ from 'jquery';


$(document).ready(function () {

  console.log('📂 Series Events JS Loaded');

  /*
  |--------------------------------------------------------------------------
  | SELECT2 INIT
  |--------------------------------------------------------------------------
  */

  const $select = $('.select2');

  console.log('Select2 elements found:', $select.length);

  if ($.fn.select2) {
    $select.select2({
      width: '100%',
      allowClear: true,
      placeholder: function () {
        return $(this).data('placeholder');
      }
    });

    console.log('Select2 initialized ✅');
  } else {
    console.warn('Select2 NOT loaded ❌');
  }

  /*
  |--------------------------------------------------------------------------
  | TOASTR CONFIG
  |--------------------------------------------------------------------------
  */

  if (window.toastr) {
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: "toast-top-right",
      timeOut: 2500
    };

    console.log('Toastr ready ✅');
  } else {
    console.warn('Toastr NOT loaded ❌');
  }

  /*
  |--------------------------------------------------------------------------
  | LOGO PREVIEW LOGIC
  |--------------------------------------------------------------------------
  */

  const $logoSelect = $('select[name="logo_existing"]');
  const $logoUpload = $('input[name="logo_upload"]');
  const $preview = $('#logo-preview');

  // When selecting existing logo
  $logoSelect.on('change', function () {

    const filename = $(this).val();

    console.log('Selected existing logo:', filename);

    if (!filename) {
      $preview.addClass('d-none');
      return;
    }

    const imageUrl = `/assets/img/logos/${filename}`;

    $preview
      .attr('src', imageUrl)
      .removeClass('d-none');

    if (window.toastr) {
      toastr.info('Existing logo selected');
    }
  });

  // When uploading new logo
  $logoUpload.on('change', function (e) {

    const file = e.target.files[0];

    if (!file) return;

    console.log('Uploading logo file:', file.name);

    const reader = new FileReader();

    reader.onload = function (event) {
      $preview
        .attr('src', event.target.result)
        .removeClass('d-none');
    };

    reader.readAsDataURL(file);

    if (window.toastr) {
      toastr.success('New logo preview loaded');
    }
  });

});
