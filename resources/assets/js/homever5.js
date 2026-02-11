'use strict';

$(function () {

  console.log('[INIT] Events page JS loaded');

  const createEventButton = $('#createEventButton');
  const getEvents = APP_URL + '/home/get_events';
  const showEvent = APP_URL + '/events/';
  const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

  console.log('[CONFIG]', {
    getEvents,
    showEvent
  });

  $('#spinner').show();

  /* =========================
     SELECT2 INIT
  ========================= */
  $('.select2user').each(function () {
    const $this = $(this);
    console.log('[SELECT2] Initialising', $this.attr('name') || $this);

    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      searchInputPlaceholder: 'Type here to search..',
      allowClear: true
    });
  });

  /* =========================
     QUILL INIT
  ========================= */
  console.log('[QUILL] Initialising editor');

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

  /* =========================
     CREATE EVENT
  ========================= */
  createEventButton.on('click', function () {
    const information = info.root.innerHTML;
    const data = $('form').serialize() + '&info=' + encodeURIComponent(information);

    console.log('[CREATE EVENT] Payload', data);

    $.ajax({
      url: APP_URL + '/events',
      method: 'POST',
      data: data,
      success: function (res) {
        console.log('[CREATE EVENT] Success', res);
        location.reload();
      },
      error: function (error) {
        console.error('[CREATE EVENT] Error', error);
        alert('Error submitting event.');
      }
    });
  });

  /* =========================
     RENDER EVENT CARD
  ========================= */
  function renderEvent(event) {
    console.log('[RENDER] Event raw', event);

    if (!event || !event.start_date) {
      console.warn('[RENDER] Skipped invalid event', event);
      return;
    }

    const startDate = new Date(event.start_date);
    const endDate = event.end_date ? new Date(event.end_date) : null;

    console.log('[RENDER] Parsed dates', {
      start_date: startDate,
      end_date: endDate
    });

    const deadlineDate = new Date(startDate);
    if (event.deadline !== null) {
      deadlineDate.setDate(startDate.getDate() - parseInt(event.deadline, 10));
    }

    console.log('[RENDER] Deadline date', deadlineDate);

    const img = event.logo
      ? `<img src="${APP_URL}/assets/img/logos/${event.logo}"
              height="120" width="120"
              style="margin:5px;border-radius:15px;display:inline-block" />`
      : '';

    const infoBtn = $('<a/>')
      .addClass('btn btn-label-success cancel-subscription waves-effect')
      .attr('href', showEvent + event.id)
      .text('More Information');

    const card = $('#eventInfo').clone().removeClass('d-none');

    card.find('.eventName')
      .text(event.name)
      .attr('href', showEvent + event.id)
      .addClass('text-white mb-4');

    card.find('.start_date').text(
      startDate.toLocaleDateString('en-US', dateOptions)
    );

    card.find('.end_date').text(
      endDate ? endDate.toLocaleDateString('en-US', dateOptions) : '—'
    );

    card.find('.deadline').text(
      event.deadline !== null
        ? deadlineDate.toLocaleDateString('en-US', dateOptions)
        : '—'
    );

    card.find('.logo').html(img);
    card.find('.buttons').html(infoBtn);

    console.log('[RENDER] Appending event card', event.id);

    $('#test').append(card);
  }

  /* =========================
     LOAD EVENTS
  ========================= */
  function loadEvents(period = 'upcoming') {
    console.log('[LOAD EVENTS] Period:', period);

    $('#spinner').show();
    $('#test').empty();

    $.ajax({
      url: getEvents,
      data: { period },
      success: function (data) {
        console.log('[LOAD EVENTS] Response', data);
        $('#spinner').hide();

        if (!Array.isArray(data)) {
          console.warn('[LOAD EVENTS] Unexpected response format', data);
          return;
        }

        if (!data.length) {
          console.info('[LOAD EVENTS] No events returned');
        }

        data.forEach(renderEvent);
      },
      error: function (err) {
        console.error('[LOAD EVENTS] AJAX error', err);
        alert('There was an error loading events.');
        $('#spinner').hide();
      }
    });
  }

  /* =========================
     PERIOD SWITCH
  ========================= */
  $('.time_period').on('change', function () {
    const period = $('.time_period input:checked').val();
    console.log('[PERIOD CHANGE] Selected:', period);
    loadEvents(period);
  });

  /* =========================
     INITIAL LOAD
  ========================= */
  console.log('[INIT] Initial load: upcoming');
  loadEvents('upcoming');

});
