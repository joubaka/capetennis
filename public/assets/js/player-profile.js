'use strict';

$(function () {
    var select2 = $(".select2").select2({
        dropdownParent: $("#addExersize")
    });
var select2Practice = $(".select2Practice").select2({
        dropdownParent: $("#addPractice")
    });
    var select2Duration = $(".select2Duration").select2({
        dropdownParent: $("#addPractice")
    });
   
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    var confirmText = $('.deleteGoal');
    const confirmColor = document.querySelector('#confirm-color');
    console.log(confirmText);
    $(document).on('click', '.addGoal', function () {
        var $this = $(this);
        var goal = $this.data('goal');


        var player_id = $this.data('playerid');
        var goal_type_id = goal.id;
        var mygoal = 'mygoal';


        update_modal(goal.name, goal, player_id, goal_type_id)






    });

    $('.addGoalButton').on('click', function (e) {


        var data = $('#add_goal_form').serialize();
        var goal_type_id = 0;
        goal_type_id = $(this).attr('data-id');
        console.log('gt', goal_type_id);


        $.post(APP_URL + '/backend/goal', data, function (data) {
            var list = 0;
            $('#flipInXAnimationModal').modal('hide');
            var list = $(document).find('.goalList' + goal_type_id + '')
            var empty = list.find('li.empty');
            console.log('empty', list);
            if (empty.length > 0) {
                list.find('li').remove();
            }
            list.find('ol').append('<li class="list-group-item bg-label-success">' + data.info + ' <a class="deleteGoal" href="#" data-id="' + data.id + '"><i class="ms-2 p-1 me-2 fs-6 fa-solid fa-minus bg-danger text-white rounded-circle"></i>Remove</a></li>');


        }).fail(function (error) {
            console.log(error);
        });
    });




    $(document).on('click', '.deleteGoal', function () {
        var $this = $(this);
        var list = $(this).closest('li');
        var goal_id = $this.data('id');
        console.log(goal_id);


        // ALERT WITH FUNCTIONAL CONFIRM BUTTON

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
            customClass: {
                confirmButton: 'btn btn-primary me-1',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.value) {

                list.remove();
                $.ajax({
                    method: "DELETE",
                    url: APP_URL + '/backend/goal/' + goal_id,
                    data: goal_id,
                })
                    .done(function (msg) {
                        console.log('deleted');
                    }).fail(function (error) {
                        console.log(error);
                    });
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Your file has been deleted.',
                    customClass: {
                        confirmButton: 'btn btn-success'
                    }

                });
            }
        });



    });
})
function update_modal(title, goal, player_id, goal_type_id) {
    console.log('go', goal);
    $('#modalTitle').text('Add a ' + title + ' goal');
    $('#player_id').val(player_id);
    $('#goal_type_id').val(goal_type_id);
    $('#goal').text('Goal');
    $('#info').val('');
    $('#addGoalButton').attr({
        'data-id': goal.id,
    });

}


