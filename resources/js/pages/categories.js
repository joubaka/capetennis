$(function () {
  // Remove category
  $(document).on('click', '.btn-remove-category', function () {
    const $btn = $(this);
    const categoryId = $btn.data('id');
    const categoryName = $btn.data('name');
    if (!categoryId) return;

    if (!confirm(`Remove category "${categoryName}" from event?`)) return;

    $.ajax({
      url: window.deleteCategoryUrl + '/' + categoryId,
      type: 'DELETE',
      data: { _token: $('meta[name="csrf-token"]').attr('content') },
      success: function () {
        $btn.closest('li').fadeOut(300, function () { $(this).remove(); });
        toastr.success('Category removed.');
      },
      error: function () {
        toastr.error('Failed to remove category.');
      }
    });
  });

  // Add category
  $(document).on('submit', '#add-category-form', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $button = $form.find('[type="submit"]');
    if ($button.prop('disabled')) return;
    const selected = $('#category-select').val();
    if (!selected || selected.length === 0) {
      toastr.error('Please select at least one category.');
      return;
    }
    $button.prop('disabled', true);
    $.post({
      url: window.eventAttachCategoryUrl,
      data: $form.serialize(),
      success: function (resp) {
        bootstrap.Modal.getInstance(document.getElementById('add-category-modal'))?.hide();
        toastr.success('Category(ies) added.');
        if (window.refreshTeamWorkspace) window.refreshTeamWorkspace();
        else setTimeout(() => location.reload(), 800);
      },
      error: function (xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to add category.'); },
      complete: function () { $button.prop('disabled', false); }
    });
  });

  $('#category-select').select2({
    dropdownParent: $('#add-category-modal'),
    width: '100%',
    placeholder: 'Select categories'
  });
});
