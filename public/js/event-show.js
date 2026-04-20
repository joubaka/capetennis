(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!******************************************!*\
  !*** ./resources/js/pages/event-show.js ***!
  \******************************************/
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
(function ($) {
  'use strict';

  var _window$routes, _window$routes2;
  console.log('Event Show JS Loaded pagesfffff/');
  // ---------- CONFIG ----------
  var CLOTHING_ITEMS_URL = (_window$routes = window.routes) === null || _window$routes === void 0 ? void 0 : _window$routes.getRegionClothingItems;
  var SAVE_ANNOUNCEMENT_URL = (_window$routes2 = window.routes) === null || _window$routes2 === void 0 ? void 0 : _window$routes2.saveAnnouncement; // set in Blade with @json(route(...))
  console.log('working');
  // ---------- CSRF FOR AJAX ----------
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // ---------- UTIL ----------
  function formatR(value) {
    var n = Number(value || 0);
    return 'R' + n.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }
  function updateSummaryCount(count, total) {
    var $count = $('#selectedCount');
    var $total = $('#selectedTotal');
    if ($count.length) $count.text(count);
    if ($total.length) $total.text(formatR(total));
  }
  function collectSelectedLines() {
    var lines = [];
    $('#clothingOrderList .item-check:checked').each(function () {
      var itemId = String(this.value);
      var name = $(this).data('name') || '';
      var price = Number($(this).data('price') || 0);
      var $sel = $('#sizes_for_' + itemId + ' input[type="radio"]:checked');
      var sizeId = Number($sel.val() || 0);
      var sizeLabel = String($sel.data('size-label') || '');
      if (sizeId > 0) lines.push({
        itemId: itemId,
        name: name,
        sizeId: sizeId,
        sizeLabel: sizeLabel,
        price: price
      });
    });
    return lines;
  }
  function renderSummary() {
    var lines = collectSelectedLines();
    var $wrap = $('#orderSummary');
    var $tableHost = $('#orderSummaryTable').empty();
    if (!lines.length) {
      $wrap.addClass('d-none');
      return;
    }
    var $table = "\n      <table class=\"table table-sm align-middle mb-0\">\n        <thead>\n          <tr>\n            <th style=\"width:55%\">Item</th>\n            <th style=\"width:25%\">Size</th>\n            <th class=\"text-end\" style=\"width:20%\">Price</th>\n          </tr>\n        </thead>\n        <tbody></tbody>\n        <tfoot>\n          <tr class=\"table-light\">\n            <th colspan=\"2\" class=\"text-end\">Total</th>\n            <th class=\"text-end\" id=\"summaryTotalCell\">R0</th>\n          </tr>\n        </tfoot>\n      </table>\n    ";
    var total = 0;
    var $tbody = $table.find('tbody');
    lines.forEach(function (l) {
      total += l.price;
      $tbody.append("\n        <tr>\n          <td>".concat(l.name, "</td>\n          <td>").concat(l.sizeLabel, "</td>\n          <td class=\"text-end\">").concat(l.price ? formatR(l.price) : 'R0', "</td>\n        </tr>\n      "));
    });
    $table.find('#summaryTotalCell').text(formatR(total));
    $tableHost.append($table);
    $wrap.removeClass('d-none');
  }
  function computeSummary() {
    var count = 0,
      total = 0;
    $('#clothingOrderList .item-check:checked').each(function () {
      var itemId = String(this.value);
      var $rad = $('#sizes_for_' + itemId + ' input[type="radio"]:checked');
      var sizeId = Number($rad.val() || 0);
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
    var id = String(item.id);
    var sizes = Array.isArray(item.sizes) ? _toConsumableArray(item.sizes) : [];
    var noneUid = "none_".concat(id);
    $container.append("\n      <input type=\"radio\" class=\"btn-check\" name=\"size_for_".concat(id, "\" id=\"").concat(noneUid, "\"\n             value=\"0\" data-size-label=\"Not needed\" autocomplete=\"off\" checked>\n      <label class=\"btn btn-outline-secondary btn-sm\" for=\"").concat(noneUid, "\">Not needed</label>\n    "));
    if (!sizes.length) {
      $container.append('<span class="text-muted small">No sizes configured.</span>');
      $container.on('change', 'input[type="radio"]', computeSummary);
      return;
    }
    sizes.sort(function (a, b) {
      var _a$ordering, _b$ordering;
      var ao = (_a$ordering = a.ordering) !== null && _a$ordering !== void 0 ? _a$ordering : 9999,
        bo = (_b$ordering = b.ordering) !== null && _b$ordering !== void 0 ? _b$ordering : 9999;
      if (ao !== bo) return ao - bo;
      return String(a.size).localeCompare(String(b.size));
    });
    sizes.forEach(function (s) {
      var uid = "size_".concat(s.id);
      var label = String(s.size);
      $container.append("\n        <input type=\"radio\" class=\"btn-check\" name=\"size_for_".concat(id, "\" id=\"").concat(uid, "\"\n               value=\"").concat(s.id, "\" data-size-label=\"").concat(label, "\" autocomplete=\"off\">\n        <label class=\"btn btn-outline-primary btn-sm\" for=\"").concat(uid, "\">").concat(label, "</label>\n      "));
    });
    $container.on('change', 'input[type="radio"]', computeSummary);
  }
  function addClothingItems(payload) {
    var list = Array.isArray(payload === null || payload === void 0 ? void 0 : payload.clothing) ? payload.clothing : Array.isArray(payload === null || payload === void 0 ? void 0 : payload.items) ? payload.items : [];
    var $host = $('#clothingOrderList').empty();
    if (!list.length) {
      $host.append('<div class="alert alert-warning mb-0">No clothing configured for this region.</div>');
      computeSummary();
      return;
    }
    list.forEach(function (item, idx) {
      var id = String(item.id);
      var name = item.item_type_name || item.name || "Item ".concat(idx + 1);
      var price = Number(item.price || 0);
      var $card = $("\n        <div class=\"item-card border rounded-3 p-3\">\n          <div class=\"d-flex align-items-center gap-3\">\n            <div class=\"form-check m-0\">\n              <input class=\"form-check-input item-check\" type=\"checkbox\" id=\"item_".concat(id, "\" value=\"").concat(id, "\">\n            </div>\n            <div class=\"flex-grow-1\">\n              <p class=\"item-name fw-semibold mb-1\">").concat(name, "</p>\n              <div class=\"text-secondary small\">Choose size below</div>\n            </div>\n            <div class=\"item-price fw-bold\">").concat(price ? formatR(price) : '', "</div>\n          </div>\n          <div class=\"size-wrap mt-3 d-flex flex-wrap gap-2\" id=\"sizes_for_").concat(id, "\"></div>\n        </div>\n      "));
      $card.find('.item-check').attr('data-price', price).attr('data-name', name);
      $host.append($card);
      addRadiosPills(item, $card.find('#sizes_for_' + id));
      var $wrap = $card.find('#sizes_for_' + id);
      $wrap.addClass('opacity-50');
      $wrap.find('input[type="radio"]').prop('disabled', true);
      $card.find('.item-check').on('change', function () {
        var checked = this.checked;
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

  // ---------- WITHDRAW PLAYER ----------
  // ---------- WITHDRAW PLAYER ----------
  $(document).on('click', '.withDrawPlayer', function () {
    var $btn = $(this);
    var withdrawUrl = $btn.data('url');

    // First dialog - Show terms of refund
    Swal.fire({
      title: 'Terms of Refund',
      html: "\n        <div class=\"text-start\">\n          <p class=\"mb-3\">Please review the refund terms before proceeding:</p>\n\n          <ul class=\"mb-4\">\n            <li><strong>10%</strong> of the total entry fee will be deducted as an administration fee.</li>\n            <li>This action is final and <strong>cannot be undone</strong>.</li>\n          </ul>\n\n          <p class=\"mb-2\"><strong>You will be able to choose between two refund methods:</strong></p>\n\n          <div class=\"row g-3\">\n            <div class=\"col-sm-6\">\n              <div class=\"p-3 border rounded bg-light h-100\">\n                <h6 class=\"mb-1 text-primary\">Refund to Wallet</h6>\n                <p class=\"small mb-0 text-muted\">Funds are available <strong>instantly</strong> in your account for future use.</p>\n              </div>\n            </div>\n\n            <div class=\"col-sm-6\">\n              <div class=\"p-3 border rounded bg-light h-100\">\n                <h6 class=\"mb-1 text-primary\">Refund via PayFast</h6>\n                <p class=\"small mb-0 text-muted\">Sent back to your <strong>original payment source</strong>. Processed within 5-7 business days.</p>\n              </div>\n            </div>\n          </div>\n        </div>\n      ",
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'I Accept, Continue',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-primary me-1',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        // Second dialog - Final confirmation
        Swal.fire({
          title: 'Are you sure?',
          text: "You are about to withdraw from this event. This action cannot be undone.",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, withdraw player!',
          cancelButtonText: 'Wait, go back',
          customClass: {
            confirmButton: 'btn btn-primary me-1',
            cancelButton: 'btn btn-label-secondary'
          },
          buttonsStyling: false
        }).then(function (confirmResult) {
          if (confirmResult.value) {
            // Submit via form POST
            var $form = $('<form>', {
              method: 'POST',
              action: withdrawUrl
            }).append($('<input>', {
              type: 'hidden',
              name: '_token',
              value: $('meta[name="csrf-token"]').attr('content')
            }));
            $('body').append($form);
            $form.submit();
          }
        });
      }
    });
  });
  // ---------- ANNOUNCEMENTS (QUILL + SAVE) ----------
  var fullToolbar = [[{
    font: []
  }, {
    size: []
  }], ['bold', 'italic', 'underline', 'strike'], [{
    color: []
  }, {
    background: []
  }], [{
    script: 'super'
  }, {
    script: 'sub'
  }], [{
    header: '1'
  }, {
    header: '2'
  }, 'blockquote', 'code-block'], [{
    list: 'ordered'
  }, {
    list: 'bullet'
  }, {
    indent: '-1'
  }, {
    indent: '+1'
  }], [{
    direction: 'rtl'
  }], ['link', 'image', 'video', 'formula'], ['clean']];
  var fullEditor = null;
  var fullEditorEdit = null;
  var fullEl = document.querySelector('#full-editor');
  if (fullEl) {
    fullEditor = new Quill(fullEl, {
      bounds: fullEl,
      placeholder: 'Type Something...',
      modules: {
        formula: true,
        toolbar: fullToolbar
      },
      theme: 'snow'
    });
  }
  var editEl = document.querySelector('#full-editor-edit');
  if (editEl) {
    fullEditorEdit = new Quill(editEl, {
      bounds: editEl,
      placeholder: 'Type Something...',
      modules: {
        formula: true,
        toolbar: fullToolbar
      },
      theme: 'snow'
    });
  }
  $('#addAnnouncementButton').on('click', function () {
    var _window$routes3;
    var url = (_window$routes3 = window.routes) === null || _window$routes3 === void 0 ? void 0 : _window$routes3.saveAnnouncement;
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
    var html = fullEditor.root.innerHTML.trim();
    var text = fullEditor.getText().trim();
    var sendEmail = $('input[name="sendMail"]').is(':checked') ? 1 : 0;
    var event_id = $('#announcement_event_id').val();
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
      url: url,
      event_id: event_id,
      sendEmail: sendEmail,
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
        event_id: event_id,
        data: html,
        send_email: sendEmail
      }
    }).done(function (resp) {
      console.log('[Announcement] ✅ Success:', resp);
      $('.mySpinner').addClass('d-none');
      $('#addAnnouncementButton, input[name="sendMail"]').prop('disabled', false);
      var message = 'Announcement created successfully.';
      if (sendEmail === 1) {
        var _resp$emails_count;
        var count = (_resp$emails_count = resp === null || resp === void 0 ? void 0 : resp.emails_count) !== null && _resp$emails_count !== void 0 ? _resp$emails_count : null;
        message = count ? "Announcement sent to ".concat(count, " recipient").concat(count === 1 ? '' : 's', ".") : 'Announcement sent successfully.';
      }
      Swal.fire({
        icon: 'success',
        title: '✅ Announcement Created',
        text: message,
        timer: 2500,
        showConfirmButton: false
      });
      setTimeout(function () {
        return location.reload();
      }, 2500);
    }).fail(function (xhr) {
      var _xhr$responseJSON;
      $('.mySpinner').addClass('d-none');
      $('#addAnnouncementButton, input[name="sendMail"]').prop('disabled', false);
      console.error('[Announcement] ❌ Failed:', xhr.responseText);
      Swal.fire({
        icon: 'error',
        title: 'Failed to create announcement',
        text: ((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || xhr.responseText || 'Error sending announcement.'
      });
    });
  });
})(jQuery);
/******/ 	return __webpack_exports__;
/******/ })()
;
});