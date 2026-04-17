/**
 * Form Wizard (Checkout)
 */
'use strict';

// rateyo (jquery)
$(function () {
  var readOnlyRating = $('.read-only-ratings');
  if (readOnlyRating.length) {
    readOnlyRating.rateYo({
      rtl: isRtl,
      rating: 4,
      starWidth: '20px'
    });
  }
});

(function () {

  // ===============================
  // INIT STEPPER
  // ===============================
  const wizardCheckout = document.querySelector('#wizard-checkout');
  if (!wizardCheckout) return;

  const wizardCheckoutNext = wizardCheckout.querySelectorAll('.btn-next');

  const stepper = new Stepper(wizardCheckout, {
    linear: false,
    animation: true
  });

  // Always allow next (except confirm-details step which has its own gated button)
  wizardCheckoutNext.forEach(btn => {
    btn.addEventListener('click', function () {
      // When leaving Step 1 (Registration) heading to Confirm Details, build cards
      var currentStep = $(wizardCheckout).find('.step.active').index('.step');
      if (currentStep === 0 && typeof REQUIRE_PROFILE_UPDATE !== 'undefined' && REQUIRE_PROFILE_UPDATE) {
        buildConfirmPlayerCards();
      }
      stepper.next();
    });
  });

  // ===============================
  // STEP 2 – CONFIRM PLAYER DETAILS
  // ===============================
  function buildConfirmPlayerCards() {
    var $container = $('#confirmPlayerCards');
    $container.html('<p class="text-muted">Loading player details...</p>');

    var selectedPlayers = [];
    $('.playerRow').each(function () {
      var $playerSelect = $(this).find('.select2player');
      var playerId = $playerSelect.val();
      if (playerId && playerId !== '0') {
        var playerName = $playerSelect.find(':selected').text().trim() || 'Player';
        if (!selectedPlayers.find(function (p) { return p.id === playerId; })) {
          selectedPlayers.push({ id: playerId, name: playerName });
        }
      }
    });

    if (selectedPlayers.length === 0) {
      $container.html('<div class="alert alert-warning">No players selected. Go back and select players first.</div>');
      $('#goToCart').prop('disabled', true);
      return;
    }

    $container.empty();

    selectedPlayers.forEach(function (player) {
      var cardId = 'confirm-card-' + player.id;
      $container.append(
        '<div id="' + cardId + '" class="confirm-player-card card mb-3 border-warning" data-player-id="' + player.id + '" data-confirmed="0">' +
          '<div class="card-header d-flex justify-content-between align-items-center py-3">' +
            '<div><h6 class="mb-0"><i class="ti ti-user me-1"></i> ' + player.name + '</h6></div>' +
            '<span class="badge bg-warning text-dark confirm-badge"><i class="ti ti-clock me-1"></i>Pending</span>' +
          '</div>' +
          '<div class="card-body">' +
            '<div class="player-detail-loading text-center py-3"><span class="spinner-border spinner-border-sm me-1"></span> Loading details...</div>' +
            '<div class="player-detail-form" style="display:none;"></div>' +
          '</div>' +
        '</div>'
      );

      // Fetch player details via AJAX
      $.ajax({
        url: APP_URL + '/register/player-details',
        type: 'POST',
        data: {
          _token: $('meta[name="csrf-token"]').attr('content'),
          player_id: player.id
        },
        success: function (res) {
          var $card = $('#' + cardId);
          $card.find('.player-detail-loading').hide();
          var hasExistingDob = !!res.dateOfBirth;
          $card.find('.player-detail-form').show().html(
            '<div class="row g-3">' +
              '<div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control pd-name" value="' + (res.name || '') + '" required></div>' +
              '<div class="col-md-6"><label class="form-label">Surname <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control pd-surname" value="' + (res.surname || '') + '" required></div>' +
              '<div class="col-md-6"><label class="form-label">Date of Birth <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control pd-dob" value="' + (res.dateOfBirth || '') + '" placeholder="YYYY-MM-DD" required></div>' +
              '<div class="col-md-6"><label class="form-label">Gender <span class="text-danger">*</span></label>' +
                '<select class="form-select pd-gender" required>' +
                  '<option value="">Select</option>' +
                  '<option value="Male"' + (res.gender === 'Male' ? ' selected' : '') + '>Male</option>' +
                  '<option value="Female"' + (res.gender === 'Female' ? ' selected' : '') + '>Female</option>' +
                '</select></div>' +
              '<div class="col-md-6"><label class="form-label">Cell Number <span class="text-danger">*</span></label>' +
                '<input type="tel" class="form-control pd-cell" value="' + (res.cellNr || '') + '" required></div>' +
              '<div class="col-md-6"><label class="form-label">Email</label>' +
                '<input type="email" class="form-control pd-email" value="' + (res.email || '') + '"></div>' +
            '</div>' +
            '<div class="d-flex gap-2 justify-content-end mt-3">' +
              '<button type="button" class="btn btn-primary btn-save-player" data-player-id="' + res.id + '">' +
                '<i class="ti ti-device-floppy me-1"></i> Save & Confirm</button>' +
            '</div>'
          );

          // Initialize flatpickr on DOB field
          $card.find('.pd-dob').flatpickr({
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            allowInput: true,
            altInput: true,
            altFormat: 'j F Y',
            defaultDate: res.dateOfBirth || null
          });

          // If DOB was pre-filled (confirmed in 2026+), auto-confirm is allowed
          // If DOB is blank, user must enter it before confirming
        },
        error: function () {
          var $card = $('#' + cardId);
          $card.find('.player-detail-loading').hide();
          $card.find('.player-detail-form').show().html(
            '<div class="alert alert-danger mb-0">Failed to load player details.</div>'
          );
        }
      });
    });

    updateGoToCartButton();
  }

  function updateGoToCartButton() {
    var allConfirmed = true;
    $('.confirm-player-card').each(function () {
      if ($(this).attr('data-confirmed') !== '1') {
        allConfirmed = false;
      }
    });
    $('#goToCart').prop('disabled', !allConfirmed);
  }

  function markPlayerConfirmed($card) {
    $card.attr('data-confirmed', '1');
    $card.removeClass('border-warning').addClass('border-success');
    $card.find('.confirm-badge')
      .removeClass('bg-warning text-dark')
      .addClass('bg-success')
      .html('<i class="ti ti-check me-1"></i>Confirmed');
    // Disable form fields
    $card.find('.player-detail-form input, .player-detail-form select').prop('disabled', true);
    $card.find('.btn-save-player').hide();
    updateGoToCartButton();
  }

  // Save changes then confirm
  $(document).on('click', '.btn-save-player', function () {
    var $btn = $(this);
    var $card = $btn.closest('.confirm-player-card');
    var $form = $card.find('.player-detail-form');
    var playerId = $btn.attr('data-player-id');
    var cardId = $card.attr('id');

    // Collect values before disabling anything
    var formData = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      player_id: playerId,
      name: $form.find('.pd-name').val(),
      surname: $form.find('.pd-surname').val(),
      dateOfBirth: $form.find('.pd-dob').val(),
      gender: $form.find('.pd-gender').val(),
      cellNr: $form.find('.pd-cell').val(),
      email: $form.find('.pd-email').val()
    };

    // Basic validation
    var valid = true;
    $form.find('[required]').each(function () {
      if (!$(this).val()) { $(this).addClass('is-invalid'); valid = false; }
      else { $(this).removeClass('is-invalid'); }
    });
    if (!valid) { toastr.error('Please fill in all required fields.'); return; }

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    $.ajax({
      url: APP_URL + '/register/update-player-details',
      type: 'POST',
      data: formData,
      success: function (res) {
        // Re-query the card from the DOM to avoid stale references
        var $freshCard = $('#' + cardId);
        if (res.player) {
          var fullName = res.player.name + ' ' + res.player.surname;
          $freshCard.find('h6').html('<i class="ti ti-user me-1"></i> ' + fullName);
        }
        markPlayerConfirmed($freshCard);
        toastr.success(res.message || 'Player details saved and confirmed.');
      },
      error: function (xhr) {
        $btn.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Save & Confirm');
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
          var msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
          toastr.error(msg);
        } else {
          toastr.error('Failed to save. Please try again.');
        }
      }
    });
  });

  // Clear validation on input in confirm cards
  $(document).on('input change', '.player-detail-form input, .player-detail-form select', function () {
    $(this).removeClass('is-invalid');
  });

  // ===============================
  // TERMS STEP – on entering Step 4
  // ===============================
  var hasTermsStep = $('#checkout-terms').length > 0;

  if (hasTermsStep) {
    $('#goToTerms').on('click', function () {
      // Copy cart items to terms order summary
      var cartHtml = $('#myUl').html();
      $('#termsOrderList').html(cartHtml);

      // Build player CoC status
      buildPlayerCocStatus();
    });

    // Terms checkbox toggles submit button
    $(document).on('change', '#acceptTerms', function () {
      updateConfirmButton();
    });
  }

  function updateConfirmButton() {
    // When terms step is not rendered, no gating needed
    if (!hasTermsStep) return;

    var termsChecked = $('#acceptTerms').length === 0 || $('#acceptTerms').is(':checked');
    var allAccepted = true;

    // Check if all players have accepted CoC
    $('.coc-player-card').each(function () {
      if ($(this).attr('data-accepted') !== '1') {
        allAccepted = false;
      }
    });

    if (termsChecked && allAccepted) {
      $('#payment').prop('disabled', false);
      $('#termsAcceptedField').val('1');
    } else {
      $('#payment').prop('disabled', true);
      $('#termsAcceptedField').val('0');
    }
  }

  function buildPlayerCocStatus() {
    var $container = $('#playerCocStatus');
    if ($container.length === 0) return;
    $container.html('<p class="text-muted">Checking player status...</p>');

    var selectedPlayers = [];
    $('.playerRow').each(function () {
      var $playerSelect = $(this).find('.select2player');
      var playerId = $playerSelect.val();
      if (playerId && playerId !== '0') {
        var playerName = $playerSelect.find(':selected').text().trim() || 'Player';
        // Avoid duplicates
        if (!selectedPlayers.find(p => p.id === playerId)) {
          selectedPlayers.push({ id: playerId, name: playerName });
        }
      }
    });

    if (selectedPlayers.length === 0) {
      $container.html('<div class="alert alert-warning">No players selected. Go back and select players first.</div>');
      return;
    }

    $container.empty();

    selectedPlayers.forEach(function (player) {
      var cardId = 'coc-card-' + player.id;
      $container.append(
        '<div id="' + cardId + '" class="coc-player-card border rounded p-3 mb-3" data-player-id="' + player.id + '" data-accepted="0">' +
          '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' + player.name + '</strong></div>' +
            '<div class="coc-status"><span class="badge bg-secondary">Checking...</span></div>' +
          '</div>' +
          '<div class="coc-action mt-2"></div>' +
        '</div>'
      );

      // AJAX check CoC status
      $.ajax({
        url: APP_URL + '/agreements/check',
        type: 'POST',
        data: {
          _token: $('meta[name="csrf-token"]').attr('content'),
          player_id: player.id
        },
        success: function (res) {
          var $card = $('#' + cardId);
          if (res.accepted) {
            $card.attr('data-accepted', '1');
            $card.find('.coc-status').html(
              '<span class="badge bg-success"><i class="ti ti-check me-1"></i>Accepted</span>'
            );
            $card.find('.coc-action').html(
              '<a href="' + APP_URL + '/agreements" target="_blank" class="btn btn-sm btn-outline-success">' +
                '<i class="ti ti-external-link me-1"></i>View Accepted Code of Conduct</a>'
            );
          } else {
            $card.attr('data-accepted', '0');
            $card.find('.coc-status').html(
              '<span class="badge bg-warning text-dark"><i class="ti ti-alert-triangle me-1"></i>Not Accepted</span>'
            );

            var actionHtml = '<div class="mt-2">';
            if (res.is_minor) {
              actionHtml += '<p class="text-muted small mb-2">This player is a minor. A parent/guardian must accept on their behalf.</p>' +
                '<div class="row g-2 mb-2">' +
                  '<div class="col-md-4"><input type="text" class="form-control form-control-sm guardian-name" placeholder="Guardian Name" required></div>' +
                  '<div class="col-md-4"><input type="email" class="form-control form-control-sm guardian-email" placeholder="Guardian Email" required></div>' +
                  '<div class="col-md-4"><input type="text" class="form-control form-control-sm guardian-relationship" placeholder="Relationship" required></div>' +
                '</div>';
            }
            actionHtml += '<button type="button" class="btn btn-sm btn-primary accept-coc-btn" data-player-id="' + player.id + '">' +
              '<i class="ti ti-check me-1"></i>Accept Code of Conduct</button></div>';

            $card.find('.coc-action').html(actionHtml);
          }
          updateConfirmButton();
        },
        error: function () {
          $('#' + cardId).find('.coc-status').html(
            '<span class="badge bg-danger">Error checking status</span>'
          );
        }
      });
    });
  }

  // Accept CoC for a player
  $(document).on('click', '.accept-coc-btn', function () {
    var $btn = $(this);
    var playerId = $btn.attr('data-player-id');
    var $card = $btn.closest('.coc-player-card');

    var postData = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      player_id: playerId
    };

    // Guardian fields for minors
    var $guardianName = $card.find('.guardian-name');
    if ($guardianName.length) {
      postData.guardian_name = $guardianName.val();
      postData.guardian_email = $card.find('.guardian-email').val();
      postData.guardian_relationship = $card.find('.guardian-relationship').val();

      if (!postData.guardian_name || !postData.guardian_email || !postData.guardian_relationship) {
        toastr.error('Please fill in all guardian details.');
        return;
      }
    }

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Accepting...');

    $.ajax({
      url: APP_URL + '/agreements/accept',
      type: 'POST',
      data: postData,
      success: function (res) {
        $card.attr('data-accepted', '1');
        $card.find('.coc-status').html(
          '<span class="badge bg-success"><i class="ti ti-check me-1"></i>Accepted</span>'
        );
        $card.find('.coc-action').html(
          '<a href="' + APP_URL + '/agreements" target="_blank" class="btn btn-sm btn-outline-success">' +
            '<i class="ti ti-external-link me-1"></i>View Accepted Code of Conduct</a>'
        );
        toastr.success('Code of Conduct accepted.');
        updateConfirmButton();
      },
      error: function (xhr) {
        $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i>Accept Code of Conduct');
        if (xhr.status === 419) {
          toastr.error('Session expired. Please refresh the page.');
        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
          var errors = xhr.responseJSON.errors;
          var msg = Object.values(errors).flat().join(', ');
          toastr.error(msg);
        } else {
          toastr.error('Failed to accept. Please try again.');
        }
      }
    });
  });

  // ===============================
  // ADD PLAYER ROW (FIXED)
  // ===============================
  $('#addPlayer').on('click', function () {

    const $firstRow = $('.playerRow').first();

    // 🔥 DESTROY select2 before cloning
    $firstRow.find('.select2player').each(function () {
      if ($(this).data('select2')) {
        $(this).select2('destroy');
      }
    });

    $firstRow.find('.select2category').each(function () {
      if ($(this).data('select2')) {
        $(this).select2('destroy');
      }
    });

    // Clone clean DOM
    const $clone = $firstRow.clone();

    // Re-init original row
    initSelect2();

    const num = $('.playerRow').length + 1;
    $clone.find('.playerNr').text('Player ' + num);

    // Reset cloned selects – clear options for AJAX player select
    $clone.find('.select2player').html('<option value="0">Please select</option>');
    $clone.find('.select2category').val('0');

    $clone.insertBefore('#tool-placeholder');

    // Init select2 on clone
    initSelect2();
  });

  // ===============================
  // UPDATE CART ON CHANGE
  // ===============================
  $(document).on('change', '.select2player, .select2category', function () {
    appendPlayers($('.playerRow'));
  });

  // ===============================
  // ADD PLAYER MODAL HANDLING
  // ===============================
  window.addPlayerTargetIndex = null;

  $(document).on(
    'click',
    '.select2-results__option .btn[data-bs-target="#addPlayerModal"]',
    function () {
      window.addPlayerTargetIndex = $(this).data('index');
    }
  );

  $('#createPlayerButton').on('click', function () {
    let formData = $('.formPlayer').serialize();

    $.ajax({
      url: APP_URL + '/backend/player/store',
      type: 'POST',
      data: formData,
      success: function (res) {
        const fullName = res.name + ' ' + res.surname;
        const index = window.addPlayerTargetIndex ?? $('.select2player').length - 1;

        const $select = $('.select2player').eq(index);
        const option = new Option(fullName, res.id, true, true);
        $(option).attr('data-name', fullName);

        $select.append(option).trigger('change');

        $('#addPlayerModal').modal('hide');
        $('.formPlayer')[0].reset();

        toastr.success(fullName + ' added');
      },
      error: function () {
        toastr.error('Failed to add player');
      }
    });
  });

  // ===============================
  // SELECT2 INIT (SAFE)
  // ===============================
  function initSelect2() {

    $('.select2category').each(function () {
      const $this = $(this);

      if ($this.data('select2')) return;

      if (!$this.parent().hasClass('position-relative')) {
        $this.wrap('<div class="position-relative"></div>');
      }

      $this.select2({
        dropdownParent: $this.parent(),
        placeholder: 'Select category'
      });
    });

    $('.select2player').each(function (index) {
      const $this = $(this);

      if ($this.data('select2')) return;

      if (!$this.parent().hasClass('position-relative')) {
        $this.wrap('<div class="position-relative"></div>');
      }

      $this.select2({
        dropdownParent: $this.parent(),
        placeholder: 'Search for a player...',
        minimumInputLength: 2,
        ajax: {
          url: APP_URL + '/register/search-players',
          dataType: 'json',
          delay: 300,
          data: function (params) {
            return { q: params.term || '' };
          },
          processResults: function (data) {
            return data;
          },
          cache: true
        },
        language: {
          noResults: function () {
            return $(
              "<button class='btn btn-sm btn-primary' data-index='" +
              index +
              "' data-bs-toggle='modal' data-bs-target='#addPlayerModal'>Add Player</button>"
            );
          },
          inputTooShort: function () {
            return 'Type at least 2 characters to search...';
          }
        }
      });
    });
  }

  initSelect2();

})();

// ===============================
// BUILD CART + TOTALS
// ===============================
function appendPlayers(rows) {

  $('.playersCart').empty();
  $('#myUl').empty();

  let total = 0;

  rows.each(function () {

    const $playerSelect = $(this).find('.select2player');
    const $categorySelect = $(this).find('.select2category');

    const playerId = $playerSelect.val();
    const categoryId = $categorySelect.val();

    // ⛔ Skip empty rows
    if (!playerId || playerId === '0' || !categoryId || categoryId === '0') {
      return;
    }

    const playerName = $playerSelect.find(':selected').text().trim();
    const categoryName = $categorySelect.find(':selected').data('name');

    const price = parseFloat(
      $categorySelect.find(':selected').data('price') ||
      $('#eventPrice').val()
    );

    $('.playersCart').append(
      `<div>${playerName} – ${categoryName}</div>`
    );

    $('#myUl').append(`
      <li class="list-group-item d-flex justify-content-between">
        <span>${playerName} – ${categoryName}</span>
        <strong>R${price.toFixed(2)}</strong>
      </li>
    `);

    total += price;
  });

  $('.orderTotal').text('R' + total.toFixed(2));
  $('#amount').val(total.toFixed(2));
  $('#item_name').val($('#myevent').val());
}
