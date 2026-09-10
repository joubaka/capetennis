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
        <input type="hidden" name="request_token" id="clothing_request_token">

        <div class="modal-body">

          {{-- ITEMS (AJAX HTML INJECTED HERE) --}}
          <div id="clothingOrderList">
            <div class="alert alert-info mb-0">Loading clothing…</div>
          </div>

          {{-- TOTAL --}}
          <div class="border-top pt-3 mt-3">
            <div class="d-flex justify-content-between"><span>Clothing subtotal</span><span id="orderSubtotal">R0.00</span></div>
            <div class="d-flex justify-content-between text-muted mt-1"><span>PayFast fee</span><span id="orderPayfastFee">R0.00</span></div>
            <div class="d-flex justify-content-between fs-5 mt-2"><strong>Total payable</strong><strong id="orderTotal">R0.00</strong></div>
            <div class="form-text">Calculated from the current PayFast settings.</div>
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
  $(document).on('click', '.clothing-order', function () {
    const token = window.crypto?.randomUUID?.()
      || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
        const random = Math.random() * 16 | 0;
        return (char === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
      });
    $('#clothing_request_token').val(token);
  });
</script>


