(function (window, document, $) {
  'use strict';

  var form = document.getElementById('createDrawForm');
  if (!form || !$) return;

  var data = window.HeadOffice || {};
  var submitButton = form.querySelector('button[type="submit"]');
  var previewRevision = 0;
  var nameCustomized = false;
  // This form owns category/type selection. Older bundles assumed mixed ID 3.
  $(document).off('change', 'input[name="draw_type_id"]');
  $(document).off('change', 'input[name="category_choice"], input[name="category_choice_boys"], input[name="category_choice_girls"]');
  $(document).off('shown.bs.modal', '#createDrawModal');
  var batchKey = null;
  function creationKey() {
    if (!batchKey) batchKey = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(36).slice(2);
    return batchKey;
  }

  function selectedMode() {
    var input = form.querySelector('input[name="draw_mode"]:checked');
    return input ? input.value : '';
  }

  function showError(message) {
    if (window.toastr) window.toastr.error(message);
  }

  function setMode(mode) {
    nameCustomized = false;
    var nameInput = document.getElementById('drawName');
    if (nameInput) nameInput.value = '';
    var isTeam = mode === 'team';
    var isIndividual = mode === 'individual';
    $('#bulkTeamGroup').toggleClass('d-none', !isTeam);
    $('#bulkTeamDraws').prop('checked', false);
    $('#bulkTeamChoices').addClass('d-none');

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
    var bulk = isTeam && document.getElementById('bulkTeamDraws').checked;
    var drawNameInput = document.getElementById('drawName');
    if (drawNameInput) drawNameInput.required = false;
    $('#singleDrawNameGroup').toggleClass('d-none', bulk);
    $('#bulkDrawNameHelp').toggleClass('d-none', !bulk);
    $('#bulkTeamChoices').toggleClass('d-none', !bulk);
    $('#teamDrawTypeSection, #manualCategoryToggleGroup').toggleClass('d-none', !isTeam || bulk);
    if (bulk) {
      $('#categorySection, #type3Categories, #manualCategoryChoices').addClass('d-none');
      if (submitButton) submitButton.textContent = 'Create Selected Draws';
      return;
    }
    if (submitButton && isTeam) submitButton.textContent = 'Create Team Draw';
    var manual = isTeam && document.getElementById('manualTeamCategories').checked;
    var type = form.querySelector('input[name="draw_type_id"]:checked');
    var mixed = type && (type.dataset.code ? type.dataset.code === 'mixed_doubles' : (type.value === '3' || type.dataset.mixed === '1'));
    $('#manualCategoryChoices').toggleClass('d-none', !manual);
    $('#manualCategoryChoices input').prop('disabled', !manual);
    $('#categorySection').toggleClass('d-none', (!isTeam && selectedMode() !== 'individual') || (isTeam && (manual || mixed)));
    $('#type3Categories').toggleClass('d-none', !isTeam || manual || !mixed);
    $('#teamCategoryChoices input').prop('disabled', !isTeam || manual || mixed);
    $('#type3Categories input').prop('disabled', !isTeam || manual || !mixed);
    if (manual) {
      $('#teamCategoryChoices input, #type3Categories input').prop('checked', false);
    }
    updateAutomaticName();
  }

  $(document).on('change', '#bulkTeamDraws, #manualTeamCategories, input[name="draw_type_id"]', updateCategorySelection);
  function categoryName(category) {
    if (!category) return '';
    var label = category.id ? form.querySelector('label[for="' + category.id + '"]') : null;
    return (category.dataset.name || category.dataset.age || (label ? label.textContent : '') || '').trim();
  }

  function updateAutomaticName() {
    if (nameCustomized || document.getElementById('bulkTeamDraws').checked) return;
    var name = '';
    var mode = selectedMode();
    if (mode === 'individual') {
      var category = form.querySelector('input[name="category_choice"]:checked:not(:disabled)');
      var individualCategoryName = categoryName(category);
      if (individualCategoryName) name = individualCategoryName + ' – Singles';
    } else if (mode === 'team') {
      var type = form.querySelector('input[name="draw_type_id"]:checked');
      if (type) {
        var code = type.dataset.code || (type.value === '3' ? 'mixed_doubles' : '');
        var mixed = code === 'mixed_doubles';
        var typeLabel = form.querySelector('label[for="' + type.id + '"]');
        var typeName = { singles: 'Singles', reverse_singles: 'Reverse singles', doubles: 'Doubles', mixed_doubles: 'Mixed doubles' }[code]
          || (typeLabel ? typeLabel.textContent.trim().replace(/^Team\s*-\s*/i, '') : '');
        var selectionName = '';
        if (document.getElementById('manualTeamCategories').checked) {
          var choices = Array.from(form.querySelectorAll('input[name="manual_category_ids[]"]:checked:not(:disabled)'));
          var labels = Array.from(new Set(choices.map(categoryName).filter(Boolean)));
          if (mixed) {
            var age = choices.length ? choices[0].dataset.age : '';
            if (age && choices.every(function (choice) { return choice.dataset.age === age; }) &&
              choices.some(function (choice) { return choice.dataset.gender === 'boys'; }) &&
              choices.some(function (choice) { return choice.dataset.gender === 'girls'; })) selectionName = age;
          } else selectionName = labels.join(' + ');
          if (selectionName.length > 190) selectionName = labels[0] + ' + ' + (labels.length - 1) + ' categories';
        } else if (mixed) {
          var boys = form.querySelector('input[name="category_choice_boys"]:checked:not(:disabled)');
          var girls = form.querySelector('input[name="category_choice_girls"]:checked:not(:disabled)');
          if (boys && girls && boys.dataset.age && boys.dataset.age === girls.dataset.age) selectionName = boys.dataset.age;
        } else selectionName = categoryName(form.querySelector('input[name="category_choice"]:checked:not(:disabled)'));
        if (selectionName && typeName) name = selectionName + ' – ' + typeName;
      }
    }
    var nameInput = document.getElementById('drawName');
    if (nameInput) nameInput.value = name;
  }
  $(document).on('change', 'input[name="draw_type_id"], input[name="category_choice"], input[name="category_choice_boys"], input[name="category_choice_girls"], input[name="manual_category_ids[]"]', updateAutomaticName);
  $('#selectAllDrawTypes, #selectAllDrawCategories').on('click', function () {
    var name = this.id === 'selectAllDrawTypes' ? 'bulk_draw_types[]' : 'bulk_categories[]';
    var inputs = Array.from(form.querySelectorAll('input[name="' + name + '"]'));
    var checked = !inputs.every(function (input) { return input.checked; });
    inputs.forEach(function (input) { input.checked = checked; });
    form.dispatchEvent(new Event('change', { bubbles: true }));
  });

  function updateIndividualName() {
    updateAutomaticName();
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
    if (document.getElementById('bulkTeamDraws').checked) return bulkPayload();
    updateAutomaticName();
    var type = form.querySelector('input[name="draw_type_id"]:checked');
    var name = ($('#drawName').val() || '').trim();
    if (!type) { showError('Select a draw type'); return null; }
    var mixed = type.dataset.code ? type.dataset.code === 'mixed_doubles' : (type.value === '3' || type.dataset.mixed === '1');
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
    if (!name) { showError('Choose compatible categories to name the draw automatically'); return null; }
    return { _token: $('meta[name="csrf-token"]').attr('content'), drawName: name,
      draw_type_id: type.value, category_ids: categories, format_id: $('#format_id').val() || null, batch_key: creationKey() };
  }

  function bulkPayload() {
    var types = Array.from(form.querySelectorAll('input[name="bulk_draw_types[]"]:checked'));
    var categories = Array.from(form.querySelectorAll('input[name="bulk_categories[]"]:checked'));
    if (!types.length || !categories.length) { showError('Select draw types and categories to create'); return null; }
    var draws = [];
    types.forEach(function (type) {
      if (type.dataset.code === 'mixed_doubles') {
        var groups = {};
        categories.forEach(function (category) {
          if (!['boys', 'girls'].includes(category.dataset.gender)) return;
          var key = category.dataset.age;
          if (!groups[key]) groups[key] = [];
          groups[key] = groups[key].concat(JSON.parse(category.dataset.pivotIds));
        });
        Object.keys(groups).forEach(function (age) {
          draws.push({ drawName: age + ' – Mixed doubles', draw_type_id: type.value, category_ids: groups[age], format_id: $('#format_id').val() || null });
        });
      } else categories.forEach(function (category) {
        draws.push({ drawName: category.dataset.name + ' – ' + type.dataset.name.replace(/^Team\s*-\s*/i, ''),
          draw_type_id: type.value, category_ids: JSON.parse(category.dataset.pivotIds), format_id: $('#format_id').val() || null });
      });
    });
    if (!draws.length) { showError('Select boys and girls categories for mixed doubles'); return null; }
    return { _token: $('meta[name="csrf-token"]').attr('content'), draws: draws, batch_key: creationKey() };
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

  function renderPreview(readiness, target) {
    var container = document.getElementById('teamDrawPreview');
    if (target) container = target;
    container.replaceChildren();
    container.classList.remove('d-none');
    if (readiness.team_count !== undefined) previewLine(container, (readiness.format_name || 'No format') + ': ' + readiness.team_count + ' teams, ' +
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
          var home = (rubber.slots || []).map(function (slot) { return slot.team1_name || 'Missing player'; }).join(' + ');
          var away = (rubber.slots || []).map(function (slot) { return slot.team2_name || 'Missing player'; }).join(' + ');
          previewLine(detail, home + ' vs ' + away, 'small text-muted');
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
      if (revision !== previewRevision || selectedMode() !== 'team') return;
      if (response.draws && response.draws.length > 1) {
        var container = document.getElementById('teamDrawPreview'); container.replaceChildren(); container.classList.remove('d-none');
        response.draws.forEach(function (draw) {
          var section = document.createElement('details'); section.className = 'border rounded p-2 mt-2';
          var summary = document.createElement('summary'); summary.textContent = draw.drawName + (draw.readiness.can_create === false ? ' — needs attention' : '');
          section.appendChild(summary); var body = document.createElement('div'); section.appendChild(body); renderPreview(draw.readiness, body); container.appendChild(section);
        });
      } else renderPreview(response.readiness);
    })
      .fail(responseError).always(function () { button.disabled = false; });
  });
  form.addEventListener('change', function () { batchKey = null; previewRevision++; $('#teamDrawPreview').empty().addClass('d-none'); });
  form.addEventListener('input', function (event) {
    if (event.target && event.target.id === 'drawName') nameCustomized = !!event.target.value.trim();
    batchKey = null; previewRevision++; $('#teamDrawPreview').empty().addClass('d-none');
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

    updateAutomaticName();
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
