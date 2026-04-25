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
  !*** ./resources/js/pages/categories.js ***!
  \******************************************/
$(function () {
  // Remove category
  $(document).on('click', '.btn-remove-category', function () {
    var $btn = $(this);
    var categoryId = $btn.data('id');
    var categoryName = $btn.data('name');
    if (!categoryId) return;
    if (!confirm("Remove category \"".concat(categoryName, "\" from event?"))) return;
    $.ajax({
      url: window.deleteCategoryUrl + '/' + categoryId,
      type: 'DELETE',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function success() {
        $btn.closest('li').fadeOut(300, function () {
          $(this).remove();
        });
        toastr.success('Category removed.');
      },
      error: function error() {
        toastr.error('Failed to remove category.');
      }
    });
  });

  // Add category
  $('#add-category-form').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var selected = $('#category-select').val();
    if (!selected || selected.length === 0) {
      toastr.error('Please select at least one category.');
      return;
    }
    $.post({
      url: window.eventAttachCategoryUrl,
      data: $form.serialize(),
      success: function success(resp) {
        $('#add-category-modal').modal('hide');
        toastr.success('Category(ies) added.');
        setTimeout(function () {
          return location.reload();
        }, 800);
      },
      error: function error() {
        toastr.error('Failed to add category.');
      }
    });
  });
  $('#category-select').select2({
    dropdownParent: $('#add-category-modal'),
    width: '100%',
    placeholder: 'Select categories'
  });
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});