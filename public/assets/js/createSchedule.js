/**
 * Drag & Drop
 */
'use strict';



(function () {
  
  const cardEl = document.getElementById('sortable-cards0'),
    cardE2 = document.getElementById('sortable-cards1'),

    sortable = $('.sortable'),
    slots = $('.slots'),
    pendingTasks = document.getElementById('pending-tasks'),
    completedTasks = document.getElementById('completed-tasks');
  

  var saveorder = APP_URL + '/backend/result/saveOrder';
  var publishResults = APP_URL + '/backend/result/publish';

  


  // Multiple
  // --------------------------------------------------------------------
slots.each(function(value,item){
    Sortable.create(item, {
        animation: 150,
        group: 'taskList',
        onEnd: function (evt) {
            var itemEl = evt.item;  // dragged HTMLElement
            evt.to;    // target list
            evt.from;  // previous list
            evt.oldIndex;  // element's old index within old parent
            evt.newIndex;  // element's new index within new parent
            evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
            evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
            evt.clone // the clone element
            evt.pullMode;  // when item is in another sortable: `"clone"` if cloning, `true` if moving
       
            console.log(evt);


      
            },
      });
})

  
    Sortable.create(pendingTasks, {
      animation: 150,
      group: 'taskList',
      onEnd: function (evt) {
        var itemEl = evt.item;  // dragged HTMLElement
		evt.to;    // target list
		evt.from;  // previous list
		evt.oldIndex;  // element's old index within old parent
		evt.newIndex;  // element's new index within new parent
		evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
		evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
		evt.clone // the clone element
		evt.pullMode;  // when item is in another sortable: `"clone"` if cloning, `true` if moving
        var time = $(evt.to).data('slot');
        
        var venue =  $(evt.to).data('venue');
        var fixture = $(evt.item).data('fixtureid');
        console.log(evt.from,evt.to);
        console.log(time)
     
        console.log(venue)
        console.log(fixture);
 var url = APP_URL+'/schedule/save';
 console.log(url);
        $.ajax({

            url:url,
            type: 'get',
            data: {
              'time': time,
             
              'venue': venue,
              'fixture': fixture,
              

      
            },
            success: function (success) {
              console.log( success);
              //location.reload();
            },
            error: function (error) {
              console.log(error);
              alert('error:' + error);
            }
      
          })
        },
    });
  



})();
