$(function () {
    'use strict';

    const cats = document.getElementById('cats');
    const completedTasks = document.getElementById('test-0');
    var ranks = $('.sortable');

    $.each(ranks, function (k, v) {
        var r = document.getElementById('test-' + k);
        console.log(r);
        Sortable.create(r, {
            animation: 150,
            group: 'taskList',
            onAdd: function (/**Event*/evt) {
                var itemEl = evt.item;
                console.log(evt.to);    // target list

                var eventcategory = $(itemEl).data('eventcategory');
                var ranking_list = $(itemEl).parent().data('ranklist');
                console.log(eventcategory, ranking_list);
                $.post(APP_URL + '/backend/ranking/addCategory',{'eventCategory':eventcategory,'ranking_list': ranking_list}, function (data) {
                    console.log(data);
                });


            }
        });

    });
    Sortable.create(cats, {
        animation: 150,
        group: 'taskList',
        // Element dragging ended
        onAdd: function (/**Event*/evt) {

            var itemEl = evt.item;
                console.log(evt.to);    // target list

                var eventcategory = $(itemEl).data('eventcategory');
                var ranking_list = $(evt.from).data('ranklist');
              
                console.log(eventcategory, ranking_list);

                $.post(APP_URL + '/backend/ranking/deleteCategory',{'eventCategory':eventcategory,'ranking_list': ranking_list}, function (data) {
                    console.log(data);
                });

        }
    });


});