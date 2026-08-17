@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Home')

@section('page-style')
<style>
  .home-controls {
    align-items: center;
    background: #fff;
    border: 1px solid rgba(75,70,92,.12);
    border-radius: .7rem;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(20rem, 1fr) minmax(15rem, .9fr);
    margin-bottom: 1.5rem;
    padding: 1rem;
  }
  .home-periods { display: grid; gap: .45rem; grid-template-columns: repeat(3, 1fr); }
  .home-periods .period-option {
    align-items: center;
    background: #f2f2f5 !important;
    border: 1px solid transparent !important;
    border-radius: .5rem;
    color: #6f6b7d !important;
    display: flex;
    font-weight: 600;
    justify-content: center;
    min-height: 2.65rem;
    padding: .5rem .65rem;
  }
  .home-periods .btn-check:checked + .period-option {
    background: #7367f0 !important;
    box-shadow: 0 .2rem .55rem rgba(115,103,240,.25);
    color: #fff !important;
  }
  .event-search-wrap { position: relative; }
  .event-search-wrap .ti { color: #6f6b7d; left: .9rem; position: absolute; top: 50%; transform: translateY(-50%); }
  .event-search-wrap .form-control { min-height: 2.75rem; padding-left: 2.6rem; }
  .events-title-row { align-items: center; display: flex; justify-content: space-between; margin: 0 .2rem .75rem; }
  #eventResults { color: #8a8794; font-size: .82rem; }

  .event-card {
    border: 1px solid rgba(75,70,92,.1);
    border-radius: .65rem;
    box-shadow: 0 .25rem 1rem rgba(75,70,92,.07);
    overflow: hidden;
  }
  .event-card-header { background: #004177; padding: 1.15rem 1.35rem; }
  .event-name {
    border-radius: .35rem;
    color: #fff;
    display: inline-block;
    line-height: 1.35;
    margin: -.2rem -.35rem;
    overflow-wrap: anywhere;
    padding: .2rem .35rem;
    transition: background-color .2s ease;
  }
  .event-name:hover { background: rgba(255,255,255,.14); color: #fff; text-decoration: none; }
  .event-card-body { align-items: center; display: grid; gap: 1.5rem; grid-template-columns: 9rem minmax(0, 1fr); }
  .event-logo-wrap { align-items: center; display: flex; justify-content: center; min-height: 8rem; }
  .event-logo { height: 8rem; max-width: 9rem; object-fit: contain; width: 100%; }
  .event-logo-placeholder { color: #7367f0; font-size: 3rem; }
  .event-details { min-width: 0; }
  .event-dates { display: grid; gap: .7rem; grid-template-columns: 1fr 1fr; margin: 0; }
  .event-date { align-items: center; display: flex; flex-wrap: wrap; gap: .45rem; margin: 0; min-width: 0; }
  .event-date dt { color: #6f6b7d; font-size: .84rem; font-weight: 600; margin: 0; }
  .event-date dd { margin: 0; }
  .event-date-value { border-radius: .35rem; display: inline-block; font-size: .78rem; font-weight: 700; line-height: 1.3; padding: .35rem .65rem; }
  .start_date, .end_date { background: #dff7e9; color: #28c76f; }
  .deadline { background: #fff0e1; color: #ff9f43; }
  .deadline-date { grid-column: 1 / -1; }
  .event-card-footer { align-items: center; display: flex; justify-content: space-between; margin-top: 1rem; }
  .event-card .buttons { position: relative; }
  .event-card .buttons .btn { background: #dff7e9; border: 0; color: #28c76f; font-weight: 700; }
  .event-card .buttons .btn:hover { background: #28c76f; color: #fff; }

  .rankings-title { color: #5d596c; font-size: 1.8rem; margin-bottom: 1.1rem; }
  .rankings-list { border: 1px solid rgba(75,70,92,.14); border-radius: .45rem; overflow: hidden; }
  .ranking-link { border: 0; border-top: 1px solid rgba(75,70,92,.12); gap: 1rem; min-height: 5.1rem; }
  .ranking-link:first-child { border-top: 0; }
  .ranking-icon { align-items: center; background: #7367f0; border-radius: .35rem; color: #fff; display: flex; flex: 0 0 3.15rem; height: 3.15rem; justify-content: center; }
  .ranking-name { color: #5d596c; line-height: 1.35; overflow-wrap: anywhere; }
  .ranking-link:hover .ranking-name { color: #7367f0; }
  .home-state { background: #fff; border: 1px dashed rgba(75,70,92,.25); border-radius: .65rem; padding: 2.5rem 1rem; text-align: center; }

  @media (max-width: 991.98px) {
    .home-controls { grid-template-columns: 1fr; }
    .rankings-title { font-size: 1.45rem; margin-top: .5rem; }
  }
  @media (max-width: 767.98px) {
    body { overflow-x: hidden; }
    .content-backdrop { max-width: 100%; }
    .home-controls { min-width: 0; padding: .8rem; }
    .home-periods .period-option { font-size: .78rem; padding-inline: .25rem; }
    .event-card-header { padding: 1rem; }
    .event-card-header .h4 { font-size: 1.05rem; }
    .event-card-body { align-items: start; gap: 1rem; grid-template-columns: 5.25rem minmax(0, 1fr); }
    .event-logo-wrap { min-height: 5.25rem; }
    .event-logo { height: 5.25rem; max-width: 5.25rem; }
    .event-dates { gap: .6rem; grid-template-columns: 1fr; }
    .deadline-date { grid-column: auto; }
    .event-card-footer { align-items: stretch; flex-direction: column; gap: .7rem; }
    .event-card .buttons .btn { width: 100%; }
  }
  @media (max-width: 359.98px) {
    .home-periods { grid-template-columns: 1fr; }
    .event-card-body { grid-template-columns: 1fr; }
    .event-logo-wrap { justify-content: flex-start; }
  }
</style>
@endsection

@section('content')
<div class="row g-4 align-items-start">
  <main class="col-xl-8">
    <section class="home-controls" aria-label="Filter events">
      <fieldset class="m-0 time_period">
        <legend class="visually-hidden">Event period</legend>
        <div class="home-periods">
          <input class="btn-check" type="radio" name="period" id="periodUpcoming" value="upcoming" checked>
          <label class="btn period-option" for="periodUpcoming">Upcoming</label>
          <input class="btn-check" type="radio" name="period" id="periodPast" value="past">
          <label class="btn period-option" for="periodPast">Past</label>
          <input class="btn-check" type="radio" name="period" id="periodAll" value="all">
          <label class="btn period-option" for="periodAll">All events</label>
        </div>
      </fieldset>
      <div>
        <label class="visually-hidden" for="eventSearch">Search events</label>
        <div class="event-search-wrap">
          <i class="ti ti-search" aria-hidden="true"></i>
          <input type="search" id="eventSearch" class="form-control" maxlength="100" autocomplete="off" placeholder="Search events by name…">
        </div>
      </div>
    </section>

    <div class="events-title-row">
      <h1 class="h5 mb-0" id="eventsHeading">Upcoming events</h1>
      <span id="eventResults" aria-live="polite"></span>
    </div>

    <div id="eventList" aria-labelledby="eventsHeading" aria-busy="true"></div>
    <div class="home-state" id="eventLoading" role="status">
      <div class="spinner-border text-primary mb-2" aria-hidden="true"></div>
      <div>Loading events…</div>
    </div>
    <div class="home-state d-none" id="eventEmpty">
      <i class="ti ti-calendar-off ti-xl text-muted mb-2" aria-hidden="true"></i>
      <h2 class="h6 mb-1">No events found</h2>
      <p class="text-muted mb-0">Try another period or clear your search.</p>
    </div>
    <div class="home-state d-none" id="eventError" role="alert">
      <i class="ti ti-alert-circle ti-xl text-danger mb-2" aria-hidden="true"></i>
      <h2 class="h6 mb-1">Events could not be loaded</h2>
      <p class="text-muted mb-3">Please check your connection and try again.</p>
      <button class="btn btn-label-primary" id="retryEvents" type="button">Try again</button>
    </div>
    <div class="text-center mt-3 d-none" id="eventLoadMoreWrap">
      <button class="btn btn-outline-primary" id="eventLoadMore" type="button">Load more events <i class="ti ti-chevron-down ms-1" aria-hidden="true"></i></button>
    </div>
  </main>

  <aside class="col-xl-4" aria-labelledby="rankingsHeading">
    <h2 class="rankings-title fw-semibold" id="rankingsHeading">Series Rankings</h2>
    @if ($series->isNotEmpty())
      <div class="list-group rankings-list">
        @foreach ($series as $value)
          <a href="{{ route('frontend.ranking.show', $value->id) }}" class="ranking-link list-group-item list-group-item-action d-flex align-items-center p-3">
            <span class="ranking-icon"><i class="ti ti-clipboard-list ti-lg" aria-hidden="true"></i></span>
            <span class="ranking-name fw-semibold flex-grow-1">{{ $value->name }}</span>
          </a>
        @endforeach
      </div>
    @else
      <div class="card"><div class="card-body text-muted">No series rankings have been published yet.</div></div>
    @endif
  </aside>
</div>

@include('templates.homeEventTemplate')

<script>
  window.routes = window.routes || {};
  window.routes.homeGetEvents = "{{ route('home.events.get') }}";
  window.routes.eventShow     = "{{ url('/events') }}/";
  window.assetBase            = "{{ asset('') }}";
</script>
@endsection

@section('page-script')
  <script>
    (function () {
      'use strict';

      const prefix = '[HomeEvents]';
      const bundleUrl = @json(asset(mix('js/home.js')));

      console.info(`${prefix} Bootstrap diagnostics`, {
        pageUrl: window.location.href,
        documentReadyState: document.readyState,
        jqueryAvailable: typeof window.jQuery === 'function',
        routes: {
          homeGetEvents: window.routes?.homeGetEvents || null,
          eventShow: window.routes?.eventShow || null
        },
        assetBase: window.assetBase || null,
        bundleUrl
      });

      window.addEventListener('error', function (event) {
        if (event.target && event.target !== window) {
          console.error(`${prefix} Resource failed to load`, {
            tagName: event.target.tagName || null,
            source: event.target.src || event.target.href || null
          });
          return;
        }

        console.error(`${prefix} Uncaught JavaScript error`, {
          message: event.message || null,
          source: event.filename || null,
          line: event.lineno || null,
          column: event.colno || null
        });
      }, true);

      window.addEventListener('unhandledrejection', function (event) {
        console.error(`${prefix} Unhandled promise rejection`, {
          reason: event.reason instanceof Error ? event.reason.message : String(event.reason)
        });
      });
    })();
  </script>
  <script
    src="{{ asset(mix('js/home.js')) }}"
    onload="console.info('[HomeEvents] Bundle asset loaded')"
    onerror="console.error('[HomeEvents] Bundle asset failed to load', { source: this.src })"></script>
@endsection
