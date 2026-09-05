@extends('layouts.backend')

@section('title', 'Venue Schedule – '.$event->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="text-uppercase text-primary fw-semibold small">Event schedule workspace</div>
      <h3 class="mb-1">{{ $event->name }}</h3>
      <p class="text-muted mb-0">Schedule every assigned age group across the event's shared physical courts.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-primary" id="open-venue-announcement"
        data-bs-toggle="modal" data-bs-target="#venueAnnouncementModal"
        {{ $announcementDraft['assignments']->isEmpty() ? 'disabled' : '' }}>
        <i class="ti ti-megaphone me-1"></i>Announce assigned courts
      </button>
      <a class="btn btn-outline-secondary" href="{{ url()->previous() }}"><i class="ti ti-arrow-left me-1"></i>Back</a>
    </div>
  </div>

  <div class="alert alert-info d-flex gap-2 align-items-start" role="note">
    <i class="ti ti-info-circle mt-1" aria-hidden="true"></i>
    <div><strong>How byes are timed:</strong> a bye uses no court but still advances through its round wave. A player with two byes first appears in the third wave.</div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-1">1. Assign age groups to courts</h5><small class="text-muted">Choose the physical courts each age group may use, then save these allocations before previewing.</small></div>
    <div class="card-body row g-4">
      <div class="col-lg-7">
        <div class="row g-2">
          @forelse($draws as $draw)
            <div class="col-md-6">
              <div class="border rounded p-3 h-100 {{ $draw['locked'] || $draw['published'] ? 'bg-light text-muted' : '' }}">
                <label class="d-flex gap-2 mb-2 align-items-center">
                <input class="form-check-input draw-choice" type="checkbox" value="{{ $draw['id'] }}" {{ $draw['locked'] || $draw['published'] ? 'disabled' : 'checked' }}>
                <strong>{{ $draw['name'] }}</strong>
                @if($draw['locked'] || $draw['published'])<span class="badge bg-label-secondary ms-auto">{{ $draw['published'] ? 'Published' : 'Locked' }}</span>@endif
                </label>
                <label class="small text-muted d-block mb-2">Start this age group later (optional)<input class="form-control form-control-sm draw-start mt-1" data-draw="{{ $draw['id'] }}" type="datetime-local" {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}></label>
                <div class="small text-muted mb-1">Permitted venues</div>
                <div class="d-grid gap-2">
                  @foreach($venues as $venue)
                    @php
                      $venueAssigned = in_array($venue['id'], $draw['venues']);
                      $allocatedLabels = $draw['court_allocations'][$venue['id']] ?? [];
                    @endphp
                    <div class="border rounded p-2 venue-assignment">
                      <label class="small fw-semibold d-flex align-items-center"><input class="form-check-input assignment-choice me-2" data-draw="{{ $draw['id'] }}" type="checkbox" value="{{ $venue['id'] }}" {{ $venueAssigned ? 'checked' : '' }} {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}>{{ $venue['name'] }} <span class="text-muted fw-normal ms-auto">{{ $venue['courts'] }} courts</span></label>
                      <div class="d-flex flex-wrap gap-2 mt-2 ps-3">
                        @foreach($venue['court_list'] as $court)
                          @php $courtChecked = $venueAssigned && (empty($allocatedLabels) || in_array($court['label'], $allocatedLabels)); @endphp
                          <label class="badge bg-label-secondary text-body fw-normal"><input class="form-check-input court-allocation me-1" data-draw="{{ $draw['id'] }}" data-venue="{{ $venue['id'] }}" type="checkbox" value="{{ $court['label'] }}" {{ $courtChecked ? 'checked' : '' }} {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}>Court {{ $court['label'] }}@if($court['ball_type']) · {{ ucfirst($court['ball_type']) }}@endif</label>
                        @endforeach
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          @empty
            <div class="text-muted">No draws have been created for this event.</div>
          @endforelse
        </div>
      </div>
      <div class="col-lg-5">
        <details class="border rounded mb-3" id="venue-management">
          <summary class="p-3 fw-semibold cursor-pointer">Manage venues and court setup</summary>
          <div class="px-3 pb-3">
          <div class="border rounded p-3 mb-3 bg-light">
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
          <div class="border rounded p-3 mb-2">
            <div class="d-flex align-items-center justify-content-between gap-2"><strong>{{ $venue['name'] }}</strong><span class="badge bg-label-primary">{{ $venue['courts'] }} courts</span></div>
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
          @empty
            <div class="alert alert-warning mb-0">Add the first venue and its courts before creating allocations.</div>
          @endforelse
          </div>
        </details>
      </div>
      @if($draws->isNotEmpty() && $venues->isNotEmpty())
        <div class="col-12 d-flex flex-wrap align-items-center gap-2 border-top pt-3">
          <button type="button" id="save-allocations" class="btn btn-outline-primary"><i class="ti ti-device-floppy me-1"></i>Save court allocations</button>
          <span id="allocation-status" class="small text-muted" role="status" aria-live="polite">Saved allocations will be used by the combined preview.</span>
        </div>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-1">2. Scheduling rules</h5><small class="text-muted">Player rest and court turnaround are calculated separately.</small></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label" for="schedule-start">Schedule starts</label><input id="schedule-start" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T08:00"></div>
        <div class="col-md-3"><label class="form-label" for="schedule-end">Schedule ends</label><input id="schedule-end" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T18:00"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-duration">Match minutes</label><input id="schedule-duration" type="number" class="form-control" value="75" min="15" max="480"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="schedule-wave">Round wave</label><input id="schedule-wave" type="number" class="form-control" value="90" min="15" max="480"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-gap">Court gap</label><input id="schedule-gap" type="number" class="form-control" value="5" min="0" max="120"></div>
        <div class="col-6 col-md-1"><label class="form-label" for="schedule-rest">Rest</label><input id="schedule-rest" type="number" class="form-control" value="60" min="0" max="480"></div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" id="generate-preview" class="btn btn-primary"><i class="ti ti-wand me-1"></i>Generate combined preview</button>
        <button type="button" id="apply-preview" class="btn btn-success" disabled><i class="ti ti-device-floppy me-1"></i>Apply schedule</button>
        <span id="schedule-status" class="align-self-center text-muted small" role="status" aria-live="polite">Preview the full event before applying.</span>
      </div>
      <div id="preview-activity" class="border rounded bg-light p-3 mt-3 d-none" aria-busy="false">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="d-flex align-items-center gap-2">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <strong id="preview-activity-label" role="status" aria-live="polite">Preparing the combined preview…</strong>
          </div>
          <span id="preview-activity-meta" class="small text-muted">About 5% · 0s elapsed</span>
        </div>
        <div class="progress" style="height: 8px;" aria-label="Estimated preview progress">
          <div id="preview-activity-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 5%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5"></div>
        </div>
        <small class="text-muted d-block mt-2">Progress is estimated while the server builds and validates the complete schedule.</small>
      </div>
    </div>
  </div>

  <div id="preview-summary" class="row g-3 mb-3 d-none"></div>
  <div id="preview-warnings"></div>
  <div id="preview-view-controls" class="d-none flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <strong>Fixtures per venue</strong>
      <div class="small text-muted">Switch to the court grid to spot unused capacity between matches.</div>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Venue schedule view">
      <button type="button" class="btn btn-primary active" data-preview-view="timeline" aria-pressed="true"><i class="ti ti-list me-1" aria-hidden="true"></i>Fixture list</button>
      <button type="button" class="btn btn-outline-primary" data-preview-view="grid" aria-pressed="false"><i class="ti ti-calendar-time me-1" aria-hidden="true"></i>Court slot grid</button>
    </div>
  </div>
  <div id="venue-timelines"></div>
  <div id="venue-slot-grids" class="d-none"></div>
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
  const assignmentUrl = @json(route('backend.event-venue-schedule.assignments', $event));
  const venueUrl = @json(route('backend.event-venue-schedule.venues', $event));
  const courtUrl = @json(route('backend.event-venue-schedule.courts', $event));
  const announcementUrl = @json(route('admin.events.announcements.store', $event));
  const eventName = @json($event->name);
  const drawIds = @json($draws->reject(fn($draw) => $draw['locked'] || $draw['published'])->pluck('id')->values());
  let payload = null;
  let revision = null;
  let allocationsDirty = false;
  let previewActivityTimer = null;

  const previewActivityStages = [
    {after: 0, percent: 5, label: 'Sending the scheduling rules…'},
    {after: 700, percent: 18, label: 'Loading selected draws, venues, and courts…'},
    {after: 2200, percent: 36, label: 'Checking match order, byes, and player rest…'},
    {after: 5000, percent: 58, label: 'Finding available court times across venues…'},
    {after: 9000, percent: 74, label: 'Resolving court and player conflicts…'},
    {after: 15000, percent: 86, label: 'Balancing age groups across the timetable…'},
    {after: 25000, percent: 94, label: 'Finalising and validating the preview…'},
  ];

  const values = selector => [...document.querySelectorAll(selector + ':checked')].map(el => Number(el.value));
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const setStatus = (element, message, tone = 'muted') => {
    element.textContent = message;
    element.classList.remove('text-muted', 'text-danger', 'text-success', 'text-warning');
    element.classList.add(`text-${tone}`);
  };
  const updatePreviewActivity = startedAt => {
    const elapsed = Math.max(0, performance.now() - startedAt);
    const stage = [...previewActivityStages].reverse().find(item => elapsed >= item.after) || previewActivityStages[0];
    const elapsedSeconds = Math.floor(elapsed / 1000);
    document.getElementById('preview-activity-label').textContent = stage.label;
    document.getElementById('preview-activity-meta').textContent = `About ${stage.percent}% · ${elapsedSeconds}s elapsed`;
    const bar = document.getElementById('preview-activity-bar');
    bar.style.width = `${stage.percent}%`;
    bar.setAttribute('aria-valuenow', String(stage.percent));
  };
  const startPreviewActivity = () => {
    if (previewActivityTimer) window.clearInterval(previewActivityTimer);
    const startedAt = performance.now();
    document.getElementById('preview-activity').classList.remove('d-none');
    document.getElementById('preview-activity').setAttribute('aria-busy', 'true');
    updatePreviewActivity(startedAt);
    previewActivityTimer = window.setInterval(() => updatePreviewActivity(startedAt), 250);
  };
  const stopPreviewActivity = () => {
    if (previewActivityTimer) window.clearInterval(previewActivityTimer);
    previewActivityTimer = null;
    document.getElementById('preview-activity').setAttribute('aria-busy', 'false');
    document.getElementById('preview-activity').classList.add('d-none');
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
  const buildPayload = () => ({
    start: document.getElementById('schedule-start').value,
    end: document.getElementById('schedule-end').value || null,
    duration: Number(document.getElementById('schedule-duration').value),
    wave_minutes: Number(document.getElementById('schedule-wave').value),
    court_gap: Number(document.getElementById('schedule-gap').value),
    player_rest: Number(document.getElementById('schedule-rest').value),
    draw_ids: values('.draw-choice'),
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
  document.querySelectorAll('.assignment-choice').forEach(input => input.addEventListener('change', () => {
    document.querySelectorAll(`.court-allocation[data-draw="${input.dataset.draw}"][data-venue="${input.value}"]`)
      .forEach(court => { court.checked = input.checked; });
    markAllocationsDirty();
  }));
  document.querySelectorAll('.court-allocation').forEach(input => input.addEventListener('change', () => {
    const courts = [...document.querySelectorAll(`.court-allocation[data-draw="${input.dataset.draw}"][data-venue="${input.dataset.venue}"]`)];
    const venue = document.querySelector(`.assignment-choice[data-draw="${input.dataset.draw}"][value="${input.dataset.venue}"]`);
    venue.checked = courts.some(court => court.checked);
    markAllocationsDirty();
  }));
  document.querySelectorAll('.draw-choice, .draw-start, #schedule-start, #schedule-end, #schedule-duration, #schedule-wave, #schedule-gap, #schedule-rest')
    .forEach(input => input.addEventListener('change', () => invalidatePreview()));
  document.querySelectorAll('.draw-choice').forEach(input => input.addEventListener('change', () => {
    const start = document.querySelector(`.draw-start[data-draw="${input.value}"]`);
    if (start) start.disabled = !input.checked;
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
  const matchEnd = (match, result) => match.ends_at
    ? asDate(match.ends_at)
    : new Date(dateKey(match.scheduled_at) + (Number(match.duration || result.input.duration) + Number(result.input.courtGap || 0)) * 60000);
  const venueRows = (result, venueId) => [...result.matches.map(match => ({...match, fixed:false})),
    ...(result.existing_matches || []).map(match => ({...match, fixed:true}))]
    .filter(match => Number(match.venue_id) === Number(venueId))
    .sort((a, b) => dateKey(a.scheduled_at) - dateKey(b.scheduled_at) || String(a.court).localeCompare(String(b.court), undefined, {numeric:true}));

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
          return `<td class="bg-label-primary"><div class="fw-semibold">${escapeHtml(starts.draw_name)}</div><div class="small">R${starts.round} · M${escapeHtml(starts.match || '—')}${starts.fixed ? ' · Existing' : ''}</div><div class="small text-muted">${escapeHtml((starts.participants || []).join(' / ') || 'Players TBD')}</div></td>`;
        }
        const occupied = rows.find(row => String(row.court) === String(court) && asDate(row.scheduled_at) < time && matchEnd(row, result) > time);
        if (occupied) return `<td class="bg-label-secondary text-muted"><span class="fw-semibold">In use</span><div class="small">until ${escapeHtml(matchEnd(occupied, result).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}))}</div></td>`;
        return '<td class="text-success"><span class="fw-semibold">Available</span><div class="small text-muted">No fixture in this slot</div></td>';
      }).join('');
      return `<tr><th class="text-nowrap">${escapeHtml(formatSlotTime(time))}</th>${cells}</tr>`;
    }).join('');
    const truncated = times.size >= 200 ? '<div class="alert alert-warning py-2 mb-0">Only the first 200 time rows are shown. Shorten the scheduling window to inspect it in more detail.</div>' : '';

    return `<div class="card mb-4"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><h5 class="mb-0">${escapeHtml(venue.name)}</h5><span>${venue.courts} courts · ${rows.length} fixtures</span></div><div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Slot starts</th>${courtHeaders}</tr></thead><tbody>${body || '<tr><td colspan="99" class="text-center text-muted py-4">No slots in this scheduling window.</td></tr>'}</tbody></table></div>${truncated}</div>`;
  }

  function render(result) {
    revision = result.revision;
    document.getElementById('preview-summary').classList.remove('d-none');
    document.getElementById('preview-summary').innerHTML = card(result.matches.length, 'Court bookings', 'success') + card(result.automatic_byes, 'Automatic byes') + card(result.venues.length, 'Venues') + card(result.unscheduled.length, 'Unscheduled', result.unscheduled.length ? 'danger' : 'success');
    let warnings = (result.warnings || []).map(message => `<div class="alert alert-warning py-2">${escapeHtml(message)}</div>`).join('');
    if (result.unscheduled.length) warnings += `<div class="alert alert-danger"><strong>Resolve before applying:</strong><ul class="mb-0 mt-2">${result.unscheduled.map(row => `<li>${escapeHtml(row.draw_name)} R${row.round} M${row.match}: ${escapeHtml(row.reason)}</li>`).join('')}</ul></div>`;
    document.getElementById('preview-warnings').innerHTML = warnings;
    document.getElementById('preview-view-controls').classList.remove('d-none');
    document.getElementById('preview-view-controls').classList.add('d-flex');
    document.getElementById('venue-timelines').innerHTML = result.venues.map(venue => {
      const rows = venueRows(result, venue.id);
      return `<div class="card mb-4"><div class="card-header d-flex justify-content-between"><h5 class="mb-0">${escapeHtml(venue.name)}</h5><span>${venue.courts} courts · ${rows.length} fixtures</span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>Court</th><th>Age group / draw</th><th>Round</th><th>Match</th><th>Players</th></tr></thead><tbody>${rows.map(row => `<tr><td class="text-nowrap fw-semibold">${escapeHtml(row.scheduled_at.slice(0,16))}${row.fixed ? '<span class="badge bg-label-secondary ms-2">Existing</span>' : ''}</td><td>${escapeHtml(row.court)}</td><td>${escapeHtml(row.draw_name)}</td><td>${row.fixed ? '' : `Wave ${row.wave} · `}R${row.round}</td><td>${escapeHtml(row.match || '—')}</td><td>${escapeHtml((row.participants || []).join(' / ') || 'TBD')}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No fixtures allocated.</td></tr>'}</tbody></table></div></div>`;
    }).join('');
    document.getElementById('venue-slot-grids').innerHTML = result.venues.map(venue => slotGrid(result, venue)).join('');
    document.getElementById('apply-preview').disabled = result.unscheduled.length > 0 || result.matches.length === 0;
    setStatus(document.getElementById('schedule-status'), result.unscheduled.length
      ? 'Preview needs attention. Resolve every unscheduled match before applying.'
      : 'Preview ready. Review every venue before applying.', result.unscheduled.length ? 'danger' : 'success');
  }

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
    startPreviewActivity();
    try { render(await post(previewUrl, payload)); }
    catch (error) { setStatus(document.getElementById('schedule-status'), error.message, 'danger'); }
    finally { stopPreviewActivity(); button.innerHTML = originalButtonHtml; button.disabled = false; }
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
    const button = event.currentTarget; button.disabled = true; setStatus(document.getElementById('schedule-status'), 'Applying and revalidating…');
    try { const result = await post(applyUrl, {...payload, revision}); setStatus(document.getElementById('schedule-status'), `Applied ${result.count} matches successfully.`, 'success'); }
    catch (error) { setStatus(document.getElementById('schedule-status'), error.message, 'danger'); button.disabled = false; }
  });
})();
</script>
@endsection
