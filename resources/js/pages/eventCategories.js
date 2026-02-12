import $ from 'jquery';
import 'select2';

$(document).ready(function () {

  console.group('📂 Categories JS Init');

  const attachedIds = window.categoryConfig?.attachedIds || [];
  const feeRouteTemplate = window.categoryConfig?.feeUpdateUrl || '';

  console.log('Attached IDs:', attachedIds);
  console.log('Fee route template:', feeRouteTemplate);

  const $select = $('.select2');

  console.log('Select elements found:', $select.length);

  // ===============================
  // SELECT2 INIT
  // ===============================

  if ($.fn.select2) {
    console.log('Select2 loaded ✔');
    $select.select2({
      width: '100%',
      placeholder: function () {
        return $(this).data('placeholder');
      },
      closeOnSelect: false
    });
  } else {
    console.warn('Select2 NOT loaded ❌');
  }

  $select.val(attachedIds).trigger('change');

  attachedIds.forEach(id => {
    $select.find(`option[value="${id}"]`).prop('disabled', true);
  });

  console.groupEnd();

  // ===============================
  // CATEGORY FEE AUTO SAVE
  // ===============================

  let saveTimers = {};

  function saveFee(id) {

    console.group(`💾 Saving Fee (CategoryEvent ID: ${id})`);

    if (!feeRouteTemplate) {
      console.error('Fee route template missing');
      return;
    }

    const input = $(`.category-fee-input[data-id="${id}"]`);
    const value = input.val();
    const url = feeRouteTemplate.replace(':id', id);

    console.log('Saving value:', value);
    console.log('Request URL:', url);

    input.addClass('border-warning');

    if (window.toastr) {
      toastr.info('Saving...');
    }

    $.ajax({
      url: url,
      type: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: {
        entry_fee: value
      },
      success: function (response) {

        console.log('Server response:', response);

        input.removeClass('border-warning border-danger')
          .addClass('border-success');

        setTimeout(() => {
          input.removeClass('border-success');
        }, 1200);

        if (window.toastr) {
          toastr.success('Entry fee saved successfully');
        }

        console.groupEnd();
      },
      error: function (xhr) {

        console.error('Save failed:', xhr.responseText);

        input.removeClass('border-warning border-success')
          .addClass('border-danger');

        if (window.toastr) {
          toastr.error('Failed to save entry fee');
        }

        console.groupEnd();
      }
    });
  }

  // ===============================
  // BUTTON CLICK SAVE
  // ===============================

  $(document).on('click', '.save-fee-btn', function () {

    const id = $(this).data('id');
    console.log('Manual save click for ID:', id);

    saveFee(id);
  });

  // ===============================
  // AUTO SAVE (DEBOUNCED)
  // ===============================

  $(document).on('keyup', '.category-fee-input', function () {

    const id = $(this).data('id');
    const currentVal = $(this).val();

    console.log(`Typing detected for ID ${id}:`, currentVal);

    clearTimeout(saveTimers[id]);

    saveTimers[id] = setTimeout(function () {
      console.log(`Auto-saving ID ${id} after debounce`);
      saveFee(id);
    }, 800);

  });

});
