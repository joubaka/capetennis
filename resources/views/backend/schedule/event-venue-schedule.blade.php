@extends('layouts.backend')

@section('title', 'Schedule – '.$event->name)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
@endsection

@section('page-style')
<style>
  .programme-day-lane { min-height:6rem; padding:.75rem; background:var(--schedule-soft); border:1px dashed var(--schedule-border); border-radius:.5rem; }
  .programme-stage { background:var(--bs-body-bg); border:1px solid var(--schedule-border); border-radius:.5rem; padding:.65rem; margin-bottom:.6rem; }
  .programme-stage-handle { cursor:grab; touch-action:none; min-width:2.5rem; min-height:2.5rem; }
  .programme-stage.sortable-ghost { opacity:.35; }
  .programme-stage-controls { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; margin-top:.5rem; }
  .programme-stage-controls .form-select { width:auto; min-width:6rem; }
  .programme-stage-summary { border-top:1px solid var(--schedule-border); padding-top:.4rem; margin-top:.5rem; font-size:.75rem; overflow-wrap:anywhere; }
  .programme-stage-summary ul { padding-left:1rem; margin:.2rem 0 .35rem; }
  #programme-rounds .programme-day { min-width:7rem; }
  #programme-rounds .programme-sequence { min-width:6rem; }
  @media (max-width:576px) {
    #programme-rounds thead { display:none; }
    #programme-rounds tbody { display:block; }
    #programme-rounds tr { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; border:1px solid var(--schedule-border); border-radius:.5rem; margin-bottom:.75rem; padding:.5rem; }
    #programme-rounds td { border:0; min-width:0; padding:.25rem; }
    #programme-rounds td:first-child { grid-column:1 / -1; font-weight:600; }
    #programme-rounds td:nth-child(2)::before { content:'Day'; display:block; margin-bottom:.25rem; }
    #programme-rounds td:nth-child(3)::before { content:'Order within day'; display:block; margin-bottom:.25rem; }
    #programme-rounds select, #programme-rounds input { width:100%; }
  }
  #rank-preferences .rank-band-row { min-width:0; border:1px solid var(--schedule-border); border-radius:.75rem; padding:1rem; background:#fff; }
  #rank-preferences .rank-band-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.8rem; }
  #rank-preferences .rank-band-fields { display:grid; grid-template-columns:minmax(9rem,.65fr) minmax(0,1.5fr) minmax(0,1fr); gap:1rem; }
  #rank-preferences .rank-band-fields > div { min-width:0; }
  #rank-preferences .rank-range { display:flex; align-items:center; gap:.5rem; }
  #rank-preferences .rank-range input { min-width:0; }
  #rank-preferences .select2-container { max-width:100%; }
  #rank-preferences .select2-selection--multiple { min-height:2.5rem; }
  #rank-preferences .select2-selection__choice { max-width:100%; white-space:normal; overflow-wrap:anywhere; }
  #rank-preferences .select2-selection__rendered { min-width:0; }
  #rank-preferences .select2-selection--multiple .select2-selection__rendered { max-height:6rem; overflow-y:auto; }
  #rank-preferences .rank-scope-chips { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; max-height:8rem; overflow-y:auto; }
  #rank-preferences .rank-scope-chips span { padding:.25rem .5rem; border:1px solid var(--schedule-border); border-radius:.4rem; font-weight:400; }
  #rank-preferences .rank-band-scope-details summary { cursor:pointer; }
  #rank-preferences .select2-dropdown { z-index:1100; }
  @media (max-width:991px) { #rank-preferences .rank-band-fields { grid-template-columns:repeat(2,minmax(0,1fr)); } #rank-preferences .rank-draw-field { grid-column:1 / -1; grid-row:2; } }
  @media (max-width:575px) { #rank-preferences .rank-band-fields { grid-template-columns:minmax(0,1fr); gap:.8rem; } #rank-preferences .rank-draw-field { grid-column:auto; grid-row:auto; } }
  .schedule-workspace { --schedule-border:#e6e8ee; --schedule-muted:#667085; --schedule-soft:#f7f8fa; }
  .schedule-workspace .workspace-header { max-width: 52rem; }
  .schedule-workspace .workspace-actions .btn { white-space: nowrap; }
  .schedule-workspace .workflow-rail { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); border:1px solid var(--schedule-border); border-radius:.85rem; background:#fff; overflow:hidden; }
  .schedule-workspace .workflow-step { display:flex; align-items:center; gap:.75rem; min-width:0; padding:.9rem 1rem; border:0; border-radius:0; color:var(--schedule-muted); background:#fff; text-align:left; cursor:pointer; }
  .schedule-workspace .workflow-step + .workflow-step { border-left:1px solid var(--schedule-border); }
  .schedule-workspace .workflow-step:hover, .schedule-workspace .workflow-step:focus-visible { color:var(--bs-primary); background:rgba(var(--bs-primary-rgb), .035); outline:0; }
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
  .schedule-workspace .draw-panel { --draw-accent:#2563eb; --draw-soft:#eff6ff; --draw-open:#dbeafe; }
  .schedule-workspace .draw-panel.draw-accent-1 { --draw-accent:#c2410c; --draw-soft:#fff7ed; --draw-open:#ffedd5; }
  .schedule-workspace .draw-panel.draw-accent-2 { --draw-accent:#7c3aed; --draw-soft:#f5f3ff; --draw-open:#ede9fe; }
  .schedule-workspace .draw-panel.draw-accent-3 { --draw-accent:#047857; --draw-soft:#ecfdf5; --draw-open:#d1fae5; }
  .schedule-workspace .draw-panel.draw-accent-4 { --draw-accent:#be123c; --draw-soft:#fff1f2; --draw-open:#ffe4e6; }
  .schedule-workspace .draw-panel.draw-accent-5 { --draw-accent:#0e7490; --draw-soft:#ecfeff; --draw-open:#cffafe; }
  .schedule-workspace .draw-panel > summary { list-style:none; display:flex; align-items:center; gap:.75rem; padding:.9rem 1rem; border-left:4px solid var(--draw-accent); cursor:pointer; background:var(--draw-soft); }
  .schedule-workspace .draw-panel[open] > summary { background:var(--draw-open); }
  .schedule-workspace .draw-panel .draw-name { color:var(--draw-accent); }
  .schedule-workspace .draw-panel .draw-preview .badge { color:var(--draw-accent) !important; border:1px solid var(--draw-accent); background:rgba(255,255,255,.72) !important; }
  .schedule-workspace .venue-age-group-summary { display:flex; flex:1; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }
  .schedule-workspace .age-group-schedule-chip { padding:.28rem .45rem; border-radius:.4rem; background:#eef2ff; color:#3730a3; font-size:.72rem; font-weight:600; white-space:nowrap; }
  .schedule-workspace .age-group-schedule-chip:nth-child(6n+2) { background:#fff7ed; color:#9a3412; }
  .schedule-workspace .age-group-schedule-chip:nth-child(6n+3) { background:#f5f3ff; color:#6d28d9; }
  .schedule-workspace .age-group-schedule-chip:nth-child(6n+4) { background:#ecfdf5; color:#047857; }
  .schedule-workspace .age-group-schedule-chip:nth-child(6n+5) { background:#fff1f2; color:#be123c; }
  .schedule-workspace .age-group-schedule-chip:nth-child(6n+6) { background:#ecfeff; color:#0e7490; }
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
  .schedule-workspace .venue-management { border:1px solid var(--schedule-border); border-radius:.75rem; background:linear-gradient(135deg, #f8fafc, #f1f5f9); }
  .schedule-workspace #court-allocation-step > .section-body { display:flex; flex-direction:column; }
  .schedule-workspace #court-allocation-step .venue-management { order:-1; }
  .schedule-workspace .venue-management-summary { display:flex; align-items:center; gap:.85rem; padding:.9rem 1rem; }
  .schedule-workspace .venue-management-icon { display:grid; place-items:center; flex:0 0 2.25rem; width:2.25rem; height:2.25rem; border-radius:.65rem; color:var(--bs-primary); background:rgba(var(--bs-primary-rgb), .1); }
  .schedule-workspace .assigned-venue-list { display:flex; flex:1; flex-wrap:wrap; justify-content:flex-end; gap:.4rem; }
  .schedule-workspace .assigned-venue-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.4rem .55rem; border:1px solid rgba(var(--bs-primary-rgb), .16); border-radius:.55rem; background:#fff; color:var(--bs-body-color); font-size:.76rem; font-weight:600; }
  .schedule-workspace .venue-management-body { padding:1rem; }
  .schedule-workspace .venue-management-modal .modal-body { background:var(--schedule-soft); }
  .schedule-workspace .venue-add-mode { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.5rem; padding:.3rem; border:1px solid var(--schedule-border); border-radius:.7rem; background:var(--schedule-soft); }
  .schedule-workspace .venue-add-mode .btn { display:flex; align-items:center; justify-content:center; gap:.4rem; border:0; border-radius:.5rem; color:var(--schedule-muted); background:transparent; box-shadow:none; }
  .schedule-workspace .venue-add-mode .btn.active { color:var(--bs-primary); background:#fff; box-shadow:0 .15rem .5rem rgba(31,42,68,.09); }
  .schedule-workspace .venue-add-panel { padding-top:1rem; }
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
  @media (max-width: 575.98px) { .schedule-workspace .court-grid-scroll tr > :first-child { min-width:6rem; width:6rem; max-width:6rem; white-space:normal !important; } }
  .schedule-workspace .court-grid-scroll::-webkit-scrollbar { width:12px; height:12px; }
  .schedule-workspace .court-grid-scroll::-webkit-scrollbar-thumb { border:3px solid transparent; border-radius:999px; background:rgba(var(--bs-secondary-rgb), .45); background-clip:padding-box; }
  .schedule-workspace .court-grid-scroll { scrollbar-color:rgba(var(--bs-secondary-rgb), .55) transparent; scrollbar-width:auto; }
  body.schedule-full-page-active { overflow:hidden; }
  body.schedule-full-page-active #layout-navbar,
  body.schedule-full-page-active #layout-menu,
  body.schedule-full-page-active .content-footer { display:none !important; }
  .schedule-workspace .schedule-display.is-full-page { position:fixed; inset:0; z-index:1035; overflow:auto; padding:1rem; background:var(--bs-body-bg); }
  .schedule-workspace .full-page-stepper { display:none; }
  .schedule-workspace .schedule-display.is-full-page .full-page-stepper { position:sticky; top:-1rem; z-index:7; display:grid; margin:-1rem -1rem 1rem; border-width:0 0 1px; border-radius:0; box-shadow:0 .35rem 1rem rgba(31,42,68,.08); }
  .schedule-workspace .schedule-display.is-full-page #preview-view-controls { position:relative; top:auto; z-index:6; margin-inline:-1rem; padding:1rem; border-bottom:1px solid var(--schedule-border); background:rgba(var(--bs-body-bg-rgb), .97); box-shadow:0 .35rem 1rem rgba(31,42,68,.08); backdrop-filter:blur(8px); }
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
    .schedule-workspace .venue-management-summary { align-items:flex-start; flex-wrap:wrap; }
    .schedule-workspace .assigned-venue-list { flex-basis:100%; justify-content:flex-start; order:3; }
    .schedule-workspace .venue-management-summary .btn { margin-left:auto; }
    .schedule-workspace .venue-add-mode { grid-template-columns:1fr; }
  }
</style>
@endsection

@section('content')
<div class="container-xxl pt-3"><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar',$event) }}">Saved schedule · all days</a></div>
@php
  $unapplyRouteAvailable = \Illuminate\Support\Facades\Route::has('backend.event-venue-schedule.unapply');
@endphp
<div class="container-xxl flex-grow-1 container-p-y schedule-workspace">
  @include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'schedule',
    'eventWorkspaceIcon' => 'ti-calendar-event',
    'eventWorkspaceSubtitle' => 'Event venue schedule',
  ])
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="workspace-header">
      <div class="text-uppercase text-primary fw-semibold small">Event schedule workspace</div>
      <h3 class="mb-1">{{ $event->name }}</h3>
      <p class="text-muted mb-0">Schedule every assigned draw / category in three clear steps: assign courts, set the timing, then review.</p>
      <p class="text-muted small mt-2 mb-0">Choose any combination of draws across all age groups in this event. Team and individual matches share the same court calendar.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 workspace-actions">
      @if($draws->isNotEmpty())
        <a class="btn btn-outline-secondary" href="{{ route('headoffice.drawPack', $event) }}" target="_blank" rel="noopener">
          <i class="ti ti-printer me-1" aria-hidden="true"></i>Print all-match pack
        </a>
      @endif
      <button type="button" class="btn btn-outline-primary" id="open-venue-announcement"
        data-bs-toggle="modal" data-bs-target="#venueAnnouncementModal"
        {{ $announcementDraft['assignments']->isEmpty() ? 'disabled' : '' }}>
        <i class="ti ti-megaphone me-1"></i>Announce assigned courts
      </button>
    </div>
  </div>

  @if(($adaptationNotices ?? collect())->isNotEmpty())
    <div class="alert alert-warning mb-3" role="status" data-schedule-adaptation-notice>
      <strong>Team changes updated the draws.</strong>
      <p class="mb-2">Review the changes below. Generate a combined preview for matches that need new times; valid bookings stay fixed.</p>
      <ul class="mb-0">
        @foreach($adaptationNotices as $notice)
          <li><strong>{{ $notice['draw_name'] }}</strong>:
            @if($notice['pending_count'] > 0)
              {{ $notice['pending_count'] }} {{ \Illuminate\Support\Str::plural('match', $notice['pending_count']) }} to schedule.
            @else
              Draw changes need review.
            @endif
            @if($notice['warnings'])
              <span class="d-block small">{{ implode(' ', $notice['warnings']) }}</span>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif
  <details class="workspace-section mb-3" id="programme-wizard">
    <summary><span class="section-title"><h5>Three-day age-group auto schedule</h5><small class="text-muted">Create one complete preview using the Platinum programme.</small></span></summary>
    <div class="section-body">
      <label class="form-label" for="programme-age">Age group</label>
      <select id="programme-age" class="form-select mb-3"><option value="">Choose age group</option>@foreach($draws->where('is_team', true)->pluck('programme_age')->filter()->unique()->sort() as $age)<option value="{{ $age }}">Under {{ $age }}</option>@endforeach</select>
      <p class="small">Day 1: singles rounds 1–3, reverse singles round 1. Day 2: reverse singles rounds 2–3 and all doubles. Day 3: all mixed doubles. Adjust each round's day and order below. Choose the boys/girls order independently for each day. Sections follow their allocated start order; available courts can serve the next section while earlier matches finish, subject to draw progression and player rest.</p>
      <div class="row g-3 mb-3">@foreach(range(0,2) as $day)<div class="col-md-4"><strong>Day {{ $day + 1 }}</strong><label class="form-label d-block mt-2" for="programme-start-{{ $day }}">Starts</label><input id="programme-start-{{ $day }}" class="form-control programme-time" type="datetime-local" value="{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->addDays($day)->format('Y-m-d').'T08:00' : '' }}"><label class="form-label d-block mt-2" for="programme-end-{{ $day }}">Finishes</label><input id="programme-end-{{ $day }}" class="form-control programme-time" type="datetime-local" value="{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->addDays($day)->format('Y-m-d').'T18:00' : '' }}"><label class="form-label d-block mt-2" for="programme-gender-{{ $day }}">Order within each round</label><select id="programme-gender-{{ $day }}" class="form-select programme-gender-day"><option value="boys_then_girls" @selected($scheduleDraft['gender_waves'] === 'boys_then_girls')>Boys then girls</option><option value="girls_then_boys" @selected($scheduleDraft['gender_waves'] === 'girls_then_boys')>Girls then boys</option><option value="combined" @selected($scheduleDraft['gender_waves'] === 'combined')>Together</option></select><a class="btn btn-sm btn-outline-primary mt-2" data-programme-review-day="{{ $day }}" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $event->start_date ? \Carbon\Carbon::parse($event->start_date)->addDays($day)->toDateString() : 'all']) }}">Review &amp; publish this day</a></div>@endforeach</div>
      <p class="small mb-2">Drag the handle to put rounds in order or move them between days. Boys and girls in the same round move together. Each drop refreshes the preview using AJAX; match times are saved only when you choose Save. Use the Up/Down buttons or day selector with a keyboard.</p>
      <div id="programme-stages" class="row g-3 mb-3" aria-label="Drag rounds into daily playing order"></div>
      <details class="mb-3"><summary>Advanced: individual draw round days and numbered order</summary><div id="programme-rounds" class="table-responsive mt-2"></div></details>
      <div class="row g-3 mb-3">@foreach(range(0,2) as $day)<div class="col-md-4"><strong>Day {{ $day + 1 }} optional break</strong><div class="row g-2 mt-1"><div class="col-6"><label class="form-label small" for="programme-break-start-{{ $day }}">Break starts</label><input id="programme-break-start-{{ $day }}" class="form-control programme-time" type="time"></div><div class="col-6"><label class="form-label small" for="programme-break-end-{{ $day }}">Break ends</label><input id="programme-break-end-{{ $day }}" class="form-control programme-time" type="time"></div></div></div>@endforeach</div>
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><label class="form-label" for="programme-duration">Match minutes</label><input id="programme-duration" class="form-control" type="number" min="15" max="480" value="{{ $scheduleDraft['duration'] }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="programme-rest">Player rest minutes</label><input id="programme-rest" class="form-control" type="number" min="0" max="480" value="{{ $scheduleDraft['player_rest'] }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="programme-gap">Court gap minutes</label><input id="programme-gap" class="form-control" type="number" min="0" max="120" value="{{ $scheduleDraft['court_gap'] }}"></div>
      </div>
      <label class="form-label" for="programme-reuse-source">Reuse courts for disciplines with no venue</label>
      <select id="programme-reuse-source" class="form-select mb-2"><option value="">Choose an assigned draw in this age group</option></select>
      <button id="programme-reuse" type="button" class="btn btn-outline-primary mb-3">Save missing discipline venue assignments</button>
      <p class="small text-muted">This explicitly saves the selected draw's current courts only to disciplines with no venue. Existing discipline assignments stay as they are. Review allocations below to select different courts.</p>
      <label class="form-check mb-3" for="programme-reschedule-existing">
        <input class="form-check-input" type="checkbox" id="programme-reschedule-existing" {{ $scheduleDraft['reschedule_existing'] ? 'checked' : '' }}>
        <span class="form-check-label">Include already scheduled matches in this age group's new preview</span>
        <small class="d-block text-muted">Unchecked keeps saved times fixed. Checking replans this age group; saved times change only when you save the preview.</small>
      </label>
      <label class="form-label" for="programme-gender-wave-release">When the next gender can start</label>
      <select id="programme-gender-wave-release" class="form-select mb-2">
        <option value="whole_wave" @selected($scheduleDraft['gender_wave_release'] === 'whole_wave')>Wait for the whole previous gender wave</option>
        <option value="court_ready" @selected($scheduleDraft['gender_wave_release'] === 'court_ready')>Use free courts while the previous gender finishes</option>
      </select>
      <p class="small text-muted">Use free courts to avoid waiting for every earlier match to finish. Your boys/girls order, round wave interval, court availability and player rest still apply.</p>
      <button id="programme-create" type="button" class="btn btn-primary">Create three-day schedule preview</button>
      <div id="programme-status" class="small mt-2" role="status" aria-live="polite"></div>
    </div>
  </details>
  <div class="workflow-rail mb-3" aria-label="Schedule workflow">
    <button type="button" class="workflow-step is-active" data-workflow-nav="1"><span class="step-number">1</span><span class="workflow-label">Court allocation</span></button>
    <button type="button" class="workflow-step" data-workflow-nav="2" data-audit-ignore="true"><span class="step-number">2</span><span class="workflow-label">Timing rules</span></button>
    <button type="button" class="workflow-step" data-workflow-nav="3" data-audit-ignore="true"><span class="step-number">3</span><span class="workflow-label">Review & apply</span></button>
  </div>

  <details class="workspace-section mb-3" id="court-allocation-step" open>
    @php
      $firstSelectedDrawId = $draws->first(fn($draw) => $draw['selected'] && ! $draw['locked'])['id'] ?? null;
    @endphp
    <summary>
      <span class="step-number">1</span>
      <span class="section-title"><h5>Assign draws / categories to courts</h5><small class="text-muted"><span id="selected-draw-count">{{ $draws->filter(fn($draw) => $draw['selected'] && ! $draw['locked'])->count() }}</span> draws / categories included · open only the one you are editing</small></span>
      <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
    </summary>
    <div class="section-body">
      <div class="draw-list">
          @forelse($draws as $draw)
            <details class="draw-panel draw-accent-{{ $loop->index % 6 }} {{ $draw['locked'] ? 'text-muted' : '' }}" data-draw-panel="{{ $draw['id'] }}" {{ $firstSelectedDrawId === $draw['id'] ? 'open' : '' }}>
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
                @if($draw['locked'])
                  <span class="badge bg-label-secondary">{{ $draw['published'] ? 'Published' : 'Locked' }}</span>
                @endif
                <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
              </summary>
              <div class="draw-panel-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                  <label class="form-check d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input draw-choice mt-0" type="checkbox" value="{{ $draw['id'] }}" {{ $draw['locked'] ? 'disabled' : ($draw['selected'] ? 'checked' : '') }}>
                    <span class="fw-semibold">Include in this schedule</span>
                  </label>
                  <div class="d-flex flex-wrap align-items-end gap-2">
                    @if($unapplyRouteAvailable && $draw['applied_match_count'] > 0 && ! $draw['locked'])
                      <button type="button" class="btn btn-sm btn-outline-danger" data-unapply-draw="{{ $draw['id'] }}" data-draw-name="{{ $draw['name'] }}">
                        <i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Unapply {{ $draw['applied_match_count'] }} scheduled {{ Str::plural('match', $draw['applied_match_count']) }}
                      </button>
                    @endif
                    @can('event.score', $event)
                      <a class="btn btn-sm btn-outline-success" href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw['id'], 'all_venues' => 1]) }}" title="Open this draw / category for scoring even when its matches are not scheduled">
                        <i class="ti ti-scoreboard me-1" aria-hidden="true"></i>Score this draw / category
                      </a>
                    @endcan
                    <label class="small text-muted">Start later (optional)<input class="form-control form-control-sm draw-start mt-1" data-draw="{{ $draw['id'] }}" type="datetime-local" value="{{ $scheduleDraft['draw_starts']->get($draw['id'], '') }}" {{ $draw['locked'] ? 'disabled' : '' }}></label>
                    @if(count($draw['rounds']))
                      @php
                        $chosenRounds = $scheduleDraft['draw_rounds']->get($draw['id'], []);
                      @endphp
                      <details class="small mt-2">
                        <summary class="fw-semibold">Rounds to schedule this day</summary>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                          <label><input type="checkbox" class="form-check-input draw-round-choice" data-draw="{{ $draw['id'] }}" value="all" @checked(!$chosenRounds) @disabled($draw['locked'])> All rounds</label>
                          @foreach($draw['rounds'] as $round)
                            <label><input type="checkbox" class="form-check-input draw-round-choice" data-draw="{{ $draw['id'] }}" value="{{ $round }}" @checked(in_array($round, $chosenRounds, true)) @disabled($draw['locked'])> Round {{ $round }}</label>
                          @endforeach
                        </div>
                        <div class="text-muted mt-1">Choose specific rounds for this day's window. Other rounds keep saved bookings. Earlier qualifying matches must already be scheduled.</div>
                      </details>
                    @endif
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
                      <div class="d-flex flex-wrap align-items-center gap-2">
                        <label class="d-flex align-items-center gap-2 mb-0 flex-grow-1"><input class="form-check-input assignment-choice mt-0" data-draw="{{ $draw['id'] }}" data-venue-name="{{ $venue['name'] }}" type="checkbox" value="{{ $venue['id'] }}" {{ $venueAssigned ? 'checked' : '' }} {{ $draw['locked'] ? 'disabled' : '' }}><span class="fw-semibold">{{ $venue['name'] }}</span></label>
                        <span class="small text-muted" data-court-summary="{{ $draw['id'] }}-{{ $venue['id'] }}">{{ $venueAssigned ? (empty($allocatedLabels) ? 'All '.$venue['courts'] : count($allocatedLabels).' of '.$venue['courts']) : 'Not used' }}</span>
                        <button class="btn btn-sm btn-text-secondary court-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courts-{{ $draw['id'] }}-{{ $venue['id'] }}" aria-expanded="false" aria-controls="courts-{{ $draw['id'] }}-{{ $venue['id'] }}">Choose courts</button>
                        @if($venueAssigned)
                        <button class="btn btn-sm btn-outline-danger remove-draw-venue" type="button" data-draw="{{ $draw['id'] }}" data-venue="{{ $venue['id'] }}" data-url="{{ route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw['id'], $venue['id']]) }}" aria-label="Remove {{ $venue['name'] }} from {{ $draw['name'] }}" {{ $draw['locked'] ? 'disabled' : '' }}>Remove</button>
                        @endif
                      </div>
                      <div class="collapse" id="courts-{{ $draw['id'] }}-{{ $venue['id'] }}"><div class="court-choices">
                        @foreach($orderedCourts as $court)
                          @php $courtChecked = $venueAssigned && (empty($allocatedLabels) || in_array($court['label'], $allocatedLabels)); @endphp
                          <label class="court-choice"><input class="form-check-input court-allocation mt-0" data-draw="{{ $draw['id'] }}" data-venue="{{ $venue['id'] }}" type="checkbox" value="{{ $court['label'] }}" {{ $courtChecked ? 'checked' : '' }} {{ $draw['locked'] ? 'disabled' : '' }}>Court {{ $court['label'] }}@if($court['ball_type']) · {{ ucfirst($court['ball_type']) }}@endif</label>
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

      <div class="venue-management mb-3" id="venue-management">
        <div class="venue-management-summary">
          <span class="venue-management-icon"><i class="ti ti-building-community" aria-hidden="true"></i></span>
          <span><strong>Venues & courts</strong><span class="d-block small text-muted">Assigned to this event</span></span>
          <span class="assigned-venue-list" aria-label="Assigned venues">
            @forelse($venues as $venue)
              <span class="assigned-venue-chip"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $venue['name'] }} <span class="text-muted">· {{ $venue['courts'] }} {{ Str::plural('court', $venue['courts']) }} · {{ ucfirst($venue['common_ball_type'] ?? 'standard') }}</span></span>
            @empty
              <span class="small text-warning">No venues assigned yet</span>
            @endforelse
          </span>
          <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#venue-management-modal"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit venues & courts</button>
        </div>
      </div>

      <div class="modal fade venue-management-modal" id="venue-management-modal" tabindex="-1" aria-labelledby="venue-management-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div><h5 class="modal-title" id="venue-management-title">Edit venues & courts</h5><div class="small text-muted" id="venue-management-counts">{{ $venues->count() }} assigned venues · {{ $venues->sum('courts') }} courts available</div></div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body venue-management-body">
          <div class="border rounded p-3 mb-2 bg-white">
            <h6>Add another venue</h6>
            <p class="small text-muted mb-2">Choose an existing venue, or create one if it is not listed.</p>
            <div class="venue-add-mode" role="group" aria-label="How to add a venue">
              <button type="button" class="btn btn-sm active" id="use-existing-venue" data-venue-mode="existing" aria-pressed="true"><i class="ti ti-map-pin" aria-hidden="true"></i>Existing venue</button>
              <button type="button" class="btn btn-sm" id="create-new-venue" data-venue-mode="new" aria-pressed="false"><i class="ti ti-plus" aria-hidden="true"></i>Create new venue</button>
            </div>
            <div id="existing-venue-panel" class="venue-add-panel">
              <label class="form-label small" for="new-venue-id">Select venue</label>
              <select id="new-venue-id" class="form-select form-select-sm"><option value="">Choose a venue…</option>@foreach($allVenues as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
            </div>
            <div id="new-venue-panel" class="venue-add-panel d-none" hidden>
              <label class="form-label small" for="new-venue-name">New venue name</label>
              <input id="new-venue-name" class="form-control form-control-sm" maxlength="191" placeholder="Enter a venue name" disabled>
            </div>
            <div class="row g-2">
              <div class="col-sm-6"><label class="form-label small" for="new-venue-courts">Number of courts</label><input id="new-venue-courts" type="number" class="form-control form-control-sm" value="1" min="1" max="100"></div>
              <div class="col-sm-6"><label class="form-label small" for="new-venue-ball">Court type</label><select id="new-venue-ball" class="form-select form-select-sm"><option value="standard">Standard</option><option value="yellow">Yellow ball</option><option value="orange">Orange ball</option><option value="green">Green ball</option><option value="red">Red ball</option></select></div>
            </div>
            <button type="button" id="add-venue" class="btn btn-sm btn-primary mt-3" data-audit-ignore="true"><i class="ti ti-plus me-1"></i><span id="add-venue-label">Add existing venue</span></button>
            <div class="form-text">Keep adding or editing venues here. Choose Done when finished.</div>
            <div id="venue-add-status" class="small text-muted mt-2" role="status" aria-live="polite"></div>
          </div>
          <div id="venue-editor-list">
          @forelse($venues as $venue)
          <details class="venue-editor" data-venue="{{ $venue['id'] }}">
            <summary><strong class="flex-grow-1">{{ $venue['name'] }}</strong><span class="badge bg-label-primary">{{ $venue['courts'] }} courts</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>
            <div class="venue-editor-body">
            <div class="d-flex justify-content-end mt-2"><button type="button" class="btn btn-sm btn-outline-danger remove-venue" data-url="{{ route('backend.event-venue-schedule.venues.remove', [$event, $venue['id']]) }}">Remove from this event</button></div>
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
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button></div>
          </div>
        </div>
      </div>

      @if($draws->isNotEmpty() && $venues->isNotEmpty())
        <div class="workspace-footer">
          <span id="allocation-status" class="small text-muted" role="status" aria-live="polite">Save changes before creating a preview.</span>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" id="save-allocations" class="btn btn-primary" data-audit-ignore="true"><i class="ti ti-device-floppy me-1"></i>Save allocations & timing</button>
            <button type="button" id="continue-to-rules" class="btn btn-outline-primary" data-audit-ignore="true">Save & next: timing<i class="ti ti-arrow-right ms-1"></i></button>
          </div>
        </div>
      @endif
    </div>
  </details>

  <details class="workspace-section mb-3 d-none" id="schedule-rules-step">
    <summary>
      <span class="step-number">2</span>
      <span class="section-title"><h5>Scheduling rules</h5><small class="text-muted">Set the event window, match length and player rest.</small></span>
      <i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i>
    </summary>
    <div class="section-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label" for="schedule-start">Schedule starts</label><input id="schedule-start" type="datetime-local" class="form-control" value="{{ $scheduleDraft['start'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="schedule-end">Schedule ends</label><input id="schedule-end" type="datetime-local" class="form-control" value="{{ $scheduleDraft['end'] }}"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-duration">Match minutes</label><input id="schedule-duration" type="number" class="form-control" value="{{ $scheduleDraft['duration'] }}" min="15" max="480"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-wave">Round wave</label><input id="schedule-wave" type="number" class="form-control" value="{{ $scheduleDraft['wave_minutes'] }}" min="15" max="480"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-gap">Court gap</label><input id="schedule-gap" type="number" class="form-control" value="{{ $scheduleDraft['court_gap'] }}" min="0" max="120"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-rest">Rest</label><input id="schedule-rest" type="number" class="form-control" value="{{ $scheduleDraft['player_rest'] }}" min="0" max="480"></div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label" for="round-progression">Team round progression</label>
          <select id="round-progression" class="form-select">
            <option value="team_ready" @selected($scheduleDraft['round_progression'] === 'team_ready')>Next tie when the team is ready and rested</option>
            <option value="all_round" @selected($scheduleDraft['round_progression'] === 'all_round')>Finish the whole round before the next round</option>
          </select>
          <div class="form-text">Individual matches always follow their qualifying dependencies.</div>
        </div>
        <div class="col-md-6 small text-muted align-self-center">Ties may use multiple venues. We prefer each player's previous venue and warn when a move is needed. Published schedules can be adjusted; matches with play stay protected.</div>
      </div>
      <div class="mt-3">
        <label class="form-label" for="gender-waves">Gender waves at shared venues</label>
        <select id="gender-waves" class="form-select">
          <option value="combined" @selected($scheduleDraft['gender_waves'] === 'combined')>Combine boys and girls</option>
          <option value="boys_then_girls" @selected($scheduleDraft['gender_waves'] === 'boys_then_girls')>Boys first, then girls in each round</option>
          <option value="girls_then_boys" @selected($scheduleDraft['gender_waves'] === 'girls_then_boys')>Girls first, then boys in each round</option>
        </select>
        <div class="form-text">Choose which gender starts in each round, then repeat that order in the next round. Mixed draws and saved matches keep their usual scheduling rules.</div>
      </div>
      <div class="mt-3">
        <label class="form-label" for="gender-wave-release">When the next gender can start</label>
        <select id="gender-wave-release" class="form-select">
          <option value="whole_wave" @selected($scheduleDraft['gender_wave_release'] === 'whole_wave')>Wait for the whole previous gender wave</option>
          <option value="court_ready" @selected($scheduleDraft['gender_wave_release'] === 'court_ready')>Use free courts while the previous gender finishes</option>
        </select>
        <div class="form-text">Using free courts still allocates the earlier gender first. The next gender may start after the round wave interval from the previous gender's first start, as courts become available. Player rest and qualifying dependencies still apply.</div>
      </div>
      <div class="mt-3">
        <label class="form-label" for="tie-allocation">Team tie allocation</label>
        <select id="tie-allocation" class="form-select">
          <option value="balanced" @selected($scheduleDraft['tie_allocation'] === 'balanced')>Share court allocation between ties</option>
          <option value="complete_tie" @selected($scheduleDraft['tie_allocation'] === 'complete_tie')>Allocate one complete tie at a time</option>
        </select>
        <div class="form-text">Allocate all available rubbers in one tie before assigning the next tie. Other ties may still play at the same time on free courts. Rest, gender waves and qualifying dependencies still apply; blocked rubbers remain for review.</div>
      </div>
      <div id="rank-preferences" class="border rounded p-3 mt-4">
        <h6 class="mb-1">Venue assignments by team roster rank (optional)</h6>
        <p class="small text-muted mb-2">Use roster positions, not player ratings. A band can apply to both boys' and girls' draws. Only selected team draws are edited here; other draws keep their saved bands. Saved matches stay fixed. Matching rank bands hold their assigned venue in automatic planning. If that venue cannot fit a match, it stays unallocated for review; add court time there or explicitly change the band.</p>
        <div id="rank-band-review" class="alert alert-warning py-2 small d-none" role="status"></div>
        <div id="rank-band-scope" class="small fw-semibold mb-2"></div>
        <div id="rank-band-rows" class="d-grid gap-2"></div>
        <div class="d-flex flex-wrap gap-2 mt-2"><button id="add-rank-band" type="button" class="btn btn-sm btn-outline-primary">Add rank band</button><button id="default-rank-bands" type="button" class="btn btn-sm btn-outline-secondary">Add bands 1–4, 5–6, 7–8</button></div>
        <label for="cross-band-policy" class="form-label mt-3">When players belong to different venue bands</label>
        <select id="cross-band-policy" class="form-select"><option value="highest_ranked" @selected($scheduleDraft['cross_band_policy'] === 'highest_ranked')>Prefer the highest-ranked player's venue and warn</option><option value="manual" @selected($scheduleDraft['cross_band_policy'] === 'manual')>Leave the match for manual placement</option></select>
        <div class="form-text">Highest ranked means the lowest roster position number. Manual venue changes remain available.</div>
      </div>
      <div class="mt-4">
        <div class="fw-semibold">Venue opening times</div>
        <div class="small text-muted mb-2">Leave blank to use the main schedule start. A later draw/category start still takes priority.</div>
        <div class="row g-2">
          @foreach($venues as $venue)
            <div class="col-md-6 col-xl-4">
              <label class="form-label small" for="venue-start-{{ $venue['id'] }}">{{ $venue['name'] }}</label>
              <input id="venue-start-{{ $venue['id'] }}" class="form-control venue-start" data-venue="{{ $venue['id'] }}" type="datetime-local" value="{{ $scheduleDraft['venue_starts']->get($venue['id'], '') }}">
            </div>
          @endforeach
        </div>
      </div>
      <label class="form-check mt-3 p-3 border rounded bg-light" for="reschedule-existing">
        <input class="form-check-input" type="checkbox" id="reschedule-existing" {{ $scheduleDraft['reschedule_existing'] ? 'checked' : '' }}>
        <span class="form-check-label fw-semibold">Include already scheduled matches and start a fresh reschedule</span>
        <span class="d-block small text-muted ms-4">The existing saved schedule stays unchanged until you review and apply the replacement.</span>
      </label>
      <div class="compact-note small text-muted mt-3" role="note"><strong class="text-body">How byes are timed:</strong> a bye uses no court but still advances through its round wave. A player with two byes first appears in the third wave.</div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" id="back-to-allocations" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Back: allocations</button>
        <button type="button" id="save-timing" class="btn btn-outline-primary" data-audit-ignore="true"><i class="ti ti-device-floppy me-1"></i>Save timing</button>
        <button type="button" id="generate-preview" class="btn btn-primary" data-audit-ignore="true"><i class="ti ti-wand me-1"></i>Generate combined preview</button>
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

  <details class="workspace-section mb-3 d-none" id="schedule-review-step">
    <summary>
      <span class="step-number">3</span>
      <span class="section-title"><h5>Review & apply</h5><small class="text-muted">Check every suggested time before saving the schedule.</small></span>
    </summary>
    <div class="section-body">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" id="back-to-rules" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Back: timing</button>
        <button type="button" id="apply-preview" class="btn btn-success" data-audit-ignore="true" disabled><i class="ti ti-device-floppy me-1"></i>Save matches that fit</button>
        <a id="continue-to-draws" class="btn btn-primary d-none" href="{{ route('headOffice.show', ['headOffice' => $event->id, 'schedule' => 'applied']) }}"><i class="ti ti-arrow-right me-1"></i>Continue to tournament draws</a>
        <span id="review-status" class="align-self-center text-muted small" role="status" aria-live="polite">Review every venue before applying.</span>
      </div>
  <div id="schedule-display" class="schedule-display">
    <div class="workflow-rail full-page-stepper" aria-label="Schedule workflow">
      <button type="button" class="workflow-step" data-workflow-nav="1"><span class="step-number">1</span><span class="workflow-label">Court allocation</span></button>
      <button type="button" class="workflow-step" data-workflow-nav="2" data-audit-ignore="true"><span class="step-number">2</span><span class="workflow-label">Timing rules</span></button>
      <button type="button" class="workflow-step is-active" data-workflow-nav="3" data-audit-ignore="true"><span class="step-number">3</span><span class="workflow-label">Review & apply</span></button>
    </div>
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
  </details>
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
          <input type="search" id="manual-match-picker-search" class="form-control" placeholder="Search by draw / category, match or player" autocomplete="off">
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
<div class="modal fade" id="programme-setup-modal" tabindex="-1" aria-labelledby="programme-setup-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="programme-setup-title">Assign venues &amp; courts</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p class="small text-muted">Choose which rounds receive this setup. Unselected rounds retain their current setup. Pair and player-position bands hold their assigned venue during automatic planning. Matches that do not fit remain unallocated. Saved matches stay in place.</p>
      <div id="programme-setup-draws"></div>
      <p id="programme-setup-policy" class="small text-muted mt-3"></p>
      <div id="programme-setup-status" class="small" role="status" aria-live="polite"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="programme-setup-save">Save setup</button></div>
  </div></div>
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
  const drawsUrl = @json(route('backend.event-venue-schedule.calendar', $event));
  const eventName = @json($event->name);
  const manualMode = @json(request()->boolean('manual'));
  const drawIds = @json($draws->reject(fn($draw) => $draw['locked'])->pluck('id')->values());
  let payload = null;
  let programmePayload = null;
  let previewGeneration = 0;
  let revision = null;
  let replanVenueIds = [];
  let allocationsDirty = false;
  let scheduleDirty = false;
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
    {after: 15000, percent: 86, label: 'Balancing draws / categories across the timetable…'},
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
  const participantNamesHtml = row => (row.participants || []).map((name, side) =>
    escapeHtml(name) + ((row.fixture_kind || 'individual') === 'individual' && side < 2
      ? (window.CTPlayerRatings?.marker({fixtureId:row.fixture_id, side:side + 1}, {drawId:row.draw_id}) || '') : '')
  ).join(' / ') || 'Participants determined by draw';
  const lineupDetails = row => ['home', 'away'].map(side => {
    const lineup = row.lineup?.[side];
    if (!lineup) return '';
    const players = lineup.players?.length ? lineup.players : [{name:'TBD', rank:null}];
    return `<div class="small text-muted mt-1">${players.map(player => {
      const rank = Number.isInteger(player.rank) && player.rank > 0 ? `Rank ${player.rank}` : 'Rank unassigned';
      return `${escapeHtml(rank)} · ${escapeHtml(player.name || 'TBD')}${window.CTPlayerRatings?.marker({playerId:player.player_id}, {drawId:row.draw_id}) || ''}${lineup.region ? ` (${escapeHtml(lineup.region)})` : ''}`;
    }).join(' / ')}</div>`;
  }).join('');
  const setStatus = (element, message, tone = 'muted') => {
    element.textContent = message;
    element.classList.remove('text-muted', 'text-danger', 'text-success', 'text-warning');
    element.classList.add(`text-${tone}`);
  };
  const notify = (message, tone = 'success') => {
    if (window.toastr && typeof window.toastr[tone] === 'function') {
      window.toastr[tone](message);
      return;
    }
    const container = document.getElementById('schedule-toast-container') || Object.assign(document.createElement('div'), {
      id: 'schedule-toast-container',
      className: 'toast-container position-fixed top-0 end-0 p-3',
    });
    if (!container.isConnected) document.body.appendChild(container);
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${tone === 'danger' ? 'danger' : tone === 'warning' ? 'warning' : 'success'} border-0`;
    toast.setAttribute('role', 'status');
    const row = document.createElement('div');
    row.className = 'd-flex';
    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.dataset.bsDismiss = 'toast';
    close.setAttribute('aria-label', 'Close');
    row.append(body, close);
    toast.appendChild(row);
    container.appendChild(toast);
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    bootstrap.Toast.getOrCreateInstance(toast, {delay: 3500}).show();
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
    previewGeneration++;
    document.getElementById('preview-warnings').replaceChildren();
    document.getElementById('venue-timelines').replaceChildren();
    document.getElementById('venue-slot-grids').replaceChildren();
    document.getElementById('preview-summary').classList.add('d-none');
    lastScheduleResult = null;
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
  const markScheduleDirty = () => {
    scheduleDirty = true;
    setStatus(document.getElementById('allocation-status'), 'Unsaved timing changes.', 'warning');
    setStatus(document.getElementById('schedule-status'), 'Unsaved timing changes.', 'warning');
    invalidatePreview('Save the timing changes, then generate a new preview.');
  };
  const setWorkflowStep = step => {
    document.querySelectorAll('.workflow-step').forEach(item => {
      const active = Number(item.dataset.workflowNav) === step;
      item.classList.toggle('is-active', active);
      if (active) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
  };
  const showWorkflowStep = step => {
    ['court-allocation-step', 'schedule-rules-step', 'schedule-review-step'].forEach((id, index) => {
      const section = document.getElementById(id);
      section.classList.toggle('d-none', index !== step - 1);
      if (index === step - 1) section.open = true;
    });
    setWorkflowStep(step);
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
  const rankDraws = @json($draws->filter(fn($draw) => $draw['is_team'] && ! $draw['locked'])->values());
  const rankVenues = @json($venues->map(fn($venue) => ['id' => $venue['id'], 'name' => $venue['name']])->values());
  let allRankRules = @json($scheduleDraft['rank_venue_preferences']);
  let rankScopeIds = [];
  const selectedRankDraws = () => rankDraws.filter(draw => values('.draw-choice').includes(Number(draw.id)));
  const readRankRules = () => [...document.querySelectorAll('.rank-band-row')].map(row => ({
    draw_ids: [...row.querySelector('.rank-band-draws').selectedOptions].map(option => Number(option.value)),
    min_rank: Number(row.querySelector('.rank-band-min').value), max_rank: Number(row.querySelector('.rank-band-max').value),
    venue_id: Number(row.querySelector('.rank-band-venue').value),
  }));
  const coalesceRankRules = rules => {
    const groups = new Map();
    const unfinished = [];
    rules.forEach(rule => {
      if (!rule.draw_ids.length) { unfinished.push(rule); return; }
      const key = `${rule.min_rank}:${rule.max_rank}:${rule.venue_id}`;
      const group = groups.get(key) || {...rule,draw_ids:[]};
      group.draw_ids=[...new Set(group.draw_ids.concat(rule.draw_ids.map(Number)))];
      groups.set(key,group);
    });
    return [...groups.values(), ...unfinished];
  };
  const applicableRankRules = () => readRankRules().flatMap(rule => {
    if (!rule.draw_ids.length) return [rule];
    const saved = Number(rule.venue_id) > 0;
    const drawIds = saved ? rule.draw_ids.filter(id => document.querySelector(`.assignment-choice[data-draw="${id}"][value="${rule.venue_id}"]`)?.checked) : rule.draw_ids;
    return drawIds.length ? [{...rule,draw_ids:drawIds}] : [];
  });
  const rememberRankRules = () => {
    allRankRules = allRankRules.map(rule => ({...rule, draw_ids:rule.draw_ids.filter(id => !rankScopeIds.includes(Number(id)))}))
      .filter(rule => rule.draw_ids.length).concat(readRankRules());
    allRankRules = coalesceRankRules(allRankRules);
  };
  const allowedRankVenues = rule => rankVenues.filter(venue => rule.draw_ids.length && rule.draw_ids.every(id => document.querySelector(`.assignment-choice[data-draw="${id}"][value="${venue.id}"]`)?.checked));
  const rankSelects = container => [...container.querySelectorAll('.rank-band-draws, .rank-band-venue')];
  const destroyRankSelects = container => {
    if (!window.jQuery?.fn?.select2) return;
    rankSelects(container).forEach(select => {
      const control = window.jQuery(select);
      control.off('.rankBands');
      if (control.hasClass('select2-hidden-accessible')) control.select2('destroy');
    });
  };
  const initializeRankSelects = container => {
    if (!window.jQuery?.fn?.select2) return;
    rankSelects(container).forEach(select => {
      const control = window.jQuery(select);
      const multiple = select.classList.contains('rank-band-draws');
      control.select2({width:'100%', dropdownParent:window.jQuery('#rank-preferences'),
        placeholder:multiple ? 'Search draws / categories' : 'Choose assigned venue',
        closeOnSelect:!multiple, minimumResultsForSearch:0});
      control.on('change.rankBands', event => {
        if (!event.originalEvent) select.dispatchEvent(new Event('change', {bubbles:true}));
      });
    });
  };
  const rankVenueOptions = rule => {
    const venues = allowedRankVenues(rule);
    const stale = rule.venue_id && !venues.some(venue => Number(venue.id) === Number(rule.venue_id));
    return `<option value="">Choose assigned venue</option>${stale ? `<option value="${Number(rule.venue_id)}" selected>Saved venue needs review</option>` : ''}${venues.map(venue => `<option value="${Number(venue.id)}" ${Number(rule.venue_id)===Number(venue.id)?'selected':''}>${escapeHtml(venue.name)}</option>`).join('')}`;
  };
  const rankVenueHint = rule => !rule.draw_ids.length ? 'Select draws for this band.' : (allowedRankVenues(rule).length ? 'Only venues assigned to every selected draw are listed.' : 'No common venue. Select fewer draws or assign the same venue to them.');
  const updateRankReview = rules => {
    const stale = rules.some(rule => rule.venue_id && !allowedRankVenues(rule).some(venue => Number(venue.id) === Number(rule.venue_id)));
    document.getElementById('rank-band-review').classList.toggle('d-none', !stale);
    document.getElementById('rank-band-review').textContent = 'A saved rank-band venue is no longer assigned to these draws. That mapping is ignored during preview and removed when you save; other draws keep their bands.';
  };
  const updateRankVenueOptions = row => {
    const select = row.querySelector('.rank-band-venue');
    const rule = {draw_ids:[...row.querySelector('.rank-band-draws').selectedOptions].map(option => Number(option.value)), venue_id:Number(select.value)};
    const container = {querySelectorAll:() => [select]};
    destroyRankSelects(container);
    select.innerHTML = rankVenueOptions(rule);
    row.querySelector('.rank-venue-hint').textContent = rankVenueHint(rule);
    initializeRankSelects(container);
  };
  let rankBandSequence = 0;
  const renderRankRows = rawRules => {
    const rules = coalesceRankRules(rawRules);
    const draws = selectedRankDraws();
    rankScopeIds = draws.map(draw => Number(draw.id));
    const scope = document.getElementById('rank-band-scope');
    scope.innerHTML = draws.length
      ? `<details class="rank-band-scope-details"><summary>${rules.length} rank ${rules.length===1?'band':'bands'} · Editing ${draws.length} team ${draws.length===1?'draw':'draws'} <span class="text-muted fw-normal">View categories</span></summary><div class="rank-scope-chips">${draws.map(draw => `<span>${escapeHtml(draw.name)}</span>`).join('')}</div></details>`
      : 'Select a team draw to configure roster rank bands.';
    ['add-rank-band','default-rank-bands'].forEach(id => document.getElementById(id).disabled = !draws.length);
    updateRankReview(rules);
    const rows = document.getElementById('rank-band-rows');
    destroyRankSelects(rows);
    rows.innerHTML = rules.map((rule, index) => {
      const id = `rank-band-${++rankBandSequence}`;
      return `<div class="rank-band-row"><div class="rank-band-header"><strong>Rank band ${index + 1}</strong><button class="btn btn-sm btn-outline-danger remove-rank-band" type="button" aria-label="Remove roster rank band ${index + 1}">Remove</button></div><div class="rank-band-fields"><div><label class="form-label small" for="${id}-min">Roster rank range</label><div class="rank-range"><input id="${id}-min" class="form-control rank-band-min" aria-label="From roster rank for band ${index + 1}" type="number" min="1" max="100" value="${escapeHtml(rule.min_rank)}"><span class="text-muted">to</span><input id="${id}-max" class="form-control rank-band-max" aria-label="To roster rank for band ${index + 1}" type="number" min="1" max="100" value="${escapeHtml(rule.max_rank)}"></div></div><div class="rank-draw-field"><label class="form-label small" for="${id}-draws">Draws / categories <span class="rank-band-selected-count text-muted fw-normal" aria-live="polite">${rule.draw_ids.length} selected</span></label><select id="${id}-draws" class="form-select rank-band-draws" multiple size="3">${draws.map(draw => `<option value="${Number(draw.id)}" ${rule.draw_ids.map(Number).includes(Number(draw.id))?'selected':''}>${escapeHtml(draw.name)}</option>`).join('')}</select></div><div><label class="form-label small" for="${id}-venue">Assigned venue</label><select id="${id}-venue" class="form-select rank-band-venue">${rankVenueOptions(rule)}</select><div class="rank-venue-hint form-text">${rankVenueHint(rule)}</div></div></div></div>`;
    }).join('');
    initializeRankSelects(rows);
  };
  const loadRankScope = () => renderRankRows(allRankRules
    .filter(rule => !rule.draw_ids.length || rule.draw_ids.some(id => selectedRankDraws().some(draw => Number(draw.id)===Number(id))))
    .map(rule => ({...rule, draw_ids:rule.draw_ids.filter(id => selectedRankDraws().some(draw => Number(draw.id)===Number(id)))})));
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => { rememberRankRules(); loadRankScope(); }));
  document.getElementById('rank-band-rows').addEventListener('change', event => {
    if (event.target.classList.contains('rank-band-draws')) {
      const row=event.target.closest('.rank-band-row');
      row.querySelector('.rank-band-selected-count').textContent = `${event.target.selectedOptions.length} selected`;
      updateRankVenueOptions(row);
    }
    updateRankReview(readRankRules());
    markScheduleDirty();
  });
  document.querySelectorAll('.assignment-choice').forEach(input => input.addEventListener('change', () => renderRankRows(readRankRules())));
  document.getElementById('rank-band-rows').addEventListener('click', event => { const button=event.target.closest('.remove-rank-band'); if(button){const row=button.closest('.rank-band-row');destroyRankSelects(row);row.remove();renderRankRows(readRankRules());markScheduleDirty();} });
  document.getElementById('add-rank-band').addEventListener('click', () => { renderRankRows(readRankRules().concat([{draw_ids:[], min_rank:1,max_rank:4,venue_id:null}]));markScheduleDirty(); });
  document.getElementById('default-rank-bands').addEventListener('click', () => { renderRankRows(readRankRules().concat([[1,4],[5,6],[7,8]].map(([min_rank,max_rank]) => ({draw_ids:[],min_rank,max_rank,venue_id:null}))));markScheduleDirty(); });
  document.getElementById('cross-band-policy').addEventListener('change', markScheduleDirty);
  loadRankScope();
  const readDrawRounds = () => [...document.querySelectorAll('.draw-round-choice:checked')]
    .filter(input => input.value !== 'all').reduce((rows, input) => {
      const id = Number(input.dataset.draw);
      let row = rows.find(row => row.draw_id === id);
      if (!row) { row = {draw_id:id, rounds:[]}; rows.push(row); }
      row.rounds.push(Number(input.value));
      return rows;
    }, []);
  document.querySelectorAll('.draw-round-choice').forEach(input => input.addEventListener('change', () => {
    const choices = [...document.querySelectorAll(`.draw-round-choice[data-draw="${input.dataset.draw}"]`)];
    if (input.checked) choices.filter(choice => (input.value === 'all' ? choice.value !== 'all' : choice.value === 'all')).forEach(choice => choice.checked = false);
    if (!choices.some(choice => choice.checked)) choices.find(choice => choice.value === 'all').checked = true;
    markScheduleDirty();
  }));
  const buildScheduleDraft = () => ({
    start: document.getElementById('schedule-start').value,
    end: document.getElementById('schedule-end').value || null,
    duration: Number(document.getElementById('schedule-duration').value),
    wave_minutes: Number(document.getElementById('schedule-wave').value),
    court_gap: Number(document.getElementById('schedule-gap').value),
    player_rest: Number(document.getElementById('schedule-rest').value),
    draw_starts: [...document.querySelectorAll('.draw-start')].filter(input => input.value).map(input => ({draw_id:Number(input.dataset.draw), start:input.value})),
    draw_rounds: readDrawRounds().filter(row => drawIds.includes(row.draw_id)),
    venue_starts: [...document.querySelectorAll('.venue-start')].filter(input => input.value).map(input => ({venue_id:Number(input.dataset.venue), start:input.value})),
    reschedule_existing: document.getElementById('reschedule-existing').checked,
    round_progression: document.getElementById('round-progression').value,
    gender_waves: document.getElementById('gender-waves').value,
    gender_wave_release: document.getElementById('gender-wave-release').value,
    tie_allocation: document.getElementById('tie-allocation').value,
    rank_venue_preferences: applicableRankRules(), rank_preference_draw_ids:rankScopeIds,
    cross_band_policy: document.getElementById('cross-band-policy').value,
  });
  const selectedAssignedVenueIds = (drawIds = values('.draw-choice')) => [...new Set(
    drawIds.flatMap(drawId => [...document.querySelectorAll(`.assignment-choice[data-draw="${drawId}"]:checked`)].map(input => Number(input.value)))
      .concat(roundVenueSetups.filter(row => drawIds.includes(Number(row.draw_id)))
        .filter(row => programmePayload || !readDrawRounds().some(selection => Number(selection.draw_id) === Number(row.draw_id))
          || readDrawRounds().some(selection => Number(selection.draw_id) === Number(row.draw_id) && selection.rounds.map(Number).includes(Number(row.round))))
        .flatMap(row => row.venue_ids.map(Number)))
  )];
  const buildPayload = () => ({
    ...buildScheduleDraft(),
    allow_partial: true,
    draw_ids: values('.draw-choice'),
    draw_rounds: readDrawRounds().filter(row => values('.draw-choice').includes(row.draw_id)),
    draw_starts: [...document.querySelectorAll('.draw-start')].filter(input => input.value && document.querySelector(`.draw-choice[value="${input.dataset.draw}"]`)?.checked).map(input => ({draw_id:Number(input.dataset.draw), start:input.value})),
    ...(programmePayload || {}),
    replan_venue_ids: document.getElementById('reschedule-existing').checked
      ? selectedAssignedVenueIds(programmePayload?.draw_ids || values('.draw-choice'))
      : (programmePayload ? [] : replanVenueIds)
  });
  const post = async (url, body) => {
    const generation = url === previewUrl ? ++previewGeneration : null;
    const stale = () => generation !== null && generation !== previewGeneration;
    try {
      const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body)});
      const data = await response.json().catch(() => ({}));
      if (stale()) throw Object.assign(new Error('Preview selection changed.'), {stalePreview:true});
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Request failed.');
      if (generation !== null) data.previewGeneration = generation;
      return data;
    } catch (error) {
      if (stale()) error.stalePreview = true;
      throw error;
    }
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
    } catch (error) { if (error.stalePreview) return;
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
  ['court-allocation-step', 'schedule-rules-step', 'schedule-review-step'].forEach(id => {
    document.querySelector(`#${id} > summary`)?.addEventListener('click', event => event.preventDefault());
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
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => invalidatePreview()));
  document.querySelectorAll('.draw-start, .venue-start, #schedule-start, #schedule-end, #schedule-duration, #schedule-wave, #schedule-gap, #schedule-rest, #round-progression, #gender-waves, #gender-wave-release, #tie-allocation, #reschedule-existing')
    .forEach(input => input.addEventListener('change', markScheduleDirty));
  document.getElementById('reschedule-existing')?.addEventListener('change', event => {
    if (!event.currentTarget.checked) replanVenueIds = [];
    document.getElementById('programme-reschedule-existing').checked = event.currentTarget.checked;
  });
  document.getElementById('programme-reschedule-existing').addEventListener('change', event => {
    const shared = document.getElementById('reschedule-existing');
    shared.checked = event.currentTarget.checked;
    shared.dispatchEvent(new Event('change', {bubbles:true}));
  });
  document.getElementById('gender-wave-release').addEventListener('change', event => {
    document.getElementById('programme-gender-wave-release').value = event.currentTarget.value;
  });
  document.getElementById('programme-gender-wave-release').addEventListener('change', event => {
    const shared = document.getElementById('gender-wave-release');
    shared.value = event.currentTarget.value;
    shared.dispatchEvent(new Event('change', {bubbles:true}));
  });
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => {
    const start = document.querySelector(`.draw-start[data-draw="${input.value}"]`);
    if (start) start.disabled = !input.checked;
    updateDrawSummary(input.value);
  }));
  const existingVenue = document.getElementById('new-venue-id');
  const newVenueName = document.getElementById('new-venue-name');
  const setVenueMode = mode => {
    const creating = mode === 'new';
    document.querySelectorAll('[data-venue-mode]').forEach(button => {
      const active = button.dataset.venueMode === mode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    const existingPanel = document.getElementById('existing-venue-panel');
    const newPanel = document.getElementById('new-venue-panel');
    existingPanel.classList.toggle('d-none', creating);
    existingPanel.hidden = creating;
    newPanel.classList.toggle('d-none', !creating);
    newPanel.hidden = !creating;
    existingVenue.disabled = creating;
    newVenueName.disabled = !creating;
    if (creating) existingVenue.value = '';
    else newVenueName.value = '';
    document.getElementById('add-venue-label').textContent = creating ? 'Create venue and courts' : 'Add existing venue';
    document.getElementById('venue-add-status').textContent = '';
    (creating ? newVenueName : existingVenue).focus();
  };
  document.querySelectorAll('[data-venue-mode]').forEach(button => button.addEventListener('click', () => setVenueMode(button.dataset.venueMode)));
  const card = (value, label, tone='primary') => `<div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><div class="fs-4 fw-bold text-${tone}">${value}</div><small class="text-muted">${label}</small></div></div></div>`;
  const asDate = value => new Date(String(value).replace(' ', 'T'));
  const fixtureKey = match => match.fixture_key || `${match.fixture_kind || 'individual'}:${match.fixture_id}`;
  const matchLabel = match => match.fixture_kind === 'team'
    ? `Rubber ${match.match || '—'} · ${match.stage || 'Team match'}`
    : `Match ${match.match || '—'}`;
  const fixtureRef = key => {
    const parts = String(key).split(':');
    return {fixture_kind: parts.length > 1 ? parts[0] : 'individual', fixture_id: Number(parts.at(-1))};
  };
  const dateKey = value => asDate(value).getTime();
  const formatSlotTime = date => date.toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
  const assignmentTime = date => {
    const pad = value => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
  };
  const scheduleRoundLabel = row => row.programme_day ? `Day ${row.programme_day} · Order ${row.programme_sequence} · R${row.round}` : row.wave ? `Wave ${row.wave} · R${row.round}` : `R${row.round}`;
  const matchEnd = (match, result) => match.ends_at
    ? asDate(match.ends_at)
    : new Date(dateKey(match.scheduled_at) + (Number(match.duration || result.input.duration) + Number(result.input.courtGap || 0)) * 60000);
  const venueRows = (result, venueId) => [...result.matches.map(match => ({...match, fixed:false})),
    ...(result.existing_matches || []).map(match => ({...match, fixed:true}))]
    .filter(match => Number(match.venue_id) === Number(venueId))
    .sort((a, b) => dateKey(a.scheduled_at) - dateKey(b.scheduled_at) || String(a.court).localeCompare(String(b.court), undefined, {numeric:true}));
  const ageGroupScheduleSummary = rows => {
    const groups = new Map();
    rows.forEach(row => {
      const key = Number(row.draw_id);
      if (!groups.has(key)) groups.set(key, {name:row.draw_name, rows:[]});
      groups.get(key).rows.push(row);
    });
    return [...groups.values()].map(group => {
      const ordered = group.rows.sort((left, right) => dateKey(left.scheduled_at) - dateKey(right.scheduled_at));
      const first = asDate(ordered[0].scheduled_at);
      const last = asDate(ordered[ordered.length - 1].scheduled_at);
      const firstLabel = first.toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
      const lastLabel = first.toDateString() === last.toDateString()
        ? last.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})
        : last.toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
      return `<span class="age-group-schedule-chip">${escapeHtml(group.name)} · ${ordered.length} · ${escapeHtml(firstLabel)}–${escapeHtml(lastLabel)}</span>`;
    }).join('');
  };
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
    const apply = planned && !result.input.programme?.days?.length ? `<button type="button" class="btn btn-sm btn-success" data-apply-venue="${venue.id}" data-venue-name="${escapeHtml(venue.name)}"><i class="ti ti-check me-1" aria-hidden="true"></i>Save venue matches</button>` : '';
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
          const round = starts.programme_day ? `Day ${starts.programme_day} · Order ${starts.programme_sequence} · R${starts.round}` : starts.wave ? `Wave ${starts.wave} · R${starts.round}` : `R${starts.round}`;
          const state = starts.fixed
            ? '<span class="badge bg-label-success mt-1">Saved</span>'
            : '<span class="badge bg-label-primary mt-1">Suggested · not saved</span>';
          const card = movable
            ? `<button type="button" class="manual-match-card" draggable="true" data-manual-fixture="${fixtureKey(starts)}" aria-pressed="false" title="Drag this match to an available slot"><span class="fw-semibold">${escapeHtml(starts.draw_name)}</span><span class="small d-block">${round} · ${escapeHtml(matchLabel(starts))}</span>${state}<span class="small text-muted d-block mt-1">${escapeHtml(path)}</span></button>`
            : `<div class="fw-semibold">${escapeHtml(starts.draw_name)}</div><div class="small">${round} · ${escapeHtml(matchLabel(starts))}</div>${state}<div class="small text-muted mt-1">${escapeHtml(path)}</div>`;
          const remove = starts.fixed && starts.editable && unapplyUrl
            ? `<button type="button" class="btn btn-sm btn-outline-danger manual-match-remove" data-unapply-fixture="${fixtureKey(starts)}" data-match-label="${escapeHtml(starts.draw_name)} ${escapeHtml(matchLabel(starts))}"><i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Remove from schedule</button>`
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

    return `<details class="card preview-venue mb-4" data-preview-venue="${venue.id}"><summary class="card-header d-flex flex-wrap align-items-center gap-2"><h5 class="mb-0">${escapeHtml(venue.name)}</h5><span class="venue-age-group-summary">${ageGroupScheduleSummary(rows)}</span><span class="small text-muted">${venue.courts} courts · ${rows.length} fixtures</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>${venueActions(result, venue)}<div class="court-grid-hint small"><i class="ti ti-arrows-horizontal" aria-hidden="true"></i><span>Scroll sideways to see every court. Court headings and start times remain visible while you scroll.</span></div><div class="court-grid-scroll" tabindex="0" role="region" aria-label="${escapeHtml(venue.name)} court schedule; scroll horizontally and vertically"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Slot starts</th>${courtHeaders}</tr></thead><tbody>${body || '<tr><td colspan="99" class="text-center text-muted py-4">No slots in this scheduling window.</td></tr>'}</tbody></table></div>${truncated}</details>`;
  }

  function venueChangeWarnings(result) {
    const warningTime = value => value ? new Date(value.replace(' ', 'T')).toLocaleString([], {weekday:'short', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', hour12:false}) : 'Time unavailable';
    return (result.venue_change_warnings || []).map(change => {
      const destination = change.to || {};
      const fixture = destination.fixture || {};
      const when = warningTime(destination.scheduled_at);
      const details = (booking, label) => {
        const source = booking?.fixture;
        const identity = source ? `${source.draw_name} · ${source.discipline} · Round ${source.round} · Match ${source.match}` : 'Previous booking details unavailable';
        return `<div class="mb-2"><strong>${label}</strong><div>${escapeHtml(identity)}</div><div>${escapeHtml(warningTime(booking?.scheduled_at))} · ${escapeHtml(booking?.venue_name || 'Venue unavailable')} · Court ${escapeHtml(booking?.court || '—')}</div></div>`;
      };
      return `<details class="alert alert-warning py-2"><summary class="fw-semibold">${escapeHtml(when)} · ${escapeHtml(fixture.draw_name || 'Match')} · Round ${escapeHtml(fixture.round || '—')} · Match ${escapeHtml(fixture.match || '—')}: ${escapeHtml(change.message)} <span class="small">View details</span></summary><div class="mt-2">${details(change.from, 'Previous match')}${details(destination, 'Next match')}<p class="mb-0"><strong>Why this venue:</strong> ${escapeHtml(change.reason || 'Review this placement with the organiser.')}</p></div></details>`;
    }).join('');
  }

  function render(result) {
    if (result.previewGeneration !== undefined && result.previewGeneration !== previewGeneration) return;
    lastScheduleResult = result;
    revision = result.revision;
    replanVenueIds = (result.input.replan_venue_ids || []).map(Number);
    showWorkflowStep(3);
    document.getElementById('preview-summary').classList.remove('d-none');
    document.getElementById('preview-summary').innerHTML = card(result.matches.length, 'Suggested · not saved', 'primary') + card((result.existing_matches || []).length, 'Saved · kept fixed', 'success') + card(result.automatic_byes, 'Automatic byes') + card(result.venues.length, 'Venues') + card(result.unscheduled.length, 'Unscheduled', result.unscheduled.length ? 'danger' : 'success');
    const detailedMessages = new Set((result.venue_change_warnings || []).map(change => change.message));
    let warnings = (result.warnings || []).filter(message => !detailedMessages.has(message)).map(message => `<div class="alert alert-warning py-2">${escapeHtml(message)}</div>`).join('') + venueChangeWarnings(result);
    if (result.unscheduled.length) warnings += `<div class="alert alert-danger"><strong>Matches remaining to schedule:</strong><div class="small mb-2">${result.input.programme?.days?.length ? 'Every match needs a time and court before the complete programme can be saved. Follow the reasons below: resolve the named earlier section first, or adjust the courts, match duration or playing hours for a section that has no available time.' : 'Save the matches that fit this batch; remaining matches stay in planning.'}</div><ul class="mb-0">${result.unscheduled.map(row => `<li>${escapeHtml(row.draw_name)} ${scheduleRoundLabel(row)} · Match ${row.match}: ${escapeHtml(row.reason)}${lineupDetails(row)}</li>`).join('')}</ul></div>`;
    document.getElementById('preview-warnings').innerHTML = warnings;
    document.getElementById('preview-view-controls').classList.remove('d-none');
    document.getElementById('preview-view-controls').classList.add('d-flex');
    document.getElementById('venue-timelines').innerHTML = result.venues.map(venue => {
      const rows = venueRows(result, venue.id);
      return `<details class="card preview-venue mb-4" data-preview-venue="${venue.id}"><summary class="card-header d-flex flex-wrap align-items-center gap-2"><h5 class="mb-0">${escapeHtml(venue.name)}</h5><span class="venue-age-group-summary">${ageGroupScheduleSummary(rows)}</span><span class="small text-muted">${venue.courts} courts · ${rows.length} fixtures</span><i class="ti ti-chevron-down summary-chevron" aria-hidden="true"></i></summary>${venueActions(result, venue)}<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>Court</th><th>Draw / category</th><th>Round</th><th>Match</th><th>Players / qualification path</th><th>State</th><th>Action</th></tr></thead><tbody>${rows.map(row => `<tr><td class="text-nowrap fw-semibold">${escapeHtml(row.scheduled_at.slice(0,16))}</td><td>${escapeHtml(row.court)}</td><td>${escapeHtml(row.draw_name)}</td><td>${scheduleRoundLabel(row)}</td><td class="text-nowrap fw-semibold">Match ${escapeHtml(row.match || '—')}</td><td>${participantNamesHtml(row)}${lineupDetails(row)}</td><td>${row.fixed ? '<span class="badge bg-label-success">Saved</span>' : '<span class="badge bg-label-primary">Suggested · not saved</span>'}</td><td>${row.fixed && row.editable && unapplyUrl ? `<button type="button" class="btn btn-sm btn-outline-danger" data-unapply-fixture="${fixtureKey(row)}" data-match-label="${escapeHtml(row.draw_name)} ${escapeHtml(matchLabel(row))}">Remove</button>` : '<span class="text-muted">—</span>'}</td></tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">No fixtures allocated.</td></tr>'}</tbody></table></div></details>`;
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
    const hasSuggestions = result.matches.length > 0;
    const scheduleComplete = !result.unscheduled.length && !hasSuggestions && (result.existing_matches || []).length > 0;
    const applyPreview = document.getElementById('apply-preview');
    applyPreview.innerHTML = `<i class="ti ti-device-floppy me-1"></i>${result.input.programme?.days?.length ? 'Save complete three-day schedule' : 'Save matches that fit'}`;
    const continueToDraws = document.getElementById('continue-to-draws');
    applyPreview.disabled = !hasSuggestions || (!!result.input.programme?.days?.length && result.unscheduled.length > 0);
    applyPreview.classList.toggle('d-none', scheduleComplete);
    continueToDraws.classList.toggle('d-none', !scheduleComplete);
    const previewMessage = result.unscheduled.length
      ? 'Preview needs attention. Resolve every unscheduled match before applying.'
      : scheduleComplete
        ? 'Schedule already saved. Continue to the tournament draws.'
        : 'Preview ready. Review every venue before applying.';
    const previewTone = result.unscheduled.length ? 'danger' : 'success';
    setStatus(document.getElementById('schedule-status'), previewMessage, previewTone);
    setStatus(document.getElementById('review-status'), previewMessage, previewTone);
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
        ...fixtureRef(fixtureId), scheduled_at:slot.dataset.time,
        venue_id:Number(slot.dataset.venue), court:slot.dataset.court,
        duration:Number(document.getElementById('schedule-duration').value),
        court_gap:Number(document.getElementById('schedule-gap').value),
        player_rest:Number(document.getElementById('schedule-rest').value),
        round_progression:document.getElementById('round-progression').value,
      });
      selectedManualFixture = null;
      replanVenueIds = replanVenueIds.filter(id => id !== Number(slot.dataset.venue));
      payload = buildPayload();
      render(await post(previewUrl, payload));
      if (saved.warnings?.length) {
        document.getElementById('preview-warnings').insertAdjacentHTML('afterbegin', saved.warnings.map(message => `<div class="alert alert-warning py-2">${escapeHtml(message)}</div>`).join(''));
      }
      setStatus(status, saved.message + ' It is saved; the remaining unsaved suggestions were adapted around it.', 'success');
    } catch (error) { if (error.stalePreview) return;
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
    return [...new Map(rows.map(match => [fixtureKey(match), match])).values()]
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
      return `<button type="button" class="list-group-item list-group-item-action manual-match-picker-option" data-picker-fixture="${fixtureKey(match)}" data-search="${escapeHtml(searchable)}"><span class="fw-semibold d-block">${escapeHtml(match.draw_name)} · ${escapeHtml(matchLabel(match))}</span><span class="d-block">${escapeHtml(players)}</span><span class="match-picker-meta d-block">${sequence} · ${escapeHtml(current)}</span></button>`;
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
      const eligible = new Set();
      for (const kind of ['individual', 'team']) {
        const matches = candidates.filter(match => (match.fixture_kind || 'individual') === kind);
        for (let offset = 0; offset < matches.length; offset += 200) {
          const options = await post(manualOptionsUrl, {
            fixture_kind:kind, fixture_ids:matches.slice(offset, offset + 200).map(match => Number(match.fixture_id)), scheduled_at:slot.dataset.time,
            venue_id:Number(slot.dataset.venue), court:slot.dataset.court,
            duration:Number(document.getElementById('schedule-duration').value),
            court_gap:Number(document.getElementById('schedule-gap').value),
            player_rest:Number(document.getElementById('schedule-rest').value),
        round_progression:document.getElementById('round-progression').value,
          });
          (options.eligible_fixture_ids || []).forEach(id => eligible.add(`${kind}:${id}`));
        }
      }
      renderPickerCandidates(candidates.filter(match => eligible.has(fixtureKey(match))));
      filterMatchPicker();
    } catch (error) { if (error.stalePreview) return;
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
    placeMatchManually(option.dataset.pickerFixture, slot);
  });
  slotGrids.addEventListener('dragstart', event => {
    const match = event.target.closest('[data-manual-fixture]');
    if (!match) return;
    selectedManualFixture = match.dataset.manualFixture;
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
    placeMatchManually(event.dataTransfer.getData('text/plain') || selectedManualFixture, slot);
  });
  slotGrids.addEventListener('dragend', () => {
    slotGrids.querySelectorAll('.is-drag-over').forEach(slot => slot.classList.remove('is-drag-over'));
  });
  slotGrids.addEventListener('click', event => {
    const match = event.target.closest('[data-manual-fixture]');
    if (match) {
      selectedManualFixture = match.dataset.manualFixture;
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
      } catch (error) { if (error.stalePreview) return;
        setStatus(document.getElementById('schedule-status'), error.message, 'danger');
        unapplyButton.innerHTML = originalButtonHtml;
        matching.forEach(control => { control.disabled = false; });
      }
      return;
    }
    if (applyButton) {
      if (!payload || !revision) {
        matching.forEach(control => { control.disabled = false; });
        return;
      }
      try {
        const applied = await post(applyUrl, {...payload, revision, apply_venue_ids:[venueId]});
        replanVenueIds = replanVenueIds.filter(id => id !== venueId);
        payload = {...buildPayload(), replan_venue_ids:replanVenueIds};
        render(await post(previewUrl, payload));
        setStatus(document.getElementById('schedule-status'), `Applied ${applied.count} fixtures at ${applyButton.dataset.venueName}. They are saved privately and kept fixed in planning.`, 'success');
      } catch (error) { if (error.stalePreview) return;
        setStatus(document.getElementById('schedule-status'), error.message, 'danger');
        matching.forEach(control => { control.disabled = false; });
      }
      return;
    }
    try {
      await refreshVenuePreview(venueId, Boolean(replanButton));
    } catch (error) { if (error.stalePreview) return;
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
      matching.forEach(control => { control.disabled = false; });
    }
  };
  document.getElementById('venue-timelines').addEventListener('click', handleVenueAction);
  document.getElementById('venue-slot-grids').addEventListener('click', handleVenueAction);
  const refreshAllAppliedVenues = async replanning => {
    const button = document.getElementById(replanning ? 'replan-all-applied' : 'keep-all-applied');
    const originalButtonHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Building preview…';
    if (replanning) {
      replanVenueIds = JSON.parse(document.getElementById('replan-all-applied').dataset.venueIds || '[]').map(Number);
    } else {
      replanVenueIds = [];
    }
    document.getElementById('reschedule-existing').checked = replanning;
    document.getElementById('programme-reschedule-existing').checked = replanning;
    payload = {...buildPayload(), replan_venue_ids:replanVenueIds};
    revision = null;
    setStatus(document.getElementById('schedule-status'), replanning ? 'Replanning every applied venue…' : 'Restoring every current applied venue schedule…');
    startScheduleActivity(previewActivityStages, 'The saved schedule remains in place until you explicitly apply this replacement preview.');
    let completed = false;
    try {
      render(await post(previewUrl, payload));
      completed = true;
      finishScheduleActivity(replanning ? 'Replacement preview ready.' : 'Current applied schedules restored.');
    } catch (error) { if (error.stalePreview) return;
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
    } catch (error) { if (error.stalePreview) return;
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
      const result = await post(unapplyUrl, fixtureRef(control.dataset.unapplyFixture));
      payload = buildPayload();
      render(await post(previewUrl, payload));
      setStatus(document.getElementById('schedule-status'), result.message + ' The saved booking is removed and the unsaved suggestions were refreshed.', 'success');
    } catch (error) { if (error.stalePreview) return;
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
    programmePayload = null;
    const button = event.currentTarget;
    if ((allocationsDirty || scheduleDirty) && ! await saveAllocationsAndTiming(button)) return;
    payload = buildPayload(); revision = null; button.disabled = true;
    const originalButtonHtml = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Building preview…';
    document.getElementById('apply-preview').disabled = true;
    setStatus(document.getElementById('schedule-status'), 'Building the combined preview…');
    startScheduleActivity(previewActivityStages, 'Progress is estimated while the server builds and validates the complete schedule.');
    let completed = false;
    try { render(await post(previewUrl, payload)); completed = true; finishScheduleActivity('Combined preview ready.'); }
    catch (error) { if (error.stalePreview) return; setStatus(document.getElementById('schedule-status'), error.message, 'danger'); }
    finally { if (!completed) stopScheduleActivity(); button.innerHTML = originalButtonHtml; button.disabled = false; }
  });
  const saveAllocationsAndTiming = async (button, scopedDrawIds = drawIds) => {
    const venues = @json($venues->map(fn($venue) => ['id' => $venue['id'], 'courts' => $venue['courts']])->values());
    const assignments = scopedDrawIds.map(drawId => {
      const venueIds = [...document.querySelectorAll(`.assignment-choice[data-draw="${drawId}"]:checked`)].map(input => Number(input.value));
      const courtAllocations = venueIds.map(venueId => ({venue_id:venueId, court_labels:[...document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venueId}"]:checked`)].map(input => input.value)}));
      return {draw_id:Number(drawId), venue_ids:venueIds, court_allocations:courtAllocations};
    });
    const buttons = [document.getElementById('save-allocations'), document.getElementById('continue-to-rules'), document.getElementById('save-timing')].filter(Boolean);
    buttons.forEach(control => { control.disabled = true; });
    setStatus(document.getElementById('allocation-status'), 'Saving court allocations and timing…');
    try {
      const schedule = buildScheduleDraft();
      schedule.draw_rounds = schedule.draw_rounds.filter(row => scopedDrawIds.includes(row.draw_id));
      const result = await post(assignmentUrl, {venues, assignments, schedule});
      if (scopedDrawIds.length === drawIds.length) allocationsDirty = false;
      scheduleDirty = false;
      setStatus(document.getElementById('allocation-status'), result.message, 'success');
      setStatus(document.getElementById('schedule-status'), result.message, 'success');
      notify(result.message, 'success');
      return true;
    } catch (error) { if (error.stalePreview) return;
      setStatus(document.getElementById('allocation-status'), error.message, 'danger');
      setStatus(document.getElementById('schedule-status'), error.message, 'danger');
      notify(error.message, 'danger');
      return false;
    } finally {
      buttons.forEach(control => { control.disabled = false; });
    }
  };
  document.getElementById('save-allocations')?.addEventListener('click', event => saveAllocationsAndTiming(event.currentTarget));
  document.getElementById('save-timing')?.addEventListener('click', event => saveAllocationsAndTiming(event.currentTarget));
  document.getElementById('continue-to-rules')?.addEventListener('click', async event => {
    if (! await saveAllocationsAndTiming(event.currentTarget)) return;
    showWorkflowStep(2);
    document.getElementById('schedule-rules-step').scrollIntoView({behavior:'smooth', block:'start'});
  });
  document.getElementById('back-to-allocations')?.addEventListener('click', () => showWorkflowStep(1));
  document.getElementById('back-to-rules')?.addEventListener('click', () => showWorkflowStep(2));
  document.querySelectorAll('[data-workflow-nav]').forEach(control => control.addEventListener('click', async event => {
    const step = Number(event.currentTarget.dataset.workflowNav);
    if (step < 3 && scheduleDisplay.classList.contains('is-full-page')) setFullPage(false);
    if (step === 1) return showWorkflowStep(1);
    if (step === 2) {
      if ((allocationsDirty || scheduleDirty) && ! await saveAllocationsAndTiming(event.currentTarget)) return;
      return showWorkflowStep(2);
    }
    if (payload && revision) return showWorkflowStep(3);
    showWorkflowStep(2);
    document.getElementById('generate-preview').click();
  }));
  const deleteVenueAssociation = async url => {
    const response = await fetch(url, {method:'DELETE', headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrf}, body:'{}'});
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || 'Unable to remove this venue.');
    return result;
  };
  document.querySelectorAll('.remove-draw-venue').forEach(button => button.addEventListener('click', async () => {
    if (!confirm('Remove this venue from this age group? The event and other age groups will keep the venue.')) return;
    button.disabled = true;
    const drawId = Number(button.dataset.draw), venueId = Number(button.dataset.venue);
    try {
      const result = await deleteVenueAssociation(button.dataset.url);
      const assignment = document.querySelector(`.assignment-choice[data-draw="${drawId}"][value="${venueId}"]`);
      if (assignment) assignment.checked = false;
      document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venueId}"]`).forEach(input => { input.checked = false; });
      rememberRankRules();
      allRankRules = allRankRules.flatMap(rule => {
        if (!rule.draw_ids.length) return [rule];
        const remaining = Number(rule.venue_id) === venueId
          ? {...rule, draw_ids:rule.draw_ids.filter(id => Number(id) !== drawId)} : rule;
        return remaining.draw_ids.length ? [remaining] : [];
      });
      loadRankScope();
      updateCourtSummary(drawId, venueId);
      updateDrawSummary(drawId);
      refreshProgrammeStageSummaries();
      invalidatePreview();
      button.remove();
      setStatus(document.getElementById('allocation-status'), result.message, 'success');
    } catch (error) {
      setStatus(document.getElementById('allocation-status'), error.message, 'danger');
      button.disabled = false;
    }
  }));
  const venueModal = document.getElementById('venue-management-modal');
  let venueManagementChanged = false;
  let venueManagementPending = false;
  const venueDraftKey = 'venue-management-draft-{{ $event->id }}-{{ auth()->id() }}';
  const venueDraftControls = () => [...document.querySelectorAll('.draw-choice, .assignment-choice, .court-allocation, .draw-round-choice, .draw-start, .venue-start, #schedule-start, #schedule-end, #schedule-duration, #schedule-wave, #schedule-gap, #schedule-rest, #round-progression, #gender-waves, #gender-wave-release, #tie-allocation, #reschedule-existing, #cross-band-policy')];
  const venueControlKey = input => JSON.stringify([input.id, input.className, input.dataset.draw, input.dataset.venue, input.type === 'checkbox' ? input.value : null]);
  const rememberVenueDraft = () => {
    rememberRankRules();
    try {
      sessionStorage.setItem(venueDraftKey, JSON.stringify({
        savedAt:Date.now(), allocationsDirty, scheduleDirty, rankRules:allRankRules,
        controls:venueDraftControls().map(input => ({key:venueControlKey(input), value:input.value, checked:input.checked})),
      }));
    } catch (_) { /* Browser storage may be unavailable. Saved settings remain on the server. */ }
  };
  venueModal.addEventListener('hide.bs.modal', event => {
    if (venueManagementPending) {
      event.preventDefault();
      setStatus(document.getElementById('venue-add-status'), 'Wait for the venue update to finish before closing.', 'warning');
    }
  });
  venueModal.addEventListener('hidden.bs.modal', () => {
    if (!venueManagementChanged) return;
    rememberVenueDraft();
    window.location.reload();
  });
  const refreshVenueEditors = async () => {
    venueManagementChanged = true;
    invalidatePreview('Venues changed. Generate a new preview after finishing venue edits.');
    const response = await fetch(window.location.href, {headers:{Accept:'text/html'}, cache:'no-store'});
    if (!response.ok) throw new Error('The venue was updated. Close this window to refresh the venue list.');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const editors = page.getElementById('venue-editor-list');
    const choices = page.getElementById('new-venue-id');
    if (!editors || !choices) throw new Error('The venue was updated. Close this window to refresh the venue list.');
    document.getElementById('venue-editor-list').innerHTML = editors.innerHTML;
    existingVenue.innerHTML = choices.innerHTML;
    document.getElementById('venue-management-counts').textContent = page.getElementById('venue-management-counts').textContent;
  };
  const venueAction = async (button, action) => {
    if (venueManagementPending) return;
    venueManagementPending = true;
    button.disabled = true;
    try { await action(); }
    catch (error) { if (!error.stalePreview) setStatus(document.getElementById('venue-add-status'), error.message, 'danger'); }
    finally { venueManagementPending = false; button.disabled = false; }
  };
  document.getElementById('add-venue')?.addEventListener('click', event => venueAction(event.currentTarget, async () => {
    const creating = !newVenueName.disabled;
    if ((!creating && !existingVenue.value) || (creating && !newVenueName.value.trim())) {
      setStatus(document.getElementById('venue-add-status'), creating ? 'Enter a name for the new venue.' : 'Choose an existing venue.', 'danger');
      (creating ? newVenueName : existingVenue).focus();
      return;
    }
    setStatus(document.getElementById('venue-add-status'), creating ? 'Creating the venue and courts...' : 'Adding the venue and courts...');
    const result = await post(venueUrl, {venue_id:Number(existingVenue.value) || null, name:newVenueName.value.trim() || null, courts:Number(document.getElementById('new-venue-courts').value), ball_type:document.getElementById('new-venue-ball').value});
    venueManagementChanged = true;
    const addedOption = [...existingVenue.options].find(option => Number(option.value) === Number(result.venue.id));
    if (addedOption) addedOption.remove();
    existingVenue.value = '';
    newVenueName.value = '';
    setStatus(document.getElementById('venue-add-status'), result.message + ' Add another venue or choose Done.', 'success');
    await refreshVenueEditors();
    (creating ? newVenueName : existingVenue).focus();
  }));
  venueModal.addEventListener('click', event => {
    const button = event.target.closest('.add-court, .update-court-setup, .update-court-type, .remove-venue');
    if (!button) return;
    return venueAction(button, async () => {
      let result;
      if (button.classList.contains('remove-venue')) {
        const editor = button.closest('.venue-editor');
        const name = editor.querySelector('summary strong').textContent;
        if (!confirm(`Remove ${name} from this event? Its draw allocations will be removed. The venue remains available for other events.`)) return;
        result = await deleteVenueAssociation(button.dataset.url);
      } else if (button.classList.contains('update-court-setup')) {
        const setup = button.closest('.venue-court-setup');
        const ballType = setup.querySelector('.setup-court-ball').value;
        if (ballType === 'mixed') throw new Error('Choose one court type before updating all courts.');
        if (button.dataset.hasCustom === '1' && !confirm('Updating all courts will replace specially named courts with numbered courts. Continue?')) return;
        result = await post(setup.dataset.url, {courts:Number(setup.querySelector('.setup-court-count').value), ball_type:ballType});
      } else {
        const venueId = Number(button.dataset.venue);
        const adding = button.classList.contains('add-court');
        const label = adding ? document.querySelector(`.add-court-label[data-venue="${venueId}"]`).value.trim() : button.dataset.label;
        if (!label) throw new Error('Enter a court label first.');
        const ballType = adding ? document.querySelector(`.add-court-ball[data-venue="${venueId}"]`).value
          : document.querySelector(`.edit-court-ball[data-venue="${venueId}"][data-label="${CSS.escape(label)}"]`).value;
        result = await post(courtUrl, {venue_id:venueId, label, ball_type:ballType});
      }
      setStatus(document.getElementById('venue-add-status'), result.message || 'Court updated.', 'success');
      await refreshVenueEditors();
    });
  });
  document.getElementById('apply-preview').addEventListener('click', async event => {
    if (!payload || !revision) return;
    const button = event.currentTarget;
    const originalButtonHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Saving schedule…';
    setStatus(document.getElementById('schedule-status'), 'Applying and revalidating…');
    setStatus(document.getElementById('review-status'), 'Applying and revalidating…');
    startScheduleActivity(applyActivityStages, 'Progress is estimated while the server applies the fixtures and rebuilds the final schedule.');
    let completed = false;
    try {
      const applied = await post(applyUrl, {...payload, revision});
      replanVenueIds = [];
      payload = {...buildPayload(), replan_venue_ids:[]};
      setScheduleActivityPhase(revalidationActivityStages);
      render(await post(previewUrl, payload));
      setStatus(document.getElementById('schedule-status'), `Saved ${applied.count} matches privately. Opening saved schedule…`, 'success');
      setStatus(document.getElementById('review-status'), `Saved ${applied.count} matches privately. Opening saved schedule…`, 'success');
      notify(`Saved ${applied.count} matches privately.`, 'success');
      completed = true;
      finishScheduleActivity('Schedule applied. Opening saved schedule…');
      window.setTimeout(() => window.location.assign(drawsUrl), 1000);
    }
    catch (error) { if (error.stalePreview) return; setStatus(document.getElementById('schedule-status'), error.message, 'danger'); setStatus(document.getElementById('review-status'), error.message, 'danger'); notify(error.message, 'danger'); button.disabled = false; }
    finally { if (!completed) stopScheduleActivity(); button.innerHTML = originalButtonHtml; }
  });
  try {
    const restored = JSON.parse(sessionStorage.getItem(venueDraftKey) || 'null');
    sessionStorage.removeItem(venueDraftKey);
    if (restored && Date.now() - restored.savedAt < 10 * 60 * 1000) {
      const controls = new Map(restored.controls.map(control => [control.key, control]));
      venueDraftControls().forEach(input => {
        const control = controls.get(venueControlKey(input));
        if (!control) return;
        if (input.type === 'checkbox') { if (!input.disabled) input.checked = control.checked; }
        else input.value = control.value;
      });
      allRankRules = restored.rankRules.filter(rule => !Number(rule.venue_id) || rankVenues.some(venue => Number(venue.id) === Number(rule.venue_id)));
      loadRankScope();
      document.querySelectorAll('.draw-start').forEach(input => {
        input.disabled = !document.querySelector(`.draw-choice[value="${input.dataset.draw}"]`)?.checked;
      });
      document.querySelectorAll('.court-allocation').forEach(input => updateCourtSummary(input.dataset.draw, input.dataset.venue));
      drawIds.forEach(updateDrawSummary);
      allocationsDirty = restored.allocationsDirty;
      scheduleDirty = restored.scheduleDirty;
    }
  } catch (_) { /* Continue with the saved server settings when browser storage is unavailable. */ }
  const programmeDraws = @json($draws->where('is_team', true)->values());
  const programmeGroup = () => programmeDraws.filter(draw => Number(draw.programme_age) === Number(document.getElementById('programme-age').value));
  const programmeStatus = (message, tone = 'secondary') => setStatus(document.getElementById('programme-status'), message, tone);
  let programmeSortables = [];
  let programmeRefreshVersion = 0;
  const programmeCreateLabel = document.getElementById('programme-create').innerHTML;
  const programmeRows = () => [...document.querySelectorAll('[data-programme-draw]')];
  const programmeDiscipline = draw => {
    const code = String(draw.rubber_code || draw.name).toLowerCase();
    return code.includes('mixed') ? 'Mixed doubles' : code.includes('reverse') ? 'Reverse singles' : code.includes('double') ? 'Doubles' : 'Singles';
  };
  const programmeSetupRules = () => [
    ...allRankRules.map(rule => ({...rule, draw_ids:rule.draw_ids.filter(id => !rankScopeIds.includes(Number(id)))})),
    ...applicableRankRules(),
  ];
  const programmeCourtLabels = labels => {
    if (!labels.every(label => /^\d+$/.test(label) && String(Number(label)) === label)) return labels.join(', ');
    const numbers = [...new Set(labels.map(Number))].sort((a,b) => a - b);
    const ranges = [];
    numbers.forEach(number => {
      const previous = ranges[ranges.length - 1];
      if (previous && number === previous[1] + 1) previous[1] = number;
      else ranges.push([number, number]);
    });
    return ranges.map(([first,last]) => first === last ? String(first) : `${first}–${last}`).join(', ');
  };
  const programmeDrawLabel = draw => /\bboys\b/i.test(draw.name) ? 'Boys' : /\bgirls\b/i.test(draw.name) ? 'Girls' : /\bmixed\b/i.test(draw.name) ? 'Mixed' : draw.name;
  const programmeUsesPairs = draw => programmeDiscipline(draw) === 'Doubles' && Number.isInteger(Number(draw.doubles_pair_count)) && Number(draw.doubles_pair_count) > 0;
  const programmeUsesMixedPairs = draw => programmeDiscipline(draw) === 'Mixed doubles' && Number.isInteger(Number(draw.mixed_pair_count)) && Number(draw.mixed_pair_count) > 0;
  const programmePairBand = rule => Number.isInteger(Number(rule.min_rank)) && Number.isInteger(Number(rule.max_rank))
    && Number(rule.min_rank) >= 1 && Number(rule.max_rank) >= Number(rule.min_rank)
    && Number(rule.min_rank) % 2 === 1 && Number(rule.max_rank) % 2 === 0;
  const programmeBandRange = (first, last) => first === last ? String(first) : `${first}–${last}`;
  const programmeBandDescription = (draw, rule) => programmeUsesPairs(draw) && programmePairBand(rule) && Number(rule.max_rank) <= Number(draw.doubles_pair_count) * 2
    ? `Pairs ${programmeBandRange((Number(rule.min_rank) + 1) / 2, Number(rule.max_rank) / 2)}`
    : `${programmeUsesMixedPairs(draw) ? 'Mixed pairs' : 'Player positions'} ${programmeBandRange(Number(rule.min_rank), Number(rule.max_rank))}`;
  const programmeRankPayload = (min, max, paired, limit = paired ? 50 : 100) => ({
    min_rank: min > limit || max > limit ? NaN : paired ? (Number.isInteger(min) ? min * 2 - 1 : NaN) : min,
    max_rank: min > limit || max > limit ? NaN : paired ? (Number.isInteger(max) ? max * 2 : NaN) : max,
  });
  let roundVenueSetups = @json($scheduleDraft['round_venue_setups'] ?? []);
  const programmeRoundSetup = (drawId, round) => roundVenueSetups.find(row => Number(row.draw_id) === Number(drawId) && Number(row.round) === Number(round));
  const programmeDrawSetup = (draw, rules, round = null) => {
    const override = programmeRoundSetup(draw.id, round);
    if (override) {
      return '<ul>' + override.court_allocations.map(allocation => {
        const name = document.querySelector(`.assignment-choice[data-draw="${draw.id}"][value="${allocation.venue_id}"]`)?.dataset.venueName || 'Venue';
        const bands = override.rank_venue_preferences.filter(rule => Number(rule.venue_id) === Number(allocation.venue_id)).map(rule => programmeBandDescription(draw, rule));
        return `<li>${escapeHtml(name)} · Courts ${escapeHtml(programmeCourtLabels(allocation.court_labels))}${bands.length ? ` · ${escapeHtml(bands.join('; '))} assigned` : ''}</li>`;
      }).join('') + '</ul>';
    }
    const venues = [...document.querySelectorAll(`.assignment-choice[data-draw="${draw.id}"]:checked`)];
    if (!venues.length) return '<span class="text-danger">No venue assigned</span>';
    const validRules = rules.filter(rule => rule.draw_ids.map(Number).includes(Number(draw.id))
      && Number(rule.min_rank) >= 1 && Number(rule.max_rank) >= Number(rule.min_rank)
      && venues.some(venue => Number(venue.value) === Number(rule.venue_id)));
    const lines = venues.map(venue => {
      const courts = [...document.querySelectorAll(`.court-allocation[data-draw="${draw.id}"][data-venue="${venue.value}"]:checked`)].map(input => input.value);
      const bands = validRules.filter(rule => Number(rule.venue_id) === Number(venue.value))
        .sort((a,b) => Number(a.min_rank) - Number(b.min_rank))
        .map(rule => programmeBandDescription(draw, rule));
      return `<li>${escapeHtml(venue.dataset.venueName)} · ${courts.length ? `Courts ${escapeHtml(programmeCourtLabels(courts))}` : '<span class="text-danger">No courts selected</span>'}${bands.length ? ` · ${escapeHtml(bands.join('; '))} assigned` : ` · No ${programmeUsesPairs(draw) || programmeUsesMixedPairs(draw) ? 'pair' : 'position'} assignment`}</li>`;
    });
    return `<ul>${lines.join('')}</ul>`;
  };
  const refreshProgrammeStageSummaries = () => {
    const draws = new Map(programmeGroup().map(draw => [Number(draw.id), draw]));
    const rules = programmeSetupRules();
    document.querySelectorAll('.programme-stage').forEach(card => {
      const memberKeys = card.dataset.programmeMembers.split(',');
      const members = memberKeys.map(key => draws.get(Number(key.split(':')[0]))).filter(Boolean);
      card.querySelector('.programme-stage-summary').innerHTML = '<div class="text-muted">Venue setup · rank bands</div>'
        + members.map(draw => {
          const label = programmeDrawLabel(draw);
          const heading = members.filter(member => programmeDrawLabel(member) === label).length === 1 ? label : draw.name;
          return `<div class="mt-1"><strong>${escapeHtml(heading)}</strong><div>${programmeDrawSetup(draw, rules, Number(memberKeys.find(key => Number(key.split(':')[0]) === Number(draw.id))?.split(':')[1]))}</div></div>`;
        }).join('');
    });
  };
  const programmeSetupModal = document.getElementById('programme-setup-modal');
  let programmeSetupScope = [];
  let programmeSetupRound = null;
  let programmeSetupRounds = [];
  const selectedProgrammeSetupRounds = () => [...programmeSetupModal.querySelectorAll('.programme-setup-round:checked')].map(input => Number(input.value));
  let programmeSetupSaving = false;
  const programmeSetupVenueOptions = (section, selected = '') => '<option value="">Choose an assigned venue</option>'
    + [...section.querySelectorAll('.programme-setup-venue:checked')].map(input => `<option value="${input.value}" ${String(input.value) === String(selected) ? 'selected' : ''}>${escapeHtml(input.dataset.name)}</option>`).join('')
    + (selected && !section.querySelector(`.programme-setup-venue[value="${Number(selected)}"]:checked`) ? `<option value="${Number(selected)}" selected>Venue no longer assigned</option>` : '');
  const addProgrammeSetupBand = (section, savedRule = null) => {
    const draw = programmeDraws.find(draw => Number(draw.id) === Number(section.dataset.setupDraw));
    const doubles = programmeUsesPairs(draw);
    const mixed = programmeUsesMixedPairs(draw);
    const paired = doubles && (!savedRule || (programmePairBand(savedRule) && Number(savedRule.max_rank) <= Number(draw.doubles_pair_count) * 2));
    const rule = savedRule || {min_rank:1, max_rank:doubles ? Math.min(4,Number(draw.doubles_pair_count)) * 2 : mixed ? Math.min(8,Number(draw.mixed_pair_count) || 100) : 4, venue_id:''};
    const min = paired ? (Number(rule.min_rank) + 1) / 2 : rule.min_rank;
    const max = paired ? Number(rule.max_rank) / 2 : rule.max_rank;
    const unit = paired ? 'pair' : mixed ? 'mixed pair' : 'player position';
    const limit = paired ? Number(draw.doubles_pair_count) : mixed ? Number(draw.mixed_pair_count) || 100 : 100;
    const row = document.createElement('div');
    row.className = 'programme-setup-band row g-2 align-items-end mb-2';
    row.dataset.setupRankUnit = paired ? 'pair' : 'position';
    row.innerHTML = `<div class="col-4 col-sm-2"><label class="form-label small mb-1">From ${unit}<input class="form-control form-control-sm programme-setup-min" type="number" min="1" max="${limit}" value="${escapeHtml(min)}"></label></div><div class="col-4 col-sm-2"><label class="form-label small mb-1">To ${unit}<input class="form-control form-control-sm programme-setup-max" type="number" min="1" max="${limit}" value="${escapeHtml(max)}"></label></div><div class="col-12 col-sm-6"><label class="form-label small mb-1 d-block">Assigned venue<select class="form-select form-select-sm programme-setup-band-venue">${programmeSetupVenueOptions(section, rule.venue_id)}</select></label></div><div class="col-4 col-sm-2"><button type="button" class="btn btn-sm btn-outline-danger programme-setup-remove-band" aria-label="Remove venue assignment">Remove</button></div>${doubles && !paired ? '<div class="col-12 small text-warning">Existing player-position band is not a complete configured pair range. It stays in player positions; remove it and add a pair assignment to regroup.</div>' : ''}`;
    section.querySelector('.programme-setup-bands').appendChild(row);
  };
  const readProgrammeSetup = () => {
    const assignments = [], rules = [];
    programmeSetupModal.querySelectorAll('[data-setup-draw]').forEach(section => {
      const drawId = Number(section.dataset.setupDraw);
      const selected = [...section.querySelectorAll('.programme-setup-venue:checked')];
      assignments.push({draw_id:drawId, venue_ids:selected.map(input => Number(input.value)), court_allocations:selected.map(input => ({
        venue_id:Number(input.value), court_labels:[...section.querySelectorAll(`.programme-setup-court[data-venue="${input.value}"]:checked`)].map(court => court.value),
      }))});
      section.querySelectorAll('.programme-setup-band').forEach(row => rules.push({draw_ids:[drawId],
        ...programmeRankPayload(Number(row.querySelector('.programme-setup-min').value), Number(row.querySelector('.programme-setup-max').value), row.dataset.setupRankUnit === 'pair', Number(row.querySelector('.programme-setup-max').max)), venue_id:Number(row.querySelector('.programme-setup-band-venue').value),
      }));
    });
    return {assignments, rules};
  };
  const validateProgrammeSetup = ({assignments, rules}) => {
    if (rules.length > 50) return 'Use at most 50 position assignment bands.';
    for (const assignment of assignments) {
      const name = programmeDraws.find(draw => Number(draw.id) === assignment.draw_id)?.name || 'Draw';
      if (!assignment.venue_ids.length) return `${name}: choose at least one venue.`;
      if (assignment.court_allocations.some(allocation => !allocation.court_labels.length)) return `${name}: choose at least one court for each selected venue.`;
      const bands = rules.filter(rule => rule.draw_ids.includes(assignment.draw_id));
      for (let index = 0; index < bands.length; index++) {
        const rule = bands[index];
        if (!Number.isInteger(rule.min_rank) || !Number.isInteger(rule.max_rank) || rule.min_rank < 1 || rule.max_rank < rule.min_rank || rule.max_rank > 100) return `${name}: use whole numbers within the displayed pair or player-position limits, with the end at or after the start.`;
        if (!assignment.venue_ids.includes(rule.venue_id)) return `${name}: every position assignment must use an assigned venue.`;
        if (bands.slice(0,index).some(other => rule.min_rank <= other.max_rank && rule.max_rank >= other.min_rank)) return `${name}: position assignment ranges cannot overlap.`;
      }
    }
    return null;
  };
  const openProgrammeSetup = card => {
    programmeSetupScope = [...new Set(card.dataset.programmeMembers.split(',').map(key => Number(key.split(':')[0])))];
    const draws = programmeSetupScope.map(id => programmeDraws.find(draw => Number(draw.id) === id)).filter(Boolean);
    if (draws.some(draw => draw.locked)) return programmeStatus('A draw is locked. Review its setup in the allocation workspace.', 'warning');
    programmeSetupRound = Number(card.dataset.programmeMembers.split(',')[0].split(':')[1]);
    programmeSetupRounds = (draws[0]?.rounds || []).map(Number).filter(round => draws.every(draw => draw.rounds.map(Number).includes(round)));
    const oldScope = document.getElementById('programme-setup-round-scope');
    oldScope?.remove();
    const roundScope = document.createElement('div');
    roundScope.id = 'programme-setup-round-scope';
    roundScope.className = 'border rounded p-3 mb-3';
    roundScope.innerHTML = `<label class="form-label" for="programme-setup-round-mode">Apply this setup to</label><select class="form-select mb-2" id="programme-setup-round-mode"><option value="current">This round only</option><option value="next">This and next round</option><option value="onward">This round onwards</option><option value="all">All rounds</option><option value="selected">Selected rounds</option></select><div class="d-flex gap-3 flex-wrap">${programmeSetupRounds.map(round => `<label><input class="form-check-input programme-setup-round" type="checkbox" value="${round}" ${round === programmeSetupRound ? 'checked' : ''}> Round ${round}</label>`).join('')}</div><small class="text-muted">Load from the clicked round. Only checked rounds change; draw defaults and saved match times stay as they are.</small>`;
    document.getElementById('programme-setup-draws').before(roundScope);
    const rules = draws.flatMap(draw => (programmeRoundSetup(draw.id, programmeSetupRound)?.rank_venue_preferences || programmeSetupRules().filter(rule => rule.draw_ids.map(Number).includes(Number(draw.id)))).map(rule => ({...rule, draw_ids:[Number(draw.id)]})));
    document.getElementById('programme-setup-title').textContent = `Assign venues & courts · ${card.querySelector('strong').textContent}`;
    document.getElementById('programme-setup-draws').innerHTML = draws.map(draw => `<section data-setup-draw="${draw.id}" class="border rounded p-3 mb-3"><h6>${escapeHtml(draw.name)}</h6>${[...document.querySelectorAll(`.assignment-choice[data-draw="${draw.id}"]`)].map(venue => {
      const override = programmeRoundSetup(draw.id, programmeSetupRound);
      const selectedVenue = override ? override.venue_ids.map(Number).includes(Number(venue.value)) : venue.checked;
      const courts = [...document.querySelectorAll(`.court-allocation[data-draw="${draw.id}"][data-venue="${venue.value}"]`)];
      return `<div class="border-top pt-2 mt-2"><label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input programme-setup-venue" value="${venue.value}" data-name="${escapeHtml(venue.dataset.venueName)}" ${selectedVenue ? 'checked' : ''}>${escapeHtml(venue.dataset.venueName)}</label><button type="button" class="btn btn-sm btn-outline-secondary programme-setup-all-courts mt-1" data-venue="${venue.value}">All courts</button><div class="d-flex flex-wrap gap-2 mt-2">${courts.map(court => `<label class="small border rounded px-2 py-1"><input type="checkbox" class="form-check-input programme-setup-court me-1" data-venue="${venue.value}" value="${escapeHtml(court.value)}" ${(override ? override.court_allocations.some(allocation => Number(allocation.venue_id) === Number(venue.value) && allocation.court_labels.includes(court.value)) : court.checked) ? 'checked' : ''} ${selectedVenue ? '' : 'disabled'}>Court ${escapeHtml(court.value)}</label>`).join('')}</div></div>`;
    }).join('')}<div class="mt-3"><strong class="small">Optional ${programmeUsesPairs(draw) ? 'pair' : programmeUsesMixedPairs(draw) ? 'mixed-pair' : 'player-position'} venue bands</strong><div class="small text-muted mb-2">${programmeUsesPairs(draw) ? `${draw.doubles_pair_count} pairs: pair 1 = players 1–2; pair 2 = players 3–4, and so on. Uncovered pairs use normal venue scheduling.` : programmeUsesMixedPairs(draw) ? 'Mixed pair 1 = boy 1 + girl 1, pair 2 = boy 2 + girl 2, and so on. Uncovered pairs use normal venue scheduling.' : 'Uncovered player positions use normal venue scheduling.'}</div><div class="programme-setup-bands"></div><button type="button" class="btn btn-sm btn-outline-secondary programme-setup-add-band">Add ${programmeUsesPairs(draw) ? 'pair' : programmeUsesMixedPairs(draw) ? 'mixed-pair' : 'position'} venue band</button></div></section>`).join('');
    programmeSetupModal.querySelectorAll('[data-setup-draw]').forEach(section => rules.filter(rule => rule.draw_ids.map(Number).includes(Number(section.dataset.setupDraw))).forEach(rule => addProgrammeSetupBand(section, rule)));
    document.getElementById('programme-setup-policy').textContent = 'Uses the saved event rule when players span different venue bands. Change that event-wide rule in scheduling rules; this setup save preserves it.';
    document.getElementById('programme-setup-status').textContent = '';
    bootstrap.Modal.getOrCreateInstance(programmeSetupModal).show();
  };
  programmeSetupModal.addEventListener('change', event => {
    if (event.target.id === 'programme-setup-round-mode') {
      const mode = event.target.value;
      const currentIndex = programmeSetupRounds.indexOf(programmeSetupRound);
      programmeSetupModal.querySelectorAll('.programme-setup-round').forEach(input => { const index = programmeSetupRounds.indexOf(Number(input.value)); input.checked = mode === 'all' || mode === 'onward' && index >= currentIndex || mode === 'next' && index >= currentIndex && index <= currentIndex + 1 || mode === 'current' && index === currentIndex || mode === 'selected' && input.checked; });
      return;
    }
    if (event.target.matches('.programme-setup-round')) { document.getElementById('programme-setup-round-mode').value = 'selected'; return; }
    if (!event.target.matches('.programme-setup-venue')) return;
    const section = event.target.closest('[data-setup-draw]');
    section.querySelectorAll(`.programme-setup-court[data-venue="${event.target.value}"]`).forEach(input => { input.disabled = !event.target.checked; });
    section.querySelectorAll('.programme-setup-band-venue').forEach(select => { select.innerHTML = programmeSetupVenueOptions(section, select.value); });
  });
  programmeSetupModal.addEventListener('click', event => {
    const allCourts = event.target.closest('.programme-setup-all-courts');
    if (allCourts) {
      const section = allCourts.closest('[data-setup-draw]');
      const venue = section.querySelector(`.programme-setup-venue[value="${allCourts.dataset.venue}"]`);
      venue.checked = true;
      venue.dispatchEvent(new Event('change', {bubbles:true}));
      section.querySelectorAll(`.programme-setup-court[data-venue="${allCourts.dataset.venue}"]`).forEach(input => { input.checked = true; });
    }
    const add = event.target.closest('.programme-setup-add-band');
    if (add) addProgrammeSetupBand(add.closest('[data-setup-draw]'));
    event.target.closest('.programme-setup-remove-band')?.closest('.programme-setup-band').remove();
  });
  programmeSetupModal.addEventListener('hide.bs.modal', event => { if (programmeSetupSaving) event.preventDefault(); });
  document.getElementById('programme-setup-save').addEventListener('click', async event => {
    if (programmeSetupSaving) return;
    const setup = readProgrammeSetup();
    const rounds = selectedProgrammeSetupRounds();
    const error = !rounds.length ? 'Select at least one round.' : validateProgrammeSetup(setup);
    const status = document.getElementById('programme-setup-status');
    if (error) return setStatus(status, error, 'danger');
    programmeSetupSaving = true;
    const controls = [...programmeSetupModal.querySelectorAll('button, input, select')];
    const disabled = controls.map(control => control.disabled);
    controls.forEach(control => { control.disabled = true; });
    setStatus(status, 'Saving these draw assignments and rank bands…');
    try {
      const round_venue_setups = setup.assignments.flatMap(assignment => rounds.map(round => ({...assignment, round, rank_venue_preferences:setup.rules.filter(rule => rule.draw_ids.includes(assignment.draw_id))})));
      const result = await post(assignmentUrl, {setup_only:true, round_venue_setups});
      roundVenueSetups = result.round_venue_setups;
      programmePayload = null;
      invalidatePreview('Draw setup saved. Generate a new preview before applying.');
      refreshProgrammeStageSummaries();
      programmeStatus('Setup saved for rounds '+rounds.join(', ')+'.' + (allocationsDirty || scheduleDirty ? ' Other page edits remain unsaved.' : '') + (result.warnings?.length ? ` ${result.warnings.join(' ')}` : ''), 'success');
      programmeSetupSaving = false;
      bootstrap.Modal.getOrCreateInstance(programmeSetupModal).hide();
    } catch (error) { setStatus(status, error.message, 'danger'); }
    finally { programmeSetupSaving = false; controls.forEach((control,index) => { control.disabled = disabled[index]; }); }
  });
  const applyProgrammeStageOrder = () => {
    const rows = new Map(programmeRows().map(row => [`${row.dataset.programmeDraw}:${row.dataset.programmeRound}`, row]));
    document.querySelectorAll('.programme-day-lane').forEach(lane => {
      [...lane.querySelectorAll('.programme-stage')].forEach((card, index) => {
        card.querySelector('.programme-stage-day').value = lane.dataset.day;
        card.querySelector('.programme-stage-order').textContent = `Order ${index + 1}`;
        card.dataset.programmeMembers.split(',').forEach(key => {
          const row = rows.get(key);
          if (!row) return;
          row.querySelector('.programme-day').value = lane.dataset.day;
          row.querySelector('.programme-sequence').value = index + 1;
        });
      });
    });
    programmePayload = null; invalidatePreview();
    refreshProgrammePreview(false);
  };
  const renderProgrammeStages = () => {
    programmeSortables.forEach(sortable => sortable.destroy()); programmeSortables = [];
    const draws = new Map(programmeGroup().map(draw => [Number(draw.id), draw]));
    const stages = new Map();
    programmeRows().forEach(row => {
      const draw = draws.get(Number(row.dataset.programmeDraw));
      if (!draw) return;
      const day = Number(row.querySelector('.programme-day').value), sequence = Number(row.querySelector('.programme-sequence').value);
      const label = `${programmeDiscipline(draw)} · Round ${row.dataset.programmeRound}`;
      const key = `${label}|${day}|${sequence}`;
      if (!stages.has(key)) stages.set(key, {label, day, sequence, members:[]});
      stages.get(key).members.push(`${row.dataset.programmeDraw}:${row.dataset.programmeRound}`);
    });
    const ordered = [...stages.values()].sort((a,b) => a.day - b.day || a.sequence - b.sequence || a.label.localeCompare(b.label));
    document.getElementById('programme-stages').innerHTML = [1,2,3].map(day => `<section class="col-lg-4"><h6>Day ${day}</h6><div class="programme-day-lane" data-day="${day}" aria-label="Day ${day} playing order">${ordered.filter(stage => stage.day === day).map(stage => `<div class="programme-stage" data-programme-members="${stage.members.join(',')}"><div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-sm btn-outline-secondary programme-stage-handle" aria-label="Drag ${escapeHtml(stage.label)}"><i class="ti ti-grip-vertical" aria-hidden="true"></i></button><strong>${escapeHtml(stage.label)}</strong></div><div class="small text-muted mt-1"><span class="programme-stage-order">Order ${stage.sequence}</span> · ${stage.members.length === 2 ? 'Boys and girls move together' : `${stage.members.length} draw${stage.members.length === 1 ? '' : 's'}`}</div><div class="programme-stage-summary"></div><button type="button" class="btn btn-sm btn-outline-primary mt-2 programme-stage-setup">Assign venues &amp; courts</button><div class="programme-stage-controls"><button type="button" class="btn btn-sm btn-outline-secondary programme-stage-move" data-direction="up" aria-label="Move ${escapeHtml(stage.label)} up">Up</button><button type="button" class="btn btn-sm btn-outline-secondary programme-stage-move" data-direction="down" aria-label="Move ${escapeHtml(stage.label)} down">Down</button><select class="form-select form-select-sm programme-stage-day" aria-label="Move ${escapeHtml(stage.label)} to another day">${[1,2,3].map(value => `<option value="${value}" ${day === value ? 'selected' : ''}>Day ${value}</option>`).join('')}</select></div></div>`).join('')}</div></section>`).join('');
    refreshProgrammeStageSummaries();
    if (typeof Sortable !== 'undefined') document.querySelectorAll('.programme-day-lane').forEach(lane => programmeSortables.push(new Sortable(lane, {
      group:'programme-days', draggable:'.programme-stage', handle:'.programme-stage-handle', animation:150,
      delay:100, delayOnTouchOnly:true, touchStartThreshold:4, onEnd:applyProgrammeStageOrder,
    })));
  };
  document.getElementById('programme-age').addEventListener('change', () => {
    programmePayload = null; invalidatePreview();
    const group = programmeGroup();
    document.getElementById('programme-rounds').innerHTML = `<table class="table"><thead><tr><th>Discipline / round</th><th>Day</th><th>Order within day</th></tr></thead><tbody>${group.flatMap(draw => draw.rounds.map(round => {
      const code = String(draw.rubber_code || draw.name).toLowerCase();
      const mixed = code.includes('mixed'), reverse = code.includes('reverse'), doubles = code.includes('double');
      const day = mixed ? 3 : reverse ? (round === 1 ? 1 : 2) : doubles ? 2 : 1;
      const sequence = mixed ? round : reverse ? (round === 1 ? 4 : round - 1) : doubles ? round + 2 : round;
      return `<tr data-programme-draw="${draw.id}" data-programme-round="${round}"><td>${escapeHtml(draw.name)} · Round ${round}</td><td><select class="form-select programme-day" aria-label="Day for ${escapeHtml(draw.name)} round ${round}">${[1,2,3].map(value => `<option value="${value}" ${day === value ? 'selected' : ''}>Day ${value}</option>`).join('')}</select></td><td><input class="form-control programme-sequence" type="number" min="1" max="100" value="${sequence}" aria-label="Order for ${escapeHtml(draw.name)} round ${round}"></td></tr>`;
    })).join('')}</tbody></table>`;
    renderProgrammeStages();
    document.getElementById('programme-reuse-source').innerHTML = '<option value="">Choose assigned draw</option>' + group.filter(draw => draw.venues.length).map(draw => `<option value="${draw.id}">${escapeHtml(draw.name)}</option>`).join('');
    programmeStatus(group.length ? `${group.length} disciplines selected. Check all days, match duration, rest and gender order before creating the preview.` : 'Choose an age group.');
  });
  document.querySelectorAll('.programme-time').forEach(input => input.addEventListener('change', () => { programmePayload = null; invalidatePreview(); }));
  document.getElementById('programme-rounds').addEventListener('change', () => { programmePayload = null; invalidatePreview(); renderProgrammeStages(); });
  document.getElementById('programme-stages').addEventListener('click', event => {
    const setupButton = event.target.closest('.programme-stage-setup');
    if (setupButton) return openProgrammeSetup(setupButton.closest('.programme-stage'));
    const button = event.target.closest('.programme-stage-move');
    if (!button) return;
    const card = button.closest('.programme-stage');
    if (button.dataset.direction === 'up' && card.previousElementSibling) card.parentNode.insertBefore(card, card.previousElementSibling);
    else if (button.dataset.direction === 'down' && card.nextElementSibling) card.parentNode.insertBefore(card.nextElementSibling, card);
    else return;
    applyProgrammeStageOrder();
    button.focus({preventScroll:true});
  });
  document.getElementById('programme-stages').addEventListener('change', event => {
    if (!event.target.matches('.programme-stage-day')) return;
    const card = event.target.closest('.programme-stage');
    document.querySelector(`.programme-day-lane[data-day="${event.target.value}"]`).appendChild(card);
    applyProgrammeStageOrder();
    event.target.focus({preventScroll:true});
  });
  [['programme-duration','schedule-duration'],['programme-rest','schedule-rest'],['programme-gap','schedule-gap']].forEach(([wizardId, sharedId]) => {
    document.getElementById(wizardId).addEventListener('change', () => { document.getElementById(sharedId).value = document.getElementById(wizardId).value; programmePayload = null; invalidatePreview(); });
    document.getElementById(sharedId).addEventListener('change', () => { document.getElementById(wizardId).value = document.getElementById(sharedId).value; });
  });
  document.getElementById('programme-reuse').addEventListener('click', async event => {
    const button = event.currentTarget;
    if (allocationsDirty) return programmeStatus('Save your existing allocation edits first, then reuse courts for missing disciplines.', 'danger');
    const sourceId = Number(document.getElementById('programme-reuse-source').value);
    const sourceVenues = [...document.querySelectorAll(`.assignment-choice[data-draw="${sourceId}"]:checked`)];
    const missing = programmeGroup().filter(draw => !document.querySelector(`.assignment-choice[data-draw="${draw.id}"]:checked`));
    if (!sourceId || !sourceVenues.length) return programmeStatus('Choose a draw with assigned courts.', 'danger');
    if (missing.some(draw => draw.locked || draw.published)) return programmeStatus('A missing discipline is locked or published. Review its assignments before changing them.', 'danger');
    if (!missing.length) return programmeStatus('Every discipline already has a venue.');
    missing.forEach(draw => sourceVenues.forEach(source => {
      const target = document.querySelector(`.assignment-choice[data-draw="${draw.id}"][value="${source.value}"]`);
      if (!target) return;
      target.checked = true;
      const labels = [...document.querySelectorAll(`.court-allocation[data-draw="${sourceId}"][data-venue="${source.value}"]:checked`)].map(input => input.value);
      document.querySelectorAll(`.court-allocation[data-draw="${draw.id}"][data-venue="${source.value}"]`).forEach(input => input.checked = labels.includes(input.value));
      updateCourtSummary(draw.id, source.value); updateDrawSummary(draw.id);
    }));
    invalidatePreview(); button.disabled = true;
    refreshProgrammeStageSummaries();
    try { if (await saveAllocationsAndTiming(button, missing.map(draw => draw.id))) programmeStatus('Missing discipline assignments saved. You can now create the preview.', 'success'); else { markAllocationsDirty(); programmeStatus('Assignments were not saved. Review the changed allocations and save them before previewing.', 'danger'); } }
    finally { button.disabled = false; }
  });
  const refreshProgrammePreview = async (showReview = true) => {
    const button = document.getElementById('programme-create');
    const group = programmeGroup();
    if (!group.length) return programmeStatus('Choose an age group.', 'danger');
    if (allocationsDirty) return programmeStatus('Save the changed court allocations before creating the programme preview.', 'danger');
    [['programme-duration','schedule-duration'],['programme-rest','schedule-rest'],['programme-gap','schedule-gap']].forEach(([wizardId, sharedId]) => document.getElementById(sharedId).value = document.getElementById(wizardId).value);
    const days = [0,1,2].map(index => {
      const start = document.getElementById(`programme-start-${index}`).value, end = document.getElementById(`programme-end-${index}`).value;
      const breakStart = document.getElementById(`programme-break-start-${index}`).value, breakEnd = document.getElementById(`programme-break-end-${index}`).value;
      return {start, end, gender_waves:document.getElementById(`programme-gender-${index}`).value, break_start:breakStart ? `${start.slice(0,10)}T${breakStart}` : null, break_end:breakEnd ? `${start.slice(0,10)}T${breakEnd}` : null};
    });
    const rounds = [...document.querySelectorAll('[data-programme-draw]')].map(row => ({draw_id:Number(row.dataset.programmeDraw), round:Number(row.dataset.programmeRound), day:Number(row.querySelector('.programme-day').value), sequence:Number(row.querySelector('.programme-sequence').value)}));
    programmePayload = {draw_ids:group.map(draw => draw.id), draw_rounds:[], draw_starts:[], venue_starts:[], start:days[0].start, end:days[2].end, programme:{days,rounds}, allow_partial:false};
    payload = buildPayload(); revision = null; button.disabled = true;
    const refreshVersion = ++programmeRefreshVersion;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Creating three-day preview…';
    programmeStatus('Checking every round, court, player rest and daily window. Your saved schedule stays in place while the preview is created.');
    try { const result = await post(previewUrl, payload); if (refreshVersion !== programmeRefreshVersion) return; render(result); if (showReview) showWorkflowStep(3); const totals = days.map((day, index) => `Day ${index + 1}: ${result.matches.filter(row => row.scheduled_at.startsWith(day.start.slice(0,10))).length} suggested`).join(' · '); programmeStatus(`${totals}. ${result.unscheduled.length} unallocated. Review before saving.`, result.unscheduled.length ? 'warning' : 'success'); }
    catch (error) { if (!error.stalePreview && refreshVersion === programmeRefreshVersion) programmeStatus(error.message, 'danger'); }
    finally { if (refreshVersion === programmeRefreshVersion) { button.disabled = false; button.innerHTML = programmeCreateLabel; } }
  };
  // Refresh only card details so allocation edits cannot reset the user's daily order.
  document.addEventListener('change', event => {
    if (event.target.matches('.assignment-choice, .court-allocation, .draw-choice, .rank-band-min, .rank-band-max, .rank-band-draws, .rank-band-venue')) refreshProgrammeStageSummaries();
  });
  document.addEventListener('click', event => {
    if (event.target.closest('.remove-rank-band, #add-rank-band, #default-rank-bands')) refreshProgrammeStageSummaries();
  });
  const updateProgrammeDayPublicationLinks = () => {
    document.querySelectorAll('[data-programme-review-day]').forEach(link => {
      const date = document.getElementById(`programme-start-${link.dataset.programmeReviewDay}`).value.slice(0,10);
      const valid = /^\d{4}-\d{2}-\d{2}$/.test(date);
      link.classList.toggle('disabled', !valid);
      link.setAttribute('aria-disabled', String(!valid));
      if (!valid) { link.removeAttribute('href'); return; }
      const target = new URL(drawsUrl, window.location.href);
      target.searchParams.set('date', date);
      link.href = target.href;
    });
  };
  document.querySelectorAll('[id^="programme-start-"]').forEach(input => {
    input.addEventListener('input', updateProgrammeDayPublicationLinks);
    input.addEventListener('change', updateProgrammeDayPublicationLinks);
  });
  updateProgrammeDayPublicationLinks();
  document.querySelectorAll('.programme-gender-day').forEach(select => {
    // Start with the current saved/restored event default, then keep each day independent.
    select.value = document.getElementById('gender-waves').value;
    select.addEventListener('change', () => {
      programmePayload = null;
      invalidatePreview();
      if (programmeGroup().length) refreshProgrammePreview(false);
    });
  });
  document.getElementById('programme-create').addEventListener('click', () => refreshProgrammePreview());
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => { programmePayload = null; }));
  showWorkflowStep(1);
})();
</script>
@endsection
