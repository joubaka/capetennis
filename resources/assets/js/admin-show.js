/**
 * App User View - Account (jquery)
 */

$(function () {
  'use strict';
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  const toastAnimationExample = document.querySelector('.toast-ex'),
    withdrawButton = $('.withdrawButton'),
    submitScoreButton = $('.submitScoreButton'),
    addPlayerSelect2 = $('#select2PlayerBasic'),
    addPlayerC = $('.addPlayerC'),
    addPlayerToCategoryButton = $('#addPlayerToCategoryButton'),
    sendMailUrl = APP_URL + '/backend/email/send',
    registerPlayerInCategoryUrl = APP_URL + '/register/registerAdmin';
  var saveorder = APP_URL + '/backend/result/saveOrder',
    select2Region = $('.select2Region'),
    addRegionButton = $('.addRegion'),
    addTeamButton = $('.addTeam'),
    updateTeamButton = $('#updateTeamButton'),
    playerPositionButton = $('#playerPosition'),
    addRegionToEventButton = $('#addRegionToEventButton'),
    sortablePlayers = $('.sortablePlayers'),
    publishTeamButton = $('.publishTeam'),
    select2playerInsert = $('.select2PlayerInsert'),
    transactionTable = $('#transactionTable'),
    select2Basic = $('.select2Basic'),
    orderItemsTable = $('#orderItemsTable');

  console.log('sel', transactionTable);

  orderItemsTable.DataTable();
  transactionTable.DataTable({
    columns: [
      {
        data: 'id',
        width: '5%'
      },
      {
        data: 'created_at',
        width: '5%'
      },
      {
        data: 'transaction_type',
        width: '5%'
      },
      {
        data: 'User',
        width: '15%'
      },
      {
        data: '',
        width: '30%'
      },

      {
        data: 'amount_gross',
        width: '8%'
      },
      {
        data: 'Payfast Fee',
        width: '8%'
      },
      {
        data: 'Cape Tennis Fee',
        width: '8%'
      },
      {
        data: 'Nett',
        width: '8%'
      }
    ],
    columnDefs: [
      {
        // For Responsive
        className: 'control dt-head-center',
        orderable: false,
        searchable: false,
        responsivePriority: 2,
        targets: 1,

        render: function (data, type, full, meta) {
          console.log(full);
          return full.created_at;
        }
      },
      {
        className: 'control dt-head-center',
        targets: 4
      }
    ],

    paging: false,
    autoWidth: false,

  });

  $(document).on('click', '.publishTeam', function () {
    var id = $(this).data('id');
    var $this = $(this);
    var btnState = $(this).parent().find('a.publishTeam').data('state');
    console.log(btnState);
    $.ajax({
      url: APP_URL + '/backend/team/publishTeam/' + id,
      data: id,
      method: 'POST',
      error: function (error) {
        console.log(error);
      },
      success: function (data) {
        console.log(data);
        if (!data.published == 1) {
          $this.parent().children('.badge');
          $this.parent().find('a.publishTeam').data('state', 1);
          $this.children().removeClass('bg-label-danger');
          $this.children().addClass('bg-label-success').text('Publish Team');
        } else {
          console.log($this.children());
          $this.parent().find('a.publishTeam').data('state', 0);
          $this.children().removeClass('bg-label-success');
          $this.children().addClass('bg-label-danger').text('Unpublish Team');
        }
        // location.reload();
      }
    });
  });
  ///sortable
  $.each(sortablePlayers, function (key, value) {
    var $this = $(this);
    Sortable.create(value, {
      group: 'list' + key,
      // Element dragging ended
      onEnd: function (/**Event*/ evt) {
        var itemEl = evt.item; // dragged HTMLElement
        var list = evt.to; // target list
        evt.from; // previous list
        evt.oldIndex; // element's old index within old parent
        evt.newIndex; // element's new index within new parent
        evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
        evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
        evt.clone; // the clone element
        evt.pullMode; // when item is in another sortable: `"clone"` if cloning, `true` if moving
        var mems = $(list).children();
        console.log(mems, list);
        var data = [];
        mems.each(function (i) {
          //alert('Order:' +$(this).attr('rel')+' I: '+i); // This is your rel value
          // save the index and the value
          data.push($(this).attr('data-playerteamid'));
        });
        var clone = $this.children().first();
        console.log(clone);
        var div = $('.dropdown').clone();
        $this.empty();

        $.post(APP_URL + '/backend/team/orderPlayerList', { data: data }, function (mydatas) {
          $.each(mydatas, function (k, v) {
            console.log(v);
            var tr = $('<tr/>')
              .addClass('row-' + v.id + ' drag-item')
              .attr({
                'data-playerteamid': v.id
              });
            var tdrank = $('<td/>').append(
              $('<span/>')
                .addClass('badge bg-label-primary')
                .text(k + 1)
            );

            var tdname = $('<td/>')
              .addClass('name')
              .text(v.player.name + ' ' + v.player.surname);
            var tdemail = $('<td/>').addClass('email').text(v.player.email);
            var tdcellnr = $('<td/>').addClass('cellNr').text(v.player.cellNr);
            var div = $('.listDropdown').first().clone().attr({
              'data-pivot': k.id
            });
            tr.append(tdrank);

            tr.append(tdname);
            tr.append(tdemail);
            tr.append(tdcellnr);
            tr.append($('<td/>').append(div));

            $this.append(tr);
          });
        }).fail(function (datas) {
          console.log(datas);
        });
      }
    });
  });

  playerPositionButton.on('click', function () {
    var player = addPlayerSelect2.select2('data');
    var data = $('#playerForm').serialize() + '&player=' + player[0].id;
    console.log(data);
    $.post(APP_URL + '/backend/team/insertPlayer', data, function (data) {
      console.log(data);
    })
      .fail(function (data) {
        console.log(data);
      })
      .done(function (data) {
        var row = $('.row-' + data.id);
        row.find('.name').text(data.player.name + ' ' + data.player.surname);
        row.find('.email').text(data.player.email);
        row.find('.cellNr').text(data.player.cellNr);

        console.log(row);
      });
    // update die row
    console.log();
  });
  $('.select2').select2();

  updateTeamButton.on('click', function () {
    var ul = $('.team-list');
    var data = $('#teamForm').serialize();
    $.post(APP_URL + '/backend/team', data, function (data) {
      var li = '<li class="list-group-item">' + data.name + '<a href="javascript:void(0)';
      li += 'class="ms-2 removeTeam" data-id="282"><i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i>';
      li += '</a></li>';
      ul.append(li);
    }).fail(function (data) {
      console.log(data);
    });
  });
  $(document).on('click', '.insertPlayer', function (e) {
    var team_id = $(this).data('teamid');
    var position = $(this).data('position');
    var pivot_id = $(this).data('pivot');
    $('#playerPosition').text('Insert player into position ' + position);
    $('#teamId').val(team_id);
    $('#position').val(position);
    $('#pivot').val(pivot_id);
    console.log(team_id, position);
  });

  $(document).on('click', '.removeTeam', function (e) {
    var id = $(this).attr('data-id');
    var url = APP_URL + '/backend/team/' + id;
    console.log(url);
    $(this).closest('li').remove();
    $.ajax({
      url: url,
      data: id,
      method: 'DELETE',
      error: function (error) {
        console.log(error);
      },
      success: function (data) {
        console.log(data);
        // location.reload();
      }
    });
  });

  addTeamButton.on('click', function () {
    $('#region_id').val($(this).data('regionid'));
    console.log($(this).data('regionid'));
  });

  addRegionToEventButton.on('click', function () {
    var data = $('#regionEventForm').serialize();
    console.log(data);
    $.post(APP_URL + '/backend/eventRegion', data, function (data) {
      console.log(data);
      $('.noRegions').addClass('d-none');
      $('.regionList').append(
        '<li class="list-group-item d-flex align-items-center">' +
          data.region_name +
          '  <a class="ms-2 removeRegionEvent" href="javascript:void(0)"  data-id="{{$region->id}}"><i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i></a></li>'
      );
      location.reload();
    }).fail(function (error) {
      console.log(error);
    });
  });

  addRegionButton.on('click', function () {
    var data = $('#regionForm').serialize();
    console.log(data);
    $.post(APP_URL + '/backend/region', data, function (data) {
      console.log(data);
      var newState = new Option(data.region_name, data.id, true, true);
      // Append it to the select
      select2Region.append(newState).trigger('change');
    });
  });
  submitScoreButton.on('click', function (t) {
    var data = $(this).parent().find('form').serialize();
    var header = $(this).closest('.card').find('.card-header').addClass('bg-label-success');
    console.log('headder', header);
    var categoryEvent = $(this).parent().find('input[name=category_event]').val();

    var saveorderUrl = saveorder + '/' + categoryEvent;
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      url: saveorderUrl,
      method: 'POST',
      data: data,
      success: function (data) {
        console.log('success', data);
      },
      error: function (error) {
        console.log(error);
      }
    });
  });

  // ALERT WITH FUNCTIONAL CONFIRM & CANCEL BUTTON
  $(document).on('click', '.withdrawButton', function () {
    var player = $(this).data('player');
    var registration = $(this).data('registrationid');
    var categoryEvent = $(this).data('categoryeventid');
    var url = APP_URL + '/backend/registration/delete';

    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, withdraw ' + player + ' !',
      customClass: {
        confirmButton: 'btn btn-primary me-1',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        withdrawPlayer(registration, categoryEvent, url);
        Swal.fire({
          icon: 'success',
          title: 'Withdrawn!',
          text: 'Player has been withdrawn from event!',
          customClass: {
            confirmButton: 'btn btn-success'
          }
        });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        Swal.fire({
          title: 'Cancelled',
          text: 'Withdrawal cancelled',
          icon: 'warning',
          customClass: {
            confirmButton: 'btn btn-success'
          }
        });
      }
    });
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
  $('.sendEmail').on('click', function () {
    var toType = $(this).data('totype');
    var email = $(this).data('email');
    if (toType == 'one') {
      $('.email-to').val(email);
    }
  });

  //
  console.log('select2', addPlayerSelect2.length);

  if (addPlayerSelect2.length) {
    addPlayerSelect2.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent()
      });
    });
  }

  addPlayerToCategoryButton.on('click', function () {
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    var data = $('#addPlayerToCatergoryForm').serialize();
    console.log(data);
    $.ajax({
      method: 'POST',
      url: registerPlayerInCategoryUrl,
      data: data,
      success: function (data) {
        console.log(data);
        location.reload();
      },
      error: function (error) {
        console.log(error);
      }
    });
  });
  addPlayerC.on('click', function () {
    var catEvent_id = $(this).data('categoryeventid');
    $('#categoryEvent').val(catEvent_id);
  });
  $('.playerTable').dataTable();

  $('.btn-close').on('click', function () {
    location.reload();
  });

  if (select2Region.length) {
    select2Region.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent(),
        searchInputPlaceholder: 'Type here to search..',
        language: {
          noResults: function () {
            return $(
              "<div class='btn btn-primary btn-sm data-name data-index='" +
                0 +
                "' data-bs-toggle='modal' data-bs-dismiss='modal' data-bs-target='#modalToggle2'>Add New Player</div>"
            );
          }
        }
      });
    });
  }

  if (select2Basic.length) {
    select2Basic.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent(),
        searchInputPlaceholder: 'Type here to search..'
      });
    });
  }

  $(document).on('click', '.removeRegionEvent', function () {
    var id = $(this).data('id');
    console.log(id);
    var url = APP_URL + '/backend/eventRegion/' + id;
    console.log(url);
    $(this).closest('li').remove();
    $.ajax({
      url: url,
      data: id,
      method: 'DELETE',
      error: function (error) {
        console.log(error);
      },
      success: function (data) {
        console.log(data);
        location.reload();
      }
    });
  });

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  var bsValidationForms = document.querySelectorAll('.needs-validation');
  console.log(bsValidationForms);
  // Loop over them and prevent submission
  Array.prototype.slice.call(bsValidationForms).forEach(function (form) {
    form.addEventListener(
      'submit',
      function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        } else {
          var message = fullEditor.root.innerHTML;

          var data = $('form').serializeArray();
          data.push({ name: 'message', value: message });
          $.ajaxSetup({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          });
          $.ajax({
            url: sendMailUrl,
            method: 'POST',
            data: $.param(data),
            success: function (data) {
              console.log(data);
              toastr.options.showMethod = 'slideDown';
              toastr.options.hideMethod = 'slideUp';
              toastr.options.closeMethod = 'slideUp';
              toastr.options.closeButton = true;
              toastr.success(data.message);
              //location.reload();
            },
            error: function (error) {
              console.log(error);
              toastr.options.showMethod = 'slideDown';
              toastr.options.hideMethod = 'slideUp';
              toastr.options.closeMethod = 'slideUp';
              toastr.options.closeButton = true;
              toastr.error(data.message);
            }
          });
          console.log(data, message);
        }

        form.classList.add('was-validated');
      },
      false
    );
  });
});
function withdrawPlayer(registration, categoryEvent, url) {
  console.log(categoryEvent);
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $.ajax({
    url: url,
    data: {
      registration: registration,
      categoryEvent: categoryEvent
    },
    type: 'POST',
    success: function (result) {
      console.log(result);
      location.reload();
    }
  });
}
function changeRecipants(to, id) {
  console.log(to, id);
  if (to == 'one') {
  } else {
    if (to == 'team') {
      $('.team_id').val(id);
      $('.email-to').attr('value', 'All players in ' + to);
    } else if (to == 'unregistered_event') {
      $('.region_id').val(id);
      $('.email-to').attr('value', 'All Unregistered players in Event');
    } else {
      $('.region_id').val(id);
      $('.email-to').attr('value', 'All players in ' + to);
    }
  }
}
function email(teamId) {
  console.log(teamId);
}
