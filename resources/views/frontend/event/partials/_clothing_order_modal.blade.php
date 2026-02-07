<style>
/* MOBILE FIX: force scroll inside modal */
@media (max-width: 576px) {
  #clothing-order-modal .modal-dialog {
    margin: 0.5rem;
    height: calc(100dvh - 1rem);
    max-height: calc(100dvh - 1rem);
  }

  #clothing-order-modal .modal-content {
    height: 100%;
    max-height: 100%;
  }

  #clothing-order-modal .modal-body {
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    max-height: calc(100dvh - 160px); /* header + footer */
  }
}
</style>



{{-- CLOTHING ORDER MODAL --}}
<div class="modal fade" id="clothing-order-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      {{-- HEADER --}}
      <div class="modal-header">
        <h5 class="modal-title">
          Clothing Order – <span id="orderPlayerName" class="fw-semibold"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      {{-- FORM --}}
      <form id="clothingOrderForm" method="POST" action="{{ route('clothingOrder.store') }}">
        @csrf

        {{-- CONTEXT --}}
        <input type="hidden" name="event_id" id="order_event_id">
        <input type="hidden" name="region_id" id="order_region_id">
        <input type="hidden" name="player_id" id="order_player_id">
        <input type="hidden" name="team_id" id="order_team_id">

        <div class="modal-body">

          {{-- ITEMS (AJAX HTML INJECTED HERE) --}}
          <div id="clothingOrderList">
            <div class="alert alert-info mb-0">Loading clothing…</div>
          </div>

          {{-- TOTAL --}}
          <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
            <strong>Total</strong>
            <strong id="orderTotal">R0.00</strong>
          </div>

        </div>

        {{-- FOOTER --}}
        <div class="modal-footer">
          <button type="button"
                  class="btn btn-outline-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>

          <button type="submit"
                  class="btn btn-primary"
                  id="submitClothingOrderBtn">
            Submit Order
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

{{-- ROUTE --}}
<script>
  window.CLOTHING_ITEMS_URL = @json(route('get.region.clothing.items'));
  console.log('[ClothingModal] CLOTHING_ITEMS_URL:', window.CLOTHING_ITEMS_URL);
</script>

<script>
/* ============================================================
   CLOTHING ORDER MODAL – FRONT-END LOGIC (DEBUG)
   ============================================================ */

(function () {

  const modalEl = document.getElementById('clothing-order-modal');
  console.log('[ClothingModal] modal found:', !!modalEl);

  if (!modalEl) return;

  /* -------------------------------
     OPEN MODAL → LOAD ITEMS
  -------------------------------- */
  modalEl.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    console.log('[ClothingModal] show modal triggered by:', btn);

    if (!btn) return;

    console.log('[ClothingModal] trigger dataset:', btn.dataset);

    $('#order_event_id').val(btn.dataset.eventid || '');
    $('#order_region_id').val(btn.dataset.region || '');
    $('#order_player_id').val(btn.dataset.playerid || '');
    $('#order_team_id').val(btn.dataset.team || '');
    $('#orderPlayerName').text(btn.dataset.name || '');

    $('#orderTotal').text('R0.00');
    $('#clothingOrderList').html(
      '<div class="alert alert-info mb-0">Loading clothing…</div>'
    );

    console.log('[ClothingModal] loading items for region:', btn.dataset.region);

    $.post(window.CLOTHING_ITEMS_URL, { region: btn.dataset.region })
      .done(function (html) {
        console.log('[ClothingModal] AJAX success – HTML length:', html?.length);
        $('#clothingOrderList').html(html);
      })
      .fail(function (xhr) {
        console.error('[ClothingModal] AJAX failed:', xhr);
        $('#clothingOrderList').html(
          '<div class="alert alert-danger mb-0">Failed to load clothing items.</div>'
        );
      });
  });

  /* -------------------------------
     TOGGLE ITEM OPTIONS
  -------------------------------- */
  $(document).on('change', '.item-toggle', function () {
    const card = $(this).closest('.clothing-item');
    console.log('[ClothingModal] item toggle:', card.data('item'), this.checked);

    if (this.checked) {
      card.find('.item-options').removeClass('d-none');
    } else {
      card.find('.item-options')
        .addClass('d-none')
        .find('select').val('').end()
        .find('input[type=number]').val(1);

      card.find('.item-total').text('R0.00');
    }

    updateTotals();
  });

  /* -------------------------------
     SIZE / QTY CHANGE
  -------------------------------- */
  $(document).on('change input', '.size-select, .qty-input', function () {
    console.log('[ClothingModal] option change:', this);
    updateTotals();
  });

  /* -------------------------------
     TOTAL CALCULATION
  -------------------------------- */
  function updateTotals() {
    let total = 0;

    $('.clothing-item').each(function () {
      const card = $(this);
      const itemId = card.data('item');

      if (!card.find('.item-toggle').is(':checked')) return;

      const price = parseFloat(card.data('price'));
      const qty   = parseInt(card.find('.qty-input').val(), 10) || 0;
      const size  = card.find('.size-select').val();

      console.log('[ClothingModal] calc item', {
        itemId, price, qty, size
      });

      if (!size || qty < 1) {
        card.find('.item-total').text('R0.00');
        return;
      }

      const itemTotal = price * qty;
      total += itemTotal;

      card.find('.item-total').text('R' + itemTotal.toFixed(2));
    });

    console.log('[ClothingModal] total updated:', total);
    $('#orderTotal').text('R' + total.toFixed(2));
  }

  /* -------------------------------
     SUBMIT → BUILD PAYLOAD
  -------------------------------- */
$('#clothingOrderForm').on('submit', function (e) {

  console.log('[ClothingModal] Building items payload');

  // remove old dynamic inputs
  $(this).find('.order-line').remove();

  let hasItems = false;

  $('.clothing-item').each(function () {
    const card = $(this);

    const checked = card.find('.item-toggle').is(':checked');
    if (!checked) return;

    const itemId = card.data('item');
    const size   = card.find('.size-select').val();
    const qty    = parseInt(card.find('.qty-input').val(), 10);

    console.log('[ClothingModal] item:', { itemId, size, qty });

    if (!size || qty < 1) return;

    hasItems = true;

    $('<input>', {
      type: 'hidden',
      name: `items[${itemId}][size]`,
      value: size,
      class: 'order-line'
    }).appendTo('#clothingOrderForm');

    $('<input>', {
      type: 'hidden',
      name: `items[${itemId}][qty]`,
      value: qty,
      class: 'order-line'
    }).appendTo('#clothingOrderForm');
  });

  if (!hasItems) {
    e.preventDefault();
    alert('Please select at least one item with size and quantity.');
    return false;
  }

  console.log('[ClothingModal] Payload built, submitting form');
  // allow normal submit
});


})();
</script>
