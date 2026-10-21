@extends('layouts.backend')

@section('title', 'Admin - Event Page')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
<style>
  .event-draw-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
  .event-draw-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; }
  .event-draw-toolbar .btn { min-height: 44px; }
  .event-publication-panel > summary { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem 1rem; padding: 1.25rem; cursor: pointer; }
  .event-publication-panel > summary::after { content: '+'; font-size: 1.25rem; }
  .event-publication-panel[open] > summary::after { content: '−'; }
  .event-publication-panel > summary:focus-visible { outline: 2px solid var(--ct-ink, #172e45); outline-offset: 2px; }
  .event-publication-panel > .card-body { padding-top: 0; }
  .event-publication-panel > summary::-webkit-details-marker { display: none; }
  .event-draw-tools { margin-top: 1rem; padding-top: .75rem; border-top: 1px solid var(--ct-border, #e4eaf0); }
  .event-draw-tools > summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; }
  .event-draw-schedule { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--ct-border, #e4eaf0); }
  .event-draw-schedule > summary, .event-draw-tools > summary { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .25rem .75rem; min-height: 44px; cursor: pointer; list-style: none; }
  .event-draw-schedule > summary::after, .event-draw-tools > summary::after { content: '+'; margin-left: auto; }
  .event-draw-schedule[open] > summary::after, .event-draw-tools[open] > summary::after { content: '−'; }
  .event-draw-schedule > summary::-webkit-details-marker, .event-draw-tools > summary::-webkit-details-marker { display: none; }
  .event-draw-next-step { margin-top: .625rem; color: var(--ct-ink, #172e45); }
  .event-draw-card[data-scoring-undecided] .event-draw-quick-publication:not(.btn-outline-danger) { background: transparent; color: var(--ct-ink, #172e45); border-color: var(--ct-border, #e4eaf0); box-shadow: none; }
  .event-draw-schedule .draw-card-publication { margin-top: .5rem !important; padding-block: 0; border: 0; }
  .event-draw-scoring-actions:has([data-enable-scoring]:not([hidden])) { margin-top: .75rem; }
  .ct-backend .event-draw-tabs { display: flex; flex-direction: row; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--ct-border, #e4eaf0); }
  .ct-backend .event-draw-tabs .nav-link { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; flex: 0 0 auto; width: auto; min-height: 44px; padding: .625rem .875rem; border: 1px solid var(--ct-border, #e4eaf0); white-space: normal; text-align: left; }
  .ct-backend .nav-pills.event-draw-tabs .nav-link.active { background: var(--ct-ink, #172e45); color: #fff; border-color: var(--ct-ink, #172e45); }
  .ct-backend .event-draw-tabs .nav-link.active .badge { color: #fff !important; background: #ffffff26 !important; }
  .event-draw-layout > div { min-width: 0; }
  .event-draw-heading { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: 1rem; }
  .event-draw-card { min-width: 0; padding: 1rem; border: 1px solid var(--bs-border-color, #dbdade); border-radius: .75rem; }
  .event-draw-card .list-group-item { padding: 0; border: 0; background: transparent; }
  .event-draw-card .user-info { width: 100%; min-width: 0; }
  .event-draw-card h6 { overflow-wrap: anywhere; }
  .event-draw-card-summary { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-height: 44px; cursor: pointer; list-style: none; }
  .event-draw-card-summary::-webkit-details-marker { display: none; }
  .event-draw-card-summary-info { min-width: 0; }
  .event-draw-card-summary-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: .5rem; }
  .event-draw-card-summary-status .badge { white-space: normal; text-align: left; }
  .event-draw-card-summary-status a.event-oop-summary { display: inline-flex; align-items: center; min-height: 44px; text-decoration: underline; text-underline-offset: .2em; }
  .event-draw-card-summary-status a.event-oop-summary:focus-visible { outline: 2px solid var(--ct-ink, #172e45); outline-offset: 3px; }
  .event-draw-card-summary:focus-visible { outline: 2px solid var(--ct-ink, #172e45); outline-offset: 4px; border-radius: .25rem; }
  .event-draw-card-toggle { display: inline-flex; align-items: center; gap: .25rem; flex-shrink: 0; }
  .event-draw-card-summary-actions { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
  .event-draw-card-summary-actions .btn { min-height: 44px; white-space: normal; }
  .event-draw-quick-publication { min-height: 44px; white-space: normal; }
  .event-draw-card[open] .event-draw-card-expand, .event-draw-card:not([open]) .event-draw-card-collapse { display: none; }
  .event-draw-card[open] .event-draw-card-toggle i { transform: rotate(180deg); }
  .event-draw-card-content { padding-top: .75rem; }
  .event-draw-card-content [data-scoring-status] { display: none; }
  .event-draw-card [data-quick-draw-status-retry][hidden] { display: none !important; }
  .event-draw-card .draw-venues .badge { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
  .event-draw-card .draw-card-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; width: 100%; margin-top: .75rem; }
  .event-draw-card .draw-card-actions .btn { min-height: 44px; margin: 0; white-space: normal; }
  .event-draw-card .draw-card-publication { padding-block: .75rem; border-block: 1px solid var(--bs-border-color, #dbdade); }
  .event-draw-card .dropdown-item { min-height: 44px; white-space: normal; }
  .event-draw-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; margin-top: .75rem; }
  .event-draw-links { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
  .event-draw-links .btn { min-height: 44px; white-space: normal; }
  @media (max-width: 575.98px) {
    .event-draw-toolbar { width: 100%; }
    .event-draw-toolbar .btn { flex: 1 1 auto; }
    .event-draw-toolbar .btn-primary { flex-basis: 100%; }
    .event-draw-header { flex-direction: column; align-items: flex-start !important; gap: .25rem; }
    .event-draw-body { padding-inline: 1rem; }
    .event-draw-card { padding: .875rem; }
    .event-draw-card-summary { flex-direction: column; align-items: stretch; }
    .event-draw-card-summary-actions { width: 100%; }
    .event-draw-card-summary-actions > .btn { flex: 1 1 calc(50% - .5rem); }
    .event-draw-card-toggle { width: 100%; justify-content: flex-end; min-height: 44px; }
    .event-draw-card .draw-card-actions > .btn { flex: 1 1 calc(50% - .5rem); padding-inline: .5rem; }
    .event-draw-card .draw-card-primary > .btn:first-child { flex-basis: 100%; }
    .event-draw-links .btn { flex: 1 1 0; }
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')
<script>
  window.HeadOffice = {
    venues: @json($allVenues),
    previewUrl: "{{ route('headoffice.previewTeamDraw', $event) }}",
    competitionSetupUrl: @json(route('team-draw.competition.setup', $event)),
    createUrl: "{{ route('headoffice.createSingleDraw.team', $event) }}",
    individualCreateUrl: "{{ route('headoffice.createSingleDraw', $event) }}",
    individualDrawTypeId: @json(($individualDrawTypes->firstWhere('drawTypeName', 'Singles') ?? $individualDrawTypes->first())?->id),
    backendDrawVenuesStoreTemplate: @json(route('backend.draw.venues.store', ['draw' => '__ID__'])),
    backendDrawVenuesJsonTemplate: @json(route('backend.draw.venues.json', ['draw' => '__ID__'])),
    // v2 endpoints
    teamDrawV2Enabled: @json($teamDrawV2Enabled ?? false),
    formatsUrl: @json(route('team-draw.formats.index', $event)),
    generateTiesUrlTemplate: @json(route('team-draw.generate-ties', ['draw' => '__DRAW_ID__'])),
    generateRubbersUrlTemplate: @json(route('team-draw.generate-rubbers', ['draw' => '__DRAW_ID__'])),
    attachFormatUrlTemplate: @json(route('team-draw.attach-format', ['draw' => '__DRAW_ID__'])),
  };

  $(function () {
    @if(session('success')) toastr.success(@json(session('success')), 'Success'); @endif
    @if(session('error')) toastr.error(@json(session('error')), 'Error'); @endif
    @if(session('warning')) toastr.warning(@json(session('warning')), 'Warning'); @endif
    @if(session('info')) toastr.info(@json(session('info')), 'Info'); @endif
  });
</script>

<script src="{{ asset(mix('js/headOffice.js')) }}"></script>
<script src="{{ asset('js/team-draw-mode.js') }}?v={{ filemtime(public_path('js/team-draw-mode.js')) }}"></script>
<script src="{{ asset('js/head-office-draw-publication.js') }}?v={{ filemtime(public_path('js/head-office-draw-publication.js')) }}"></script>
@endsection


@section('content')
@include('backend.draw.partials.guide-modal')

@include('backend.event.partials.header', [
  'eventWorkspaceActive' => 'draws',
  'eventWorkspaceIcon' => 'ti-tournament',
  'eventWorkspaceSubtitle' => 'Team and individual draws, fixtures and venues',
  'eventWorkspaceShowHome' => false,
])
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
  <div><h2 class="h4 mb-1">Tournament draws</h2><p class="text-muted mb-0">Create team ties or individual singles draws, allocate venues and manage fixtures.</p></div>
  <div class="event-draw-toolbar">
  <button class="btn btn-primary" id="createNewDrawBtn" data-bs-toggle="modal" data-bs-target="#createDrawModal">
      <i class="ti ti-plus me-1"></i> Create new draw
  </button>
  @can('team-draw.createFormat', $event)
    <a class="btn btn-outline-secondary" href="{{ route('backend.team-rules.edit', $event) }}">Event scoring rules</a>
  @endcan
  <a class="btn btn-outline-secondary" href="{{ route('headoffice.printOptions', $event) }}"><i class="ti ti-printer me-1" aria-hidden="true"></i> Print options</a>
  </div>
</div>

        @php
          $drawGrouping = request('draw_grouping') === 'gender' ? 'gender' : 'age';
          $drawGroups = app(\App\Services\Scheduling\AgeGroupVenueDefaultService::class)->groups($event);
          if ($drawGrouping === 'age') {
            $combinedGroups = collect();
            foreach ($drawGroups as $label => $draws) {
              $ageLabel = preg_replace('/ (Boys|Girls)$/', '', $label);
              $combinedGroups->put($ageLabel, $combinedGroups->get($ageLabel, collect())->concat($draws));
            }
            $drawGroups = $combinedGroups;
          }
          $drawGroups = $drawGroups->map(fn ($draws) => $draws->sortBy(function ($draw) {
            $typeName = mb_strtolower($draw->draw_types?->drawTypeName ?? '');
            $typeOrder = match (true) {
              str_contains($typeName, 'single') && ! str_contains($typeName, 'reverse') => 0,
              str_contains($typeName, 'single') && str_contains($typeName, 'reverse') => 1,
              str_contains($typeName, 'double') && ! str_contains($typeName, 'mixed') && ! str_contains($typeName, 'reverse') => 2,
              default => 3,
            };
            return [$typeOrder, mb_strtolower($draw->drawName), $draw->id];
          })->values());
        @endphp
<div class="row event-draw-layout">

  <div class="col-12">
    <div class="card mb-4">
      <div class="card-header event-draw-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Manage Draws</h5>
        <small class="text-muted">{{ $event->draws->count() }} {{ \Illuminate\Support\Str::plural('draw', $event->draws->count()) }} · {{ $scheduledVenues->count() }} active {{ \Illuminate\Support\Str::plural('venue', $scheduledVenues->count()) }}</small>
      </div>

      <div class="card-body event-draw-body pt-0">

        @if($drawGroups->isNotEmpty())
        <form method="GET" action="{{ url()->current() }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <label for="draw-grouping" class="form-label mb-0">Group draws by</label>
          <select id="draw-grouping" name="draw_grouping" class="form-select w-auto" onchange="this.form.submit()">
            <option value="age" @selected($drawGrouping === 'age')>Age group (both genders)</option>
            <option value="gender" @selected($drawGrouping === 'gender')>Age group and gender</option>
          </select>
          <noscript><button type="submit" class="btn btn-outline-primary">Apply</button></noscript>
        </form>
        <nav class="nav nav-pills event-draw-tabs" aria-label="Draw age groups">
          @foreach($drawGroups as $groupLabel => $groupDraws)
            <div class="nav-item">
            <a class="nav-link" id="event-draw-tab-{{ $loop->index }}"
               href="#event-draw-panel-{{ $loop->index }}">
              {{ $groupLabel }} <span class="badge bg-label-secondary ms-1">{{ $groupDraws->count() }}</span>
            </a>
            </div>
          @endforeach
        </nav>
        @endif
        <div class="event-draw-groups p-0">
          @forelse($drawGroups as $groupLabel => $groupDraws)
            <div class="event-draw-group mb-4" id="event-draw-panel-{{ $loop->index }}"
                 role="region" aria-labelledby="event-draw-tab-{{ $loop->index }}" tabindex="0" style="scroll-margin-top: 10rem">
            <div class="event-draw-heading"><h6 class="mb-0">{{ $groupLabel }}</h6><span class="text-muted small">{{ $groupDraws->count() }} {{ \Illuminate\Support\Str::plural('draw', $groupDraws->count()) }}</span></div>
            <div class="event-draw-list">
            @foreach($groupDraws as $draw)
            <details id="publication-draw-{{ $draw->id }}" class="event-draw-card event-draw-publication-card" data-quick-publication-card data-draw-id="{{ $draw->id }}" @if($draw->isTeamDraw() && ($draw->team_scoring_rules['undecided'] ?? false)) data-scoring-undecided @endif>
              <summary class="event-draw-card-summary">
                <div class="event-draw-card-summary-info">
                <h6 class="mb-0">{{ $draw->drawName }} <span class="text-muted">— {{ $draw->formatDisplayLabel() }}</span></h6>
                  <div class="event-draw-card-summary-status" aria-live="polite">
                    <span class="event-draw-status badge bg-label-{{ $draw->published ? 'success' : 'warning' }}">{{ $draw->published ? 'Draw published' : 'Draw hidden' }}</span>
                    @include('backend.draw.partials.scoring-readiness', ['statusOnly' => true])
                    @php
                      $hasOrderOfPlay = $draw->scheduled_team_fixture_count > 0 || $draw->order_of_play_count > 0;
                    @endphp
                    <a href="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'draw_id' => $draw->id, 'date' => 'all']) }}" aria-label="View order of play for {{ $draw->drawName }}" class="event-oop-summary badge bg-label-{{ $draw->oop_published ? 'success' : ($hasOrderOfPlay ? 'info' : 'secondary') }}" data-created="{{ $hasOrderOfPlay ? 1 : 0 }}">Order of play: {{ $draw->oop_published ? ($draw->published ? 'Published' : 'Preview only') : ($hasOrderOfPlay ? 'Created' : 'Not done') }}</a>
                    @if($draw->locked)<span class="badge bg-label-secondary">Locked</span>@endif
                    @if($draw->is_done)<span class="badge bg-label-success">Completed</span>@endif
                  </div>
                  <div class="event-draw-card-summary-status event-draw-venue-summary">
                    @forelse($draw->venues as $venue)
                      <span class="badge bg-label-primary">{{ $venue->name }} ({{ $venue->pivot->num_courts }})</span>
                    @empty
                      <span class="badge bg-label-secondary">No venues assigned</span>
                    @endforelse
                  </div>
                  @if($draw->isTeamDraw() && ($draw->team_scoring_rules['undecided'] ?? false))
                    <p class="event-draw-next-step small mb-0"><strong>Next: confirm scoring.</strong> Review the phase scoring rules before publishing.</p>
                  @endif
                  <div class="small mt-2 d-none" data-quick-draw-feedback role="status" aria-live="polite"></div>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-quick-draw-status-retry hidden>Retry publication status check</button>
                </div>
                <div class="event-draw-card-summary-actions">
                  @can('event.manage', $event)
                  @can('publish', $draw)
                    <button type="button" class="btn btn-sm {{ $draw->published ? 'btn-outline-danger' : 'btn-success' }} event-draw-quick-publication" data-quick-draw-publication data-published="{{ $draw->published ? 'true' : 'false' }}" aria-pressed="{{ $draw->published ? 'true' : 'false' }}" aria-label="{{ $draw->published ? 'Unpublish' : 'Publish' }} {{ $draw->drawName }}" @disabled($draw->published && $draw->locked) @if($draw->published && $draw->locked) title="Locked draws cannot be unpublished." @endif>{{ $draw->published ? 'Unpublish draw' : 'Publish draw' }}</button>
                  @endcan
                  @endcan
                  @can('publish', $draw)
                    <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish-schedule" data-url="{{ route('draw.toggle.publish.schedule', $draw->id) }}" data-status="{{ $draw->oop_published ? 1 : 0 }}"><i class="ti ti-{{ $draw->oop_published ? 'eye-off' : 'eye' }} me-1"></i>{{ $draw->oop_published ? 'Hide schedule' : 'Publish schedule' }}</button>
                  @endcan
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]) }}"><i class="ti ti-calendar me-1"></i>Schedule</a>
                  @can('event.manage', $event)
                    <a class="btn btn-sm btn-outline-secondary" href="#draw-day-publication-{{ $draw->id }}" onclick="document.getElementById('publication-draw-{{ $draw->id }}').open = true; document.getElementById('draw-day-publication-{{ $draw->id }}').open = true">Publish by day</a>
                  @endcan
                  <span class="event-draw-card-toggle small text-primary"><span class="event-draw-card-expand">Open workspace</span><span class="event-draw-card-collapse">Close workspace</span><i class="ti ti-chevron-down" aria-hidden="true"></i></span>
                </div>
              </summary>
              <div class="event-draw-card-content">
                @include('backend.draw._includes.draw_tab_team', ['hideDrawHeading' => true, 'compactOverview' => true])
                @can('event.manage', $event)
                  <details id="draw-day-publication-{{ $draw->id }}" class="event-draw-tools" data-draw-day-publication data-draw-id="{{ $draw->id }}" data-initial-unconfirmed="true" data-event-id="{{ $event->id }}" data-status-url="{{ route('backend.event-venue-schedule.calendar.publication-status', ['event' => $event->id, 'draw_id' => $draw->id]) }}" data-publish-url="{{ route('backend.event-venue-schedule.calendar.publish', $event) }}" data-hide-url="{{ route('backend.event-venue-schedule.calendar.hide', $event) }}" data-calendar-url="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'draw_id' => $draw->id]) }}">
                    <summary><span class="fw-semibold">Publish schedule by day</span><span class="small text-muted">This draw only</span></summary>
                    <p class="small text-muted mt-2">Choose a saved day to publish or hide this draw's match times across all venues. Draw publication is separate.</p>
                    <div class="small mb-3" data-day-feedback role="status" aria-live="polite">Open to check saved and published days.</div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" data-day-status-retry hidden>Retry day status check</button>
                    <div class="row g-3" data-day-cards></div>
                    <template data-day-card-template>
                      @include('backend.headOffice.partials.day-publication-card', ['day' => '', 'dayDrawId' => $draw->id, 'counts' => ['saved' => 0, 'published' => 0, 'matched' => 0, 'pending' => 0, 'status' => 'Not scheduled']])
                    </template>
                  </details>
                @endcan
              </div>
            </details>
            @endforeach
            </div>
            </div>
          @empty
            <div class="text-center py-5">
              <i class="ti ti-folders ti-lg text-muted mb-2"></i>
              <p class="text-muted">No draws created for this event yet.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <details class="card mb-4 event-publication-panel">
      <summary><span class="fw-semibold">Venue Fixture Lists</span><span class="small text-muted">{{ $scheduledVenues->count() }} active {{ \Illuminate\Support\Str::plural('venue', $scheduledVenues->count()) }}</span></summary>
      <div class="card-body">
        <div class="list-group">
          @forelse($scheduledVenues as $venue)
            <a href="{{ route('headoffice.venue.fixtures', [$event->id, $venue->id]) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 mb-2 border rounded">
              <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-3">
                  <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-building-community"></i></span>
                </div>
                <div>
                  <div class="fw-bold text-heading">{{ $venue->name }}</div>
                  <small class="text-muted">{{ $venue->location ?? 'Main Complex' }}</small>
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-label-info rounded-pill">
                  @php
                    $total = $venue->scheduled_fixtures_count ?? 0;
                    $finished = $venue->finished_fixtures_count ?? 0;
                  @endphp
                  {{ $finished }}/{{ $total }} finished
                </span>
                <div class="mt-1"><i class="ti ti-chevron-right text-muted ti-xs"></i></div>
              </div>
            </a>
          @empty
            <div class="alert alert-outline-secondary d-flex align-items-center" role="alert">
              <span class="alert-icon text-secondary me-2">
                <i class="ti ti-info-circle ti-xs"></i>
              </span>
              No venues have been assigned fixtures yet.
            </div>
          @endforelse
        </div>
      </div>
    </details>
  </div>

</div>

<details class="card mb-4 no-print event-publication-panel" data-event-draw-publication data-event-id="{{ $event->id }}" data-status-url="{{ route('backend.event-draws.publication-status', $event) }}" data-url="{{ route('backend.event-draws.bulk-publication', $event) }}" data-draw-ids="{{ json_encode($event->draws->pluck('id')->values()->all()) }}">
  <summary><span class="fw-semibold">Publish specific draws or batches</span><span class="small text-muted" data-draw-publication-summary><strong>{{ $drawPublicationSummary['status'] }}</strong> · {{ $drawPublicationSummary['published'] }} published · {{ $drawPublicationSummary['unpublished'] }} unpublished</span></summary>
  <div class="card-body">
    <p class="text-muted">{{ $event->draws->count() }} draws across every age-group tab. Draw publication and match-time publication are separate actions.</p>
    <div class="mb-3" data-publication-groups>
      @forelse($drawGroups as $groupLabel => $groupDraws)
        <details class="border rounded p-3 mb-2" data-publication-group>
          <summary class="d-flex flex-wrap gap-2 align-items-center" style="min-height:44px">
            <strong>{{ $groupLabel }}</strong>
            <span data-publication-group-counts>{{ $groupDraws->where('published', true)->count() }} published · {{ $groupDraws->where('published', false)->count() }} unpublished · {{ $groupDraws->count() }} total</span>
          </summary>
          @if($canPublishAllDraws ?? false)
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-select-publication-group>Select this age group</button>
          @endif
          <ul class="list-unstyled mb-0 mt-3">
            @foreach($groupDraws as $draw)
              <li class="d-flex flex-wrap align-items-center gap-2 py-2 border-top" data-publication-draw-row data-draw-id="{{ $draw->id }}">
                @if($canPublishAllDraws ?? false)
                  <input type="checkbox" class="form-check-input" data-publication-draw-select value="{{ $draw->id }}" aria-label="Select {{ $draw->drawName }}" style="min-width:24px;min-height:24px">
                @endif
                <a href="#publication-draw-{{ $draw->id }}" data-publication-draw-link data-panel-id="event-draw-panel-{{ $loop->parent->index }}">{{ $draw->drawName }}</a>
                <span class="text-muted small">{{ $draw->draw_types?->drawTypeName }}</span>
                <span class="badge bg-label-{{ $draw->published ? 'success' : 'warning' }}" data-publication-draw-state>{{ $draw->published ? 'Published' : 'Unpublished' }}</span>
                <span class="badge bg-label-secondary" data-publication-draw-lock @if(!$draw->locked) hidden @endif>Locked</span>
              </li>
            @endforeach
          </ul>
        </details>
      @empty
        <p class="text-muted mb-0">No draws to review.</p>
      @endforelse
    </div>
    @if($canPublishAllDraws ?? false)
    <p data-publication-selected-count role="status" aria-live="polite">0 draws selected</p>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button type="button" class="btn btn-outline-primary" data-select-publication-all>Select all draws</button>
      <button type="button" class="btn btn-outline-secondary" data-clear-publication-selection>Clear selection</button>
      <button type="button" class="btn btn-success" data-bulk-draw-action="publish" data-bulk-draw-scope="selected">Publish selected draws</button>
      <button type="button" class="btn btn-outline-danger" data-bulk-draw-action="unpublish" data-bulk-draw-scope="selected">Unpublish selected draws</button>
    </div>
    <details class="mb-3"><summary>Whole-event options</summary>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="button" class="btn btn-success" data-bulk-draw-action="publish">Publish all {{ $event->draws->count() }} draws</button>
      <button type="button" class="btn btn-outline-danger" data-bulk-draw-action="unpublish">Unpublish all {{ $event->draws->count() }} draws</button>
    </div>
    </details>
    <div class="mt-3 d-none" role="status" aria-live="polite" data-bulk-draw-feedback></div>
    <a class="btn btn-sm btn-outline-primary mt-2 d-none" href="{{ route('headOffice.show', $event) }}" data-bulk-draw-refresh>Refresh draw statuses</a>
    @endif
  </div>
</details>

@can('event.manage', $event)
<details class="card mb-4 no-print event-publication-panel" @if($schedulePublicationUnconfirmed ?? false) open @endif data-whole-day-publication data-initial-unconfirmed="{{ ($schedulePublicationUnconfirmed ?? false) ? 'true' : 'false' }}" data-event-id="{{ $event->id }}" data-status-url="{{ route('backend.event-venue-schedule.calendar.publication-status', $event) }}" data-publish-url="{{ route('backend.event-venue-schedule.calendar.publish', $event) }}" data-hide-url="{{ route('backend.event-venue-schedule.calendar.hide', $event) }}" data-calendar-url="{{ route('backend.event-venue-schedule.calendar', $event) }}">
  <summary><span class="fw-semibold">Whole-day schedule publication</span><span class="small text-muted">Review and publish match times</span></summary>
  <div class="card-body">
    <p class="text-muted">Review, publish or hide one whole day's match times across all venues and draws. Publishing times does not publish hidden draws; existing public visibility rules still apply.</p>
    <div class="alert {{ ($schedulePublicationUnconfirmed ?? false) ? 'alert-warning' : 'd-none' }}" data-day-feedback role="status" aria-live="polite">@if($schedulePublicationUnconfirmed ?? false)Publication status unconfirmed. Actions are paused; retry the status check before changing a day.@endif</div>
    <button type="button" class="btn btn-outline-primary mb-3" data-day-status-retry @if(!($schedulePublicationUnconfirmed ?? false)) hidden @endif>Retry status check</button>
    <div class="row g-3" data-day-cards>
      @forelse($wholeDaySchedule ?? collect() as $day => $counts)
        @include('backend.headOffice.partials.day-publication-card')
      @empty
        @unless($schedulePublicationUnconfirmed ?? false)<p class="text-muted mb-0"><strong>Not scheduled</strong> · No saved or published match times yet. Save a schedule before publishing a day.</p>@endunless
      @endforelse
    </div>
    <template data-day-card-template>
      @include('backend.headOffice.partials.day-publication-card', ['day' => '', 'counts' => ['saved' => 0, 'published' => 0, 'matched' => 0, 'pending' => 0, 'status' => 'Not scheduled']])
    </template>
  </div>
</details>
@endcan


<!-- Modal: Create New Draw (Team Event) -->
@include('backend.draw.partials.guide-visibility')
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="createDrawForm">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <p class="small text-primary" data-create-guide-status>Step 1 of 6 · Competition</p>
          <fieldset class="mb-3" data-create-competition>
            <legend class="form-label fw-bold mb-2">Competition</legend>
            <div class="row g-2">
              <div class="col-sm-6">
                <label class="form-check border rounded p-3 m-0 h-100" for="drawModeIndividual">
                  <input class="form-check-input" type="radio" name="draw_mode" id="drawModeIndividual" value="individual">
                  <span class="form-check-label ms-1">
                    <span class="fw-semibold d-block">Individual singles</span>
                    <span class="text-muted small">One player competes directly against another.</span>
                  </span>
                </label>
              </div>
              <div class="col-sm-6">
                <label class="form-check border rounded p-3 m-0 h-100" for="drawModeTeam">
                  <input class="form-check-input" type="radio" name="draw_mode" id="drawModeTeam" value="team">
                  <span class="form-check-label ms-1">
                    <span class="fw-semibold d-block">Team tie</span>
                    <span class="text-muted small">Teams compete through singles, reverse singles, doubles and mixed doubles, as defined by the event format.</span>
                  </span>
                </label>
              </div>
            </div>
            <div class="form-text">Choose individual singles for a normal player draw.</div>
          </fieldset>

          <div data-create-guide-details hidden>
          @can('team-draw.createFormat', $event)
          <div data-regional-participants hidden>
            <label class="form-label" for="team-participant-scope">Participating sides</label>
            <select class="form-select mb-3" id="team-participant-scope">
              <option value="category">Teams from selected categories</option>
              <option value="regional">Combined regional sides (boys and girls)</option>
            </select>
            <div data-regional-age hidden>
              <label class="form-label" for="regional-age-group">Age group</label>
              <select class="form-select" id="regional-age-group"><option value="">Choose an age group</option>
                @foreach(app(\App\Services\TeamPlacementCompetitionService::class)->groups($event) as $groupIndex => $competitionGroup)
                  <option value="{{ $groupIndex }}">{{ $competitionGroup['label'] }} · {{ count($competitionGroup['teams']) }} gender teams</option>
                @endforeach
              </select>
              <p class="form-text">Each region combines its boys and girls teams into one side. Review the full team list before saving.</p>
            </div>
          </div>
          @endcan
          <div data-team-competition-format hidden>
            <h6>Competition format</h6>
            <label class="form-check border rounded p-3 mb-2"><input class="form-check-input" type="radio" name="guided_team_format" value="category"><span class="form-check-label">Round robin · selected category teams</span></label>
            @can('team-draw.createFormat', $event)
              <label class="form-check border rounded p-3 mb-2"><input class="form-check-input" type="radio" name="guided_team_format" value="regional"><span class="form-check-label">Round robin with placement playoffs · combined regional sides</span></label>
            @endcan
            <p data-team-format-description></p>
            <p class="small text-muted">Next: review match types and pairings, then set up the programme and schedule. Draws and match times are published separately.</p>
          </div>
          <div id="bulkDrawNameHelp" class="form-text mb-3 d-none">Team draw names are generated automatically from the selected categories and draw types.</div>

          {{-- Draw Type --}}
          <div class="mb-3 d-none" id="teamDrawTypeSection">
            <label class="form-label fw-bold">Team Draw Type</label>
            <div class="d-flex flex-wrap gap-2">
              @foreach($teamDrawTypes as $drawType)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="draw_type_id"
                         id="drawType{{ $drawType->id }}"
                         value="{{ $drawType->id }}" data-code="{{ app(\App\Services\TeamDrawSelectionService::class)->code($drawType) }}">
                  <label class="form-check-label" for="drawType{{ $drawType->id }}">
                    {{ $drawType->drawTypeName }}
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          @php
            $standardCategories = $categories;
            $teamCategories = \App\Support\TeamDrawCategoryGroups::make($categories);
            $individualCategories = \App\Support\IndividualDrawCategoryChoices::make($categories);
            $duplicateCategoryNames = collect($categories)->groupBy(fn ($cat) => mb_strtolower(trim($cat->name)))
              ->filter(fn ($rows) => $rows->count() > 1)->keys();
            $mixedCategoryGroups = [];
            foreach ($teamCategories as $cat) {
              if (in_array($cat->parsed_gender, ['boys', 'girls'], true)) {
                $mixedCategoryGroups[$cat->parsed_age][$cat->parsed_gender][] = $cat;
              }
            }
          @endphp

          <div class="mb-3 d-none" id="bulkTeamGroup">
            <label class="form-check"><input class="form-check-input" type="checkbox" id="bulkTeamDraws"><span class="form-check-label">Create multiple draws</span></label>
            <div id="bulkTeamChoices" class="d-none border rounded p-3 mt-2">
              <div class="d-flex justify-content-between gap-2"><strong>Draw types to create</strong><button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllDrawTypes">Select all types</button></div>
              <div class="d-grid gap-2 mt-2">
                @foreach($teamDrawTypes as $drawType)
                  @php
                    $typeCode = app(\App\Services\TeamDrawSelectionService::class)->code($drawType);
                  @endphp
                  @if($typeCode)
                  <label class="form-check"><input class="form-check-input" type="checkbox" name="bulk_draw_types[]" value="{{ $drawType->id }}" data-code="{{ $typeCode }}" data-name="{{ $drawType->name }}"><span class="form-check-label">{{ $drawType->name }}</span></label>
                  @endif
                @endforeach
              </div>
              <div class="d-flex justify-content-between gap-2 mt-3"><strong>Categories to include</strong><button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllDrawCategories">Select all categories</button></div>
              <div class="d-grid gap-2 mt-2">
                @foreach($teamCategories as $cat)
                @php
                  $bulkCategoryKey = app(\App\Services\TeamDrawSideResolver::class)->categoryKey($cat->name);
                @endphp
                <label class="form-check"><input class="form-check-input" type="checkbox" name="bulk_categories[]" value="{{ $cat->pivot_id }}" data-pivot-ids="{{ json_encode($cat->pivot_ids) }}" data-name="{{ $cat->name }}" data-age="{{ $bulkCategoryKey['group'] }}" data-gender="{{ $bulkCategoryKey['gender'] }}"><span class="form-check-label">{{ $cat->name }}</span></label>
                @endforeach
              </div>
              <div class="form-text">Each chosen category gets the selected singles, reverse singles and doubles draws. Mixed combines boys and girls of the same age and division within each region. Preview flags missing partners; deselect those categories or types before creating.</div>
            </div>
          </div>

          <div class="mb-3 d-none" id="manualCategoryToggleGroup">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" id="manualTeamCategories">
              <span class="form-check-label">Choose categories manually</span>
            </label>
            <div class="form-text">Select the categories whose teams should compete in this draw.</div>
          </div>
          <div class="mb-3 d-none" id="manualCategoryChoices">
            <label class="form-label fw-bold">Categories to combine</label>
            <div class="d-grid gap-2">
              @foreach($standardCategories as $cat)
                @php
                  $group = collect($teamCategories)->first(fn ($group) => in_array((int) $cat->pivot_id, $group->pivot_ids, true));
                @endphp
                <label class="form-check m-0">
                  <input class="form-check-input" type="checkbox" name="manual_category_ids[]"
                         value="{{ $cat->pivot_id }}" data-gender="{{ $group?->parsed_gender }}" data-name="{{ $group?->name ?? $cat->name }}" data-age="{{ $group?->parsed_age }}" disabled>
                  <span class="form-check-label">{{ $cat->name }} <span class="text-muted small">({{ $cat->teams_count }} teams · category {{ $cat->pivot_id }})</span></span>
                </label>
              @endforeach
            </div>
            <div class="form-text">For mixed doubles, select both boys and girls categories.</div>
          </div>

          {{-- Category --}}
          <div class="mb-3 d-none" id="categorySection">
            <label class="form-label fw-bold">Category</label>
            <div class="d-grid gap-2" id="individualCategoryChoices">
              @foreach($individualCategories as $cat)
                <label class="form-check m-0" for="cat{{ $cat->pivot_id }}">
                  <input class="form-check-input" type="radio" name="category_choice"
                         id="cat{{ $cat->pivot_id }}" value="{{ $cat->pivot_id }}"
                         data-pivot-id="{{ $cat->pivot_id }}" data-age="{{ $cat->name }}"
                         data-source-name="{{ $cat->source_name }}"
                         data-source-label="{{ $cat->source_name }}{{ $cat->duplicate_name ? ' (category '.$cat->pivot_id.')' : '' }}" data-gender="">
                  <span class="form-check-label">{{ $cat->name }}</span>
                </label>
              @endforeach
              @if(empty($individualCategories))
                <div class="text-muted">No standard categories available. Choose a category manually.</div>
              @endif
            </div>
            <div class="d-none" id="individualCategoryToggleGroup">
              <label class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="manualIndividualCategories">
                <span class="form-check-label">Choose category manually</span>
              </label>
              <div class="form-text">Automatic choices link to one standard category. Use manual selection for a specific division or duplicate category.</div>
            </div>
            <div class="d-none mt-2" id="manualIndividualCategoryChoices">
              <div class="d-grid gap-2">
                @foreach($standardCategories as $cat)
                  <label class="form-check m-0" for="individualManualCat{{ $cat->pivot_id }}">
                    <input class="form-check-input" type="radio" name="category_choice"
                           id="individualManualCat{{ $cat->pivot_id }}" value="{{ $cat->pivot_id }}"
                           data-pivot-id="{{ $cat->pivot_id }}" data-age="{{ $cat->name }}"
                           data-source-name="{{ $cat->name }}"
                           data-source-label="{{ $cat->name }}{{ $duplicateCategoryNames->contains(mb_strtolower(trim($cat->name))) ? ' (category '.$cat->pivot_id.')' : '' }}" data-gender="" disabled>
                    <span class="form-check-label">{{ $cat->name }} @if($duplicateCategoryNames->contains(mb_strtolower(trim($cat->name))))<span class="text-muted small">(category {{ $cat->pivot_id }})</span>@endif</span>
                  </label>
                @endforeach
              </div>
            </div>
            <div class="form-text d-none" id="individualCategorySource" aria-live="polite"></div>
            <div class="gap-2" id="teamCategoryChoices" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
              @foreach($teamCategories as $cat)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="category_choice"
                         id="teamcat{{ $cat->pivot_id }}"
                         value="{{ $cat->pivot_id }}"
                         data-pivot-id="{{ $cat->pivot_id }}"
                         data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                         data-age="{{ $cat->name }}"
                         data-gender="">
                  <label class="form-check-label" for="teamcat{{ $cat->pivot_id }}">
                    {{ $cat->name }}
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          {{-- Draw Name --}}
          <div class="mb-3 d-none" id="singleDrawNameGroup">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="Choose a category to suggest a name" maxlength="255">
            <div class="form-text">The name fills in automatically from your choices. You can edit it.</div>
          </div>

          <div class="mb-3 d-none" id="type3Categories">
            <label class="form-label fw-bold">Mixed Doubles Pairing</label>
            <div class="alert alert-info py-2 px-3">
              Choose one boys category and one girls category for the same age group.
            </div>

            @if(empty($mixedCategoryGroups))
              <div class="alert alert-warning mb-0">
                No boys/girls category pairs are available for this event.
              </div>
            @else
              @foreach($mixedCategoryGroups as $age => $genders)
                <div class="card mb-3 border">
                  <div class="card-header py-2">
                    <strong>{{ $age }}</strong>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <h6 class="mb-2">Boys</h6>
                        <div class="d-grid gap-2">
                          @forelse(($genders['boys'] ?? []) as $cat)
                            <label class="form-check form-check-inline border rounded p-2 m-0 w-100">
                              <input class="form-check-input me-2" type="radio"
                                     name="category_choice_boys"
                                     value="{{ $cat->pivot_id }}"
                                     data-pivot-id="{{ $cat->pivot_id }}"
                                     data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                                     data-age="{{ $cat->parsed_age }}"
                                     data-gender="{{ $cat->parsed_gender }}">
                              <span class="form-check-label">{{ $cat->name }}</span>
                            </label>
                          @empty
                            <div class="text-muted small">No boys category for this age group.</div>
                          @endforelse
                        </div>
                      </div>
                      <div class="col-md-6">
                        <h6 class="mb-2">Girls</h6>
                        <div class="d-grid gap-2">
                          @forelse(($genders['girls'] ?? []) as $cat)
                            <label class="form-check form-check-inline border rounded p-2 m-0 w-100">
                              <input class="form-check-input me-2" type="radio"
                                     name="category_choice_girls"
                                     value="{{ $cat->pivot_id }}"
                                     data-pivot-id="{{ $cat->pivot_id }}"
                                     data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                                     data-age="{{ $cat->parsed_age }}"
                                     data-gender="{{ $cat->parsed_gender }}">
                              <span class="form-check-label">{{ $cat->name }}</span>
                            </label>
                          @empty
                            <div class="text-muted small">No girls category for this age group.</div>
                          @endforelse
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            @endif
          </div>

          <div class="mb-3 d-none" id="mixedPlaceholder">
            <div class="alert alert-secondary mb-0">
              Select a mixed draw type to choose boys and girls categories.
            </div>
          </div>

          {{-- Existing event formats are selectable without changing rollout settings. --}}
          @if(($availableFormats ?? collect())->isNotEmpty())
          <div class="mb-3 d-none" id="formatSelectGroup">
            <label for="format_id" class="form-label fw-bold">Tie Format <span class="text-muted fw-normal">(optional – use event default)</span></label>
            <select id="format_id" name="format_id" class="form-select">
              <option value="">— Use event default —</option>
              @foreach($availableFormats ?? [] as $fmt)
                <option value="{{ $fmt->id }}">{{ $fmt->name }}</option>
              @endforeach
            </select>
            <div class="form-text">
              Defines the rubber sequence (singles, doubles, mixed, etc.) for each tie.
            </div>
          </div>
          @endif

        </div>

          </div>
        <div id="teamDrawPreview" class="px-4 pb-3 d-none" aria-live="polite"></div>

        <div id="drawCreationProgress" class="px-4 pb-3 d-none">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span id="drawCreationSpinner" class="spinner-border spinner-border-sm text-primary flex-shrink-0" aria-hidden="true"></span>
            <span id="drawCreationStatus" class="small" role="status" aria-live="polite">Creating draws… Estimated progress</span>
            <strong id="drawCreationPercent" class="small ms-auto flex-shrink-0">0%</strong>
          </div>
          <div class="progress" style="height: 8px;">
            <div id="drawCreationBar" class="progress-bar" role="progressbar" aria-label="Estimated draw creation progress"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%;"></div>
          </div>
        </div>
        <div id="drawCreationError" class="alert alert-danger mx-4 mb-3 d-none" role="alert"></div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="previewTeamDrawButton" class="btn btn-outline-primary d-none">Preview team ties</button>
          <button type="button" class="btn btn-outline-secondary" data-create-guide-back hidden>Back</button>
          <button type="button" class="btn btn-primary" data-create-guide-continue>Continue</button>
          <button type="submit" class="btn btn-primary" data-create-guide-submit hidden>Save draw &amp; continue</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Single Venues Modal (centralized to avoid duplicates / flicker) -->
<div class="modal fade" id="venuesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="venuesForm" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Venues</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="venues-container"></div>
          <button type="button" class="btn btn-sm btn-secondary" id="addVenueRow">+ Add Venue</button>
          @include('backend.draw._modals.age-group-venue-default')
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  // Expose venues to legacy scripts that expect ALL_VENUES
  window.ALL_VENUES = window.HeadOffice?.venues || @json($allVenues ?? []);

  // Remove any other legacy venuesModal instances that might still be present
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#venuesModal').forEach(function (el, idx) {
      // Keep the first one, remove extras
      if (idx > 0) el.remove();
    });
  });

</script>

@endsection
