(function () {
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  var userSelect2 = $('#select2user');
  var editEventButton = $('#editEventButton'),
    select2Gender = $('.select2gender'),
    select2Categories = $('.select2Categories'),
    clothingButton = $('.clothing-order'),
    submitClothingOrderButton = $('#submitClothingOrderButton'),
    cloothingOrderList = $('#clothingOrderList'),
    createPlayerButton = $('#createPlayerButton');

  console.log(cloothingOrderList);
  $('.btn-close-but').on('click',function(){
    location.reload();
  })

  $('.checkbox').on('change', function () {
    var id = $(this).data('id');
    var nrOf = $(document).find('#nrOf' + id);
    if (this.checked) {
      nrOf.removeClass('d-none');
    } else {
      console.log(nrOf);
      nrOf.addClass('d-none');
    }
  });
  clothingButton.on('click', function (e) {
    let region_id = $(this).data('region');
    console.log(region_id);

    $.ajax({
      method: 'POST',
      url: APP_URL + '/backend/region/getRegionClothingItems',
      data: {
        region: region_id
      },

      success: function (response) {
        $('#addPlayerModal').modal('hide');
        console.log('responses is', response);

        addClothingItems(response);

        // Define available sizes for each clothing type
      },
      error: function (error) {
        alert('Please log in to order Clothing')
        window.location.href = APP_URL+'/login';
        console.log(error);
      }
    });

     $('#name').html($(this).data('name'));
     var playerId =$(this).data('playerid');
     var teamId =$(this).data('team');
     console.log(playerId,teamId)
     console.log($('#player_id'));
     console.log($('#team_id'));
     $('#player_id').val(playerId);
     $('#team_id').val(teamId);
    // $('.switch-input').on('change', function () {
    //   console.log('checked-toggle');
    //   if (this.checked) {
    //     $(this).parents('.item').find('.options').removeClass('d-none');
    //     $(this).find('option:selected').remove();
    //   } else {
    //     $(this).parents('.item').find('.options').addClass('d-none');
    //   }

    // })
  });
submitClothingOrderButton.on('click',function(e){
  var selectedRadio =  $('input[type="radio"]:checked');
 var radioVals = [];
 var items = [];
  $.each(selectedRadio,function(key,item){
    console.log(item)
    radioVals.push($(this).val());
    items.push($(this).attr('name'));
    


    $('<input>').attr({
      type: 'hidden',
      name: 'size[]',
      value: $(this).val()
  }).appendTo('#myForm');

  $('<input>').attr({
      type: 'hidden',
      name: 'item[]',
      value: $(this).attr('name')
  }).appendTo('#myForm');
  })
  console.log(radioVals,items)
  $('#myForm').submit();
  $('input[name="size[]"]').remove();
  $('input[name="item[]"]').remove();
})
  select2Gender.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..'
    });
  });
  createPlayerButton.on('click', function (f) {
    var mydata = $('.formPlayer').serialize();
    var selectIndex = 10;

    selectIndex = $('#createPlayerButton').attr('data-index');
    console.log($('.select2Gender'));

    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      method: 'POST',
      url: APP_URL + '/backend/player',
      data: mydata,

      success: function (datas) {
        $('#addPlayerModal').modal('hide');
        console.log(datas);
      },
      error: function (error) {
        console.log(error);
      }
    });
  });

  console.log('sel', select2Categories);

  userSelect2.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',
      allowClear: true
    });
  });

  select2Categories.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',
      allowClear: true,
      multiple: true
    });
  });

  var test = [];

  $.each(eventCategories, function (e, v) {
    test.push(v.category_id);
  });
  console.log(test);
  $('#select2Categories').val(test).trigger('change');
  var editEvent = $('#editEvent');

  var test1 = [];
  console.log('p', administrators);
  $.each(administrators, function (e, v) {
    test1.push(v.user_id);
  });
  console.log(test1);
  $('#select2user').val(test1).trigger('change');

  const url = APP_URL + '/backend/announcement';
  var deleteFileButton = $('.deleteFileButton');

  deleteFileButton.on('click', function () {
    var id = $(this).attr('data-id');
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    var url = APP_URL + '/file/' + id;
    console.log(url);
    $.ajax({
      url: APP_URL + '/file/' + id,
      type: 'DELETE',
      success: function (data) {
        console.log(data);
      },
      error: function (error) {
        console.log(error);
      }
    });
    console.log($(this.closest('.file').remove()), id);
  });

  // Full Toolbar
  // --------------------------------------------------------------------
  const fullToolbar = [
    [
      {
        font: []
      },
      {
        size: []
      }
    ],
    ['bold', 'italic', 'underline', 'strike'],
    [
      {
        color: []
      },
      {
        background: []
      }
    ],
    [
      {
        script: 'super'
      },
      {
        script: 'sub'
      }
    ],
    [
      {
        header: '1'
      },
      {
        header: '2'
      },
      'blockquote',
      'code-block'
    ],
    [
      {
        list: 'ordered'
      },
      {
        list: 'bullet'
      },
      {
        indent: '-1'
      },
      {
        indent: '+1'
      }
    ],
    [{ direction: 'rtl' }],
    ['link', 'image', 'video', 'formula'],
    ['clean']
  ];
  const fullEditor = new Quill('#full-editor', {
    bounds: '#full-editor',
    placeholder: 'Type Something...',
    modules: {
      formula: true,
      toolbar: fullToolbar
    },
    theme: 'snow'
  });

  const fullEditorEdit = new Quill('#full-editor-edit', {
    bounds: '#full-editor',
    placeholder: 'Type Something...s',
    modules: {
      formula: true,
      toolbar: fullToolbar
    },
    theme: 'snow'
  });
  $('#addAnouncementButton').on('click', function () {
    var data = fullEditor.root.innerHTML;
    var sendEmail = $('input[name="sendMail"]:checked').length;

    $('.mySpinner').removeClass('d-none');
    var event_id = $('#event_id').val();

    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      method: 'POST',
      url: url,
      data: {
        data: data,
        event_id: event_id,
        send_email: sendEmail
      },
      success: function (data) {
        console.log(data);
        location.reload();
      }
    }).fail(function (error) {
      console.log('error send mail announcement', error);
    });
  });

  editEventButton.on('click', function () {
    var information = fullEditorEdit.root.innerHTML;
    console.log(information);

    $('.mySpinner').removeClass('d-none');
    var categories = $('#select2Categories').select2('data');

    var cats = [];
    $.each(categories, function (k, v) {
      cats.push(v.id);
    });
    var data = $(this).closest('form').serializeArray();
    data.push({ name: 'information', value: information });
    var id = $(this).data('id');

    console.log(cats);

    $.ajax({
      method: 'PATCH',
      url: APP_URL + '/events/' + id,
      data: {
        data: $.param(data),
        categories: cats
      },
      success: function (data) {
        console.log(data);
        location.reload();
      },
      error: function (error) {
        alert(error);
        //location.reload();
      }
    });
  });

  $('.region').on('click', function (e) {
    e.preventDefault();
    var region = $(this).attr('data-regionid');
    $('.allregions').hide();
    $('#' + region).show();
  });

  var withDrawPlayer = $('.withDrawPlayer');

  console.log('witdraw', withDrawPlayer);
  // ALERT WITH FUNCTIONAL CONFIRM BUTTON
  withDrawPlayer.on('click', function () {
    var cateventReg = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: 'Your registraition fee will be refunded to your wallet. R10.00 will be deducted for administration fees.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, withdraw player from event!',
      customClass: {
        confirmButton: 'btn btn-primary me-1',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Your file has been deleted.',
          customClass: {
            confirmButton: 'btn btn-success'
          }
        });
        $.post(
          APP_URL + '/backend/registration/withdrawPlayer',
          { categoryEventRegistration: cateventReg },
          function (data) {
            console.log(data);

            //location.reload();
          }
        ).fail(function (error) {
          console.log(error);
        });
      }
    });
  });
  function addClothingItems(response) {
    $.each(response.clothing, function (index, value) {
      var checkbox = $('<input>')
        .attr({
          type: 'checkbox',
          id: 'radio_' + value.id,
         
          value: value.id
        })
        .addClass('form-check-input');

      // Create a label element
      var checkboxlabel = $('<label>')
        .attr('for', 'checkbox_' + value.id)
        .html('<h4>' + value.item_type_name + '</h4>');
      var div = $('<div>').addClass('form-check mt-4');
      var radioContainer = $('<div>')
        .attr({
          id: 'item_' + value.id
        })
        .addClass('d-none');
      checkbox.append(checkboxlabel);
      // Append the checkbox and label to the container

      div.append(checkbox).append(checkboxlabel).append('<br>');
      div.append(radioContainer);

      addRadios(value, radioContainer);

      // div.append(radio)
      //radioContainer.append(div);
      $('#clothingOrderList').append(div);

      $('#radio_' + value.id).on('click', function (e) {
        var radios = $('#container' + value.id).find('input[type="radio"]');
        console.log($('#item_' + value.id));
        
        $('#item_' + value.id).removeClass('d-none');
      });
    });
  }
  function addRadios(value, radioContainer) {
    console.log(value, $(radioContainer));
    var radiolabel = $('<label>').attr('for', 'radio_none').text('Not Needed');

    var noneRadio = $('<input>')
      .attr({
        type: 'radio',
        id: 'radio_none',
       name: value.id,
        value: '0'
      })
      .addClass('form-check-input ');
      noneRadio.prop('checked', true);
    $(radioContainer).append(noneRadio).append(radiolabel).append('<br>');
    $.each(value.sizes, function (key, size) {
      console.log(key, size);
      var radio = $('<input>')
        .attr({
          type: 'radio',
          id: 'radio_' + size.id,
          name: value.id,
          value: size.id
        })
        .addClass('form-check-input ')
        .addClass('');

      var radiolabel = $('<label>')
        .attr('for', 'radio_' + size.id)
        .text(size.size)
        .addClass('');
      //var itemInfo = $('#item_' + value.id);

   
      $(radioContainer).append(radio).append(radiolabel).append('<br>');

      
    });
   
    //div;
  }
})();
function checkLogin() {
  var loggedIn = AuthUser;
  if (!loggedIn) {
    alert('Please log-in as a user !');
    location.reload();
  } else {
  }
}
