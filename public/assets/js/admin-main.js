$(function () {
 
  function loadTabContent($tab) {
    const target = $tab.attr('href');
    const url = $tab.data('url');
    const $targetPane = $(target);

    if ($targetPane.data('loaded')) return; // prevent reloading

    $targetPane.html('<div class="text-center p-4">Loading...</div>');

    $.get(url, function (response) {
      $targetPane.html(response).data('loaded', true);
    });
  }

  // Load initial active tab
  loadTabContent($('.ajax-tab.active'));

  // Handle clicks on tabs
  $('.ajax-tab').on('shown.bs.tab', function (e) {
    loadTabContent($(e.target));
  });

  // Open modal and set category ID
$(document).on('click', '.add-entry', function () {
  const categoryId = $(this).data('category-event-id');
  $('#modalCategoryEventId').val(categoryId);
  $('#addPlayerModal').modal('show');
});

// Submit form
$('#addPlayerForm').on('submit', function (e) {
  e.preventDefault();

  $.post('/admin/registrations', $(this).serialize(), function () {
    $('#addPlayerModal').modal('hide');
    // Optionally reload the tab
    $('[href="#entriesTab"]').removeData('loaded'); // clear loaded flag
    $('[href="#entriesTab"]').tab('show');
  }).fail(function () {
    alert('Could not add player.');
  });
});

});
