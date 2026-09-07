(function (window, document, $) {
  'use strict';

  var form = document.getElementById('createDrawForm');
  if (!form || !$) return;

  var data = window.HeadOffice || {};
  var submitButton = form.querySelector('button[type="submit"]');

  function selectedMode() {
    var input = form.querySelector('input[name="draw_mode"]:checked');
    return input ? input.value : '';
  }

  function showError(message) {
    if (window.toastr) window.toastr.error(message);
  }

  function setMode(mode) {
    var isTeam = mode === 'team';
    var isIndividual = mode === 'individual';

    $('#teamDrawTypeSection').toggleClass('d-none', !isTeam);
    $('#formatSelectGroup').toggleClass('d-none', !isTeam);

    if (isIndividual) {
      $('input[name="draw_type_id"]').prop('checked', false);
      $('input[name="category_choice_boys"], input[name="category_choice_girls"]').prop('checked', false);
      $('#type3Categories, #mixedPlaceholder').addClass('d-none');
      $('#categorySection').removeClass('d-none');
    } else if (isTeam) {
      $('#type3Categories, #mixedPlaceholder').addClass('d-none');
      $('#categorySection').removeClass('d-none');
    } else {
      $('#categorySection, #type3Categories, #mixedPlaceholder').addClass('d-none');
    }

    if (submitButton) {
      submitButton.textContent = isIndividual ? 'Create Singles Draw' : (isTeam ? 'Create Team Draw' : 'Create Draw');
    }
  }

  function updateIndividualName() {
    if (selectedMode() !== 'individual') return;

    var category = form.querySelector('input[name="category_choice"]:checked');
    if (!category) return;

    var label = form.querySelector('label[for="' + category.id + '"]');
    var categoryName = (category.dataset.age || (label ? label.textContent : '') || '').trim();
    var nameInput = document.getElementById('drawName');

    if (nameInput && categoryName) nameInput.value = categoryName + ' – Singles';
  }

  $(document).on('change', 'input[name="draw_mode"]', function () {
    setMode(this.value);
    window.setTimeout(updateIndividualName, 0);
  });

  $(document).on('change', 'input[name="category_choice"]', function () {
    window.setTimeout(updateIndividualName, 0);
  });

  $(document).on('hidden.bs.modal', '#createDrawModal', function () {
    form.reset();
    setMode('');
  });

  // Capture the individual submission before the legacy team-only handler.
  form.addEventListener('submit', function (event) {
    var mode = selectedMode();

    if (!mode) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showError('Choose individual singles or team tie');
      return;
    }

    if (mode === 'team') return;

    event.preventDefault();
    event.stopImmediatePropagation();

    var drawName = ($('#drawName').val() || '').trim();
    var category = form.querySelector('input[name="category_choice"]:checked');

    if (!drawName) {
      showError('Please enter a draw name');
      return;
    }

    if (!category) {
      showError('Please select a category');
      return;
    }

    if (!data.individualDrawTypeId) {
      showError('No individual Singles draw type is configured');
      return;
    }

    var originalText = submitButton ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Creating…';
    }

    $.post(data.individualCreateUrl, {
      _token: $('meta[name="csrf-token"]').attr('content'),
      drawName: drawName,
      draw_type_id: data.individualDrawTypeId,
      category_event_id: category.dataset.pivotId || category.value
    }).done(function (response) {
      window.location.assign(response.setup_url);
    }).fail(function (xhr) {
      if (xhr.responseJSON && xhr.responseJSON.errors) {
        Object.values(xhr.responseJSON.errors).flat().forEach(showError);
      } else {
        showError((xhr.responseJSON && xhr.responseJSON.message) || 'Error creating singles draw');
      }
    }).always(function () {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    });
  }, true);

  setMode(selectedMode());
})(window, document, window.jQuery);
