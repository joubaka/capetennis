@extends('layouts.backend')
@section('title', 'Print options')
@section('page-style')
<style>
  .print-options .btn, .print-options .form-select, .print-options .form-control, .print-options .print-choice { min-height:44px; }
  .print-options .print-choice { display:flex; align-items:center; gap:.75rem; padding:.5rem; }
  .print-options .print-choice input { flex-shrink:0; }
  .print-options .btn { white-space:normal; }
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
  @php($individualDraws = $event->draws->reject(fn ($draw) => $draw->isTeamDraw()))
  @if($individualDraws->isNotEmpty())
  <form id="individual-print-options" action="{{ route('headoffice.drawPack', $event) }}" method="get" target="_blank" class="card mb-4">
    <div class="card-body">
      <h3 class="h5">Individual draws</h3>
      <p class="text-muted">Select all draws, an age group, or individual draws. Bracket PDFs require only Flexible Monrad draws.</p>
      <label class="print-choice"><input type="checkbox" data-select-all checked> Select all individual draws</label>
      @foreach($drawGroups as $label => $draws)
        @php($choices = $draws->reject(fn ($draw) => $draw->isTeamDraw()))
        @if($choices->isNotEmpty())
        <fieldset class="border rounded p-3 mb-3" data-print-group>
          <legend class="float-none w-auto h6 px-2">{{ $label }}</legend>
          <label class="print-choice"><input type="checkbox" data-select-group checked> Select this age group</label>
          @foreach($choices as $draw)
          <label class="print-choice"><input type="checkbox" name="draw_ids[]" value="{{ $draw->id }}" checked> {{ $draw->drawName }}</label>
          @endforeach
        </fieldset>
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
  @if($event->draws->contains(fn ($draw) => $draw->isTeamDraw()))
  <div class="card mb-4"><div class="card-body">
    <h3 class="h5">Team draws by age group</h3><p class="text-muted">Download each draw’s team fixtures, players and scores.</p>
    @foreach($drawGroups as $label => $draws)
      @php($teamDraws = $draws->filter(fn ($draw) => $draw->isTeamDraw()))
      @if($teamDraws->isNotEmpty())
      <h4 class="h6 mt-3">{{ $label }}</h4><div class="d-flex flex-wrap gap-2">
        @foreach($teamDraws as $draw)
          @can('fixture.view', $draw)
          <a class="btn btn-outline-primary" href="{{ route('fixture.create.pdf', ['fixtures' => $draw->id]) }}">{{ $draw->drawName ?: 'Draw #'.$draw->id }} — PDF</a>
          @endcan
        @endforeach
      </div>
      @endif
    @endforeach
  </div></div>
  <div class="card mb-4"><div class="card-body"><h3 class="h5">Team fixtures by venue</h3><p class="text-muted">Open the venue sheet, then choose Print / Save PDF for scheduled matches and lineups.</p><div class="d-flex flex-wrap gap-2">
    @forelse($venues as $venue)<a class="btn btn-outline-primary" href="{{ route('headoffice.venue.fixtures', ['event' => $event, 'venue' => $venue]) }}">{{ $venue->name }} — preview / print</a>@empty<p>No venues assigned to this event yet.</p>@endforelse
  </div></div></div>
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
