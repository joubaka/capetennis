(function ($) {
  'use strict';
  alert()
  // ---------- CONFIG ----------
  const CLOTHING_ITEMS_URL = window.routes.getRegionClothingItems; // auth-required (backend)

  // ---------- GLOBAL SAFETY ----------
  window.eventCategories = Array.isArray(window.eventCategories) ? window.eventCategories : [];
  window.administrators = Array.isArray(window.administrators) ? window.administrators : [];

  // ---------- CSRF FOR AJAX ----------
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // ---------- UTIL ----------
  function formatR(value) {
    const n = Number(value || 0);
    return 'R' + n.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }
  function updateSummaryCount(count, total) {
    const $count = $('#selectedCount');
    const $total = $('#selectedTotal');
    if ($count.length) $count.text(count);
    if ($total.length) $total.text(formatR(total));
  }

  // Collect all selected lines for summary
  function collectSelectedLines() {
    const lines = [];
    $('#clothingOrderList .item-check:checked').each(function () {
      const itemId = String(this.value);
      const name = $(this).data('name') || '';
      const price = Number($(this).data('price') || 0);
      const $sel = $('#sizes_for_' + itemId + ' input[type="radio"]:checked');
      const sizeId = Number($sel.val() || 0);
      const sizeLabel = String($sel.data('size-label') || '');
      if (sizeId > 0) {
        lines.push({ itemId, name, sizeId, sizeLabel, price });
      }
    });
    return lines;
  }

  // Render summary table
  function renderSummary() {
    const lines = collectSelectedLines();
    const $wrap = $('#orderSummary');
    const $tableHost = $('#orderSummaryTable').empty();

    if (!lines.length) {
      $wrap.addClass('d-none');
      return;
    }

    const $table = $(`
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th style="width:55%">Item</th>
            <th style="width:25%">Size</th>
            <th class="text-end" style="width:20%">Price</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr class="table-light">
            <th colspan="2" class="text-end">Total</th>
            <th class="text-end" id="summaryTotalCell">R0</th>
          </tr>
        </tfoot>
      </table>
    `);

    let total = 0;
    const $tbody = $table.find('tbody');

    lines.forEach(l => {
      total += l.price;
      $tbody.append(`
        <tr>
          <td>${l.name}</td>
          <td>${l.sizeLabel}</td>
          <td class="text-end">${l.price ? formatR(l.price) : 'R0'}</td>
        </tr>
      `);
    });

    $table.find('#summaryTotalCell').text(formatR(total));
    $tableHost.append($table);
    $wrap.removeClass('d-none');
  }

  // Compute counts + totals + summary
  function computeSummary() {
    let count = 0, total = 0;
    $('#clothingOrderList .item-check:checked').each(function () {
      const itemId = String(this.value);
      const $rad = $('#sizes_for_' + itemId + ' input[type="radio"]:checked');
      const sizeId = Number($rad.val() || 0);
      if (sizeId > 0) {
        count++;
        total += Number($(this).data('price') || 0);
      }
    });
    updateSummaryCount(count, total);
    renderSummary();
  }

  // ---------- RENDER HELPERS ----------
  function addClothingItems(payload) {
    const list = Array.isArray(payload?.clothing) ? payload.clothing
      : Array.isArray(payload?.items) ? payload.items
        : [];

    const $host = $('#clothingOrderList').empty();

    if (!list.length) {
      $host.append('<div class="alert alert-warning mb-0">No clothing configured for this region.</div>');
      computeSummary();
      return;
    }

    list.forEach((item, idx) => {
      const id = String(item.id);
      const name = item.item_type_name || item.name || `Item ${idx + 1}`;
      const price = Number(item.price || 0);

      const $card = $(`
        <div class="item-card border rounded-3 p-3">
          <div class="d-flex align-items-center gap-3">
            <div class="form-check m-0">
              <input class="form-check-input item-check" type="checkbox" id="item_${id}" value="${id}">
            </div>
            <div class="flex-grow-1">
              <p class="item-name fw-semibold mb-1">${name}</p>
              <div class="text-secondary small">Choose size below</div>
            </div>
            <div class="item-price fw-bold">${price ? formatR(price) : ''}</div>
          </div>
          <div class="size-wrap mt-3 d-flex flex-wrap gap-2" id="sizes_for_${id}"></div>
        </div>
      `);

      // attach data for summary
      $card.find('.item-check')
        .attr('data-price', price)
        .attr('data-name', name);

      $host.append($card);

      // Render sizes
      addRadiosPills(item, $card.find('#sizes_for_' + id));

      // Disable until checked
      const $wrap = $card.find('#sizes_for_' + id);
      $wrap.addClass('opacity-50');
      $wrap.find('input[type="radio"]').prop('disabled', true);

      // Toggle
      $card.find('.item-check').on('change', function () {
        const checked = this.checked;
        $wrap.toggleClass('opacity-50', !checked);
        $wrap.find('input[type="radio"]').prop('disabled', !checked);
        if (!checked) {
          $wrap.find('input[type="radio"][value="0"]')
            .prop('checked', true)
            .trigger('change');
        }
        computeSummary();
      });
    });

    computeSummary();
  }

  function addRadiosPills(item, $container) {
    const id = String(item.id);
    const sizes = Array.isArray(item.sizes) ? [...item.sizes] : [];

    // Not needed
    const noneUid = `none_${id}`;
    $container.append(`
      <input type="radio" class="btn-check" name="size_for_${id}" id="${noneUid}" 
             value="0" data-size-label="Not needed" autocomplete="off" checked>
      <label class="btn btn-outline-secondary btn-sm" for="${noneUid}">Not needed</label>
    `);

    if (!sizes.length) {
      $container.append('<span class="text-muted small">No sizes configured.</span>');
      $container.on('change', 'input[type="radio"]', computeSummary);
      return;
    }

    sizes.sort((a, b) => {
      const ao = a.ordering ?? 9999, bo = b.ordering ?? 9999;
      if (ao !== bo) return ao - bo;
      return String(a.size).localeCompare(String(b.size));
    });

    sizes.forEach((s) => {
      const uid = `size_${s.id}`;
      const label = String(s.size);
      $container.append(`
        <input type="radio" class="btn-check" name="size_for_${id}" id="${uid}" 
               value="${s.id}" data-size-label="${label}" autocomplete="off">
        <label class="btn btn-outline-primary btn-sm" for="${uid}">${label}</label>
      `);
    });

    $container.on('change', 'input[type="radio"]', computeSummary);
  }

  // ---------- OPEN MODAL + LOAD ITEMS ----------
  const clothingModalEl = document.getElementById('clothing-order-modal');
  if (clothingModalEl) {
    clothingModalEl.addEventListener('show.bs.modal', function (ev) {
      const trigger = ev.relatedTarget;
      if (!trigger) return;

      const regionId = trigger.getAttribute('data-region');
      const playerId = trigger.getAttribute('data-playerid');
      const teamId = trigger.getAttribute('data-team');
      const name = trigger.getAttribute('data-name') || '';
      const eventId = trigger.getAttribute('data-eventid');
      $('#event_id').val(eventId || $('#event_id').val() )

      $('#region_id').val(regionId);
      $('#player_id').val(playerId);
      $('#team_id').val(teamId);
      $('#name').text(name);

      $('#clothingOrderList').html('<div class="alert alert-info mb-0">Loading…</div>');

      $.post(CLOTHING_ITEMS_URL, { region: regionId })
        .done(function (response) {
          $('#clothingOrderList').empty();
          addClothingItems(response);
        })
        .fail(function () {
          $('#clothingOrderList').html('<div class="alert alert-danger mb-0">Failed to load clothing items.</div>');
        });
    });

    clothingModalEl.addEventListener('hidden.bs.modal', function () {
      $('#clothingOrderList').empty();
      $('#orderSummary').addClass('d-none');
      if ($('#selectedCount').length) $('#selectedCount').text('0');
      if ($('#selectedTotal').length) $('#selectedTotal').text('R0');
    });
  }

  // ---------- SUBMIT ----------
  // ---------- SUBMIT ----------
  $('#submitClothingOrderButton').on('click', function () {
    // remove previous hidden fields
    $('#myForm input[name="size[]"], #myForm input[name="item[]"]').remove();

    // ✅ disable raw radios so they don't end up in the request
    $('#clothingOrderList input[name^="size_for_"]').prop('disabled', true);

    let added = 0;

    $('#clothingOrderList .item-check:checked').each(function () {
      const itemId = String(this.value);
      const sizeId = Number($('#sizes_for_' + itemId + ' input[type="radio"]:checked').val() || 0);

      if (sizeId > 0) {
        $('<input>', { type: 'hidden', name: 'item[]', value: itemId }).appendTo('#myForm');
        $('<input>', { type: 'hidden', name: 'size[]', value: sizeId }).appendTo('#myForm');
        added++;
      }
    });

    if (!added) {
      alert('Please tick at least one item and pick a size.');
      return;
    }

    $('#myForm').trigger('submit');
  });

})(jQuery);
