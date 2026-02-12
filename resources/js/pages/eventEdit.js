import toastr from 'toastr';
import 'toastr/build/toastr.min.css';


import $ from 'jquery';

$(document).ready(function () {

  console.log('📂 Event Edit JS Loaded');

  const $form = $('#event-edit-form'); // ✅ FIXED

  if (!$form.length) {
    console.warn('Event edit form not found ❌');
    return;
  }

  /*
  |--------------------------------------------------------------------------
  | QUILL
  |--------------------------------------------------------------------------
  */

  let quill = null;

  if (window.Quill && document.getElementById('information-editor')) {

    quill = new Quill('#information-editor', {
      theme: 'snow',
      placeholder: 'Enter event information...',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link'],
          ['clean']
        ]
      }
    });

    console.log('Quill initialized ✅');
  }

  /*
  |--------------------------------------------------------------------------
  | LOGO PREVIEW
  |--------------------------------------------------------------------------
  */

  const preview = document.getElementById('logo-preview');
  const existingSelect = document.querySelector('select[name="logo_existing"]');
  const uploadInput = document.querySelector('input[name="logo_upload"]');
  const logoBaseUrl = window.eventConfig?.logoBaseUrl || '';

  if (existingSelect && preview) {
    existingSelect.addEventListener('change', function () {
      if (this.value) {
        preview.src = logoBaseUrl + this.value;
        preview.classList.remove('d-none');
      } else {
        preview.classList.add('d-none');
        preview.src = '';
      }
    });
  }

  if (uploadInput && preview) {
    uploadInput.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | SELECT2
  |--------------------------------------------------------------------------
  */

  if (typeof $.fn.select2 !== 'undefined') {
    $('.select2').select2({
      width: '100%',
      allowClear: true
    });
    console.log('Select2 initialized ✅');
  }

  /*
  |--------------------------------------------------------------------------
  | AJAX SAVE
  |--------------------------------------------------------------------------
  */

  $form.on('submit', function (e) {

    e.preventDefault();

    console.log('Submitting via AJAX...');

    // Sync Quill content
    if (quill) {
      $('#information-input').val(quill.root.innerHTML);
    }

    const formData = new FormData(this);
    const submitBtn = $form.find('button[type="submit"]');

    submitBtn.prop('disabled', true).text('Saving...');

    $.ajax({
      url: $form.attr('action'),
      method: 'POST', // _method=PATCH handles Laravel
      data: formData,
      processData: false,
      contentType: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest' // ✅ Important
      },
      success: function (response) {

        console.log('Save successful', response);

        submitBtn.prop('disabled', false).text('Save Changes');

        if (window.toastr) {
          toastr.success('Event updated successfully');
        }

      },
      error: function (xhr) {

        console.error('Save failed', xhr);

        submitBtn.prop('disabled', false).text('Save Changes');

        if (xhr.status === 422 && xhr.responseJSON?.errors) {

          Object.values(xhr.responseJSON.errors).forEach(messages => {
            if (window.toastr) {
              toastr.error(messages[0]);
            }
          });

        } else {

          if (window.toastr) {
            toastr.error('Failed to save event');
          }
        }
      }
    });

  });

});
