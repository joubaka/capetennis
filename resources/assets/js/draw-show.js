

$(function () {
    'use strict';
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var unlockDrawButton = $('.unlock-draw-button'),
        removeDrawButton = $('.remove-draw-button'),
        submitResultButton = $('#submit-result-button'),
        submitResultForm = $('#submit-result-form'),
        createDrawButton = $('#create-draw-button'),
        removeFromDrawButton = $('.remove-from-draw-button'),
        editResult = $('.editResult'),
        seedSelect = $('.seed-select');



    editResult.on('click', function () {
        var $this = $(this);


        var results = $this.data('result');
        $('#fixture_id').val($this.data('id'));
        updateScores(results);
        var $reg1name = $this.data('reg1')
        var $reg2name = $this.data('reg2')

        $('#reg2name').html($reg2name);
        $('#reg1name').html($reg1name);

        submitResultButton.on('click', function () {


            var data = submitResultForm.serialize();
            $.get(APP_URL + '/backend/fixture/insertResult', data, function (results) {

                clearScores($('.score'));
                $('#result-modal').modal('toggle');
                updateFixtureResult($this, results)
            }).fail(function (error) {
                console.log(error);
            })
            console.log(data);
            submitResultButton.off('click');
        })
        /*  */




        /*  $.get(APP_URL + '/backend/fixture/ajax/' + fixId, function (data) {
                   console.log(data);
               }).fail(function (error) {
                   console.log(error);
               }); 
               */


    })

    removeFromDrawButton.on('click', function () {
        console.log($(this).parents().closest('tr').remove())
        var id = $(this).data('id');
        var drawid = $(this).data('drawid');
        var $this = $(this);
        var url = APP_URL + '/backend/draw/registration/removePlayer/' + id;
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.ajax({
            url: url,
            data: {
                'id': id,
                'draw_id': drawid,
            },
            type: 'post',
            success: function (result) {
                console.log(result);
                Swal.fire({
                    title: 'Draw deleted',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
                // location.reload();
            },
            error: function (error) {
                console.log(error);
            }
        });

    })
    unlockDrawButton.on('click', function () {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete all fixtures and results for this draw!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, unlock it!',
            customClass: {
                confirmButton: 'btn btn-primary me-1',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.value) {
                var url = APP_URL + '/backend/draw/unlock/' + id;
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: url,

                    type: 'post',
                    success: function (result) {
                        console.log(result);
                        location.reload();
                    }
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
    })

    removeDrawButton.on('click', function () {
        var id = $(this).data('id');
        var $this = $(this);
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
                console.log($(this).parents().closest('.list-group-item'));
                $this.parents().closest('.list-group-item').remove();
                var url = APP_URL + '/backend/draw/' + id;
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: url,

                    type: 'DELETE',
                    success: function (result) {
                        console.log(result);
                        Swal.fire({
                            title: 'Draw deleted',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                        // location.reload();
                    }
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

    })

    console.log(seedSelect);
    seedSelect.on('change', function () {
        var reg_id = $(this).data('registration');
        var val = $(this).val();
        var draw_id = $(this).data('drawid');
        var url = APP_URL + '/backend/draw/changeSeed/' + draw_id;

        console.log(val, reg_id);
        $.post(url, { 'reg': reg_id, 'seed': val }, function (success) {
            console.log('success', success);
        }).fail(function (error) {
            console.log('error', error);
        });

        var array = [];
        $.each(seedSelect, function (key, value) {
            var tempVal = $(value).val()
            if (array.includes(tempVal)) {

                toastr.error('Seed already selected')
                console.log($(this).val(null));
            } else {
                if (tempVal > 0)

                    array.push(tempVal)
            }

            tempVal = null;


        })

        console.log(array)
    })
    $(".select2").each(function () {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>').select2({
            placeholder: 'Select value',
            dropdownParent: $this.parent(),
            searchInputPlaceholder: 'Type here to search..',

        });
    });

})
function updateScores(results) {
    $.each(results, function (key, item) {
        console.log(item.registration1_score);
        $('#reg1ScoreSet' + (key + 1)).val(item.registration1_score);
        $('#reg2ScoreSet' + (key + 1)).val(item.registration2_score);

    })
}
function clearScores(myClass) {
    $(myClass).each(function (key, item) {
        $(item).val('');
    });
    $.clearfo


}
function updateFixtureResult($this, results) {

    console.log($this.closest('tr').find('.resultTd'));

    var result = '';
    var sets = results.length;
    console.log(sets, results)
    $.each(results, function (key, item) {
        result += item.registration1_score + '-' + item.registration2_score
        if (key < (sets - 1)) {
            console.log(key, (sets - 1));
            result += ', ';
        } else {
            console.log(key, sets);

        }
    })
    var tr = $this.closest('tr');
    var td = tr.find('.resultTd').html(result);
    console.log(tr.find('a'));


}
// initialize SVG.js
var drawSize = 32;
var draw = SVG().addTo('#draw')

var height = 100;
var width = 100;
var boxTopPoint = 0;
var boxLeftPoint = 0;

drawBoxRnd1();

function drawBoxRnd1(height,width,boxLeftPoint,boxTopPoint) {
   var rect = draw.rect( width, height).fill('#f06')
   rect.attr({ x: boxLeftPoint, y: boxTopPoint })

return rect;

}