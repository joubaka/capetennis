(function (window, document, $) {
  'use strict';

  var form = document.getElementById('createDrawForm');
  if (!form || !$) return;

  var data = window.HeadOffice || {};
  var submitButton = form.querySelector('button[type="submit"]');
  var previewRevision = 0;
  var nameCustomized = false;
  var individualNameDraft = '';
  var individualNameCustomized = false;
  var creationPending = false;
  var creationTimer = null;
  var creationPercent = 0;
  var creationControls = [];
  var creationButtonText = '';
  var previewPending = false;

  function stopCreationTimer() {
    if (creationTimer !== null) window.clearInterval(creationTimer);
    creationTimer = null;
  }

  function renderCreationPercent(percent) {
    creationPercent = percent;
    $('#drawCreationPercent').text(percent + '%');
    $('#drawCreationBar').css('width', percent + '%').attr('aria-valuenow', percent);
  }

  function resetCreationProgress() {
    stopCreationTimer();
    renderCreationPercent(0);
    $('#drawCreationProgress, #drawCreationError').addClass('d-none');
    $('#drawCreationError').empty();
  }

  function startCreationProgress() {
    creationPending = true;
    creationButtonText = submitButton ? submitButton.textContent : '';
    creationControls = Array.from(form.querySelectorAll('input, select, textarea, button')).map(function (control) {
      var state = { control: control, disabled: control.disabled };
      control.disabled = true;
      return state;
    });
    if (submitButton) submitButton.textContent = 'Creating…';
    form.setAttribute('aria-busy', 'true');
    resetCreationProgress();
    $('#drawCreationProgress, #drawCreationSpinner').removeClass('d-none');
    $('#drawCreationStatus').text('Creating draws… Estimated progress');
    creationTimer = window.setInterval(function () {
      renderCreationPercent(Math.min(95, creationPercent + Math.max(1, Math.ceil((95 - creationPercent) * 0.08))));
      if (creationPercent === 95) stopCreationTimer();
    }, 700);
  }

  function completeCreationProgress() {
    stopCreationTimer();
    renderCreationPercent(100);
    $('#drawCreationSpinner').addClass('d-none');
    $('#drawCreationStatus').text('Draw creation confirmed. Opening draws…');
    // Keep controls and dismissal locked until navigation completes.
    form.setAttribute('aria-busy', 'false');
  }

  function failCreationProgress(xhr) {
    stopCreationTimer();
    creationPending = false;
    creationControls.forEach(function (state) { state.control.disabled = state.disabled; });
    var previewButton = document.getElementById('previewTeamDrawButton');
    if (previewButton) previewButton.disabled = previewPending;
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = creationButtonText;
    }
    form.setAttribute('aria-busy', 'false');
    renderCreationPercent(0);
    $('#drawCreationProgress, #drawCreationSpinner').addClass('d-none');
    var errors = xhr.responseJSON && xhr.responseJSON.errors;
    var message = errors ? Object.values(errors).flat().join(' ') :
      ((xhr.responseJSON && xhr.responseJSON.message) || 'Could not confirm draw creation. Check your draws before retrying.');
    $('#drawCreationError').text(message).removeClass('d-none');
    responseError(xhr);
  }

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
    nameCustomized = mode === 'individual' && individualNameCustomized;
    var nameInput = document.getElementById('drawName');
    if (nameInput) nameInput.value = nameCustomized ? individualNameDraft : '';
    var isTeam = mode === 'team';
    var isIndividual = mode === 'individual';
    $('#bulkTeamGroup').toggleClass('d-none', !isTeam);
    $('#bulkTeamDraws').prop('checked', false);
    $('#bulkTeamChoices').addClass('d-none');

    $('#manualIndividualCategories').prop('checked', false).prop('disabled', !isIndividual);
    $('#manualIndividualCategoryChoices').addClass('d-none');
    $('#manualIndividualCategoryChoices input').prop('disabled', true).prop('checked', false);
    $('#individualCategoryToggleGroup').toggleClass('d-none', !isIndividual);
    $('#individualCategorySource').empty().addClass('d-none');
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
    var isIndividual = selectedMode() === 'individual';
    var individualManual = isIndividual && document.getElementById('manualIndividualCategories').checked;
    $('#individualCategoryChoices').toggleClass('d-none', !isIndividual || individualManual);
    $('#individualCategoryChoices input').prop('disabled', !isIndividual || individualManual);
    $('#manualIndividualCategoryChoices').toggleClass('d-none', !individualManual);
    $('#manualIndividualCategoryChoices input').prop('disabled', !individualManual);
    var bulk = isTeam && document.getElementById('bulkTeamDraws').checked;
    var drawNameInput = document.getElementById('drawName');
    if (drawNameInput) drawNameInput.required = false;
    updateDrawNameVisibility();
    $('#bulkDrawNameHelp').toggleClass('d-none', !isTeam);
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
    // Clear inline visibility left by an older cached draw bundle when reopening.
    $('#categorySection').css('display', '').toggleClass('d-none', (!isTeam && selectedMode() !== 'individual') || (isTeam && (manual || mixed)));
    $('#type3Categories').toggleClass('d-none', !isTeam || manual || !mixed);
    $('#teamCategoryChoices input').prop('disabled', !isTeam || manual || mixed);
    $('#type3Categories input').prop('disabled', !isTeam || manual || !mixed);
    if (manual) {
      $('#teamCategoryChoices input, #type3Categories input').prop('checked', false);
    }
    updateAutomaticName();
  }

  $(document).on('change', '#bulkTeamDraws, #manualTeamCategories, input[name="draw_type_id"]', updateCategorySelection);
  $(document).on('change', '#manualIndividualCategories', function () {
    $('#individualCategoryChoices input, #manualIndividualCategoryChoices input').prop('checked', false);
    updateCategorySelection();
  });
  function categoryName(category) {
    if (!category) return '';
    var label = category.id ? form.querySelector('label[for="' + category.id + '"]') : null;
    return (category.dataset.name || category.dataset.age || (label ? label.textContent : '') || '').trim();
  }

  function updateDrawNameVisibility() {
    var category = form.querySelector('input[name="category_choice"]:checked:not(:disabled)');
    var source = selectedMode() === 'individual' && category ? (category.dataset.sourceLabel || category.dataset.sourceName || categoryName(category)) : '';
    $('#individualCategorySource').text(source ? 'Linked category: ' + source + '. Players come only from this category.' : '').toggleClass('d-none', !source);
    var ready = selectedMode() === 'individual' && !!data.individualDrawTypeId && !!category;
    $('#singleDrawNameGroup').toggleClass('d-none', !ready);
  }

  function updateAutomaticName() {
    updateDrawNameVisibility();
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

  $(document).on('hide.bs.modal', '#createDrawModal', function (event) {
    if (creationPending) event.preventDefault();
  });
  $(document).on('hidden.bs.modal', '#createDrawModal', function () {
    if (creationPending) return;
    resetCreationProgress();
    form.reset();
    individualNameDraft = '';
    individualNameCustomized = false;
    batchKey = null;
    previewRevision++;
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
    if (creationPending) return;
    var payload = teamPayload();
    if (!payload) return;
    var button = this;
    var revision = previewRevision;
    previewPending = true;
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
      .fail(responseError).always(function () { previewPending = false; button.disabled = creationPending; });
  });
  form.addEventListener('change', function () { batchKey = null; previewRevision++; $('#teamDrawPreview').empty().addClass('d-none'); });
  form.addEventListener('input', function (event) {
    if (event.target && event.target.id === 'drawName' && selectedMode() === 'individual') {
      individualNameDraft = event.target.value;
      individualNameCustomized = !!event.target.value.trim();
      nameCustomized = individualNameCustomized;
    }
    batchKey = null; previewRevision++; $('#teamDrawPreview').empty().addClass('d-none');
  });

  // Capture the individual submission before the legacy team-only handler.
  form.addEventListener('submit', function (event) {
    if (creationPending) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }
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
      startCreationProgress();
      $.post(data.createUrl, payload).done(function (response) {
        if (!response || response.success !== true) {
          failCreationProgress({ responseJSON: { message: 'Could not confirm draw creation. Check your draws before retrying.' } });
          return;
        }
        completeCreationProgress();
        window.location.reload();
      }).fail(failCreationProgress);
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

    startCreationProgress();

    $.post(data.individualCreateUrl, {
      _token: $('meta[name="csrf-token"]').attr('content'),
      drawName: drawName,
      draw_type_id: data.individualDrawTypeId,
      category_event_id: category.dataset.pivotId || category.value,
      category_selection_mode: document.getElementById('manualIndividualCategories').checked ? 'manual' : 'automatic'
    }).done(function (response) {
      if (!response || response.success !== true || !response.setup_url) {
        failCreationProgress({ responseJSON: { message: 'Could not confirm draw creation. Check your draws before retrying.' } });
        return;
      }
      completeCreationProgress();
      window.location.assign(response.setup_url);
    }).fail(failCreationProgress);
  }, true);

  setMode(selectedMode());
})(window, document, window.jQuery);

(function (window, document) {
  'use strict';
  var panel = document.querySelector('[data-event-draw-publication]');
  if (!panel) return;
  var eventIds = JSON.parse(panel.dataset.drawIds || '[]');
  var buttons = Array.from(panel.querySelectorAll('[data-bulk-draw-action]'));
  var feedback = panel.querySelector('[data-bulk-draw-feedback]');
  var refresh = panel.querySelector('[data-bulk-draw-refresh]');
  var summary = panel.querySelector('[data-draw-publication-summary]');
  var pending = false;
  var selections = Array.from(panel.querySelectorAll('[data-publication-draw-select]'));
  function updateSelection() {
    var count = selections.filter(function (input) { return input.checked; }).length;
    panel.querySelector('[data-publication-selected-count]').textContent = count + ' draws selected';
    if (window.HeadOfficeDrawPublicationUI) window.HeadOfficeDrawPublicationUI.unlock();
  }
  selections.forEach(function (input) { input.addEventListener('change', updateSelection); });
  panel.querySelectorAll('[data-select-publication-group], [data-select-publication-all], [data-clear-publication-selection]').forEach(function (control) {
    control.addEventListener('click', function () {
      if (pending || window.HeadOfficeDrawPublicationPending) return;
      var group = control.closest('[data-publication-group]');
      (group ? Array.from(group.querySelectorAll('[data-publication-draw-select]')) : selections).forEach(function (input) { input.checked = !control.hasAttribute('data-clear-publication-selection'); });
      updateSelection();
    });
  });
  buttons.forEach(function (button) {
    button.addEventListener('click', async function () {
      var selectedScope = button.dataset.bulkDrawScope === 'selected';
      var ids = selectedScope ? selections.filter(function (input) { return input.checked; }).map(function (input) { return Number(input.value); }) : eventIds;
      if (pending || window.HeadOfficeDrawPublicationPending || window.HeadOfficeDrawPublicationUnconfirmed) return;
      if (!ids.length) { feedback.classList.remove('d-none'); feedback.textContent = 'Select at least one draw.'; return; }
      var action = button.dataset.bulkDrawAction;
      if (!window.confirm((action === 'publish' ? 'Publish' : 'Unpublish') + ' ' + ids.length + (selectedScope ? ' selected draws?' : ' draws across every age-group tab?') + ' Match times have separate publication controls.')) return;
      pending = true;
      window.HeadOfficeDrawPublicationPending = true;
      buttons.forEach(function (control) { control.disabled = true; });
      selections.forEach(function (input) { input.disabled = true; });
      feedback.classList.remove('d-none');
      feedback.textContent = 'Updating ' + ids.length + (selectedScope ? ' selected draws…' : ' draws across the event…');
      summary.textContent = 'Updating publication status…';
      var changed = 0, unchanged = 0, failed = [], processed = 0, uncertain = false;
      try {
        for (var offset = 0; offset < ids.length; offset += 200) {
          var chunk = ids.slice(offset, offset + 200);
          var response = await window.fetch(panel.dataset.url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ operation: 'draws', action: action, draw_ids: chunk })
          });
          var result = await response.json();
          if (!response.ok || !Array.isArray(result.changed) || !Array.isArray(result.unchanged) || !Array.isArray(result.failed)
              || result.changed.length + result.unchanged.length + result.failed.length !== chunk.length) {
            throw new Error(result.message || 'The server could not confirm this batch.');
          }
          changed += result.changed.length;
          unchanged += result.unchanged.length;
          failed = failed.concat(result.failed);
          if (window.HeadOfficeDrawPublicationUI && result.draw_states) window.HeadOfficeDrawPublicationUI.apply(result);
          processed += chunk.length;
          feedback.textContent = processed + ' of ' + ids.length + ' draws checked…';
        }
      } catch (error) {
        uncertain = true;
        window.HeadOfficeDrawPublicationUnconfirmed = true;
        if (window.HeadOfficeDrawPublicationUI) window.HeadOfficeDrawPublicationUI.pause();
        failed.push({ name: 'Request interrupted', message: error.message || 'Could not confirm the request.' });
      } finally {
        if (window.HeadOfficeDrawPublicationUI && (uncertain || failed.length)) {
          window.HeadOfficeDrawPublicationUI.pause();
          try { await window.HeadOfficeDrawPublicationUI.reconcile(); }
          catch (_) { window.HeadOfficeDrawPublicationUI.pause(); }
        }
        feedback.textContent = changed + ' draws ' + (action === 'publish' ? 'published' : 'unpublished') + ', ' + unchanged + ' unchanged.'
          + (failed.length ? ' Issues: ' + failed.map(function (failure) { return failure.name + ': ' + failure.message; }).join('; ') : '')
          + (uncertain ? ' ' + (ids.length - processed) + ' remaining draws have unconfirmed status. Refresh before retrying.' : ' Refresh to see current draw statuses.');
        refresh.classList.remove('d-none');
        if (!window.HeadOfficeDrawPublicationUI) summary.textContent = uncertain ? 'Publication status unconfirmed. Refresh to see current counts.' : 'Publication status changed. Refresh to see current counts.';
        buttons.forEach(function (control) { control.disabled = Boolean(window.HeadOfficeDrawPublicationUnconfirmed); });
        pending = false;
        window.HeadOfficeDrawPublicationPending = false;
        if (window.HeadOfficeDrawPublicationUI) window.HeadOfficeDrawPublicationUI.unlock();
        if (!uncertain && !failed.length) window.location.reload();
      }
    });
  });
})(window, document);

(function (window, document) {
  'use strict';
  var panel = document.querySelector('[data-whole-day-publication]');
  if (!panel) return;
  var cards = panel.querySelector('[data-day-cards]');
  var template = panel.querySelector('[data-day-card-template]');
  var feedback = panel.querySelector('[data-day-feedback]');
  var retry = panel.querySelector('[data-day-status-retry]');
  var pending = false, unconfirmed = panel.dataset.initialUnconfirmed === 'true', activeDate = null;
  function controls(busy) {
    panel.querySelectorAll('[data-day-action-form] button').forEach(function (button) {
      button.disabled = busy || unconfirmed || button.dataset.disabled === 'true';
    });
    retry.disabled = busy;
    panel.setAttribute('aria-busy', busy ? 'true' : 'false');
  }
  function show(message) {
    feedback.classList.remove('d-none');
    feedback.textContent = message;
  }
  function restoreFocus() {
    if (!activeDate) return;
    var button = panel.querySelector('[data-day-card][data-date="' + activeDate + '"] [data-day-toggle]');
    if (button && !button.disabled) button.focus();
    else { feedback.setAttribute('tabindex', '-1'); feedback.focus(); }
  }
  function render(result) {
    if (result.success !== true || Number(result.event_id) !== Number(panel.dataset.eventId) || !/^[a-f0-9]{64}$/.test(result.revision) || !Array.isArray(result.days)) throw new Error('Could not confirm the schedule status.');
    var dates = new Set();
    result.days.forEach(function (day) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(day.date) || dates.has(day.date) || typeof day.label !== 'string'
          || ['Published', 'Partly published', 'Unpublished', 'Updates not published', 'Not scheduled'].indexOf(day.status) < 0
          || ['saved', 'published', 'matched', 'pending'].some(function (key) { return !Number.isInteger(day[key]) || day[key] < 0; })) throw new Error('Could not confirm all day statuses.');
      dates.add(day.date);
    });
    var updated = result.days.map(function (day) {
      var card = template.content.firstElementChild.cloneNode(true);
      var published = day.status === 'Published';
      card.dataset.date = day.date;
      card.querySelector('[data-day-label]').textContent = day.label;
      card.querySelector('[data-day-status]').textContent = day.status;
      card.querySelector('[data-day-counts]').textContent = day.saved + ' saved match times · ' + day.published + ' published snapshot times';
      card.querySelector('[data-day-pending]').textContent = day.matched + ' saved times match the published snapshots · ' + day.pending + ' matches with pending changes';
      card.querySelector('[data-day-review]').href = panel.dataset.calendarUrl + '?date=' + encodeURIComponent(day.date);
      var main = card.querySelector('[data-main-day-action]');
      main.action = published ? panel.dataset.hideUrl : panel.dataset.publishUrl;
      var button = card.querySelector('[data-day-toggle]');
      button.textContent = published ? 'Hide day' : (day.published > 0 || day.status === 'Updates not published' ? 'Publish updates' : 'Publish day');
      button.setAttribute('aria-pressed', published ? 'true' : 'false');
      button.classList.toggle('btn-outline-danger', published);
      button.classList.toggle('btn-success', !published);
      button.dataset.disabled = (published ? day.published === 0 : day.saved === 0) ? 'true' : 'false';
      card.querySelector('[data-day-hide-menu]').hidden = published || day.published === 0;
      card.querySelectorAll('[data-day-action-form]').forEach(function (form) {
        form.querySelector('[name="date"]').value = day.date;
        form.querySelector('[name="revision"]').value = result.revision;
        if (form !== main) form.querySelector('button').dataset.disabled = day.published === 0 ? 'true' : 'false';
      });
      return card;
    });
    cards.replaceChildren.apply(cards, updated);
    if (!updated.length) {
      var empty = document.createElement('p');
      empty.textContent = 'Not scheduled · No saved or published match times yet.';
      cards.appendChild(empty);
    }
    unconfirmed = false;
    retry.hidden = true;
  }
  async function readStatus() {
    var response = await window.fetch(panel.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var result = await response.json();
    if (!response.ok) throw new Error('Status check failed.');
    render(result);
  }
  panel.addEventListener('submit', async function (event) {
    var form = event.target;
    if (!form.matches('[data-day-action-form]')) return;
    event.preventDefault();
    if (pending || unconfirmed) return;
    activeDate = form.querySelector('[name="date"]').value;
    pending = true;
    controls(true);
    show('Updating this whole day’s schedule publication…');
    try {
      var response = await window.fetch(form.action, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ date: form.querySelector('[name="date"]').value, revision: form.querySelector('[name="revision"]').value }) });
      var result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.message || 'The publication request could not be confirmed.');
      render(result);
      show(result.action === 'hide' ? 'Whole-day times hidden. All day statuses are current.' : 'Whole-day times published. All day statuses are current.');
    } catch (error) {
      unconfirmed = true;
      show(error.message + ' Checking the current saved publication status…');
      try {
        await readStatus();
        show(error.message + ' Current day statuses have been refreshed. Review before another action.');
      } catch (_) {
        show('Publication status unconfirmed. Actions are paused; retry the status check before changing another day.');
        retry.hidden = false;
      }
    } finally {
      pending = false;
      controls(false);
      restoreFocus();
    }
  });
  retry.addEventListener('click', async function () {
    if (pending) return;
    pending = true;
    controls(true);
    try { await readStatus(); show('Current day statuses refreshed.'); }
    catch (_) { unconfirmed = true; show('Publication status unconfirmed. Retry the status check when the connection is available.'); }
    finally { pending = false; controls(false); restoreFocus(); }
  });
  controls(false);
})(window, document);
