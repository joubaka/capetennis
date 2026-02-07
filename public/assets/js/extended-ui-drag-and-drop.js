/**
 * Drag & Drop
 */
'use strict';



(function () {
  const cardEl = document.getElementById('sortable-cards0'),
    cardE2 = document.getElementById('sortable-cards1'),

    sortable = $('.sortable'),
    pendingTasks = document.getElementById('pending-tasks'),
    completedTasks = document.getElementById('completed-tasks'),
    cloneSource1 = document.getElementById('clone-source-1'),
    cloneSource2 = document.getElementById('clone-source-2'),
    handleList1 = document.getElementById('handle-list-1'),
    handleList2 = document.getElementById('handle-list-2'),
    imageList1 = document.getElementById('image-list-1'),
    imageList2 = document.getElementById('image-list-2');

  var saveorder = APP_URL + '/backend/result/saveOrder';
  var publishResults = APP_URL + '/backend/result/publish';
  // Cards

  sortable.each(function (item) {
    Sortable.create(this, {
      onEnd: function (evt) {
        $(evt.item).parent().find('.list-group-item').each(function () {
          var number = $(this).find('.number')
          number.empty();
          $(this).find('.number').text($(this).index() + 1);
          console.log($(this).closest('.card').find('.card-header'))
          $(this).closest('.card').find('.card-header').addClass('bg-label-success');

        });
        var order = Array();
        var ui = evt.to;
        var list = $(ui).find('li').toArray()
        var categoryevent = $(list[0]).data('categoryevent');
        $.each(list, function (key, value) {
          order[key] = $(value).val();
        })
        console.log(order)
        var saveorderUrl = saveorder + '/' + categoryevent;
        console.log(saveorderUrl);
        $.ajaxSetup({
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        });
        $.ajax({
          url: saveorderUrl,
          method: 'POST',
          data: {
            'order': order,

          }
          ,
          success: function (data) {
            console.log('success', data)
          },
          error: function (error) {
            console.log(error)
          }

        }


        )

      },

    })
  })
  // Images
  // --------------------------------------------------------------------
  if (imageList1) {
    Sortable.create(imageList1, {
      animation: 150,
      group: 'imgList'
    });
  }
  if (imageList2) {
    Sortable.create(imageList2, {
      animation: 150,
      group: 'imgList'
    });
  }

  // Cloning
  // --------------------------------------------------------------------
  if (cloneSource1) {
    Sortable.create(cloneSource1, {
      animation: 150,
      group: {
        name: 'cloneList',
        pull: 'clone',
        revertClone: true
      }
    });
  }
  if (cloneSource2) {
    Sortable.create(cloneSource2, {
      animation: 150,
      group: {
        name: 'cloneList',
        pull: 'clone',
        revertClone: true
      }
    });
  }

  // Multiple
  // --------------------------------------------------------------------
  if (pendingTasks) {
    Sortable.create(pendingTasks, {
      animation: 150,
      group: 'taskList'
    });
  }
  if (completedTasks) {
    Sortable.create(completedTasks, {
      animation: 150,
      group: 'taskList'
    });
  }

  // Handles
  // --------------------------------------------------------------------
  if (handleList1) {
    Sortable.create(handleList1, {
      animation: 150,
      group: 'handleList',
      handle: '.drag-handle'
    });
  }
  if (handleList2) {
    Sortable.create(handleList2, {
      animation: 150,
      group: 'handleList',
      handle: '.drag-handle'
    });
  }

  $('#publishResults').on('click', function () {
    var event = $(this).attr('data-event_id');
    var publishUrl = publishResults + '/' + event;

    $.ajax({

      url: publishUrl,
      type: 'get',
      data: {
        'event': event

      },
      success: function (success) {
        console.log('category:' + success);
        location.reload();
      },
      error: function (error) {
        console.log(error);
        alert('error:' + error);
      }

    })
  })
})();
