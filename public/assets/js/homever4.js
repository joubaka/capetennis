'use strict';

$(function () {
  const createEventButton = $('#createEventButton');
  const getEvents = APP_URL + '/home/get_events';
  const showEvent = APP_URL + '/events/';
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

  $('#spinner').show();

  // Initialize Select2
  $('.select2user').each(function () {
    const $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',
      allowClear: true
    });
  });

  // Initialize Quill
  const fullToolbar = [
    [{ font: [] }, { size: [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ script: 'super' }, { script: 'sub' }],
    [{ header: '1' }, { header: '2' }, 'blockquote', 'code-block'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
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

  // Create Event Button Click
  createEventButton.on('click', function () {
    const information = info.root.innerHTML;
    const data = $('form').serialize() + '&info=' + encodeURIComponent(information);

    $.ajax({
      url: APP_URL + '/events',
      method: 'POST',
      data: data,
      success: function (response) {
        console.log(response);
        location.reload();
      },
      error: function (error) {
        console.log(error);
        alert('Error submitting event.');
      }
    });
  });

  // Render Event Card
  function renderEvent(event) {
    const startDate = new Date(event.startDate);
    const endDate = new Date(event.endDate);
    const deadlineDate = new Date(startDate);
    deadlineDate.setDate(startDate.getDate() - event.deadline);

    const img = `<img src="${APP_URL}/assets/img/logos/${event.logo}" height="120px" width="120px"
      style="margin:5px;border-radius:15px;display:inline-block" />`;

    const infoBtn = $('<a/>')
      .addClass('btn btn-label-success cancel-subscription waves-effect')
      .attr({ href: showEvent + event.id })
      .text('More Information');

    const div = $('#eventInfo').clone().removeClass('d-none');

    div.find('.eventName')
      .text(event.name)
      .attr('href', showEvent + event.id)
      .addClass('text-white mb-4');
    div.find('.startDate').text(startDate.toLocaleDateString('en-US', options));
    div.find('.endDate').text(endDate.toLocaleDateString('en-US', options));
    div.find('.deadline').text(deadlineDate.toLocaleDateString('en-US', options));
    div.find('.logo').html(img);
    div.find('.buttons').html(infoBtn);

    $('#test').append(div);
  }

  // Load Events
  function loadEvents(period = 'upcoming') {
    $('#spinner').show();
    $('#test').empty();

    $.ajax({
      url: getEvents,
      data: { period },
      success: function (data) {
        console.log(`${period} data`, data);
        $('#spinner').hide();
        data.forEach(renderEvent);
      },
      error: function (err) {
        console.log(err);
        alert('There was an error loading events.');
        $('#spinner').hide();
      }
    });
  }

  // On Period Change
  $('.time_period').on('change', function () {
    const period = $('.time_period input:checked').val();
    console.log('Selected period:', period);
    loadEvents(period);
  });

  // Initial load
  loadEvents('upcoming');
});
