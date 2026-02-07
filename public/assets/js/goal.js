'use strict';

$(function () {
    const wizardNumbered = document.querySelector(".wizard-numbered");
    const pendingTasks = document.getElementById('pending-tasks');
    const completedTasks = document.getElementById('completed-tasks');
    const submitGoalButton = $('#submitGoalButton');




    var goalByDate = $('#goalByDate'),
        customDateSelect = $('#custom-date-select'),
        dates = $('.dates'),
        customDate = $('#custom-date');


    customDate.on('change', function () {
        customDateSelect.removeClass('d-none');
    });
    dates.on('click', function () {
        customDateSelect.addClass('d-none');
        var date = $('input[name=goalDate]:checked', '#goalForm').val();

        updateDate(date);
    });


    if (typeof wizardNumbered !== undefined && wizardNumbered !== null) {
        const wizardNumberedBtnNextList = [].slice.call(wizardNumbered.querySelectorAll('.btn-next')),
            wizardNumberedBtnPrevList = [].slice.call(wizardNumbered.querySelectorAll('.btn-prev')),
            wizardNumberedBtnSubmit = wizardNumbered.querySelector('.btn-submit');

        const numberedStepper = new Stepper(wizardNumbered, {
            linear: false
        });
        if (wizardNumberedBtnNextList) {
            wizardNumberedBtnNextList.forEach(wizardNumberedBtnNext => {
                wizardNumberedBtnNext.addEventListener('click', event => {
                    numberedStepper.next();
                });
            });
        }
        if (wizardNumberedBtnPrevList) {
            wizardNumberedBtnPrevList.forEach(wizardNumberedBtnPrev => {
                wizardNumberedBtnPrev.addEventListener('click', event => {
                    numberedStepper.previous();
                });
            });
        }
        if (wizardNumberedBtnSubmit) {
            wizardNumberedBtnSubmit.addEventListener('click', event => {


                var strokeList = $('#pending-tasks').find('li');
                var strokes = [];
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.each(strokeList, function (key, item) {
                    strokes.push($(item).data('id'));
                });

                
                var data = $('#goalForm').serializeArray();
                data.push({name:'names',value:strokes});
                var query_string = $.param(data);
                console.log(data);
                $.post(APP_URL+'/backend/goal', { 'names[]':  strokes,data:query_string },function(d){
                    console.log(d);
                    window.location.replace(APP_URL+'/frontend/player/profile/'+d);
                }).fail(function(error){
                    console.log(error);
                });
                console.log(data);
                console.log($('input[name=goalDate]:checked', '#goalForm').val())
                console.log(strokes);
            });
        }
    }
    goalByDate.on('change', function () {
        var val = goalByDate.val();
        updateDate(val);

    })

    Sortable.create(pendingTasks, {
        animation: 150,
        group: {
            name: 'taskList',
            put: function (to) {
              //return to.el.children.length < 1;
            }
          },
        onAdd: function (/**Event*/evt) {
            var strokeList = $(evt.to).find('li');
            var strokePlaceholder = $('#stroke');
            strokePlaceholder.text('');
            $.each(strokeList, function (key, item) {
                var id = $(item).data('id');
                var name = $(item).data('name');
                console.log(name);


                strokePlaceholder.append('<div class="badge bg-warning m-2">' + name + '</div><br>');
            })

        },
    });
    Sortable.create(completedTasks, {
        animation: 150,
        group: 'taskList',
        onAdd: function (/**Event*/evt) {
            var strokeList = $(evt.from).find('li');
            var strokePlaceholder = $('#stroke');
            strokePlaceholder.text('');
            $.each(strokeList, function (key, item) {
                var id = $(item).data('id');
                var name = $(item).data('name');
                console.log(name);


                strokePlaceholder.append('<div class="badge bg-warning m-2">' + name + '</div><br>');
            })
        }
    });
})
function updateDate(val) {
    $('#date').text(val);
    console.log(val);
}