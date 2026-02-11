import $ from 'jquery';

$(function () {

  /**
   * --------------------------------------------------
   * Base URLs (injected from Blade)
   * --------------------------------------------------
   *
   * Required in Blade:
   *
   * <meta name="app-url" content="{{ config('app.url') }}">
   *
   * <script>
   *   window.routes = {
   *     homeGetEvents: "{{ route('home.events.get') }}",
   *     eventShow: "{{ url('/events') }}/"
   *   };
   *   window.assetBase = "{{ asset('') }}";
   * </script>
   */

  const APP_URL =
    document.querySelector('meta[name="app-url"]')?.content ||
    window.location.origin;

  const getEvents = window.routes?.homeGetEvents;
  const showEvent = window.routes?.eventShow;
  const assetBase = window.assetBase || `${APP_URL}/`;

  if (!getEvents || !showEvent) {
    console.error('Required routes not defined on window.routes');
    return;
  }

  // --------------------------------------------------
  // Date formatting options
  // --------------------------------------------------
  const dateOptions = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  };

  let searchTimer = null;

  // --------------------------------------------------
  // Render single event card
  // --------------------------------------------------
  function renderEvent(event) {
    if (!event || !event.start_date) return;

    const startDate = new Date(event.start_date);
    const endDate = event.end_date ? new Date(event.end_date) : null;

    const deadlineDate = new Date(startDate);
    if (event.deadline !== null) {
      deadlineDate.setDate(
        startDate.getDate() - parseInt(event.deadline, 10)
      );
    }

    const img = event.logo
      ? `<img src="${assetBase}assets/img/logos/${event.logo}"
              height="120"
              width="120"
              style="margin:5px;border-radius:15px" />`
      : '';

    const card = $('#eventInfo').clone().removeClass('d-none');

    card.find('.eventName')
      .text(event.name)
      .attr('href', showEvent + event.id)
      .addClass('text-white');

    card.find('.start_date')
      .text(startDate.toLocaleDateString('en-US', dateOptions));

    card.find('.end_date')
      .text(endDate ? endDate.toLocaleDateString('en-US', dateOptions) : '—');

    card.find('.deadline')
      .text(
        event.deadline !== null
          ? deadlineDate.toLocaleDateString('en-US', dateOptions)
          : '—'
      );

    card.find('.logo').html(img);

    card.find('.buttons').html(
      `<a href="${showEvent + event.id}"
          class="btn btn-label-success">
        More Information
       </a>`
    );

    $('#test').append(card);
  }

  // --------------------------------------------------
  // Load events via AJAX
  // --------------------------------------------------
  function loadEvents() {
    const period = $('.time_period input:checked').val();
    const search = $('#eventSearch').val();

    $('#test').empty();
    $('#spinner1').removeClass('d-none');

    $.ajax({
      url: getEvents,
      data: { period, search },
      success(data) {
        $('#spinner1').addClass('d-none');
        if (Array.isArray(data)) {
          data.forEach(renderEvent);
        }
      },
      error(xhr) {
        $('#spinner1').addClass('d-none');
        console.error('Error loading events', xhr);
        alert('Error loading events');
      }
    });
  }

  // --------------------------------------------------
  // UI bindings
  // --------------------------------------------------
  $('.time_period').on('change', loadEvents);

  $('#eventSearch').on('keyup', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEvents, 300);
  });

  // --------------------------------------------------
  // Initial load
  // --------------------------------------------------
  loadEvents();
});
