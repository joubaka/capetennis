(function (window, document, $) {
  'use strict';

  var form = document.getElementById('createDrawForm');
  if (!form || !$) return;

  var data = window.HeadOffice || {};
  var submitButton = form.querySelector('button[type="submit"]');
  var previewRevision = 0;

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

    $('#manualTeamCategories').prop('checked', false).prop('disabled', !isTeam);
    $('#manualCategoryToggleGroup').toggleClass('d-none', !isTeam);
    $('#manualCategoryChoices').addClass('d-none');
    $('#manualCategoryChoices input').prop('disabled', true).prop('checked', false);
    $('#individualCategoryChoices').toggleClass('d-none', !isIndividual);
    $('#teamCategoryChoices').toggleClass('d-none', !isTeam);
    $('#individualCategoryChoices input').prop('disabled', !isIndividual).prop('checked', false);
    $('#teamCategoryChoices input').prop('disabled', !isTeam).prop('checked', false);

    $('#teamDrawTypeSection').toggleClass('d-none', !isTeam);
    $('#formatSelectGroup, #previewTeamDrawButton').toggleClass('d-none', !isTeam);
    $('#teamDrawPreview').empty().addClass('d-none');

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
    updateCategorySelection();
  }

  function updateCategorySelection() {
    var isTeam = selectedMode() === 'team';
    var manual = isTeam && document.getElementById('manualTeamCategories').checked;
    var type = form.querySelector('input[name="draw_type_id"]:checked');
    var mixed = type && (type.value === '3' || type.dataset.mixed === '1');
    $('#manualCategoryChoices').toggleClass('d-none', !manual);
    $('#manualCategoryChoices input').prop('disabled', !manual);
    $('#categorySection').toggleClass('d-none', (!isTeam && selectedMode() !== 'individual') || (isTeam && (manual || mixed)));
    $('#type3Categories').toggleClass('d-none', !isTeam || manual || !mixed);
    $('#teamCategoryChoices input, #type3Categories input').prop('disabled', !isTeam || manual);
    if (manual) {
      $('#teamCategoryChoices input, #type3Categories input').prop('checked', false);
    }
  }

  $(document).on('change', '#manualTeamCategories, input[name="draw_type_id"]', updateCategorySelection);

  function updateIndividualName() {
    if (selectedMode() !== 'individual') return;

    var category = form.querySelector('input[name="category_choice"]:checked:not(:disabled)');
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

  function teamPayload() {
    var type = form.querySelector('input[name="draw_type_id"]:checked');
    var name = ($('#drawName').val() || '').trim();
    if (!name || !type) { showError('Enter a draw name and select a draw type'); return null; }
    var mixed = type.value === '3' || type.dataset.mixed === '1';
    var selectors = mixed ? ['category_choice_boys', 'category_choice_girls'] : ['category_choice'];
    var categories = [];
    if (document.getElementById('manualTeamCategories').checked) {
      var choices = Array.from(form.querySelectorAll('input[name="manual_category_ids[]"]:checked:not(:disabled)'));
      if (!choices.length) { showError('Select categories to combine'); return null; }
      if (mixed && (!choices.some(function (choice) { return choice.dataset.gender === 'boys'; }) ||
        !choices.some(function (choice) { return choice.dataset.gender === 'girls'; }))) {
        showError('Select both boys and girls categories for mixed doubles'); return null;
      }
      categories = choices.map(function (choice) { return choice.value; });
    } else for (var i = 0; i < selectors.length; i++) {
      var category = form.querySelector('input[name="' + selectors[i] + '"]:checked:not(:disabled)');
      if (!category) { showError(mixed ? 'Select both boys and girls categories' : 'Select a category'); return null; }
      var ids = category.dataset.pivotIds ? JSON.parse(category.dataset.pivotIds) : [category.dataset.pivotId || category.value];
      ids.forEach(function (id) {
        id = String(id);
        if (categories.indexOf(id) === -1) categories.push(id);
      });
    }
    return { _token: $('meta[name="csrf-token"]').attr('content'), drawName: name,
      draw_type_id: type.value, category_ids: categories, format_id: $('#format_id').val() || null };
  }

  function responseError(xhr) {
    if (xhr.responseJSON && xhr.responseJSON.errors) Object.values(xhr.responseJSON.errors).flat().forEach(showError);
    else showError((xhr.responseJSON && xhr.responseJSON.message) || 'Could not process the draw');
  }

  function previewLine(parent, text, className) {
    var line = document.createElement('div');
    line.className = className || '';
    line.textContent = text;
    parent.appendChild(line);
  }

  function renderPreview(readiness) {
    var container = document.getElementById('teamDrawPreview');
    container.replaceChildren();
    container.classList.remove('d-none');
    previewLine(container, (readiness.format_name || 'No format') + ': ' + readiness.team_count + ' teams, ' +
      readiness.round_count + ' rounds, ' + readiness.tie_count + ' ties, ' + readiness.rubber_count +
      ' rubbers, ' + readiness.bye_count + ' byes.', 'fw-semibold mb-2');
    (readiness.warnings || []).forEach(function (warning) { previewLine(container, warning, 'text-warning mb-1'); });
    if (readiness.ready) previewLine(container, 'All required player slots are assigned. Review before publication.', 'text-success mb-2');
    (readiness.rounds || []).forEach(function (round) {
      var detail = document.createElement('details');
      var summary = document.createElement('summary');
      summary.textContent = 'Round ' + round.round_nr;
      detail.appendChild(summary);
      detail.className = 'border rounded p-2 mt-2';
      (round.byes || []).forEach(function (bye) { previewLine(detail, bye.name + ' — bye', 'text-muted small'); });
      (round.ties || []).forEach(function (tie) {
        previewLine(detail, tie.home_team.name + ' vs ' + tie.away_team.name, 'fw-semibold mt-2');
        (tie.rubbers || []).forEach(function (rubber) {
          previewLine(detail, rubber.sequence + '. ' + rubber.name + ' (' + rubber.rubber_code.replace(/_/g, ' ') + ')', 'small');
          (rubber.slots || []).forEach(function (slot) {
            var home = slot.team1_name || 'Missing player';
            var away = slot.team2_name || 'Missing player';
            previewLine(detail, 'Slot ' + slot.slot_no + ': ' + home + ' vs ' + away, 'small text-muted');
          });
        });
      });
      container.appendChild(detail);
    });
  }

  $('#previewTeamDrawButton').on('click', function () {
    var payload = teamPayload();
    if (!payload) return;
    var button = this;
    var revision = previewRevision;
    button.disabled = true;
    $.post(data.previewUrl, payload).done(function (response) {
      if (revision === previewRevision && selectedMode() === 'team') renderPreview(response.readiness);
    })
      .fail(responseError).always(function () { button.disabled = false; });
  });
  form.addEventListener('change', function () { previewRevision++; $('#teamDrawPreview').empty().addClass('d-none'); });
  form.addEventListener('input', function () { previewRevision++; $('#teamDrawPreview').empty().addClass('d-none'); });

  // Capture the individual submission before the legacy team-only handler.
  form.addEventListener('submit', function (event) {
    var mode = selectedMode();

    if (!mode) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showError('Choose individual singles or team tie');
      return;
    }

    if (mode === 'team') {
      event.preventDefault();
      event.stopImmediatePropagation();
      var payload = teamPayload();
      if (!payload) return;
      if (submitButton) submitButton.disabled = true;
      $.post(data.createUrl, payload).done(function () { window.location.reload(); })
        .fail(responseError).always(function () { if (submitButton) submitButton.disabled = false; });
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    var drawName = ($('#drawName').val() || '').trim();
    var category = form.querySelector('input[name="category_choice"]:checked:not(:disabled)');

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
