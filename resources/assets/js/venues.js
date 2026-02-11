$(function () {
  'use strict';
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $('#venues').select2({
    allowClear: true
  });
  $('#apply-venue-button').on('click', function () {
    let selectedVenues = $('#venues').val(); // Get selected values as an array
    
    console.log(selectedVenues); // Output the selected values
  });



  
});
