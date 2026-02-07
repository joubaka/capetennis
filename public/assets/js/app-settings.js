/**
 * Selects & Tags
 */

'use strict';



$(function () {
  const submitButton = $('#submitSettingsButton'),
    eventMultipleSelect = $('#select2Multiple'),
    submitTablePointsButton = $('#submit-points-table-button'),
    numEvents = $('#select2Basic');

   
  var series = $('input[name=series_id]').val();
  var updateSetting = APP_URL + '/backend/ranking/updateSettings/' + series;
  var dashboard = APP_URL + '/backend/dashboard';


  submitButton.on('click', function () {
    var events = eventMultipleSelect.val();

    var nums = numEvents.val();


    var formData = $('form').serialize() + "&events=" + events + "&nums=" + nums
    console.log(formData);
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      url: updateSetting,
      method: 'POST',
      data: formData,
      success: function (data) {
        console.log(data);
       // window.location = dashboard;
      },
      error: function (error) {
        console.log(error);
      },
    })

  })
  submitTablePointsButton.on('click', function () {
    var series = $('input[name=series_id]').val();
    var url = APP_URL + '/backend/ranking/updatePoints/' + series;
    console.log(url);
    var formData = $('#modalForm').serialize();
    console.log(formData);
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      url: url,
      method: 'POST',
      data: formData,
      success: function (data) {

        $('#add-edit-points').modal('toggle');
        var clone = '';
        var div = '';
        var ol = '';
        var small = '';

        clone = $('#displayPointsTable').empty();
        div = $('<div/>').addClass('demo-inline-spacing mt-3')
        ol = $('<ol/>').addClass('list-group list-group-numbered');
        // small = $('<small/>').addClass('text-light fw-semibold').text('Points for position');

        $.each(data, function (k, item) {

          var li = $('<li/>').addClass('list-group-item').text(item.score + ' points');

          div.append(ol);
          ol.append(li);
          $('#displayPointsTable').append(div);

        });
        console.log(clone);
      },
      error: function (error) {
        console.log(error);
      },
    })
  })

  $('.addButton').on('click', function () {
    console.log('ye');
    var rankBlock = $('.rank-block').first().clone().removeClass('d-none');
    console.log(rankBlock);
    $('.rank-placeholder').append(rankBlock)

    $('.minusButton').on('click', function () {
      console.log('ye')

      $(this).closest('.rank-block').remove();
    })
  })


});
