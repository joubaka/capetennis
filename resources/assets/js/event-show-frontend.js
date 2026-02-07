


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
    createPlayerButton = $('#createPlayerButton');



  select2Gender.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',


    });
  })
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
      method: "POST",
      url: APP_URL + '/backend/player',
      data: mydata,


      success: function (datas) {

        $('#addPlayerModal').modal('hide');
        console.log(datas);




      },
      error: function (error) {
        console.log(error)
      },

    });
  })



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
      multiple: true,
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
    $.ajax({
      url: APP_URL + '/backend/file/' + id,
      type: 'DELETE',
      success: function (data) {
        console.log(data)
      },
      error: function (error) {
        console.log(error)
      },


    });
    console.log($(this.closest('.file').remove()), id)
  })

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


    $('.mySpinner').removeClass('d-none')
    var event_id = $('#event_id').val();

    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      method: "POST",
      url: url,
      data: {
        'data': data,
        'event_id': event_id,
        'send_email': sendEmail,
      },
      success: function (data) {
        console.log(data);
        location.reload();
      }

    })
  })

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
      method: "PATCH",
      url: APP_URL + '/events/' + id,
      data: {
        data: $.param(data),
        categories: cats,
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



  $(".region").on('click', function (e) {
    e.preventDefault();
    var region = $(this).attr('data-regionid');
    $('.allregions').hide();
    $('#' + region).show();

  });


var withDrawPlayer = $('.withDrawPlayer');

 console.log('witdraw',withDrawPlayer);
// ALERT WITH FUNCTIONAL CONFIRM BUTTON
withDrawPlayer.on('click',function() {

  var cateventReg = $(this).data('id');


  Swal.fire({
    title: 'Are you sure?',
    text: "Your registraition fee will be refunded to your wallet. R10.00 will be deducted for administration fees.",
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
  }).then(function(result) {
    if (result.value) {
      Swal.fire({
        icon: 'success',
        title: 'Deleted!',
        text: 'Your file has been deleted.',
        customClass: {
          confirmButton: 'btn btn-success'
        }
        
      });
      $.post(APP_URL + '/backend/registration/withdrawPlayer', { 'categoryEventRegistration': cateventReg }, function (data) {
        console.log(data);
    
        //location.reload();
      }).fail(function (error) {
        console.log(error)
      });
    }
  });

  
});



})();
function checkLogin() {
  var loggedIn = AuthUser;
  if (!loggedIn) {
    alert('Please log-in as a user !');
    location.reload();
  } else {

  }

}