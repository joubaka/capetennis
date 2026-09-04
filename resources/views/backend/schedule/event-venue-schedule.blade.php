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
    <a class="btn btn-outline-secondary" href="{{ url()->previous() }}"><i class="ti ti-arrow-left me-1"></i>Back</a>
  </div>

  <div class="alert alert-info">
    A bye uses no court but still advances through its round wave. A player with two byes will therefore first appear in the third wave, not at the event start.
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-1">1. Select draws and venues</h5><small class="text-muted">A draw can use only its assigned venues. Shared venues contribute one physical court pool.</small></div>
    <div class="card-body row g-4">
      <div class="col-lg-7">
        <div class="row g-2">
          @forelse($draws as $draw)
            <div class="col-md-6">
              <div class="border rounded p-3 h-100 {{ $draw['locked'] || $draw['published'] ? 'bg-light text-muted' : '' }}">
                <label class="d-flex gap-2 mb-2">
                <input class="form-check-input draw-choice" type="checkbox" value="{{ $draw['id'] }}" {{ $draw['locked'] || $draw['published'] ? 'disabled' : 'checked' }}>
                <strong>{{ $draw['name'] }}</strong>
                </label>
                <label class="small text-muted d-block mb-2">Start this age group later (optional)<input class="form-control form-control-sm draw-start mt-1" data-draw="{{ $draw['id'] }}" type="datetime-local" {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}></label>
                <div class="small text-muted mb-1">Permitted venues</div>
                <div class="d-grid gap-2">
                  @foreach($venues as $venue)
                    @php
                      $venueAssigned = in_array($venue['id'], $draw['venues']);
                      $allocatedLabels = $draw['court_allocations'][$venue['id']] ?? [];
                    @endphp
                    <div class="border rounded p-2">
                      <label class="small fw-semibold"><input class="form-check-input assignment-choice me-1" data-draw="{{ $draw['id'] }}" type="checkbox" value="{{ $venue['id'] }}" {{ $venueAssigned ? 'checked' : '' }} {{ $draw['locked'] || $draw['published'] ? 'disabled' : '' }}>{{ $venue['name'] }}</label>
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
        <div class="border rounded p-3 mb-3 bg-light">
          <h6>Add another venue</h6>
          <select id="new-venue-id" class="form-select form-select-sm mb-2"><option value="">Create a new venue…</option>@foreach($allVenues as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
          <input id="new-venue-name" class="form-control form-control-sm mb-2" placeholder="New venue name">
          <div class="row g-2"><div class="col-6"><input id="new-venue-courts" type="number" class="form-control form-control-sm" value="1" min="1" max="100" aria-label="Number of courts"></div><div class="col-6"><select id="new-venue-ball" class="form-select form-select-sm"><option value="yellow">Yellow / standard</option><option value="orange">Orange ball</option><option value="green">Green ball</option><option value="red">Red ball</option></select></div></div>
          <button id="add-venue" class="btn btn-sm btn-primary mt-2"><i class="ti ti-plus me-1"></i>Add venue and courts</button>
          <div id="venue-add-status" class="small text-muted mt-2"></div>
        </div>
        @forelse($venues as $venue)
          <div class="border rounded p-3 mb-2">
            <div class="d-flex align-items-center justify-content-between gap-2"><span><input class="form-check-input venue-choice me-2" type="checkbox" value="{{ $venue['id'] }}" checked><strong>{{ $venue['name'] }}</strong></span><span class="badge bg-label-primary">{{ $venue['courts'] }} courts</span></div>
            <div class="d-flex flex-wrap gap-1 mt-2">@foreach($venue['court_list'] as $court)<span class="badge bg-label-secondary">Court {{ $court['label'] }}@if($court['ball_type']) · {{ ucfirst($court['ball_type']) }}@endif</span>@endforeach</div>
            <div class="row g-2 mt-2"><div class="col-5"><input class="form-control form-control-sm add-court-label" data-venue="{{ $venue['id'] }}" placeholder="Court label"></div><div class="col-4"><select class="form-select form-select-sm add-court-ball" data-venue="{{ $venue['id'] }}"><option value="yellow">Yellow</option><option value="orange">Orange</option><option value="green">Green</option><option value="red">Red</option></select></div><div class="col-3"><button class="btn btn-sm btn-outline-primary w-100 add-court" data-venue="{{ $venue['id'] }}">Add</button></div></div>
          </div>
        @empty
          <div class="alert alert-warning mb-0">Assign at least one venue to an age-group draw before generating a schedule.</div>
        @endforelse
      </div>
      @if($draws->isNotEmpty() && $venues->isNotEmpty())
        <div class="col-12 d-flex align-items-center gap-2">
          <button id="save-allocations" class="btn btn-outline-primary"><i class="ti ti-map-pin me-1"></i>Save age-group venue allocations</button>
          <span id="allocation-status" class="small text-muted"></span>
        </div>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-1">2. Scheduling rules</h5><small class="text-muted">Player rest and court turnaround are calculated separately.</small></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Start</label><input id="schedule-start" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T08:00"></div>
        <div class="col-md-3"><label class="form-label">End</label><input id="schedule-end" type="datetime-local" class="form-control" value="{{ optional($event->start_date)->format('Y-m-d') }}T18:00"></div>
        <div class="col-6 col-md-2"><label class="form-label">Match minutes</label><input id="schedule-duration" type="number" class="form-control" value="75" min="15"></div>
        <div class="col-6 col-md-2"><label class="form-label">Round wave</label><input id="schedule-wave" type="number" class="form-control" value="90" min="15"></div>
        <div class="col-6 col-md-1"><label class="form-label">Court gap</label><input id="schedule-gap" type="number" class="form-control" value="5" min="0"></div>
        <div class="col-6 col-md-1"><label class="form-label">Rest</label><input id="schedule-rest" type="number" class="form-control" value="60" min="0"></div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button id="generate-preview" class="btn btn-primary"><i class="ti ti-wand me-1"></i>Generate combined preview</button>
        <button id="apply-preview" class="btn btn-success" disabled><i class="ti ti-device-floppy me-1"></i>Apply schedule</button>
        <span id="schedule-status" class="align-self-center text-muted small"></span>
      </div>
    </div>
  </div>

  <div id="preview-summary" class="row g-3 mb-3 d-none"></div>
  <div id="preview-warnings"></div>
  <div id="venue-timelines"></div>
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
  const drawIds = @json($draws->reject(fn($draw) => $draw['locked'] || $draw['published'])->pluck('id')->values());
  let payload = null;
  let revision = null;

  const values = selector => [...document.querySelectorAll(selector + ':checked')].map(el => Number(el.value));
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const buildPayload = () => ({
    start: document.getElementById('schedule-start').value,
    end: document.getElementById('schedule-end').value || null,
    duration: Number(document.getElementById('schedule-duration').value),
    wave_minutes: Number(document.getElementById('schedule-wave').value),
    court_gap: Number(document.getElementById('schedule-gap').value),
    player_rest: Number(document.getElementById('schedule-rest').value),
    draw_ids: values('.draw-choice'), venue_ids: values('.venue-choice'),
    draw_starts: [...document.querySelectorAll('.draw-start')].filter(input => input.value && document.querySelector(`.draw-choice[value="${input.dataset.draw}"]`)?.checked).map(input => ({draw_id:Number(input.dataset.draw), start:input.value}))
  });
  const post = async (url, body) => {
    const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body)});
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Request failed.');
    return data;
  };
  document.querySelectorAll('.assignment-choice').forEach(input => input.addEventListener('change', () => {
    document.querySelectorAll(`.court-allocation[data-draw="${input.dataset.draw}"][data-venue="${input.value}"]`)
      .forEach(court => { court.checked = input.checked; });
  }));
  const card = (value, label, tone='primary') => `<div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><div class="fs-4 fw-bold text-${tone}">${value}</div><small class="text-muted">${label}</small></div></div></div>`;

  function render(result) {
    revision = result.revision;
    document.getElementById('preview-summary').classList.remove('d-none');
    document.getElementById('preview-summary').innerHTML = card(result.matches.length, 'Court bookings', 'success') + card(result.automatic_byes, 'Automatic byes') + card(result.venues.length, 'Venues') + card(result.unscheduled.length, 'Unscheduled', result.unscheduled.length ? 'danger' : 'success');
    let warnings = (result.warnings || []).map(message => `<div class="alert alert-warning py-2">${escapeHtml(message)}</div>`).join('');
    if (result.unscheduled.length) warnings += `<div class="alert alert-danger"><strong>Resolve before applying:</strong><ul class="mb-0 mt-2">${result.unscheduled.map(row => `<li>${escapeHtml(row.draw_name)} R${row.round} M${row.match}: ${escapeHtml(row.reason)}</li>`).join('')}</ul></div>`;
    document.getElementById('preview-warnings').innerHTML = warnings;
    document.getElementById('venue-timelines').innerHTML = result.venues.map(venue => {
      const rows = result.matches.filter(match => match.venue_id === venue.id);
      return `<div class="card mb-4"><div class="card-header d-flex justify-content-between"><h5 class="mb-0">${escapeHtml(venue.name)}</h5><span>${venue.courts} courts · ${rows.length} matches</span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>Court</th><th>Age group / draw</th><th>Round</th><th>Match</th><th>Players</th></tr></thead><tbody>${rows.map(row => `<tr><td class="text-nowrap fw-semibold">${escapeHtml(row.scheduled_at.slice(0,16))}</td><td>${escapeHtml(row.court)}</td><td>${escapeHtml(row.draw_name)}</td><td>Wave ${row.wave} · R${row.round}</td><td>${escapeHtml(row.match)}</td><td>${escapeHtml((row.participants || []).join(' / ') || 'TBD')}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No matches allocated.</td></tr>'}</tbody></table></div></div>`;
    }).join('');
    document.getElementById('apply-preview').disabled = result.unscheduled.length > 0 || result.matches.length === 0;
    document.getElementById('schedule-status').textContent = 'Preview ready. Review every venue before applying.';
  }

  document.getElementById('generate-preview').addEventListener('click', async event => {
    const button = event.currentTarget; payload = buildPayload(); revision = null; button.disabled = true;
    document.getElementById('apply-preview').disabled = true; document.getElementById('schedule-status').textContent = 'Building preview…';
    try { render(await post(previewUrl, payload)); }
    catch (error) { document.getElementById('schedule-status').textContent = error.message; }
    finally { button.disabled = false; }
  });
  document.getElementById('save-allocations')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const venues = @json($venues->map(fn($venue) => ['id' => $venue['id'], 'courts' => $venue['courts']])->values());
    const assignments = drawIds.map(drawId => {
      const venueIds = [...document.querySelectorAll(`.assignment-choice[data-draw="${drawId}"]:checked`)].map(input => Number(input.value));
      const courtAllocations = venueIds.map(venueId => ({venue_id:venueId, court_labels:[...document.querySelectorAll(`.court-allocation[data-draw="${drawId}"][data-venue="${venueId}"]:checked`)].map(input => input.value)}));
      return {draw_id:Number(drawId), venue_ids:venueIds, court_allocations:courtAllocations};
    });
    button.disabled = true; document.getElementById('allocation-status').textContent = 'Saving…';
    try { const result = await post(assignmentUrl, {venues, assignments}); document.getElementById('allocation-status').textContent = result.message + ' Refreshing…'; window.location.reload(); }
    catch (error) { document.getElementById('allocation-status').textContent = error.message; button.disabled = false; }
  });
  document.getElementById('add-venue')?.addEventListener('click', async event => {
    const button = event.currentTarget; button.disabled = true;
    try { const result = await post(venueUrl, {venue_id:Number(document.getElementById('new-venue-id').value) || null, name:document.getElementById('new-venue-name').value || null, courts:Number(document.getElementById('new-venue-courts').value), ball_type:document.getElementById('new-venue-ball').value}); document.getElementById('venue-add-status').textContent = result.message; window.location.reload(); }
    catch (error) { document.getElementById('venue-add-status').textContent = error.message; button.disabled = false; }
  });
  document.querySelectorAll('.add-court').forEach(button => button.addEventListener('click', async event => {
    const venueId = Number(event.currentTarget.dataset.venue); event.currentTarget.disabled = true;
    const label = document.querySelector(`.add-court-label[data-venue="${venueId}"]`).value;
    const ballType = document.querySelector(`.add-court-ball[data-venue="${venueId}"]`).value;
    try { await post(courtUrl, {venue_id:venueId, label, ball_type:ballType}); window.location.reload(); }
    catch (error) { document.getElementById('allocation-status').textContent = error.message; event.currentTarget.disabled = false; }
  }));
  document.getElementById('apply-preview').addEventListener('click', async event => {
    if (!payload || !revision || !confirm('Apply this combined schedule to every selected draw?')) return;
    const button = event.currentTarget; button.disabled = true; document.getElementById('schedule-status').textContent = 'Applying and revalidating…';
    try { const result = await post(applyUrl, {...payload, revision}); document.getElementById('schedule-status').textContent = `Applied ${result.count} matches successfully.`; }
    catch (error) { document.getElementById('schedule-status').textContent = error.message; }
  });
})();
</script>
@endsection
