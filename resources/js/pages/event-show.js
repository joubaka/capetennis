(function ($) {
  'use strict';
  console.log('Event Show JS Loaded pages/');
  // ---------- CONFIG ----------
  const CLOTHING_ITEMS_URL = window.routes?.getRegionClothingItems;
  const SAVE_ANNOUNCEMENT_URL = window.routes?.saveAnnouncement; // set in Blade with @json(route(...))

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

  function collectSelectedLines() {
    const lines = [];
    $('#clothingOrderList .item-check:checked').each(function () {
      const itemId = String(this.value);
      const name = $(this).data('name') || '';
      const price = Number($(this).data('price') || 0);
      const $sel = $('#sizes_for_' + itemId + ' input[type="radio"]:checked');
      const sizeId = Number($sel.val() || 0);
      const sizeLabel = String($sel.data('size-label') || '');
      if (sizeId > 0) lines.push({ itemId, name, sizeId, sizeLabel, price });
    });
    return lines;
  }

  function renderSummary() {
    const lines = collectSelectedLines();
    const $wrap = $('#orderSummary');
    const $tableHost = $('#orderSummaryTable').empty();

    if (!lines.length) { $wrap.addClass('d-none'); return; }

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
  function addRadiosPills(item, $container) {
    const id = String(item.id);
    const sizes = Array.isArray(item.sizes) ? [...item.sizes] : [];

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
   alert()
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

      $card.find('.item-check').attr('data-price', price).attr('data-name', name);
      $host.append($card);

      addRadiosPills(item, $card.find('#sizes_for_' + id));

      const $wrap = $card.find('#sizes_for_' + id);
      $wrap.addClass('opacity-50');
      $wrap.find('input[type="radio"]').prop('disabled', true);

      $card.find('.item-check').on('change', function () {
        const checked = this.checked;
        $wrap.toggleClass('opacity-50', !checked);
        $wrap.find('input[type="radio"]').prop('disabled', !checked);
        if (!checked) {
          $wrap.find('input[type="radio"][value="0"]').prop('checked', true).trigger('change');
        }
        computeSummary();
      });
    });

    computeSummary();
  }



  // ---------- ANNOUNCEMENTS (QUILL + SAVE) ----------
  const fullToolbar = [
    [{ font: [] }, { size: [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ script: 'super' }, { script: 'sub' }],
    [{ header: '1' }, { header: '2' }, 'blockquote', 'code-block'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
    [{ direction: 'rtl' }],
    ['link', 'image', 'video', 'formula'],
    ['clean']
  ];

  let fullEditor = null;
  let fullEditorEdit = null;

  const fullEl = document.querySelector('#full-editor');
  if (fullEl) {
    fullEditor = new Quill(fullEl, {
      bounds: fullEl,
      placeholder: 'Type Something...',
      modules: { formula: true, toolbar: fullToolbar },
      theme: 'snow'
    });
  }

  const editEl = document.querySelector('#full-editor-edit');
  if (editEl) {
    fullEditorEdit = new Quill(editEl, {
      bounds: editEl,
      placeholder: 'Type Something...',
      modules: { formula: true, toolbar: fullToolbar },
      theme: 'snow'
    });
  }


  $('#addAnnouncementButton').on('click', function () {

    const url = window.routes?.saveAnnouncement;
    if (!url) {
      console.error('[Announcement] Missing route: window.routes.saveAnnouncement');
      return;
    }
    if (!fullEditor) {
      Swal.fire({
        icon: 'error',
        title: 'Editor not available',
        text: 'Announcement editor is not available on this page.'
      });
      return;
    }

    const html = fullEditor.root.innerHTML.trim();
    const text = fullEditor.getText().trim();
    const sendEmail = $('input[name="sendMail"]').is(':checked') ? 1 : 0;
    const event_id = $('#announcement_event_id').val();

    if (!text || html === '<p><br></p>') {
      Swal.fire({
        icon: 'warning',
        title: 'Empty Announcement',
        text: 'Please type your announcement before saving.'
      });
      return;
    }

    if (!event_id) {
      Swal.fire({
        icon: 'error',
        title: 'Missing Event ID',
        text: 'Please select an event before sending.'
      });
      return;
    }

    console.log('[Announcement] Sending announcement', {
      url,
      event_id,
      sendEmail,
      htmlLength: html.length
    });

    // Disable UI
    $('.mySpinner').removeClass('d-none');
    $('#addAnnouncementButton, input[name="sendMail"]').prop('disabled', true);

    $.ajax({
      method: 'POST',
      url: url,
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        event_id,
        data: html,
        send_email: sendEmail
      }
    })
      .done(function (resp) {
        console.log('[Announcement] ✅ Success:', resp);
        $('.mySpinner').addClass('d-none');
        $('#addAnnouncementButton, input[name="sendMail"]').prop('disabled', false);

        let message = 'Announcement created successfully.';
        if (sendEmail === 1) {
          const count = resp?.emails_count ?? null;
          message = count
            ? `Announcement sent to ${count} recipient${count === 1 ? '' : 's'}.`
            : 'Announcement sent successfully.';
        }

        Swal.fire({
          icon: 'success',
          title: '✅ Announcement Created',
          text: message,
          timer: 2500,
          showConfirmButton: false
        });

        setTimeout(() => location.reload(), 2500);
      })
      .fail(function (xhr) {
        $('.mySpinner').addClass('d-none');
        $('#addAnnouncementButton, input[name="sendMail"]').prop('disabled', false);
        console.error('[Announcement] ❌ Failed:', xhr.responseText);

        Swal.fire({
          icon: 'error',
          title: 'Failed to create announcement',
          text: xhr.responseJSON?.message || xhr.responseText || 'Error sending announcement.'
        });
      });
  });

})(jQuery);
