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
  !*** ./resources/js/pages/headOffice.js ***!
  \******************************************/
/*
  HeadOffice — team-event-show.js
  Requires:
  window.HeadOffice = {
      venues,
      previewUrl,
      createUrl,
      backendDrawVenuesStoreTemplate,
      backendDrawVenuesJsonTemplate
  }
*/

(function (window, $) {
  'use strict';

  var data = window.HeadOffice || {};
  var venues = data.venues || [];
  var csrfToken = $('meta[name="csrf-token"]').attr('content');

  // =====================================================
  // Loading Helpers
  // =====================================================
  function showLoading() {
    $('#loading-overlay').removeClass('d-none').addClass('d-flex');
  }
  function hideLoading() {
    $('#loading-overlay').removeClass('d-flex').addClass('d-none');
  }

  // =====================================================
  // CREATE DRAW — Auto-name
  // =====================================================

  function updateDrawName() {
    var selectedType = $('input[name="draw_type_id"]:checked');
    var typeText = selectedType.closest('.switch').find('.switch-label').text().trim();
    var selectedCat = $('input[name="category_choice"]:checked');
    var catText = selectedCat.data('age') || selectedCat.closest('.switch').find('.switch-label').text().trim();
    var name = '';
    if (selectedCat.length) name += catText;
    if (selectedType.val() == "3" && selectedCat.length) {
      name += ' – ' + typeText + ' (Boys & Girls)';
    } else if (typeText) {
      name += ' – ' + typeText;
    }
    $('#drawName').val(name);
  }

  // =====================================================
  // CREATE DRAW — Toggle category sections
  // =====================================================

  $(document).on('change', 'input[name="draw_type_id"]', function () {
    var selectedVal = $(this).val();
    var isMixed = $(this).data('mixed') == 1;
    if (selectedVal == "3") {
      $('#categorySection').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#type3Categories').removeClass('d-none');
      $('input[name="category_choice"]').prop('checked', false);
    } else if (isMixed) {
      $('#type3Categories').addClass('d-none');
      $('#categorySection').addClass('d-none');
      $('#mixedPlaceholder').removeClass('d-none');
      $('input[name="category_choice"]').prop('checked', false);
    } else {
      $('#type3Categories').addClass('d-none');
      $('#mixedPlaceholder').addClass('d-none');
      $('#categorySection').removeClass('d-none');
    }
    updateDrawName();
  });
  $(document).on('change', 'input[name="category_choice"]', updateDrawName);

  // =====================================================
  // CREATE DRAW — Form submit
  // =====================================================

  $('#createDrawForm').on('submit', function (e) {
    e.preventDefault();
    var drawName = $('#drawName').val().trim();
    if (!drawName) {
      toastr.error('Please enter a draw name');
      return;
    }
    var $selectedType = $('input[name="draw_type_id"]:checked');
    var $selectedCat = $('input[name="category_choice"]:checked');
    if (!$selectedType.length) {
      toastr.error('Please select a draw type');
      return;
    }
    if (!$selectedCat.length) {
      toastr.error('Please select a category');
      return;
    }

    // Build category_ids[] from data attributes
    var categoryIds = [];
    if ($selectedType.val() == "3") {
      // Type 3 (mixed): data-ids is a JSON array [boysPivot, girlsPivot]
      categoryIds = $selectedCat.data('ids') || [];
    } else {
      // Standard: data-pivot-id is a single category_events.id
      var pivotId = $selectedCat.data('pivot-id');
      if (pivotId) categoryIds = [pivotId];
    }
    if (!categoryIds.length) {
      toastr.error('No category selected');
      return;
    }

    // Build POST payload with category_ids[] (what the controller expects)
    var postData = {
      _token: csrfToken,
      draw_type_id: $selectedType.val(),
      drawName: drawName
    };
    categoryIds.forEach(function (id, i) {
      postData['category_ids[' + i + ']'] = id;
    });
    $.post(data.createUrl, postData).done(function (response) {
      toastr.success(response.message);
      $('#createDrawModal').modal('hide');
      location.reload();
    }).fail(function (xhr) {
      if (xhr.responseJSON && xhr.responseJSON.errors) {
        var msgs = Object.values(xhr.responseJSON.errors).flat();
        msgs.forEach(function (m) {
          toastr.error(m);
        });
      } else {
        toastr.error('Error creating draw');
      }
    });
  });

  // =====================================================
  // RECREATE FIXTURES
  // =====================================================

  $(document).off('click.recreate', '.btn-recreate-fixtures').on('click.recreate', '.btn-recreate-fixtures', function () {
    var $btn = $(this);
    Swal.fire({
      title: 'Recreate Fixtures?',
      html: "This will <strong>delete and rebuild</strong> all fixtures for <b>".concat($btn.data('draw-name'), "</b>."),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, recreate',
      confirmButtonColor: '#28a745'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      showLoading();
      $.post($btn.data('url'), {
        _token: csrfToken
      }).done(function (response) {
        if (response.success) {
          toastr.success(response.message);
          setTimeout(function () {
            return location.reload();
          }, 1200);
        } else {
          toastr.error(response.message);
        }
      }).fail(function () {
        toastr.error('Failed to recreate fixtures.');
      }).always(hideLoading);
    });
  });

  // =====================================================
  // GENERATE ALL FIXTURES
  // =====================================================

  $('#generate-fixtures-btn').on('click', function (e) {
    e.preventDefault();
    var url = $(this).data('url');
    if (!url) {
      toastr.error('No fixture generation URL');
      return;
    }
    showLoading();
    $.post(url, {
      _token: csrfToken
    }).done(function (response) {
      toastr.success(response.message || 'Fixtures generated');
      location.reload();
    }).fail(function () {
      toastr.error('Error generating fixtures');
    }).always(hideLoading);
  });

  // =====================================================
  // VENUES MODAL
  // =====================================================

  var $venuesModal = $('#venuesModal');
  var $venuesForm = $('#venuesForm');
  var $venuesContainer = $('#venues-container');
  function venueRowTemplate() {
    var selectedId = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : "";
    var numCourts = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;
    var options = '<option value="">-- Select Venue --</option>';
    venues.forEach(function (v) {
      var selected = selectedId == v.id ? 'selected' : '';
      options += "<option value=\"".concat(v.id, "\" ").concat(selected, ">").concat(v.name, "</option>");
    });
    return "\n      <div class=\"venue-row d-flex gap-2 mb-2\">\n        <select name=\"venue_id[]\" class=\"form-select venue-select\" required>\n          ".concat(options, "\n        </select>\n        <input type=\"number\" name=\"num_courts[]\" class=\"form-control\" value=\"").concat(numCourts, "\" min=\"1\" required>\n        <button type=\"button\" class=\"btn btn-danger btn-remove-row\">&times;</button>\n      </div>\n    ");
  }
  function initSelect2($row) {
    var $select = $row.find('.venue-select');
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    $select.select2({
      dropdownParent: $venuesModal,
      width: '100%'
    });
  }

  // =====================================================
  // OPEN VENUES MODAL
  // =====================================================

  $(document).off('click.venues', '.btn-add-venues').on('click.venues', '.btn-add-venues', function () {
    var drawId = $(this).data('draw-id');
    var drawName = $(this).data('draw-name');
    var storeUrl = data.backendDrawVenuesStoreTemplate.replace('__ID__', drawId);
    var jsonUrl = data.backendDrawVenuesJsonTemplate.replace('__ID__', drawId);
    $venuesForm.attr('action', storeUrl).data('draw-id', drawId);
    $venuesModal.find('.modal-title').text('Assign Venues to ' + drawName);
    $venuesContainer.empty();
    $.get(jsonUrl).done(function (existing) {
      if (existing.length > 0) {
        existing.forEach(function (v) {
          var $row = $(venueRowTemplate(v.id, v.num_courts));
          $venuesContainer.append($row);
          initSelect2($row);
        });
      } else {
        var $row = $(venueRowTemplate());
        $venuesContainer.append($row);
        initSelect2($row);
      }
      $venuesModal.modal('show');
    });
  });

  // =====================================================
  // ADD ROW
  // =====================================================

  $('#addVenueRow').off('click.venues').on('click.venues', function () {
    var $row = $(venueRowTemplate());
    $venuesContainer.append($row);
    initSelect2($row);
  });

  // =====================================================
  // REMOVE ROW
  // =====================================================

  $(document).off('click.venues', '.btn-remove-row').on('click.venues', '.btn-remove-row', function () {
    $(this).closest('.venue-row').remove();
  });

  // =====================================================
  // SAVE VENUES
  // =====================================================

  $venuesForm.off('submit.venues').on('submit.venues', function (e) {
    e.preventDefault();
    var url = $(this).attr('action');
    var formData = $(this).serialize();
    var drawId = $(this).data('draw-id');
    $.post(url, formData + '&_token=' + csrfToken).done(function (response) {
      if (!response.success) {
        toastr.error('Could not save venues.');
        return;
      }
      toastr.success('Venues updated successfully.');
      $venuesModal.modal('hide');
      var $container = $('.draw-venues[data-draw-id="' + drawId + '"]');
      if (response.venues && response.venues.length) {
        var html = '<small class="text-muted">Venues:</small> ';
        response.venues.forEach(function (v) {
          html += "\n                <span class=\"badge bg-label-primary me-1\">\n                  ".concat(v.name, " \n                  <span class=\"text-muted\">(").concat(v.pivot.num_courts, ")</span>\n                </span>\n              ");
        });
        $container.html(html);
      } else {
        $container.empty();
      }
    }).fail(function () {
      toastr.error('Error while saving venues.');
    });
  });

  // =====================================================
  // TOGGLE PUBLISH
  // =====================================================

  $(document).off('click.publish', '.toggle-publish').on('click.publish', '.toggle-publish', function () {
    var $btn = $(this);
    var url = $btn.data('url');
    var currentStatus = $btn.data('status');
    $btn.prop('disabled', true);
    $.post(url, {
      _token: csrfToken,
      status: currentStatus
    }).done(function (response) {
      if (!response.success) {
        toastr.error('Could not update publish status.');
        return;
      }
      var newStatus = response.published ? 1 : 0;
      $btn.data('status', newStatus);
      if (newStatus === 1) {
        $btn.html('<i class="ti ti-eye-off me-2"></i> Unpublish');
        toastr.success('Draw published.');
      } else {
        $btn.html('<i class="ti ti-eye me-2"></i> Publish');
        toastr.info('Draw unpublished.');
      }
    }).fail(function () {
      toastr.error('Error while toggling publish.');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // =====================================================
  // DELETE DRAW
  // =====================================================

  $(document).off('click.delete', '.btn-delete-draw').on('click.delete', '.btn-delete-draw', function () {
    var $btn = $(this);
    var url = $btn.data('url');
    var drawName = $btn.data('draw-name');
    Swal.fire({
      title: 'Delete Draw?',
      html: "Are you sure you want to delete <strong>".concat(drawName, "</strong>?"),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      confirmButtonColor: '#d33'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.ajax({
        url: url,
        type: 'DELETE',
        data: {
          _token: csrfToken
        },
        success: function success(response) {
          if (!response.success) {
            toastr.error(response.message);
            return;
          }
          toastr.success(response.message);
          $btn.closest('.list-group-item').fadeOut(300, function () {
            $(this).remove();
          });
        },
        error: function error() {
          toastr.error('Error while deleting draw.');
        }
      });
    });
  });
})(window, jQuery);
/******/ 	return __webpack_exports__;
/******/ })()
;
});