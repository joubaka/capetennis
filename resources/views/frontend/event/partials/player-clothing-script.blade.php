<script>
(function ($) {
  const modal = document.getElementById('clothing-order-modal');
  if (!modal) return;
  let catalogueRequest; let catalogueGeneration = 0;
  modal.addEventListener('show.bs.modal', function (event) {
    if (!@json(auth()->check())) { event.preventDefault(); window.location.href = @json(route('login')) + '?redirect=' + encodeURIComponent(window.location.href); return; }
    const button = event.relatedTarget;
    if (!button) return;
    $('#order_event_id').val(button.dataset.eventid); $('#order_region_id').val(button.dataset.region);
    $('#order_player_id').val(button.dataset.playerid); $('#order_team_id').val(button.dataset.team);
    $('#orderPlayerName').text(button.dataset.name); $('#orderTotal').text('R0.00');
    $('#clothingOrderList').html('<div class="alert alert-info mb-0">Loading clothing…</div>');
    $('#submitClothingOrderBtn').prop('disabled', true);
    catalogueRequest?.abort(); const generation = ++catalogueGeneration;
    catalogueRequest = $.post(window.CLOTHING_ITEMS_URL, {region: button.dataset.region, _token: $('#clothingOrderForm input[name="_token"]').val()})
      .done(function (html) { if (generation !== catalogueGeneration) return; $('#clothingOrderList').html(html); $('#submitClothingOrderBtn').prop('disabled', false); })
      .fail(function (xhr) { if (generation !== catalogueGeneration || xhr.statusText === 'abort') return; $('#clothingOrderList').html('<div class="alert alert-danger mb-0">Could not load clothing. Close and try again.</div>'); });
  });
  function updateTotals() {
    let total = 0;
    $('#clothingOrderList .clothing-item').each(function () {
      const card = $(this); const qty = Number(card.find('.qty-input').val()) || 0;
      if (!card.find('.item-toggle').is(':checked') || !card.find('.size-select').val() || qty < 1) { card.find('.item-total').text('R0.00'); return; }
      const itemTotal = Number(card.data('customer-price')) * qty;
      total += itemTotal; card.find('.item-total').text('R' + itemTotal.toFixed(2));
    });
    $('#orderTotal').text('R' + total.toFixed(2));
  }
  $(document).on('change', '#clothingOrderList .item-toggle', function () {
    const options = $(this).closest('.clothing-item').find('.item-options'); options.toggleClass('d-none', !this.checked);
    if (!this.checked) { options.find('select').val(''); options.find('input[type="number"]').val(1); } updateTotals();
  });
  $(document).on('change input', '#clothingOrderList .size-select, #clothingOrderList .qty-input', updateTotals);
  $('#clothingOrderForm').on('submit', function (event) {
    $(this).find('.order-line').remove(); let selected = 0; let valid = true;
    $('#clothingOrderList .clothing-item').each(function () {
      const card = $(this); if (!card.find('.item-toggle').is(':checked')) return; selected++;
      const size = card.find('.size-select').val(); const qty = Number(card.find('.qty-input').val());
      if (!size || !Number.isInteger(qty) || qty < 1 || qty > 20) { valid = false; return; }
      $('<input>', {type:'hidden', name:`items[${card.data('item')}][size]`, value:size, class:'order-line'}).appendTo('#clothingOrderForm');
      $('<input>', {type:'hidden', name:`items[${card.data('item')}][qty]`, value:qty, class:'order-line'}).appendTo('#clothingOrderForm');
    });
    if (!selected || !valid) { event.preventDefault(); alert('Select at least one item and choose a size and quantity from 1 to 20 for every selected item.'); }
  });
})(jQuery);
</script>
