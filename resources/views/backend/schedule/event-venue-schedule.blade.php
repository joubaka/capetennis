@extends('layouts.backend')

@section('title', 'Venue Schedule – '.$event->name)

@section('page-style')
<style>
  .schedule-workspace { --schedule-border:#e6e8ee; --schedule-muted:#667085; --schedule-soft:#f7f8fa; }
  .schedule-workspace .workspace-header { max-width: 52rem; }
  .schedule-workspace .workspace-actions .btn { white-space: nowrap; }
  .schedule-workspace .workflow-rail { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); border:1px solid var(--schedule-border); border-radius:.85rem; background:#fff; overflow:hidden; }
  .schedule-workspace .workflow-step { display:flex; align-items:center; gap:.75rem; min-width:0; padding:.9rem 1rem; color:var(--schedule-muted); }
  .schedule-workspace .workflow-step + .workflow-step { border-left:1px solid var(--schedule-border); }
  .schedule-workspace .workflow-step.is-active { color:var(--bs-primary); background:rgba(var(--bs-primary-rgb), .055); }
  .schedule-workspace .step-number { display:grid; place-items:center; flex:0 0 1.75rem; width:1.75rem; height:1.75rem; border-radius:50%; background:var(--schedule-soft); font-size:.78rem; font-weight:700; }
  .schedule-workspace .is-active .step-number { color:#fff; background:var(--bs-primary); }
  .schedule-workspace .workflow-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.84rem; font-weight:600; }
  .schedule-workspace .workspace-section { border:1px solid var(--schedule-border); border-radius:.9rem; background:#fff; box-shadow:0 .35rem 1.25rem rgba(31, 42, 68, .045); overflow:hidden; }
  .schedule-workspace .workspace-section > summary { list-style:none; display:flex; align-items:center; gap:1rem; padding:1.15rem 1.25rem; cursor:pointer; }
  .schedule-workspace .workspace-section > summary::-webkit-details-marker,
  .schedule-workspace .draw-panel > summary::-webkit-details-marker,
  .schedule-workspace .venue-editor > summary::-webkit-details-marker,
  .schedule-workspace .preview-venue > summary::-webkit-details-marker { display:none; }
  .schedule-workspace .section-title { flex:1; min-width:0; }
  .schedule-workspace .section-title h5 { margin:0 0 .2rem; }
  .schedule-workspace .summary-chevron { color:var(--schedule-muted); transition:transform .2s ease; }
  .schedule-workspace details[open] > summary .summary-chevron { transform:rotate(180deg); }
  .schedule-workspace .section-body { border-top:1px solid var(--schedule-border); padding:1.25rem; }
  .schedule-workspace .draw-list { border:1px solid var(--schedule-border); border-radius:.75rem; overflow:hidden; }
  .schedule-workspace .draw-panel + .draw-panel { border-top:1px solid var(--schedule-border); }
  .schedule-workspace .draw-panel > summary { list-style:none; display:flex; align-items:center; gap:.75rem; padding:.9rem 1rem; cursor:pointer; background:#fff; }
  .schedule-workspace .draw-panel[open] > summary { background:var(--schedule-soft); }
  .schedule-workspace .draw-heading { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex:1; min-width:0; }
  .schedule-workspace .draw-name { min-width:0; }
  .schedule-workspace .draw-preview { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }
  .schedule-workspace .draw-preview .badge { max-width:20rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500; }
  .schedule-workspace .draw-panel-body { padding:1rem; border-top:1px solid var(--schedule-border); }
  .schedule-workspace .venue-assignment { border-top:1px solid var(--schedule-border); padding:.8rem 0; }
  .schedule-workspace .venue-assignment:first-child { border-top:0; }
  .schedule-workspace .venue-assignment > .d-flex { flex-wrap:wrap; }
  .schedule-workspace .venue-assignment label { min-width:12rem; }
  .schedule-workspace .court-toggle { margin-left:auto; padding:.15rem .35rem; text-decoration:none; }
  .schedule-workspace .court-choices { padding:.75rem 0 0 1.8rem; }
  .schedule-workspace .court-choice { display:inline-flex; align-items:center; gap:.35rem; padding:.38rem .55rem; margin:0 .35rem .35rem 0; border:1px solid var(--schedule-border); border-radius:.45rem; background:#fff; color:var(--bs-body-color); font-size:.78rem; font-weight:500; }
  .schedule-workspace .venue-management { border:1px solid var(--schedule-border); border-radius:.75rem; background:var(--schedule-soft); }
  .schedule-workspace .venue-management > summary { list-style:none; display:flex; align-items:center; gap:.75rem; padding:1rem; cursor:pointer; }
  .schedule-workspace .venue-management-body { padding:0 1rem 1rem; }
  .schedule-workspace .venue-editor { border-top:1px solid var(--schedule-border); }
  .schedule-workspace .venue-editor > summary { list-style:none; display:flex; align-items:center; gap:.75rem; padding:.9rem 0; cursor:pointer; }
  .schedule-workspace .venue-editor-body { padding:0 0 1rem; }
  .schedule-workspace .preview-venue > summary { list-style:none; align-items:center; cursor:pointer; }
  .schedule-workspace .preview-venue > summary:hover { background:var(--schedule-soft); }
  .schedule-workspace .preview-venue > summary:focus-visible { outline:2px solid var(--bs-primary); outline-offset:-2px; }
  .schedule-workspace .preview-venue-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; padding:.75rem 1rem; border-top:1px solid var(--schedule-border); background:var(--schedule-soft); }
  .schedule-workspace .workspace-footer { position:sticky; bottom:.75rem; z-index:4; display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:1rem; padding:.8rem; border:1px solid var(--schedule-border); border-radius:.75rem; background:rgba(255,255,255,.96); box-shadow:0 .5rem 1.5rem rgba(31,42,68,.1); backdrop-filter:blur(8px); }
  .schedule-workspace .compact-note { border-left:3px solid var(--bs-info); padding:.55rem .75rem; background:rgba(var(--bs-info-rgb), .06); border-radius:0 .5rem .5rem 0; }
  .schedule-workspace .manual-match-card { display:block; width:100%; min-width:8.75rem; padding:.5rem; border:1px solid rgba(var(--bs-primary-rgb), .25); border-radius:.5rem; background:rgba(var(--bs-primary-rgb), .07); color:var(--bs-body-color); text-align:left; cursor:grab; }
  .schedule-workspace .manual-match-card:hover, .schedule-workspace .manual-match-card:focus-visible { border-color:var(--bs-primary); background:rgba(var(--bs-primary-rgb), .12); outline:0; }
  .schedule-workspace .manual-match-card.is-selected { border-color:var(--bs-primary); box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb), .18); }
  .schedule-workspace .manual-match-card[draggable="true"]:active { cursor:grabbing; }
  .schedule-workspace .manual-match-cell { display:grid; gap:.4rem; min-width:8.75rem; }
  .schedule-workspace .manual-match-remove { justify-self:start; }
  .schedule-workspace .manual-drop-slot { min-width:8.75rem; background:rgba(var(--bs-success-rgb), .035); transition:background .15s ease, box-shadow .15s ease; }
  .schedule-workspace .manual-drop-slot.is-drag-over { background:rgba(var(--bs-success-rgb), .18); box-shadow:inset 0 0 0 2px var(--bs-success); }
  .schedule-workspace .manual-slot-button { width:100%; min-height:4rem; border:1px dashed rgba(var(--bs-success-rgb), .5); border-radius:.5rem; background:transparent; color:var(--bs-success); text-align:left; }
  .schedule-workspace .manual-slot-button:hover, .schedule-workspace .manual-slot-button:focus-visible { border-style:solid; background:rgba(var(--bs-success-rgb), .08); outline:0; }
  .schedule-workspace .court-grid-hint { display:flex; align-items:center; gap:.4rem; padding:.55rem 1rem; border-top:1px solid var(--schedule-border); color:var(--bs-secondary-color); background:var(--schedule-soft); }
  .schedule-workspace .court-grid-scroll { position:relative; max-height:clamp(24rem, 62vh, 48rem); overflow:auto; overscroll-behavior:contain; scrollbar-gutter:stable both-edges; border-top:1px solid var(--schedule-border); }
  .schedule-workspace .court-grid-scroll table { min-width:max-content; border-top:0; }
  .schedule-workspace .court-grid-scroll thead th { position:sticky; top:0; z-index:3; background:var(--bs-body-bg); box-shadow:0 1px 0 var(--schedule-border); }
  .schedule-workspace .court-grid-scroll tr > :first-child { position:sticky; left:0; z-index:2; min-width:8.75rem; background:var(--bs-body-bg); box-shadow:1px 0 0 var(--schedule-border); }
  .schedule-workspace .court-grid-scroll thead tr > :first-child { z-index:4; }
  .schedule-workspace .court-grid-scroll::-webkit-scrollbar { width:12px; height:12px; }
  .schedule-workspace .court-grid-scroll::-webkit-scrollbar-thumb { border:3px solid transparent; border-radius:999px; background:rgba(var(--bs-secondary-rgb), .45); background-clip:padding-box; }
  .schedule-workspace .court-grid-scroll { scrollbar-color:rgba(var(--bs-secondary-rgb), .55) transparent; scrollbar-width:auto; }
  body.schedule-full-page-active { overflow:hidden; }
  body.schedule-full-page-active #layout-navbar,
  body.schedule-full-page-active #layout-menu,
  body.schedule-full-page-active .content-footer { display:none !important; }
  .schedule-workspace .schedule-display.is-full-page { position:fixed; inset:0; z-index:1035; overflow:auto; padding:1rem; background:var(--bs-body-bg); }
  .schedule-workspace .schedule-display.is-full-page #preview-view-controls { position:sticky; top:-1rem; z-index:6; margin-inline:-1rem; padding:1rem; border-bottom:1px solid var(--schedule-border); background:rgba(var(--bs-body-bg-rgb), .97); box-shadow:0 .35rem 1rem rgba(31,42,68,.08); backdrop-filter:blur(8px); }
  .schedule-workspace .schedule-display.is-full-page .court-grid-scroll { max-height:calc(100vh - 15rem); }
  .manual-match-picker-option { text-align:left; }
  .manual-match-picker-option .match-picker-meta { color:var(--bs-secondary-color); font-size:.78rem; }
  @media (max-width: 767.98px) {
    .schedule-workspace .workflow-rail { grid-template-columns:1fr; }
    .schedule-workspace .workflow-step { display:none; }
    .schedule-workspace .workflow-step.is-active { display:flex; }
    .schedule-workspace .workflow-step + .workflow-step { border-left:0; }
    .schedule-workspace .workspace-actions, .schedule-workspace .workspace-actions .btn { width:100%; }
    .schedule-workspace .workspace-section > summary, .schedule-workspace .section-body { padding:1rem; }
    .schedule-workspace .workspace-footer { position:static; align-items:stretch; flex-direction:column; }
    .schedule-workspace .workspace-footer .btn { width:100%; }
    .schedule-workspace .court-choices { padding-left:0; }
    .schedule-workspace .draw-heading { align-items:flex-start; flex-direction:column; gap:.35rem; }
    .schedule-workspace .draw-preview { justify-content:flex-start; }
    .schedule-workspace .draw-preview .badge { max-width:15rem; }
  }
</style>
@endsection

@section('content')
@php
  $unapplyRouteAvailable = \Illuminate\Support\Facades\Route::has('backend.event-venue-schedule.unapply');
@endphp
<div class="container-xxl flex-grow-1 container-p-y schedule-workspace">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="workspace-header">
      <div class="text-uppercase text-primary fw-semibold small">Event schedule workspace</div>
      <h3 class="mb-1">{{ $event->name }}</h3>
      <p class="text-muted mb-0">Schedule every assigned age group in three clear steps: assign courts, set the timing, then review.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 workspace-actions">
      <a class="btn btn-label-secondary" href="{{ url()->previous() }}"><i class="ti ti-arrow-left me-1"></i>Back</a>
      <button type="button" class="btn btn-outline-primary" id="open-venue-announcement"
        data-bs-toggle="modal" data-bs-target="#venueAnnouncementModal"
        {{ $announcementDraft['assignments']->isEmpty() ? 'disabled' : '' }}>
        <i class="ti ti-megaphone me-1"></i>Announce assigned courts
      </button>
    </div>
  </div>

  <div class="workflow-rail mb-3" aria-label="Schedule workflow">
    <div class="workflow-step is-active"><span class="step-number">1</span><span class="workflow-label">Court allocation</span></div>
    <div class="workflow-step"><span class="step-number">2</span><span class="workflow-label">Timing rules</span></div>
    <div class="workflow-step"><span class="step-number">3</span><span class="workflow-label">Review & apply</span></div>
  </div>

  <details class="workspace-section mb-3" id="court-allocation-step" open>
    @php
      $firstSelectedDrawId = $draws->first(fn($draw) => $draw['selected'] && ! $draw['locked'] && ! $draw['published'])['id'] ?? null;
    @endphp
    <summary>
      <span class="step-number">1</span>
      <span class="section-title"><h5>Assign age groups to courts</h5><small class="text-muted"><span id="selected-draw-count">{{ $draws->filter(fn($draw) => $draw['selected'] && ! $draw['locked'] && ! $draw['published'])->count() }}</span> age groups included · open only the one you are editing</small></span>
      <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
    </summary>
    <div class="section-body">
      <div class="draw-list">
          @forelse($draws as $draw)
            <details class="draw-panel {{ $draw['locked'] || $draw['published'] ? 'text-muted' : '' }}" data-draw-panel="{{ $draw['id'] }}" {{ $firstSelectedDrawId === $draw['id'] ? 'open' : '' }}>
              <summary>
                <span class="draw-heading">
                  <span class="draw-name fw-semibold">{{ $draw['name'] }}</span>
                  <span class="draw-preview" data-draw-summary="{{ $draw['id'] }}" aria-label="Assigned venues and courts">
                    @forelse($venues->whereIn('id', $draw['venues']) as $assignedVenue)
                      @php
                        $previewLabels = $draw['court_allocations'][$assignedVenue['id']] ?? [];
                        $previewCourtCount = empty($previewLabels) ? $assignedVenue['courts'] : count($previewLabels);
                      @endphp
                      <span class="badge bg-label-primary">{{ $assignedVenue['name'] }} · {{ $previewCourtCount }} {{ Str::plural('court', $previewCourtCount) }}</span>
                    @empty
                      <span class="badge bg-label-secondary">No venue assigned</span>
                    @endforelse
                  </span>
                </span>
                @if($draw['locked'] || $draw['published'])
                  <span class="badge bg-label-secondary">{{ $draw['published'] ? 'Published' : 'Locked' }}</span>
                @endif
                <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
              </summary>
              <div class="draw-panel-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                  <label class="form-check d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input draw-choice mt-0" type="checkbox" value="{{ $draw['id'] }}" {{ $draw['locked'] || $draw['published'] ? 'disabled' : ($draw['selected'] ? 'checked' : '') }}>
                    <span class="fw-semibold">Include in this schedule</span>
                  </label>
                  <div class="d-flex flex-wrap align-items-end gap-2">
                    @if($unapplyRouteAvailable && $draw['applied_match_count'] > 0 && ! $draw['locked'] && ! $draw['published'])
                      <button type="button" class="btn btn-sm btn-outline-danger" data-unapply-draw="{{ $draw['id'] }}" data-draw-name="{{ $draw['name'] }}">
                        <i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Unapply {{ $draw['applied_match_count'] }} scheduled {{ Str::plural('match', $draw['applied_match_count']) }}
                      </button>
                    @endif
                    <label class="small text-muted">Start later (optional)<input class="form-control form-control-sm draw-start mt-1" data-draw="{{ $draw['id'] }}" type="datetime-local" {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}></label>
                  </div>
                </div>
                <div class="small text-uppercase fw-semibold text-muted mb-1">Permitted venues</div>
                @php
                  [$selectedVenues, $availableVenues] = $venues->partition(fn($venue) => in_array($venue['id'], $draw['venues']));
                  $orderedVenues = $selectedVenues->concat($availableVenues);
                @endphp
                <div class="venue-list">
                  @foreach($orderedVenues as $venue)
                    @php
                      $venueAssigned = in_array($venue['id'], $draw['venues']);
                      $allocatedLabels = $draw['court_allocations'][$venue['id']] ?? [];
                      [$selectedCourts, $availableCourts] = collect($venue['court_list'])->partition(
                        fn($court) => $venueAssigned && (empty($allocatedLabels) || in_array($court['label'], $allocatedLabels))
                      );
                      $orderedCourts = $selectedCourts->concat($availableCourts);
                    @endphp
                    <div class="venue-assignment">
                      <div class="d-flex align-items-center gap-2">
                        <label class="d-flex align-items-center gap-2 mb-0 flex-grow-1"><input class="form-check-input assignment-choice mt-0" data-draw="{{ $draw['id'] }}" data-venue-name="{{ $venue['name'] }}" type="checkbox" value="{{ $venue['id'] }}" {{ $venueAssigned ? 'checked' : '' }} {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}><span class="fw-semibold">{{ $venue['name'] }}</span></label>
                        <span class="small text-muted" data-court-summary="{{ $draw['id'] }}-{{ $venue['id'] }}">{{ $venueAssigned ? (empty($allocatedLabels) ? 'All '.$venue['courts'] : count($allocatedLabels).' of '.$venue['courts']) : 'Not used' }}</span>
                        <button class="btn btn-sm btn-text-secondary court-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courts-{{ $draw['id'] }}-{{ $venue['id'] }}" aria-expanded="false" aria-controls="courts-{{ $draw['id'] }}-{{ $venue['id'] }}">Choose courts</button>
                      </div>
                      <div class="collapse" id="courts-{{ $draw['id'] }}-{{ $venue['id'] }}"><div class="court-choices">
                        @foreach($orderedCourts as $court)
                          @php $courtChecked = $venueAssigned && (empty($allocatedLabels) || in_array($court['label'], $allocatedLabels)); @endphp
                          <label class="court-choice"><input class="form-check-input court-allocation mt-0" data-draw="{{ $draw['id'] }}" data-venue="{{ $venue['id'] }}" type="checkbox" value="{{ $court['label'] }}" {{ $courtChecked ? 'checked' : '' }} {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}>Court {{ $court['label'] }}@if($court['ball_type']) · {{ ucfirst($court['ball_type']) }}@endif</label>
                        @endforeach
                      </div></div>
                    </div>
                  @endforeach
                </div>
              </div>
            </details>
          @empty
            <div class="text-muted p-3">No draws have been created for this event.</div>
          @endforelse
      </div>

      <details class="venue-management mt-3" id="venue-management">
        <summary><i class="ti ti-settings" aria-hidden="true"></i><span class="flex-grow-1"><strong>Manage venues and court setup</strong><span class="d-block small text-muted">{{ $venues->count() }} venues · {{ $venues->sum('courts') }} courts available</span></span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>
        <div class="venue-management-body">
          <div class="border rounded p-3 mb-2 bg-white">
            <h6>Add another venue</h6>
            <label class="form-label small" for="new-venue-id">Use an existing venue</label>
            <select id="new-venue-id" class="form-select form-select-sm mb-2"><option value="">Create a new venue instead…</option>@foreach($allVenues as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
            <label class="form-label small" for="new-venue-name">New venue name</label>
            <input id="new-venue-name" class="form-control form-control-sm mb-2" maxlength="191" placeholder="Enter a venue name">
            <div class="row g-2">
              <div class="col-sm-6"><label class="form-label small" for="new-venue-courts">Number of courts</label><input id="new-venue-courts" type="number" class="form-control form-control-sm" value="1" min="1" max="100"></div>
              <div class="col-sm-6"><label class="form-label small" for="new-venue-ball">Court type</label><select id="new-venue-ball" class="form-select form-select-sm"><option value="standard">Standard</option><option value="yellow">Yellow ball</option><option value="orange">Orange ball</option><option value="green">Green ball</option><option value="red">Red ball</option></select></div>
            </div>
            <button type="button" id="add-venue" class="btn btn-sm btn-primary mt-3"><i class="ti ti-plus me-1"></i>Add venue and courts</button>
            <div id="venue-add-status" class="small text-muted mt-2" role="status" aria-live="polite"></div>
          </div>
          @forelse($venues as $venue)
          <details class="venue-editor">
            <summary><strong class="flex-grow-1">{{ $venue['name'] }}</strong><span class="badge bg-label-primary">{{ $venue['courts'] }} courts</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>
            <div class="venue-editor-body">
            <div class="small fw-semibold mt-3 mb-2">Edit this venue's court setup</div>
            <div class="row g-2 venue-court-setup" data-url="{{ route('backend.event-venue-schedule.courts.configure', [$event, $venue['id']]) }}">
              <div class="col-sm-4"><label class="visually-hidden">Total courts at {{ $venue['name'] }}</label><input type="number" class="form-control form-control-sm setup-court-count" value="{{ $venue['courts'] }}" min="1" max="100" aria-label="Total courts at {{ $venue['name'] }}"></div>
              <div class="col-sm-5"><label class="visually-hidden">Court type at {{ $venue['name'] }}</label><select class="form-select form-select-sm setup-court-ball"><option value="mixed" disabled {{ $venue['common_ball_type'] === 'mixed' ? 'selected' : '' }}>Mixed types</option><option value="standard" {{ $venue['common_ball_type'] === 'standard' ? 'selected' : '' }}>Standard</option><option value="yellow" {{ $venue['common_ball_type'] === 'yellow' ? 'selected' : '' }}>Yellow</option><option value="orange" {{ $venue['common_ball_type'] === 'orange' ? 'selected' : '' }}>Orange</option><option value="green" {{ $venue['common_ball_type'] === 'green' ? 'selected' : '' }}>Green</option><option value="red" {{ $venue['common_ball_type'] === 'red' ? 'selected' : '' }}>Red</option></select></div>
              <div class="col-sm-3"><button type="button" class="btn btn-sm btn-primary w-100 update-court-setup" data-has-custom="{{ $venue['has_custom_courts'] ? '1' : '0' }}">Update all</button></div>
              <div class="col-12"><small class="setup-status text-muted" role="status" aria-live="polite">Sets numbered Courts 1–{{ $venue['courts'] }} to one type{{ $venue['has_custom_courts'] ? ' and replaces specially named courts' : '' }}.</small></div>
            </div>
            <div class="small fw-semibold mt-3 mb-2">Individual courts</div>
            <div class="d-grid gap-2">
              @foreach($venue['court_list'] as $court)
                <div class="row g-2 align-items-center">
                  <div class="col-sm-5 small">Court {{ $court['label'] }}</div>
                  <div class="col-7 col-sm-4"><select class="form-select form-select-sm edit-court-ball" aria-label="Type for court {{ $court['label'] }} at {{ $venue['name'] }}" data-venue="{{ $venue['id'] }}" data-label="{{ $court['label'] }}"><option value="standard" {{ !$court['ball_type'] || $court['ball_type'] === 'standard' ? 'selected' : '' }}>Standard</option><option value="yellow" {{ $court['ball_type'] === 'yellow' ? 'selected' : '' }}>Yellow</option><option value="orange" {{ $court['ball_type'] === 'orange' ? 'selected' : '' }}>Orange</option><option value="green" {{ $court['ball_type'] === 'green' ? 'selected' : '' }}>Green</option><option value="red" {{ $court['ball_type'] === 'red' ? 'selected' : '' }}>Red</option></select></div>
                  <div class="col-5 col-sm-3"><button type="button" class="btn btn-sm btn-outline-secondary w-100 update-court-type" data-venue="{{ $venue['id'] }}" data-label="{{ $court['label'] }}">Save</button></div>
                </div>
              @endforeach
            </div>
            <div class="small fw-semibold mt-3">Add a specially named court</div>
            <div class="row g-2 mt-2"><div class="col-sm-5"><input class="form-control form-control-sm add-court-label" data-venue="{{ $venue['id'] }}" aria-label="New court label at {{ $venue['name'] }}" placeholder="Court label"></div><div class="col-7 col-sm-4"><select class="form-select form-select-sm add-court-ball" data-venue="{{ $venue['id'] }}" aria-label="New court type"><option value="standard">Standard</option><option value="yellow">Yellow</option><option value="orange">Orange</option><option value="green">Green</option><option value="red">Red</option></select></div><div class="col-5 col-sm-3"><button type="button" class="btn btn-sm btn-outline-primary w-100 add-court" data-venue="{{ $venue['id'] }}">Add</button></div></div>
            </div>
          </details>
          @empty
            <div class="alert alert-warning mb-0">Add the first venue and its courts before creating allocations.</div>
          @endforelse
        </div>
      </details>

      @if($draws->isNotEmpty() && $venues->isNotEmpty())
        <div class="workspace-footer">
          <span id="allocation-status" class="small text-muted" role="status" aria-live="polite">Save changes before creating a preview.</span>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" id="save-allocations" class="btn btn-outline-primary"><i class="ti ti-device-floppy me-1"></i>Save allocations</button>
            <button type="button" id="continue-to-rules" class="btn btn-primary">Next: timing rules<i class="ti ti-arrow-right ms-1"></i></button>
          </div>
        </div>
      @endif
    </div>
  </details>

  <details class="workspace-section mb-3" id="schedule-rules-step">
    <summary>
      <span class="step-number">2</span>
      <span class="section-title"><h5>Scheduling rules</h5><small class="text-muted">Set the event window, match length and player rest.</small></span>
      <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
    </summary>
    <div class="section-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label" for="schedule-start">Schedule starts</label><input id="schedule-start" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T08:00"></div>
        <div class="col-md-3"><label class="form-label" for="schedule-end">Schedule ends</label><input id="schedule-end" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T18:00"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-duration">Match minutes</label><input id="schedule-duration" type="number" class="form-control" value="75" min="15" max="480"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-wave">Round wave</label><input id="schedule-wave" type="number" class="form-control" value="90" min="15" max="480"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-gap">Court gap</label><input id="schedule-gap" type="number" class="form-control" value="5" min="0" max="120"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-rest">Rest</label><input id="schedule-rest" type="number" class="form-control" value="60" min="0" max="480"></div>
      </div>
      <div class="compact-note small text-muted mt-3" role="note"><strong class="text-body">How byes are timed:</strong> a bye uses no court but still advances through its round wave. A player with two byes first appears in the third wave.</div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" id="generate-preview" class="btn btn-primary"><i class="ti ti-wand me-1"></i>Generate combined preview</button>
        <button type="button" id="apply-preview" class="btn btn-success" disabled><i class="ti ti-device-floppy me-1"></i>Apply schedule</button>
        <span id="schedule-status" class="align-self-center text-muted small" role="status" aria-live="polite">Preview the full event before applying.</span>
      </div>
      <div id="schedule-activity" class="border rounded bg-light p-3 mt-3 d-none" aria-busy="false">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="d-flex align-items-center gap-2">
            <span id="schedule-activity-spinner" class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <strong id="schedule-activity-label" role="status" aria-live="polite">Preparing the combined preview…</strong>
          </div>
          <span id="schedule-activity-meta" class="small text-muted">About 5% · 0s elapsed</span>
        </div>
        <div class="progress" style="height: 8px;" aria-label="Estimated scheduling progress">
          <div id="schedule-activity-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 5%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5"></div>
        </div>
        <small id="schedule-activity-note" class="text-muted d-block mt-2">Progress is estimated while the server builds and validates the complete schedule.</small>
      </div>
    </div>
  </details>

  <div id="schedule-display" class="schedule-display">
    <div id="preview-summary" class="row g-3 mb-3 d-none"></div>
    <div id="preview-warnings"></div>
    <div id="preview-view-controls" class="d-none flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <strong>Fixtures per venue</strong>
        <div class="small text-muted">In the court grid, drag a match onto an Available slot. You can also select a match, then select a slot. Saved matches stay in the grid; unsaved suggestions adapt around them.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" id="keep-all-applied" class="btn btn-sm btn-outline-secondary d-none"><i class="ti ti-arrow-back-up me-1" aria-hidden="true"></i>Keep all current venue times</button>
        <button type="button" id="replan-all-applied" class="btn btn-sm btn-outline-primary d-none"><i class="ti ti-refresh me-1" aria-hidden="true"></i>Replan all applied venues</button>
        <button type="button" id="toggle-schedule-full-page" class="btn btn-sm btn-outline-secondary" aria-pressed="false" title="Use the full browser page for the schedule; press Escape to exit"><i class="ti ti-maximize me-1" aria-hidden="true"></i><span>Full page</span></button>
        <div class="btn-group btn-group-sm" role="group" aria-label="Venue schedule view">
          <button type="button" class="btn btn-primary active" data-preview-view="timeline" aria-pressed="true"><i class="ti ti-list me-1" aria-hidden="true"></i>Fixture list</button>
          <button type="button" class="btn btn-outline-primary" data-preview-view="grid" aria-pressed="false"><i class="ti ti-calendar-time me-1" aria-hidden="true"></i>Court slot grid</button>
        </div>
      </div>
    </div>
    <div id="venue-timelines"></div>
    <div id="venue-slot-grids" class="d-none"></div>
  </div>
</div>

<div class="modal fade" id="manualMatchPickerModal" tabindex="-1" aria-labelledby="manualMatchPickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="manualMatchPickerLabel">Choose a match for this slot</h5>
          <div class="small text-muted mt-1" id="manual-match-picker-slot"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label visually-hidden" for="manual-match-picker-search">Search matches</label>
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
          <input type="search" id="manual-match-picker-search" class="form-control" placeholder="Search by age group, match or player" autocomplete="off">
        </div>
        <div class="small text-muted mb-2">Selecting a match saves it in this box immediately, removes it from this list, and adapts the remaining unsaved suggestions. Match order, participant conflicts and player rest are checked before saving.</div>
        <div class="list-group" id="manual-match-picker-list"></div>
        <div class="alert alert-info mb-0 d-none" id="manual-match-picker-empty">No matching schedulable matches are available.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="venueAnnouncementModal" tabindex="-1" aria-labelledby="venueAnnouncementLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="venueAnnouncementLabel">Review venue announcement and email</h5>
          <div class="small text-muted mt-1">Edit the draft below before publishing it on the event page and emailing players.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2" role="note">
          This will publish one announcement and queue email to
          <strong>{{ $announcementDraft['recipient_count'] }}</strong>
          unique active, paid player {{ Str::plural('email address', $announcementDraft['recipient_count']) }}.
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="venue-announcement-title">Announcement title / email subject</label>
          <input type="text" id="venue-announcement-title" class="form-control" maxlength="255" value="{{ $announcementDraft['title'] }}" required>
          <div class="form-text">Email subject: <span id="venue-email-subject"></span></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="venue-announcement-message">Email and announcement draft</label>
          <div id="venue-announcement-message" class="form-control overflow-auto" contenteditable="true"
            role="textbox" aria-multiline="true" style="min-height: 22rem; max-height: 55vh;">{!! $announcementDraft['message'] !!}</div>
          <div class="form-text">The same edited wording will appear in Announcements on the public event page and in the email.</div>
        </div>
        <div id="venue-announcement-status" class="small" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('admin.events.announcements', $event) }}" class="btn btn-outline-secondary">View announcements</a>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="publish-venue-announcement"
          {{ $announcementDraft['assignments']->isEmpty() || $announcementDraft['recipient_count'] === 0 ? 'disabled' : '' }}>
          <i class="ti ti-send me-1"></i>Publish and queue email
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const previewUrl = @json(route('backend.event-venue-schedule.preview', $event));
  const applyUrl = @json(route('backend.event-venue-schedule.apply', $event));
  const unapplyUrl = @json($unapplyRouteAvailable ? route('backend.event-venue-schedule.unapply', $event) : null);
  const manualAssignmentUrl = @json(route('backend.event-venue-schedule.manual-assignment', $event));
  const manualOptionsUrl = @json(route('backend.event-venue-schedule.manual-options', $event));
  const assignmentUrl = @json(route('backend.event-venue-schedule.assignments', $event));
  const venueUrl = @json(route('backend.event-venue-schedule.venues', $event));
  const courtUrl = @json(route('backend.event-venue-schedule.courts', $event));
  const announcementUrl = @json(route('admin.events.announcements.store', $event));
  const drawsUrl = @json(route('headOffice.show', ['headOffice' => $event->id, 'schedule' => 'applied']));
  const eventName = @json($event->name);
  const manualMode = @json(request()->boolean('manual'));
  const drawIds = @json($draws->reject(fn($draw) => $draw['locked'] || $draw['published'])->pluck('id')->values());
  let payload = null;
  let revision = null;
  let replanVenueIds = [];
  let allocationsDirty = false;
  let scheduleActivityTimer = null;
  let scheduleActivityHideTimer = null;
  let scheduleActivityStartedAt = 0;
  let scheduleActivityStages = [];
  let selectedManualFixture = null;
  let pendingManualSlot = null;
  let lastScheduleResult = null;

  const previewActivityStages = [
    {after: 0, percent: 5, label: 'Sending the scheduling rules…'},
    {after: 700, percent: 18, label: 'Loading selected draws, venues, and courts…'},
    {after: 2200, percent: 36, label: 'Checking match order, byes, and player rest…'},
    {after: 5000, percent: 58, label: 'Finding available court times across venues…'},
    {after: 9000, percent: 74, label: 'Resolving court and player conflicts…'},
    {after: 15000, percent: 86, label: 'Balancing age groups across the timetable…'},
    {after: 25000, percent: 94, label: 'Finalising and validating the preview…'},
  ];
  const applyActivityStages = [
    {after: 0, percent: 5, label: 'Submitting the approved schedule…'},
    {after: 700, percent: 18, label: 'Locking the schedule revision…'},
    {after: 2200, percent: 34, label: 'Applying fixtures to their courts…'},
    {after: 5000, percent: 50, label: 'Saving the combined timetable…'},
    {after: 9000, percent: 60, label: 'Confirming the applied fixtures…'},
  ];
  const revalidationActivityStages = [
    {after: 0, percent: 66, label: 'Rebuilding the applied schedule view…'},
    {after: 1200, percent: 76, label: 'Loading the fixed court bookings…'},
    {after: 3500, percent: 87, label: 'Rechecking courts and player conflicts…'},
    {after: 7000, percent: 95, label: 'Finalising the applied schedule…'},
  ];

  const values = selector => [...document.querySelectorAll(selector + ':checked')].map(el => Number(el.value));
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const setStatus = (element, message, tone = 'muted') => {
    element.textContent = message;
    element.classList.remove('text-muted', 'text-danger', 'text-success', 'text-warning');
    element.classList.add(`text-${tone}`);
  };
  const updateScheduleActivity = () => {
    const elapsed = Math.max(0, performance.now() - scheduleActivityStartedAt);
    const stage = [...scheduleActivityStages].reverse().find(item => elapsed >= item.after) || scheduleActivityStages[0];
    const elapsedSeconds = Math.floor(elapsed / 1000);
    document.getElementById('schedule-activity-label').textContent = stage.label;
    document.getElementById('schedule-activity-meta').textContent = `About ${stage.percent}% · ${elapsedSeconds}s elapsed`;
    const bar = document.getElementById('schedule-activity-bar');
    bar.style.width = `${stage.percent}%`;
    bar.setAttribute('aria-valuenow', String(stage.percent));
  };
  const setScheduleActivityPhase = stages => {
    scheduleActivityStages = stages;
    scheduleActivityStartedAt = performance.now();
    updateScheduleActivity();
  };
  const startScheduleActivity = (stages, note) => {
    if (scheduleActivityTimer) window.clearInterval(scheduleActivityTimer);
    if (scheduleActivityHideTimer) window.clearTimeout(scheduleActivityHideTimer);
    scheduleActivityHideTimer = null;
    document.getElementById('schedule-activity').classList.remove('d-none');
    document.getElementById('schedule-activity').setAttribute('aria-busy', 'true');
    document.getElementById('schedule-activity-spinner').classList.remove('d-none');
    document.getElementById('schedule-activity-bar').classList.add('progress-bar-animated');
    document.getElementById('schedule-activity-note').textContent = note;
    setScheduleActivityPhase(stages);
    scheduleActivityTimer = window.setInterval(updateScheduleActivity, 250);
  };
  const stopScheduleActivity = () => {
    if (scheduleActivityTimer) window.clearInterval(scheduleActivityTimer);
    scheduleActivityTimer = null;
    document.getElementById('schedule-activity').setAttribute('aria-busy', 'false');
    document.getElementById('schedule-activity').classList.add('d-none');
  };
  const finishScheduleActivity = label => {
    if (scheduleActivityTimer) window.clearInterval(scheduleActivityTimer);
    scheduleActivityTimer = null;
    const bar = document.getElementById('schedule-activity-bar');
    bar.style.width = '100%';
    bar.setAttribute('aria-valuenow', '100');
    bar.classList.remove('progress-bar-animated');
    document.getElementById('schedule-activity-spinner').classList.add('d-none');
    document.getElementById('schedule-activity-label').textContent = label;
    document.getElementById('schedule-activity-meta').textContent = '100% · Complete';
    document.getElementById('schedule-activity').setAttribute('aria-busy', 'false');
    scheduleActivityHideTimer = window.setTimeout(() => {
      document.getElementById('schedule-activity').classList.add('d-none');
      scheduleActivityHideTimer = null;
    }, 800);
  };
  const invalidatePreview = (message = 'Settings changed. Generate a new preview before applying.') => {
    payload = null;
    revision = null;
    document.getElementById('apply-preview').disabled = true;
    setStatus(document.getElementById('schedule-status'), message, 'warning');
  };
  const markAllocationsDirty = () => {
    allocationsDirty = true;
    setStatus(document.getElementById('allocation-status'), 'Unsaved court allocation changes.', 'warning');
    invalidatePreview('Save the court allocations, then generate a new preview.');
  };
  const setWorkflowStep = step => {
    document.querySelectorAll('.workflow-step').forEach((item, index) => item.classList.toggle('is-active', index === step - 1));
  };
  const updateCourtSummary = (drawId, venueId) => {
    const venue = document.querySelector(`.assignment-choice[data-draw="${drawId}"][value="${venueId}"]`);
    const courts = [...document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venueId}"]`)];
    const selected = courts.filter(court => court.checked).length;
    const summary = document.querySelector(`[data-court-summary="${drawId}-${venueId}"]`);
    if (summary) summary.textContent = !venue?.checked ? 'Not used' : selected === courts.length ? `All ${courts.length}` : `${selected} of ${courts.length}`;
  };
  const updateDrawSummary = drawId => {
    const summary = document.querySelector(`[data-draw-summary="${drawId}"]`);
    const assigned = [...document.querySelectorAll(`.assignment-choice[data-draw="${drawId}"]:checked`)];
    if (summary) {
      const badges = assigned.map(venue => {
        const selectedCourts = document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venue.value}"]:checked`).length;
        const badge = document.createElement('span');
        badge.className = 'badge bg-label-primary';
        badge.textContent = `${venue.dataset.venueName} · ${selectedCourts} ${selectedCourts === 1 ? 'court' : 'courts'}`;
        return badge;
      });
      if (!badges.length) {
        const emptyBadge = document.createElement('span');
        emptyBadge.className = 'badge bg-label-secondary';
        emptyBadge.textContent = 'No venue assigned';
        badges.push(emptyBadge);
      }
      summary.replaceChildren(...badges);
    }
    const count = document.getElementById('selected-draw-count');
    if (count) count.textContent = document.querySelectorAll('.draw-choice:checked').length;
  };
  const sortCheckedFirst = (container, checkboxSelector, itemSelector) => {
    if (!container) return;
    [...container.querySelectorAll(checkboxSelector)]
      .map((checkbox, index) => ({checkbox, index, item:checkbox.closest(itemSelector)}))
      .sort((left, right) => Number(right.checkbox.checked) - Number(left.checkbox.checked) || left.index - right.index)
      .forEach(({item}) => { if (item) container.appendChild(item); });
  };
  const buildPayload = () => ({
    start: document.getElementById('schedule-start').value,
    end: document.getElementById('schedule-end').value || null,
    duration: Number(document.getElementById('schedule-duration').value),
    wave_minutes: Number(document.getElementById('schedule-wave').value),
    court_gap: Number(document.getElementById('schedule-gap').value),
    player_rest: Number(document.getElementById('schedule-rest').value),
    draw_ids: values('.draw-choice'),
    replan_venue_ids: replanVenueIds,
    draw_starts: [...document.querySelectorAll('.draw-start')].filter(input => input.value && document.querySelector(`.draw-choice[value="${input.dataset.draw}"]`)?.checked).map(input => ({draw_id:Number(input.dataset.draw), start:input.value}))
  });
  const post = async (url, body) => {
    const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body)});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Request failed.');
    return data;
  };
  const announcementTitle = document.getElementById('venue-announcement-title');
  const announcementMessage = document.getElementById('venue-announcement-message');
  const announcementStatus = document.getElementById('venue-announcement-status');
  const publishAnnouncement = document.getElementById('publish-venue-announcement');
  const updateEmailSubject = () => {
    document.getElementById('venue-email-subject').textContent = `${announcementTitle.value.trim() || 'Announcement'} – ${eventName}`;
  };
  announcementTitle?.addEventListener('input', updateEmailSubject);
  updateEmailSubject();
  document.getElementById('open-venue-announcement')?.addEventListener('click', event => {
    if (!allocationsDirty) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    setStatus(document.getElementById('allocation-status'), 'Save the court allocations before reviewing the announcement draft.', 'danger');
    document.getElementById('save-allocations')?.focus();
  });
  publishAnnouncement?.addEventListener('click', async () => {
    const title = announcementTitle.value.trim();
    const message = announcementMessage.innerHTML.trim();
    const plainMessage = announcementMessage.textContent.trim();
    if (!title || !plainMessage) {
      setStatus(announcementStatus, 'Enter a title and message before publishing.', 'danger');
      (!title ? announcementTitle : announcementMessage).focus();
      return;
    }

    publishAnnouncement.disabled = true;
    setStatus(announcementStatus, 'Publishing the announcement and queueing player email…');
    try {
      const result = await post(announcementUrl, {title, message, sendMail:true});
      const queued = Number(result.mail?.queued || 0);
      setStatus(announcementStatus, `Announcement published. ${queued} player ${queued === 1 ? 'email is' : 'emails are'} queued for delivery.`, 'success');
      publishAnnouncement.innerHTML = '<i class="ti ti-check me-1"></i>Published';
      document.getElementById('open-venue-announcement').disabled = true;
    } catch (error) {
      setStatus(announcementStatus, error.message, 'danger');
      publishAnnouncement.disabled = false;
    }
  });
  document.querySelectorAll('.draw-panel').forEach(panel => panel.addEventListener('toggle', () => {
    if (!panel.open) return;
    document.querySelectorAll('.draw-panel').forEach(other => { if (other !== panel) other.open = false; });
    sortCheckedFirst(panel.querySelector('.venue-list'), '.assignment-choice', '.venue-assignment');
  }));
  document.querySelectorAll('.court-choices').forEach(choices => {
    choices.closest('.collapse')?.addEventListener('show.bs.collapse', () => {
      sortCheckedFirst(choices, '.court-allocation', '.court-choice');
    });
  });
  document.getElementById('court-allocation-step')?.addEventListener('toggle', event => {
    if (event.currentTarget.open) setWorkflowStep(1);
  });
  document.getElementById('schedule-rules-step')?.addEventListener('toggle', event => {
    if (event.currentTarget.open) setWorkflowStep(2);
  });
  document.getElementById('continue-to-rules')?.addEventListener('click', () => {
    if (allocationsDirty) {
      setStatus(document.getElementById('allocation-status'), 'Save the court allocations before continuing.', 'danger');
      document.getElementById('save-allocations')?.focus();
      return;
    }
    const rules = document.getElementById('schedule-rules-step');
    rules.open = true;
    document.getElementById('court-allocation-step').open = false;
    setWorkflowStep(2);
    rules.scrollIntoView({behavior:'smooth', block:'start'});
  });
  document.querySelectorAll('.assignment-choice').forEach(input => input.addEventListener('change', () => {
    document.querySelectorAll(`.court-allocation[data-draw="${input.dataset.draw}"][data-venue="${input.value}"]`)
      .forEach(court => { court.checked = input.checked; });
    updateCourtSummary(input.dataset.draw, input.value);
    updateDrawSummary(input.dataset.draw);
    markAllocationsDirty();
  }));
  document.querySelectorAll('.court-allocation').forEach(input => input.addEventListener('change', () => {
    const courts = [...document.querySelectorAll(`.court-allocation[data-draw="${input.dataset.draw}"][data-venue="${input.dataset.venue}"]`)];
    const venue = document.querySelector(`.assignment-choice[data-draw="${input.dataset.draw}"][value="${input.dataset.venue}"]`);
    venue.checked = courts.some(court => court.checked);
    updateCourtSummary(input.dataset.draw, input.dataset.venue);
    updateDrawSummary(input.dataset.draw);
    markAllocationsDirty();
  }));
  document.querySelectorAll('.draw-choice, .draw-start, #schedule-start, #schedule-end, #schedule-duration, #schedule-wave, #schedule-gap, #schedule-rest')
    .forEach(input => input.addEventListener('change', () => invalidatePreview()));
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => {
    const start = document.querySelector(`.draw-start[data-draw="${input.value}"]`);
    if (start) start.disabled = !input.checked;
    updateDrawSummary(input.value);
  }));
  const existingVenue = document.getElementById('new-venue-id');
  const newVenueName = document.getElementById('new-venue-name');
  existingVenue?.addEventListener('change', () => {
    newVenueName.disabled = Boolean(existingVenue.value);
    if (existingVenue.value) newVenueName.value = '';
    newVenueName.placeholder = existingVenue.value ? 'Using the selected venue' : 'Enter a venue name';
  });
  const card = (value, label, tone='primary') => `<div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><div class="fs-4 fw-bold text-${tone}">${value}</div><small class="text-muted">${label}</small></div></div></div>`;
  const asDate = value => new Date(String(value).replace(' ', 'T'));
  const dateKey = value => asDate(value).getTime();
  const formatSlotTime = date => date.toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
  const assignmentTime = date => {
    const pad = value => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
  };
  const matchEnd = (match, result) => match.ends_at
    ? asDate(match.ends_at)
    : new Date(dateKey(match.scheduled_at) + (Number(match.duration || result.input.duration) + Number(result.input.courtGap || 0)) * 60000);
  const venueRows = (result, venueId) => [...result.matches.map(match => ({...match, fixed:false})),
    ...(result.existing_matches || []).map(match => ({...match, fixed:true}))]
    .filter(match => Number(match.venue_id) === Number(venueId))
    .sort((a, b) => dateKey(a.scheduled_at) - dateKey(b.scheduled_at) || String(a.court).localeCompare(String(b.court), undefined, {numeric:true}));
  const permittedCourts = (match, venueId) => match?.venue_courts?.[String(venueId)] || [];
  const canUseCourt = (match, venueId, court) => permittedCourts(match, venueId).map(String).includes(String(court));
  const unresolvedAtVenue = (result, venueId) => (result.unscheduled || [])
    .filter(match => permittedCourts(match, venueId).length > 0).length;
  const venueActions = (result, venue) => {
    const planned = result.matches.filter(match => Number(match.venue_id) === Number(venue.id)).length;
    const fixed = (result.existing_matches || []).filter(match => Number(match.venue_id) === Number(venue.id)).length;
    const unresolved = unresolvedAtVenue(result, venue.id);
    const replanning = (result.input.replan_venue_ids || []).map(Number).includes(Number(venue.id));
    let state = '<span class="badge bg-label-secondary">Not applied yet</span>';
    if (fixed && planned) state = `<span class="badge bg-label-success">${fixed} saved</span><span class="badge bg-label-primary ms-2">${planned} suggested · not saved</span>`;
    else if (fixed) state = `<span class="badge bg-label-success">${fixed} saved</span>`;
    else if (planned) state = `<span class="badge bg-label-primary">${planned} suggested · not saved</span>`;
    if (replanning) state = '<span class="badge bg-label-warning">Replanning this venue</span>';
    if (unresolved) state += `<span class="badge bg-label-danger ms-2">${unresolved} unresolved</span>`;
    const apply = planned && !unresolved ? `<button type="button" class="btn btn-sm btn-success" data-apply-venue="${venue.id}" data-venue-name="${escapeHtml(venue.name)}"><i class="ti ti-check me-1" aria-hidden="true"></i>Apply this venue</button>` : '';
    const change = fixed && !replanning ? `<button type="button" class="btn btn-sm btn-outline-primary" data-replan-venue="${venue.id}" data-venue-name="${escapeHtml(venue.name)}">Change this venue</button>` : '';
    const unapply = unapplyUrl && fixed && !replanning ? `<button type="button" class="btn btn-sm btn-outline-danger" data-unapply-venue="${venue.id}" data-venue-name="${escapeHtml(venue.name)}"><i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Unapply venue times</button>` : '';
    const keep = replanning ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-keep-venue="${venue.id}">Keep current applied schedule</button>` : '';
    return `<div class="preview-venue-actions"><div>${state}<span class="small text-muted ms-2">Change previews new times; unapply removes saved times and returns matches to planning.</span></div><div class="d-flex flex-wrap gap-2">${keep}${change}${unapply}${apply}</div></div>`;
  };

  function slotGrid(result, venue) {
    const rows = venueRows(result, venue.id);
    const start = asDate(result.input.start);
    const end = result.input.end ? asDate(result.input.end) : new Date(Math.max(start.getTime(), ...rows.map(row => matchEnd(row, result).getTime())));
    const step = Math.max(15, Number(result.input.duration) + Number(result.input.courtGap || 0));
    const times = new Map(rows.map(row => [dateKey(row.scheduled_at), asDate(row.scheduled_at)]));
    let cursor = new Date(start);
    while (cursor < end && times.size < 200) {
      times.set(cursor.getTime(), new Date(cursor));
      cursor = new Date(cursor.getTime() + step * 60000);
    }
    const slots = [...times.values()].filter(time => time >= start && time < end).sort((a, b) => a - b).slice(0, 200);
    const courtHeaders = venue.court_labels.map(court => `<th class="text-nowrap">Court ${escapeHtml(court)}</th>`).join('');
    const body = slots.map(time => {
      const cells = venue.court_labels.map(court => {
        const starts = rows.find(row => String(row.court) === String(court) && dateKey(row.scheduled_at) === time.getTime());
        if (starts) {
          const movable = !starts.fixed || starts.editable;
          const path = (starts.participants || []).join(' / ') || 'Participants determined by draw';
          const round = starts.wave ? `Wave ${starts.wave} · R${starts.round}` : `R${starts.round}`;
          const state = starts.fixed
            ? '<span class="badge bg-label-success mt-1">Saved</span>'
            : '<span class="badge bg-label-primary mt-1">Suggested · not saved</span>';
          const card = movable
            ? `<button type="button" class="manual-match-card" draggable="true" data-manual-fixture="${starts.fixture_id}" aria-pressed="false" title="Drag this match to an available slot"><span class="fw-semibold">${escapeHtml(starts.draw_name)}</span><span class="small d-block">${round} · Match ${escapeHtml(starts.match || '—')}</span>${state}<span class="small text-muted d-block mt-1">${escapeHtml(path)}</span></button>`
            : `<div class="fw-semibold">${escapeHtml(starts.draw_name)}</div><div class="small">${round} · Match ${escapeHtml(starts.match || '—')}</div>${state}<div class="small text-muted mt-1">${escapeHtml(path)}</div>`;
          const remove = starts.fixed && starts.editable && unapplyUrl
            ? `<button type="button" class="btn btn-sm btn-outline-danger manual-match-remove" data-unapply-fixture="${starts.fixture_id}" data-match-label="${escapeHtml(starts.draw_name)} Match ${escapeHtml(starts.match || '—')}"><i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Remove from schedule</button>`
            : '';
          return `<td><div class="manual-match-cell">${card}${remove}</div></td>`;
        }
        const occupied = rows.find(row => String(row.court) === String(court) && asDate(row.scheduled_at) < time && matchEnd(row, result) > time);
        if (occupied) return `<td class="bg-label-secondary text-muted"><span class="fw-semibold">In use</span><div class="small">until ${escapeHtml(matchEnd(occupied, result).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}))}</div></td>`;
        return `<td class="manual-drop-slot" data-manual-slot data-venue="${venue.id}" data-court="${escapeHtml(court)}" data-time="${escapeHtml(assignmentTime(time))}"><button type="button" class="manual-slot-button"><span class="fw-semibold">Available</span><span class="small text-muted d-block">Drop or select a valid match</span></button></td>`;
      }).join('');
      return `<tr><th class="text-nowrap">${escapeHtml(formatSlotTime(time))}</th>${cells}</tr>`;
    }).join('');
    const truncated = times.size >= 200 ? '<div class="alert alert-warning py-2 mb-0">Only the first 200 time rows are shown. Shorten the scheduling window to inspect it in more detail.</div>' : '';

    return `<details class="card preview-venue mb-4" data-preview-venue="${venue.id}"><summary class="card-header d-flex flex-wrap gap-2"><h5 class="mb-0 flex-grow-1">${escapeHtml(venue.name)}</h5><span class="small text-muted">${venue.courts} courts · ${rows.length} fixtures</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>${venueActions(result, venue)}<div class="court-grid-hint small"><i class="ti ti-arrows-horizontal" aria-hidden="true"></i><span>Scroll sideways to see every court. Court headings and start times remain visible while you scroll.</span></div><div class="court-grid-scroll" tabindex="0" role="region" aria-label="${escapeHtml(venue.name)} court schedule; scroll horizontally and vertically"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Slot starts</th>${courtHeaders}</tr></thead><tbody>${body || '<tr><td colspan="99" class="text-center text-muted py-4">No slots in this scheduling window.</td></tr>'}</tbody></table></div>${truncated}</details>`;
  }

  function render(result) {
    lastScheduleResult = result;
    revision = result.revision;
    replanVenueIds = (result.input.replan_venue_ids || []).map(Number);
    setWorkflowStep(3);
    document.getElementById('preview-summary').classList.remove('d-none');
    document.getElementById('preview-summary').innerHTML = card(result.matches.length + (result.existing_matches || []).length, 'Court bookings', 'success') + card(result.automatic_byes, 'Automatic byes') + card(result.venues.length, 'Venues') + card(result.unscheduled.length, 'Unscheduled', result.unscheduled.length ? 'danger' : 'success');
    let warnings = (result.warnings || []).map(message => `<div class="alert alert-warning py-2">${escapeHtml(message)}</div>`).join('');
    if (result.unscheduled.length) warnings += `<div class="alert alert-danger"><strong>Resolve before applying the combined schedule:</strong><div class="small mb-2">A venue can only be applied when none of its selected matches remain unresolved.</div><ul class="mb-0">${result.unscheduled.map(row => `<li>${escapeHtml(row.draw_name)} Wave ${row.wave} · R${row.round} · Match ${row.match}: ${escapeHtml(row.reason)}</li>`).join('')}</ul></div>`;
    document.getElementById('preview-warnings').innerHTML = warnings;
    document.getElementById('preview-view-controls').classList.remove('d-none');
    document.getElementById('preview-view-controls').classList.add('d-flex');
    document.getElementById('venue-timelines').innerHTML = result.venues.map(venue => {
      const rows = venueRows(result, venue.id);
      return `<details class="card preview-venue mb-4" data-preview-venue="${venue.id}"><summary class="card-header d-flex flex-wrap gap-2"><h5 class="mb-0 flex-grow-1">${escapeHtml(venue.name)}</h5><span class="small text-muted">${venue.courts} courts · ${rows.length} fixtures</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>${venueActions(result, venue)}<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>Court</th><th>Age group / draw</th><th>Round</th><th>Match</th><th>Players / qualification path</th><th>State</th><th>Action</th></tr></thead><tbody>${rows.map(row => `<tr><td class="text-nowrap fw-semibold">${escapeHtml(row.scheduled_at.slice(0,16))}</td><td>${escapeHtml(row.court)}</td><td>${escapeHtml(row.draw_name)}</td><td>${row.wave ? `Wave ${row.wave} · R${row.round}` : `R${row.round}`}</td><td class="text-nowrap fw-semibold">Match ${escapeHtml(row.match || '—')}</td><td>${escapeHtml((row.participants || []).join(' / ') || 'Participants determined by draw')}</td><td>${row.fixed ? '<span class="badge bg-label-success">Saved</span>' : '<span class="badge bg-label-primary">Suggested · not saved</span>'}</td><td>${row.fixed && unapplyUrl ? `<button type="button" class="btn btn-sm btn-outline-danger" data-unapply-fixture="${row.fixture_id}" data-match-label="${escapeHtml(row.draw_name)} Match ${escapeHtml(row.match || '—')}">Remove</button>` : '<span class="text-muted">—</span>'}</td></tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">No fixtures allocated.</td></tr>'}</tbody></table></div></details>`;
    }).join('');
    document.getElementById('venue-slot-grids').innerHTML = result.venues.map(venue => slotGrid(result, venue)).join('');
    const appliedVenueIds = [...new Set([
      ...(result.existing_matches || []).map(match => Number(match.venue_id)),
      ...(result.input.replan_venue_ids || []).map(Number),
    ])];
    const replanAll = document.getElementById('replan-all-applied');
    const keepAll = document.getElementById('keep-all-applied');
    replanAll.dataset.venueIds = JSON.stringify(appliedVenueIds);
    replanAll.classList.toggle('d-none', !appliedVenueIds.length || appliedVenueIds.every(id => replanVenueIds.includes(id)));
    keepAll.classList.toggle('d-none', replanVenueIds.length === 0);
    document.getElementById('apply-preview').disabled = result.unscheduled.length > 0 || result.matches.length === 0;
    setStatus(document.getElementById('schedule-status'), result.unscheduled.length
      ? 'Preview needs attention. Resolve every unscheduled match before applying.'
      : 'Preview ready. Review every venue before applying.', result.unscheduled.length ? 'danger' : 'success');
    if (manualMode) {
      document.querySelector('[data-preview-view="grid"]')?.click();
      document.querySelector('#venue-slot-grids [data-preview-venue]')?.setAttribute('open', 'open');
    }
  }

  const placeMatchManually = async (fixtureId, slot) => {
    if (!fixtureId || !slot) return;
    const status = document.getElementById('schedule-status');
    setStatus(status, 'Checking and saving the manual match assignment…');
    document.getElementById('venue-slot-grids').classList.add('pe-none');
    try {
      const saved = await post(manualAssignmentUrl, {
        fixture_id:Number(fixtureId), scheduled_at:slot.dataset.time,
        venue_id:Number(slot.dataset.venue), court:slot.dataset.court,
        duration:Number(document.getElementById('schedule-duration').value),
        court_gap:Number(document.getElementById('schedule-gap').value),
        player_rest:Number(document.getElementById('schedule-rest').value),
      });
      selectedManualFixture = null;
      replanVenueIds = replanVenueIds.filter(id => id !== Number(slot.dataset.venue));
      payload = buildPayload();
      render(await post(previewUrl, payload));
      setStatus(status, saved.message + ' It is saved; the remaining unsaved suggestions were adapted around it.', 'success');
    } catch (error) {
      setStatus(status, error.message, 'danger');
    } finally {
      document.getElementById('venue-slot-grids').classList.remove('pe-none');
    }
  };

  const slotGrids = document.getElementById('venue-slot-grids');
  const matchPickerModal = document.getElementById('manualMatchPickerModal');
  const matchPickerList = document.getElementById('manual-match-picker-list');
  const matchPickerSearch = document.getElementById('manual-match-picker-search');
  const matchPickerEmpty = document.getElementById('manual-match-picker-empty');
  const pickerCandidates = (result = lastScheduleResult) => {
    const rows = [
      ...(result?.matches || []),
      ...(result?.unscheduled || []),
    ];
    return [...new Map(rows.map(match => [Number(match.fixture_id), match])).values()]
      .sort((left, right) => String(left.draw_name).localeCompare(String(right.draw_name), undefined, {numeric:true})
        || Number(left.wave || left.round) - Number(right.wave || right.round)
        || Number(left.match || left.fixture_id) - Number(right.match || right.fixture_id));
  };
  const filterMatchPicker = () => {
    const query = matchPickerSearch.value.trim().toLowerCase();
    let visible = 0;
    matchPickerList.querySelectorAll('[data-picker-fixture]').forEach(option => {
      const show = !query || option.dataset.search.includes(query);
      option.classList.toggle('d-none', !show);
      if (show) visible += 1;
    });
    matchPickerEmpty.classList.toggle('d-none', visible > 0);
  };
  const renderPickerCandidates = matches => {
    matchPickerList.innerHTML = matches.map(match => {
      const players = (match.participants || []).join(' / ') || 'Participants determined by feeder path';
      const current = match.scheduled_at ? `Suggested · not saved: ${match.scheduled_at.slice(0, 16)} · Court ${match.court}` : 'Unscheduled';
      const searchable = `${match.draw_name} match ${match.match || ''} ${players}`.toLowerCase();
      const sequence = match.wave ? `Wave ${match.wave} · Round ${match.round}` : `Round ${match.round}`;
      return `<button type="button" class="list-group-item list-group-item-action manual-match-picker-option" data-picker-fixture="${match.fixture_id}" data-search="${escapeHtml(searchable)}"><span class="fw-semibold d-block">${escapeHtml(match.draw_name)} · Match ${escapeHtml(match.match || '—')}</span><span class="d-block">${escapeHtml(players)}</span><span class="match-picker-meta d-block">${sequence} · ${escapeHtml(current)}</span></button>`;
    }).join('');
  };
  const openMatchPicker = async slot => {
    pendingManualSlot = slot;
    const venue = lastScheduleResult?.venues?.find(item => Number(item.id) === Number(slot.dataset.venue));
    document.getElementById('manual-match-picker-slot').textContent = `${venue?.name || 'Venue'} · Court ${slot.dataset.court} · ${formatSlotTime(asDate(slot.dataset.time))}`;
    matchPickerSearch.value = '';
    matchPickerList.innerHTML = '<div class="list-group-item text-muted">Checking match order, court allocation and player rest…</div>';
    matchPickerEmpty.textContent = 'No match can use this slot after checking match order, court allocation and player rest.';
    matchPickerEmpty.classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(matchPickerModal).show();
    matchPickerModal.addEventListener('shown.bs.modal', () => matchPickerSearch.focus(), {once:true});
    const candidates = pickerCandidates().filter(match => canUseCourt(match, slot.dataset.venue, slot.dataset.court));
    try {
      const options = await post(manualOptionsUrl, {
        fixture_ids:candidates.map(match => Number(match.fixture_id)), scheduled_at:slot.dataset.time,
        venue_id:Number(slot.dataset.venue), court:slot.dataset.court,
        duration:Number(document.getElementById('schedule-duration').value),
        court_gap:Number(document.getElementById('schedule-gap').value),
        player_rest:Number(document.getElementById('schedule-rest').value),
      });
      const eligible = new Set((options.eligible_fixture_ids || []).map(Number));
      renderPickerCandidates(candidates.filter(match => eligible.has(Number(match.fixture_id))));
      filterMatchPicker();
    } catch (error) {
      matchPickerList.innerHTML = '';
      matchPickerEmpty.textContent = error.message;
      matchPickerEmpty.classList.remove('d-none');
    }
  };
  matchPickerSearch.addEventListener('input', filterMatchPicker);
  matchPickerList.addEventListener('click', event => {
    const option = event.target.closest('[data-picker-fixture]');
    if (!option || !pendingManualSlot) return;
    const slot = pendingManualSlot;
    pendingManualSlot = null;
    bootstrap.Modal.getOrCreateInstance(matchPickerModal).hide();
    placeMatchManually(Number(option.dataset.pickerFixture), slot);
  });
  slotGrids.addEventListener('dragstart', event => {
    const match = event.target.closest('[data-manual-fixture]');
    if (!match) return;
    selectedManualFixture = Number(match.dataset.manualFixture);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(selectedManualFixture));
  });
  slotGrids.addEventListener('dragover', event => {
    const scrollArea = event.target.closest('.court-grid-scroll');
    if (scrollArea) {
      const bounds = scrollArea.getBoundingClientRect();
      const edge = 64;
      if (event.clientX < bounds.left + edge) scrollArea.scrollLeft -= 24;
      else if (event.clientX > bounds.right - edge) scrollArea.scrollLeft += 24;
      if (event.clientY < bounds.top + edge) scrollArea.scrollTop -= 24;
      else if (event.clientY > bounds.bottom - edge) scrollArea.scrollTop += 24;
    }
    const slot = event.target.closest('[data-manual-slot]');
    if (!slot) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    slot.classList.add('is-drag-over');
  });
  slotGrids.addEventListener('dragleave', event => {
    const slot = event.target.closest('[data-manual-slot]');
    if (slot && !slot.contains(event.relatedTarget)) slot.classList.remove('is-drag-over');
  });
  slotGrids.addEventListener('drop', event => {
    const slot = event.target.closest('[data-manual-slot]');
    if (!slot) return;
    event.preventDefault();
    slot.classList.remove('is-drag-over');
    placeMatchManually(Number(event.dataTransfer.getData('text/plain') || selectedManualFixture), slot);
  });
  slotGrids.addEventListener('dragend', () => {
    slotGrids.querySelectorAll('.is-drag-over').forEach(slot => slot.classList.remove('is-drag-over'));
  });
  slotGrids.addEventListener('click', event => {
    const match = event.target.closest('[data-manual-fixture]');
    if (match) {
      selectedManualFixture = Number(match.dataset.manualFixture);
      slotGrids.querySelectorAll('[data-manual-fixture]').forEach(option => {
        const selected = option === match;
        option.classList.toggle('is-selected', selected);
        option.setAttribute('aria-pressed', String(selected));
      });
      setStatus(document.getElementById('schedule-status'), 'Match selected. Choose an Available slot.', 'success');
      return;
    }
    const slot = event.target.closest('[data-manual-slot]');
    if (slot && selectedManualFixture) placeMatchManually(selectedManualFixture, slot);
    else if (slot) openMatchPicker(slot);
  });

  const refreshVenuePreview = async (venueId, replanning) => {
    replanVenueIds = replanning
      ? [...new Set([...replanVenueIds, Number(venueId)])]
      : replanVenueIds.filter(id => id !== Number(venueId));
    payload = {...buildPayload(), replan_venue_ids:replanVenueIds};
    revision = null;
    setStatus(document.getElementById('schedule-status'), replanning ? 'Replanning this venue while keeping all other applied venues fixed…' : 'Restoring the current applied venue schedule…');
    const result = await post(previewUrl, payload);
    render(result);
    document.querySelectorAll(`[data-preview-venue="${venueId}"]`).forEach(panel => { panel.open = true; });
  };

  const handleVenueAction = async event => {
    const applyButton = event.target.closest('[data-apply-venue]');
    const replanButton = event.target.closest('[data-replan-venue]');
    const keepButton = event.target.closest('[data-keep-venue]');
    const unapplyButton = event.target.closest('[data-unapply-venue]');
    if (!applyButton && !replanButton && !keepButton && !unapplyButton) return;
    const button = applyButton || replanButton || keepButton || unapplyButton;
    const venueId = Number(button.dataset.applyVenue || button.dataset.replanVenue || button.dataset.keepVenue || button.dataset.unapplyVenue);
    const matching = document.querySelectorAll(`[data-apply-venue="${venueId}"], [data-replan-venue="${venueId}"], [data-keep-venue="${venueId}"], [data-unapply-venue="${venueId}"]`);
    matching.forEach(control => { control.disabled = true; });
    if (unapplyButton) {
      if (!confirm(`Unapply every unplayed scheduled match at ${unapplyButton.dataset.venueName} for this event? Saved times and courts will be removed, but fixtures and draw structure will remain.`)) {
        matching.forEach(control => { control.disabled = false; });
        return;
      }
      const originalButtonHtml = unapplyButton.innerHTML;
      unapplyButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Unapplying…';
      try {
        const result = await post(unapplyUrl, {venue_id:venueId});
        window.AppFeedback?.afterReload(result.message, 'success');
        setStatus(document.getElementById('schedule-status'), result.message + ' Refreshing…', 'success');
        window.location.reload();
      } catch (error) {
        setStatus(document.getElementById('schedule-status'), error.message, 'danger');
        unapplyButton.innerHTML = originalButtonHtml;
        matching.forEach(control => { control.disabled = false; });
      }
      return;
    }
    if (applyButton) {
      if (!payload || !revision || !confirm(`Apply only ${applyButton.dataset.venueName}? Its fixtures will stay fixed in future planning previews until you explicitly change this venue.`)) {
        matching.forEach(control => { control.disabled = false; });
        return;
      }
      try {
        const applied = await post(applyUrl, {...payload, revision, apply_venue_ids:[venueId]});
        replanVenueIds = replanVenueIds.filter(id => id !== venueId);
        payload = {...buildPayload(), replan_venue_ids:replanVenueIds};
        render(await post(previewUrl, payload));
        setStatus(document.getElementById('schedule-status'), `Applied ${applied.count} fixtures at ${applyButton.dataset.venueName}. They are now fixed in planning.`, 'success');
      } catch (error) {
        setStatus(document.getElementById('schedule-status'), error.message, 'danger');
        matching.forEach(control => { control.disabled = false; });
      }
      return;
    }
    try {
      await refreshVenuePreview(venueId, Boolean(replanButton));
    } catch (error) {
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
      matching.forEach(control => { control.disabled = false; });
    }
  };
  document.getElementById('venue-timelines').addEventListener('click', handleVenueAction);
  document.getElementById('venue-slot-grids').addEventListener('click', handleVenueAction);
  const refreshAllAppliedVenues = async replanning => {
    const button = document.getElementById(replanning ? 'replan-all-applied' : 'keep-all-applied');
    if (replanning && !confirm('Build replacement times for every applied venue? The saved schedule stays unchanged until you review and apply the new preview.')) return;
    const originalButtonHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Building preview…';
    if (replanning) {
      replanVenueIds = JSON.parse(document.getElementById('replan-all-applied').dataset.venueIds || '[]').map(Number);
    } else {
      replanVenueIds = [];
    }
    payload = {...buildPayload(), replan_venue_ids:replanVenueIds};
    revision = null;
    setStatus(document.getElementById('schedule-status'), replanning ? 'Replanning every applied venue…' : 'Restoring every current applied venue schedule…');
    startScheduleActivity(previewActivityStages, 'The saved schedule remains in place until you explicitly apply this replacement preview.');
    let completed = false;
    try {
      render(await post(previewUrl, payload));
      completed = true;
      finishScheduleActivity(replanning ? 'Replacement preview ready.' : 'Current applied schedules restored.');
    } catch (error) {
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
    } finally {
      if (!completed) stopScheduleActivity();
      button.innerHTML = originalButtonHtml;
      button.disabled = false;
    }
  };
  document.getElementById('replan-all-applied').addEventListener('click', () => refreshAllAppliedVenues(true));
  document.getElementById('keep-all-applied').addEventListener('click', () => refreshAllAppliedVenues(false));
  document.querySelectorAll('[data-unapply-draw]').forEach(button => button.addEventListener('click', async event => {
    const control = event.currentTarget;
    if (!confirm(`Unapply every unplayed scheduled match in ${control.dataset.drawName}? Saved times and courts will be removed, but fixtures and draw structure will remain.`)) return;
    const originalButtonHtml = control.innerHTML;
    control.disabled = true;
    control.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Unapplying…';
    try {
      const result = await post(unapplyUrl, {draw_id:Number(control.dataset.unapplyDraw)});
      window.AppFeedback?.afterReload(result.message, 'success');
      setStatus(document.getElementById('schedule-status'), result.message + ' Refreshing…', 'success');
      window.location.reload();
    } catch (error) {
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
      control.innerHTML = originalButtonHtml;
      control.disabled = false;
    }
  }));

  const handleFixtureRemoval = async event => {
    const control = event.target.closest('[data-unapply-fixture]');
    if (!control || !confirm(`Remove only ${control.dataset.matchLabel}? Its saved time, venue and court will be cleared.`)) return;
    event.preventDefault();
    event.stopPropagation();
    control.disabled = true;
    try {
      const result = await post(unapplyUrl, {fixture_id:Number(control.dataset.unapplyFixture)});
      payload = buildPayload();
      render(await post(previewUrl, payload));
      setStatus(document.getElementById('schedule-status'), result.message + ' The saved booking is removed and the unsaved suggestions were refreshed.', 'success');
    } catch (error) {
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
      control.disabled = false;
    }
  };
  document.getElementById('venue-timelines').addEventListener('click', handleFixtureRemoval);
  document.getElementById('venue-slot-grids').addEventListener('click', handleFixtureRemoval);

  const scheduleDisplay = document.getElementById('schedule-display');
  const fullPageButton = document.getElementById('toggle-schedule-full-page');
  const setFullPage = enabled => {
    scheduleDisplay.classList.toggle('is-full-page', enabled);
    document.body.classList.toggle('schedule-full-page-active', enabled);
    fullPageButton.setAttribute('aria-pressed', String(enabled));
    fullPageButton.querySelector('i').className = `ti ${enabled ? 'ti-minimize' : 'ti-maximize'} me-1`;
    fullPageButton.querySelector('span').textContent = enabled ? 'Exit full page' : 'Full page';
    if (enabled) scheduleDisplay.querySelector('.court-grid-scroll')?.focus({preventScroll:true});
  };
  fullPageButton.addEventListener('click', () => setFullPage(!scheduleDisplay.classList.contains('is-full-page')));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && scheduleDisplay.classList.contains('is-full-page') && !document.querySelector('.modal.show')) setFullPage(false);
  });

  document.querySelectorAll('[data-preview-view]').forEach(button => button.addEventListener('click', () => {
    const grid = button.dataset.previewView === 'grid';
    document.getElementById('venue-timelines').classList.toggle('d-none', grid);
    document.getElementById('venue-slot-grids').classList.toggle('d-none', !grid);
    document.querySelectorAll('[data-preview-view]').forEach(option => {
      const selected = option === button;
      option.classList.toggle('btn-primary', selected);
      option.classList.toggle('active', selected);
      option.classList.toggle('btn-outline-primary', !selected);
      option.setAttribute('aria-pressed', String(selected));
    });
  }));

  document.getElementById('generate-preview').addEventListener('click', async event => {
    if (allocationsDirty) {
      setStatus(document.getElementById('schedule-status'), 'Save the court allocations before generating a preview.', 'danger');
      document.getElementById('save-allocations')?.focus();
      return;
    }
    const button = event.currentTarget; payload = buildPayload(); revision = null; button.disabled = true;
    const originalButtonHtml = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Building preview…';
    document.getElementById('apply-preview').disabled = true;
    setStatus(document.getElementById('schedule-status'), 'Building the combined preview…');
    startScheduleActivity(previewActivityStages, 'Progress is estimated while the server builds and validates the complete schedule.');
    let completed = false;
    try { render(await post(previewUrl, payload)); completed = true; finishScheduleActivity('Combined preview ready.'); }
    catch (error) { setStatus(document.getElementById('schedule-status'), error.message, 'danger'); }
    finally { if (!completed) stopScheduleActivity(); button.innerHTML = originalButtonHtml; button.disabled = false; }
  });
  document.getElementById('save-allocations')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const venues = @json($venues->map(fn($venue) => ['id' => $venue['id'], 'courts' => $venue['courts']])->values());
    const assignments = drawIds.map(drawId => {
      const venueIds = [...document.querySelectorAll(`.assignment-choice[data-draw="${drawId}"]:checked`)].map(input => Number(input.value));
      const courtAllocations = venueIds.map(venueId => ({venue_id:venueId, court_labels:[...document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venueId}"]:checked`)].map(input => input.value)}));
      return {draw_id:Number(drawId), venue_ids:venueIds, court_allocations:courtAllocations};
    });
    button.disabled = true; setStatus(document.getElementById('allocation-status'), 'Saving…');
    try { const result = await post(assignmentUrl, {venues, assignments}); allocationsDirty = false; setStatus(document.getElementById('allocation-status'), result.message + ' Refreshing…', 'success'); window.location.reload(); }
    catch (error) { setStatus(document.getElementById('allocation-status'), error.message, 'danger'); button.disabled = false; }
  });
  document.getElementById('add-venue')?.addEventListener('click', async event => {
    const button = event.currentTarget; button.disabled = true;
    try { const result = await post(venueUrl, {venue_id:Number(document.getElementById('new-venue-id').value) || null, name:document.getElementById('new-venue-name').value || null, courts:Number(document.getElementById('new-venue-courts').value), ball_type:document.getElementById('new-venue-ball').value}); setStatus(document.getElementById('venue-add-status'), result.message + ' Refreshing…', 'success'); window.location.reload(); }
    catch (error) { setStatus(document.getElementById('venue-add-status'), error.message, 'danger'); button.disabled = false; }
  });
  document.querySelectorAll('.add-court').forEach(button => button.addEventListener('click', async event => {
    const venueId = Number(event.currentTarget.dataset.venue); event.currentTarget.disabled = true;
    const label = document.querySelector(`.add-court-label[data-venue="${venueId}"]`).value;
    const ballType = document.querySelector(`.add-court-ball[data-venue="${venueId}"]`).value;
    if (!label.trim()) { setStatus(document.getElementById('allocation-status'), 'Enter a court label first.', 'danger'); event.currentTarget.disabled = false; return; }
    try { await post(courtUrl, {venue_id:venueId, label, ball_type:ballType}); window.location.reload(); }
    catch (error) { setStatus(document.getElementById('allocation-status'), error.message, 'danger'); event.currentTarget.disabled = false; }
  }));
  document.querySelectorAll('.update-court-setup').forEach(button => button.addEventListener('click', async event => {
    const setup = event.currentTarget.closest('.venue-court-setup'); const status = setup.querySelector('.setup-status'); event.currentTarget.disabled = true; status.textContent = 'Updating…';
    const ballType = setup.querySelector('.setup-court-ball').value;
    if (ballType === 'mixed') { setStatus(status, 'Choose one court type before updating all courts.', 'danger'); event.currentTarget.disabled = false; return; }
    if (event.currentTarget.dataset.hasCustom === '1' && !confirm('Updating all courts will replace specially named courts with numbered courts. Continue?')) { event.currentTarget.disabled = false; return; }
    try { const result = await post(setup.dataset.url, {courts:Number(setup.querySelector('.setup-court-count').value), ball_type:ballType}); setStatus(status, result.message + ' Refreshing…', 'success'); window.location.reload(); }
    catch (error) { setStatus(status, error.message, 'danger'); event.currentTarget.disabled = false; }
  }));
  document.querySelectorAll('.update-court-type').forEach(button => button.addEventListener('click', async event => {
    const venueId = Number(event.currentTarget.dataset.venue); const label = event.currentTarget.dataset.label;
    const type = document.querySelector(`.edit-court-ball[data-venue="${venueId}"][data-label="${CSS.escape(label)}"]`).value;
    event.currentTarget.disabled = true;
    try { await post(courtUrl, {venue_id:venueId, label, ball_type:type}); window.location.reload(); }
    catch (error) { setStatus(document.getElementById('allocation-status'), error.message, 'danger'); event.currentTarget.disabled = false; }
  }));
  document.getElementById('apply-preview').addEventListener('click', async event => {
    if (!payload || !revision || !confirm('Apply this combined schedule to every selected draw?')) return;
    const button = event.currentTarget;
    const originalButtonHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Applying schedule…';
    setStatus(document.getElementById('schedule-status'), 'Applying and revalidating…');
    startScheduleActivity(applyActivityStages, 'Progress is estimated while the server applies the fixtures and rebuilds the final schedule.');
    let completed = false;
    try {
      const applied = await post(applyUrl, {...payload, revision});
      replanVenueIds = [];
      payload = {...buildPayload(), replan_venue_ids:[]};
      setScheduleActivityPhase(revalidationActivityStages);
      render(await post(previewUrl, payload));
      setStatus(document.getElementById('schedule-status'), `Applied ${applied.count} fixtures. Opening tournament draws…`, 'success');
      completed = true;
      finishScheduleActivity('Schedule applied. Opening tournament draws…');
      window.setTimeout(() => window.location.assign(drawsUrl), 1000);
    }
    catch (error) { setStatus(document.getElementById('schedule-status'), error.message, 'danger'); button.disabled = false; }
    finally { if (!completed) stopScheduleActivity(); button.innerHTML = originalButtonHtml; }
  });
  if (manualMode) {
    document.getElementById('court-allocation-step').open = false;
    document.getElementById('schedule-rules-step').open = true;
    setWorkflowStep(2);
    window.requestAnimationFrame(() => document.getElementById('generate-preview').click());
  }
})();
</script>
@endsection
