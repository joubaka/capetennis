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
  $('#venues').next('.select2-container').find('.select2-selection').attr({
    'aria-labelledby': 'venues-label',
    'aria-describedby': 'venue-scope'
  });
  $('#apply-venue-button').on('click', function () {
    let selectedVenues = $('#venues').val(); // Get selected values as an array
    
    console.log(selectedVenues); // Output the selected values
  });



  
});
