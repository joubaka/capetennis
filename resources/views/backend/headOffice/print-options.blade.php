@extends('layouts.backend')
@section('title', 'Print options')
@section('page-style')
<style>
  .print-options .btn, .print-options .form-select, .print-options .form-control, .print-options .print-choice { min-height:44px; }
  .print-options .print-choice { display:flex; align-items:center; gap:.75rem; padding:.5rem; }
  .print-options .print-choice input { flex-shrink:0; }
  .print-options .btn { white-space:normal; }
  .print-options { font-size:14px; }
  .print-group-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr)); gap:1rem; }
  .print-group { border:1px solid #d9e2eb; border-radius:10px; background:#fff; min-width:0; }
  .print-group summary { min-height:56px; padding:14px 16px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; font-weight:600; list-style:none; }
  .print-group summary::-webkit-details-marker { display:none; }
  .print-group summary::after { content:'⌄'; font-size:20px; }
  .print-group[open] summary::after { content:'⌃'; }
  .print-group summary:focus-visible { outline:2px solid #172e45; outline-offset:2px; }
  .print-group-body { padding:0 16px 16px; }
  .print-draw-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:14px 0; border-top:1px solid #e4eaf0; }
  .print-draw-name { flex:1 1 160px; overflow-wrap:anywhere; }
  .print-draw-actions { display:flex; gap:8px; flex-wrap:wrap; }
  .print-venue-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); grid-auto-rows:1fr; gap:12px; }
  .print-venue-tool { display:flex; min-height:96px; min-width:0; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px solid #e4eaf0; border-radius:8px; }
  .print-venue-tool span { flex:1; min-width:0; overflow-wrap:anywhere; }
  .print-venue-tool .btn { flex:0 0 76px; width:76px; min-height:44px; }
  @media(max-width:991px) { .print-venue-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media(max-width:575px) { .print-venue-grid { grid-template-columns:minmax(0,1fr); } }
  @media(max-width:575px) { .print-draw-actions { width:100%; } .print-draw-actions .btn { flex:1; } }
</style>
@endsection
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'draws', 'eventWorkspaceIcon' => 'ti-printer', 'eventWorkspaceSubtitle' => 'Draws, age groups and venue printing'])
<div class="print-options">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h2>Print options</h2><p class="mb-0 text-muted">Choose what to print for {{ $event->name }}. Preview a sheet or download a PDF.</p></div>
    <a class="btn btn-outline-primary" href="{{ route('headOffice.show', $event) }}">Back to draws</a>
  </div>
  @if($event->draws->isEmpty())
    <div class="alert alert-info">Create a draw to make draw printing available.</div>
  @endif
  @php($hasTeamDraws = $event->draws->contains(fn ($draw) => $draw->isTeamDraw()))
  @php($individualDraws = $event->draws->reject(fn ($draw) => $draw->isTeamDraw()))
  <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Print sections">
    @if($hasTeamDraws)<a class="btn btn-outline-primary" href="#venue-print-tools">Venues</a><a class="btn btn-outline-primary" href="#team-print-tools">Team draws by age</a>@endif
    @if($individualDraws->isNotEmpty())<a class="btn btn-outline-primary" href="#individual-print-options">Individual draw packs</a>@endif
  </nav>
  @if($hasTeamDraws)
  <section class="card mb-4" id="venue-print-tools"><div class="card-body">
    <h3 class="h5">Venue sheets</h3><p class="text-muted">Scheduled team matches and lineups, grouped by venue. Open a sheet, then choose Print / Save PDF.</p>
    @forelse($venueGroups as $ageLabel => $ageVenues)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-2">
      <h4 class="h6 mb-0">{{ $ageLabel }}</h4>
      @if($ageLabel !== 'Other venues')
      <a class="btn btn-outline-primary" href="{{ route('headoffice.venuePrintPack', ['event' => $event, 'age' => (int) substr($ageLabel, 6)]) }}" target="_blank" rel="noopener" aria-label="Print all venues for {{ $ageLabel }}">Print all venues</a>
      @endif
    </div>
    @if($ageVenues->isEmpty())<p class="small text-muted">These matches use shared venues listed under a younger age above.</p>@endif
    <div class="print-venue-grid">
      @foreach($ageVenues as $venue)
      <div class="print-venue-tool"><span>{{ $venue->name }}@if($venueAges->get($venue->id)->isNotEmpty())<small class="d-block text-muted">{{ $venueAges->get($venue->id)->map(fn ($age) => 'U'.$age)->implode(' · ') }}</small>@endif</span><a class="btn btn-primary" href="{{ route('headoffice.venue.fixtures', ['event' => $event, 'venue' => $venue]) }}" target="_blank" rel="noopener" aria-label="Print venue {{ $venue->name }}">Print</a></div>
      @endforeach
    </div>
    @empty
      <p class="mb-0">No venue matches have been scheduled yet.</p>
    @endforelse
  </div></section>
  @endif
  @if($individualDraws->isNotEmpty())
  <form id="individual-print-options" action="{{ route('headoffice.drawPack', $event) }}" method="get" target="_blank" class="card mb-4">
    <div class="card-body">
      <h3 class="h5">Individual draws</h3>
      <p class="text-muted">Select all draws, an age group, or individual draws. Bracket PDFs require only Flexible Monrad draws.</p>
      <label class="print-choice"><input type="checkbox" data-select-all checked> Select all individual draws</label>
      @foreach($drawGroups as $label => $draws)
        @php($choices = $draws->reject(fn ($draw) => $draw->isTeamDraw()))
        @if($choices->isNotEmpty())
        <details class="print-group mb-3" data-print-group>
          <summary>{{ $label }} <span class="badge bg-label-primary">{{ $choices->count() }} draws</span></summary>
          <div class="print-group-body">
          <label class="print-choice"><input type="checkbox" data-select-group checked> Select this age group</label>
          @foreach($choices as $draw)
          <label class="print-choice"><input type="checkbox" name="draw_ids[]" value="{{ $draw->id }}" checked> {{ $draw->drawName }}</label>
          @endforeach
          </div>
        </details>
        @endif
      @endforeach
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label for="print-type" class="form-label">Print layout</label><select id="print-type" name="print_type" class="form-select">
          <option value="pack">Complete draw pack: players, fixtures and schedule</option>
          <option value="venue">Venue order of play / match schedule</option>
          <option value="bracket">Flexible Monrad brackets (PDF)</option>
          <option value="matrix">Round-robin matrices (PDF)</option>
          <option value="fixtures">Individual fixtures only (PDF)</option>
          <option value="combined">Fixtures and matrices (PDF)</option>
        </select></div>
        <div class="col-md-6"><label for="schedule-source" class="form-label">Venue schedule version</label><select id="schedule-source" name="schedule_source" class="form-select"><option value="published">Published schedule</option><option value="working">Working schedule</option></select></div>
        <div class="col-md-6"><label for="print-date" class="form-label">Venue schedule day</label><input class="form-control" id="print-date" name="date" type="date"><small class="text-muted">Leave blank for every day.</small></div>
        <div class="col-md-6"><label for="print-venue" class="form-label">Venue schedule venue</label><select class="form-select" id="print-venue" name="venue_id"><option value="">All venues</option>@foreach($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}</option>@endforeach</select></div>
      </div>
      <p class="small text-muted">Day, venue and schedule version apply to venue order of play. Printing does not publish draws or match times.</p>
      <input type="hidden" name="include_standings" value="0">
      <label class="print-choice mb-3"><input type="checkbox" name="include_standings" value="1"> Include standings</label>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit" name="download" value="0">Preview / print</button><button class="btn btn-outline-primary" type="submit" name="download" value="1">Download PDF</button></div>
      <p class="small text-muted mt-2 mb-0" id="print-layout-help">Use your browser’s Print or Save as PDF from the preview.</p>
    </div>
  </form>
  @endif
  @if($hasTeamDraws)
  <section id="team-print-tools" class="mb-4">
    <h3 class="h5">Team draws by age group</h3><p class="text-muted">Expand an age group to print a draw’s team fixtures, players and scores, or download its PDF.</p>
    <div class="print-group-grid">
    @foreach($drawGroups as $label => $draws)
      @php($teamDraws = $draws->filter(fn ($draw) => $draw->isTeamDraw() && auth()->user()->can('fixture.view', $draw)))
      @if($teamDraws->isNotEmpty())
      <details class="print-group">
        <summary>{{ $label }} <span class="badge bg-label-primary">{{ $teamDraws->count() }} draws</span></summary>
        <div class="print-group-body">
        @foreach($teamDraws as $draw)
          <div class="print-draw-row">
            <strong class="print-draw-name">{{ $draw->drawName ?: 'Draw #'.$draw->id }}</strong>
            <div class="print-draw-actions">
              <a class="btn btn-primary" href="{{ route('fixture.create.pdf', ['fixtures' => $draw->id, 'preview' => 1]) }}" target="_blank" rel="noopener" aria-label="Print {{ $draw->drawName ?: 'Draw #'.$draw->id }}">Print</a>
              <a class="btn btn-outline-primary" href="{{ route('fixture.create.pdf', ['fixtures' => $draw->id]) }}" aria-label="Download PDF for {{ $draw->drawName ?: 'Draw #'.$draw->id }}">Download PDF</a>
            </div>
          </div>
        @endforeach
        </div>
      </details>
      @endif
    @endforeach
    </div>
  </section>
  @endif
</div>
@endsection
@section('page-script')
<script>
document.getElementById('individual-print-options')?.addEventListener('change', function (event) {
  if (event.target.matches('[data-select-all]')) this.querySelectorAll('input[type="checkbox"]:not([name="include_standings"])').forEach(input => input.checked = event.target.checked);
  if (event.target.matches('[data-select-group]')) event.target.closest('[data-print-group]').querySelectorAll('[name="draw_ids[]"]').forEach(input => input.checked = event.target.checked);
  const sync = (toggle, inputs) => {
    const selected = Array.from(inputs).filter(input => input.checked).length;
    toggle.checked = selected === inputs.length;
    toggle.indeterminate = selected > 0 && selected < inputs.length;
  };
  this.querySelectorAll('[data-print-group]').forEach(group => sync(group.querySelector('[data-select-group]'), group.querySelectorAll('[name="draw_ids[]"]')));
  sync(this.querySelector('[data-select-all]'), this.querySelectorAll('[name="draw_ids[]"]'));
  if (event.target.id === 'print-type') {
    const pdfOnly = ['bracket', 'matrix', 'combined', 'fixtures'].includes(event.target.value);
    this.querySelector('[name="download"][value="0"]').disabled = pdfOnly;
    document.getElementById('print-layout-help').textContent = pdfOnly ? 'This layout is available as a PDF download.' : 'Use your browser’s Print or Save as PDF from the preview.';
  }
});
document.getElementById('individual-print-options')?.addEventListener('submit', function (event) {
  if (!this.querySelector('[name="draw_ids[]"]:checked')) { event.preventDefault(); alert('Select at least one draw.'); return; }
  this.action = ['matrix', 'combined', 'fixtures'].includes(document.getElementById('print-type').value) ? @json(route('headoffice.printDrawsPdf', $event)) : @json(route('headoffice.drawPack', $event));
});
</script>
@endsection
