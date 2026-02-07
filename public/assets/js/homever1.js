'use strict';

$(function () {
    var createEventButton = $('#createEventButton');

    $("#spinner").show();
    var base_url = window.location.host;
    var getEvents = APP_URL + '/home/get_events';
    var showEvent = APP_URL + '/events/';
    var div = '';

    $('.select2user').each(function () {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>').select2({
            placeholder: 'Select value',
            dropdownParent: $this.parent(),
            searchInputPlaceholder: 'Type here to search..',
            allowClear: true

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
    const info = new Quill('#full-editor', {
        bounds: '#full-editor',
        placeholder: 'Type Something...',
        modules: {
            formula: true,
            toolbar: fullToolbar
        },
        theme: 'snow'
    });


    createEventButton.on('click', function () {
        var information = info.root.innerHTML;
        var data = $('form').serialize() + '&info=' + information;



        $.ajax({
            url: APP_URL + '/events',
            method: "POST",
            data: data,
            success: function (data) {
                console.log(data);
                location.reload();
            },
            error: function (error) {
                console.log(error)
            }
        })
    });

    $.ajax({
        url: getEvents,
        data: {
            'period': 'upcoming'
        },
        success: function (data) {
          console.log('data',data)
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

            $('#spinner').hide();
          $.each(data, function ($index, $value) {
console.log('start',$value.start_date)
            console.log('deadline',$value.deadline)


            var img = '';
            var href = '';
            img = '<img src="' + window.location + 'assets/img/logos/' + $value.logo + '" height="120px" width="120px"  style="margin:5px;border-radius: 15px;display: inline-block" />';

            var start_date = new Date($value.start_date);
            var endDate = new Date($value.endDate);
            var information = $('<a/>').addClass('btn btn-label-success cancel-subscription waves-effect').attr({
              href: showEvent + $value.id,

            }).text('More Information')
            let deadline = new Date();

// Subtract 14 days
            deadline.setDate(start_date.getDate() - 14);


console.log(deadline);







            div = $('#eventInfo').clone().removeClass('d-none');
            div.find('.eventName').text($value.name).attr({

              'href': showEvent + $value.id,
            }).addClass('text-white mb-4');
            div.find('.start_date').text(start_date);
            div.find('.endDate').text(endDate);
            div.find('.buttons').html(information)





            div.find('.deadline').text(deadline.toLocaleDateString("en-US", options));
            div.find('.start_date').text(start_date.toLocaleDateString("en-US", options));
            div.find('.endDate').text(endDate.toLocaleDateString("en-US", options));
            div.find('.logo').html(img);


            $("#test").append(div);





          })


        },
        error: function (error) {
            console.log(error);
            alert("There was an error.", error);
        }
    });


    var html = $('#display_event').html();

    // on click, load the data dynamically into the #result div
    $(".time_period").on('change', function () {
        $('#spinner').show();
        var period;
        period = $('.time_period input:checked').val();
console.log('period',period)
        var template =
            $('#test').html('');
        $.ajax({
            url: getEvents,
            data: {
                'period': period
            },
            success: function (data) {
              console.log('data',data)
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                $('#spinner').hide();
                $.each(data, function ($index, $value) {

                    var img = '';
                    var href = '';
                    img = '<img src="' + window.location + 'assets/img/logos/' + $value.logo + '" height="120px" width="120px"  style="margin:5px;border-radius: 15px;display: inline-block" />';
                    var deadline = new Date($value.endDate);
                    var start_date = new Date($value.start_date);
                    var endDate = new Date($value.endDate);
                    var information = $('<a/>').addClass('btn btn-label-success cancel-subscription waves-effect').attr({
                        href: showEvent + $value.id,

                    }).text('More Information')




                    start_date.setDate(start_date.getDate());
                    endDate.setDate(endDate.getDate());
                    deadline.setDate(start_date.getDate() - ($value.deadline));
                    div = $('#eventInfo').clone().removeClass('d-none');
                    div.find('.eventName').text($value.name).attr({

                        'href': showEvent + $value.id,
                    }).addClass('text-white mb-4');
                    div.find('.start_date').text(start_date);
                    div.find('.endDate').text(endDate);
                    div.find('.buttons').html(information)
                    console.log(div.find('.buttons'))




                    div.find('.deadline').text(deadline.toLocaleDateString("en-US", options));
                    div.find('.start_date').text(start_date.toLocaleDateString("en-US", options));
                    div.find('.endDate').text(endDate.toLocaleDateString("en-US", options));
                    div.find('.logo').html(img);


                    $("#test").append(div);





                })



            },
            error: function () {
                console.log(error);
                alert("There was an error.");
            }
        });


    });


    $('#spinner').hide();

});
