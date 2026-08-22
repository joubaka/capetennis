import $ from 'jquery';

const debugPrefix = '[HomeEvents]';

function debug(message, context = {}) {
  console.info(`${debugPrefix} ${message}`, context);
}

function debugError(message, context = {}) {
  console.error(`${debugPrefix} ${message}`, context);
}

debug('Bundle evaluated', {
  documentReadyState: document.readyState,
  jqueryAvailable: typeof $ === 'function',
  pageUrl: window.location.href
});

window.homeEventsRuntime = {
  version: '2026-08-17.1',
  initialized: false
};

$(function () {
  const getEvents = window.routes?.homeGetEvents;
  const showEvent = window.routes?.eventShow;
  const assetBase = window.assetBase || `${window.location.origin}/`;

  debug('DOM ready', {
    getEvents,
    showEvent,
    assetBase,
    selectedPeriod: $('.time_period input:checked').val() || null,
    eventListPresent: $('#eventList').length === 1,
    templatePresent: $('#eventInfo').children().length > 0
  });
  window.homeEventsRuntime.initialized = true;

  if (!getEvents || !showEvent) {
    debugError('Required home routes are not defined', {
      routesPresent: Boolean(window.routes),
      getEventsPresent: Boolean(getEvents),
      showEventPresent: Boolean(showEvent)
    });
    return;
  }

  const $list = $('#eventList');
  const $loading = $('#eventLoading');
  const $empty = $('#eventEmpty');
  const $error = $('#eventError');
  const $results = $('#eventResults');
  const $loadMoreWrap = $('#eventLoadMoreWrap');
  const $loadMore = $('#eventLoadMore');
  const periodLabels = { upcoming: 'Upcoming events', past: 'Past events', all: 'All events' };
  const dateOptions = { day: 'numeric', month: 'short', year: 'numeric' };

  let searchTimer = null;
  let activeRequest = null;
  let currentPage = 1;
  let lastPage = 1;
  let renderedTotal = 0;
  let requestSequence = 0;

  function parseLocalDate(value) {
    if (!value) return null;
    const parts = String(value).slice(0, 10).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function formatDate(date) {
    return date ? date.toLocaleDateString('en-ZA', dateOptions) : 'Not set';
  }

  function eventUrl(id) {
    return `${showEvent}${encodeURIComponent(id)}`;
  }

  function renderLogo($card, logo, eventName) {
    if (!logo) return;

    const safeFilename = String(logo).replace(/\\/g, '/').split('/').pop();
    if (!safeFilename) return;

    $card.find('.logo').empty().append($('<img>', {
      alt: `${eventName} logo`,
      class: 'event-logo',
      loading: 'lazy',
      src: `${assetBase}assets/img/logos/${encodeURIComponent(safeFilename)}`
    }));
  }

  function renderEvent(event) {
    if (!event?.id || !event?.name || !event?.start_date) {
      console.warn(`${debugPrefix} Event skipped because required fields are missing`, {
        eventId: event?.id || null,
        hasName: Boolean(event?.name),
        hasStartDate: Boolean(event?.start_date)
      });
      return false;
    }

    const startDate = parseLocalDate(event.start_date);
    const endDate = parseLocalDate(event.end_date);
    if (!startDate) {
      console.warn(`${debugPrefix} Event skipped because start_date is invalid`, {
        eventId: event.id,
        startDate: event.start_date
      });
      return false;
    }

    let deadlineDate = null;
    if (event.deadline !== null && event.deadline !== '' && !Number.isNaN(Number(event.deadline))) {
      deadlineDate = new Date(startDate);
      deadlineDate.setDate(startDate.getDate() - Number(event.deadline));
    }

    const $card = $('#eventInfo').children().first().clone();
    const url = eventUrl(event.id);

    $card.find('.eventName').text(event.name).attr('href', url);
    $card.find('.start_date').text(formatDate(startDate));
    $card.find('.end_date').text(formatDate(endDate));
    $card.find('.deadline').text(formatDate(deadlineDate));
    $card.find('.buttons').append($('<a>', {
      class: 'btn btn-label-primary',
      href: url,
      text: 'View event'
    }).append(' ').append($('<i>', { class: 'ti ti-arrow-right', 'aria-hidden': 'true' })));

    renderLogo($card, event.logo, event.name);
    $list.append($card);
    return true;
  }

  function setLoading(isLoading) {
    $list.attr('aria-busy', String(isLoading));
    $loading.toggleClass('d-none', !isLoading);
    if (isLoading) {
      $empty.addClass('d-none');
      $error.addClass('d-none');
      $results.text('');
    }
  }

  function loadEvents(page = 1) {
    const period = $('.time_period input:checked').val() || 'upcoming';
    const search = $('#eventSearch').val().trim();
    const appending = page > 1;
    const requestId = ++requestSequence;

    debug('Request starting', {
      requestId,
      endpoint: getEvents,
      period,
      page,
      searchLength: search.length,
      appending
    });

    if (activeRequest) {
      debug('Aborting previous request', { requestId });
      activeRequest.abort();
    }
    if (!appending) {
      $list.empty();
      renderedTotal = 0;
      $('#eventsHeading').text(periodLabels[period]);
      $loadMoreWrap.addClass('d-none');
      setLoading(true);
    } else {
      $loadMore.prop('disabled', true).text('Loading…');
    }

    activeRequest = $.ajax({ url: getEvents, data: { period, search, page } })
      .done(function (response, _status, xhr) {
        const events = Array.isArray(response?.data) ? response.data : [];
        const meta = response?.meta || {};

        debug('Request succeeded', {
          requestId,
          serverRequestId: xhr.getResponseHeader?.('X-Home-Events-Request-Id') || null,
          responseType: Array.isArray(response) ? 'array' : typeof response,
          hasDataArray: Array.isArray(response?.data),
          eventCount: events.length,
          meta
        });

        if (!Array.isArray(response?.data)) {
          console.warn(`${debugPrefix} Response did not contain a data array`, {
            requestId,
            responseKeys: response && typeof response === 'object' ? Object.keys(response) : []
          });
        }

        const renderedCount = events.reduce(
          (count, event) => count + (renderEvent(event) ? 1 : 0),
          0
        );

        renderedTotal += renderedCount;
        currentPage = Number(meta.current_page) || page;
        lastPage = Number(meta.last_page) || currentPage;
        const total = Number(meta.total) || renderedTotal;
        const noun = total === 1 ? 'event' : 'events';

        $results.text(renderedTotal < total ? `${renderedTotal} of ${total} ${noun}` : `${total} ${noun}`);
        $empty.toggleClass('d-none', total !== 0);
        $loadMoreWrap.toggleClass('d-none', currentPage >= lastPage);

        debug('Rendering completed', {
          requestId,
          renderedCount,
          renderedTotal,
          total,
          currentPage,
          lastPage
        });
      })
      .fail(function (xhr, status, errorThrown) {
        if (status === 'abort') {
          debug('Request aborted', { requestId });
          return;
        }
        $error.removeClass('d-none');
        $results.text('Unavailable');
        debugError('Request failed', {
          requestId,
          serverRequestId: xhr.getResponseHeader?.('X-Home-Events-Request-Id') || null,
          httpStatus: xhr.status,
          statusText: xhr.statusText,
          ajaxStatus: status,
          errorThrown,
          responseType: xhr.getResponseHeader?.('content-type') || null,
          responsePreview: typeof xhr.responseText === 'string'
            ? xhr.responseText.slice(0, 300)
            : null
        });
      })
      .always(function (_response, status) {
        debug('Request finished', { requestId, status });
        if (status !== 'abort') {
          setLoading(false);
          $loadMore.prop('disabled', false).html(
            'Load more events <i class="ti ti-chevron-down ms-1" aria-hidden="true"></i>'
          );
        }
        activeRequest = null;
      });
  }

  $('.time_period').on('change', function () {
    debug('Period changed', {
      period: $('.time_period input:checked').val() || null
    });
    $('.home-periods label').removeClass('btn-primary').addClass('btn-label-secondary');
    $(`label[for="${$('.time_period input:checked').attr('id')}"]`)
      .removeClass('btn-label-secondary')
      .addClass('btn-primary');
    loadEvents();
  });

  $('#eventSearch').on('input', function () {
    clearTimeout(searchTimer);
    debug('Search scheduled', { searchLength: $(this).val().trim().length });
    searchTimer = setTimeout(loadEvents, 300);
  });
  $('#retryEvents').on('click', function () {
    debug('Retry selected');
    loadEvents();
  });
  $loadMore.on('click', function () {
    debug('Load more selected', { currentPage, lastPage });
    if (currentPage < lastPage) loadEvents(currentPage + 1);
  });

  debug('Initial request scheduled');
  loadEvents();
});
