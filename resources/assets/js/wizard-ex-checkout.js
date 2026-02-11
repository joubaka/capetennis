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

  // Always allow next
  wizardCheckoutNext.forEach(btn => {
    btn.addEventListener('click', () => stepper.next());
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

    // Reset cloned selects
    $clone.find('select').val('0');

    $clone.insertBefore('#tool-placeholder');

    // Init select2 on clone
    initSelect2();
  });

  // ===============================
  // UPDATE CART ON CHANGE
  // ===============================
  $(document).on('change', '.select2Basic', function () {
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
        placeholder: 'Select player',
        language: {
          noResults: function () {
            return $(
              "<button class='btn btn-sm btn-primary' data-index='" +
              index +
              "' data-bs-toggle='modal' data-bs-target='#addPlayerModal'>Add Player</button>"
            );
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

    const playerName = $playerSelect.find(':selected').data('name');
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
