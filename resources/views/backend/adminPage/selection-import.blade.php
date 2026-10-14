@extends('layouts.backend')
@section('title', 'Import selected team - '.$event->name)
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'teams', 'eventWorkspaceSubtitle' => 'Import selected teams and reserves from a finalized event'])
<div class="card mb-3"><div class="card-body">
  <h3>Import selected team + reserves</h3>
  <p>Choose a source event, then review its approved selected team or select source regions. Players and reserves keep their saved order.</p>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($previewErrors)<div class="alert alert-danger" role="alert"><strong>Unable to review these teams.</strong><ul class="mb-0">@foreach($previewErrors as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="GET" action="{{ route('backend.selection-import.index', $event) }}" id="source-event-form" class="row g-3 align-items-end">
    <input type="hidden" name="choose_regions" value="1">
    <div class="col-12 col-md-8"><label class="form-label" for="source-event">Source event</label><select id="source-event" name="source_event_id" class="form-select" required><option value="">Choose a source event</option>@if($sourceEvent && !$sources->contains('id', $sourceEvent->id))<option value="{{ $sourceEvent->id }}" selected>{{ $sourceEvent->name }}</option>@endif @foreach($sources as $source)<option value="{{ $source->id }}" @selected((int)request('source_event_id') === (int)$source->id)>{{ $source->name }}</option>@endforeach</select></div>
    <div class="col-12 col-md-4"><button class="btn btn-primary" style="min-height:44px">Show source options</button></div>
  </form>
  @if($sources->isEmpty())<p class="mt-3 mb-0">No source team events are available for you to manage.</p>@endif
  <div class="mt-3">{{ $sources->withQueryString()->links('pagination::bootstrap-5') }}</div>
</div></div>
<p id="source-choices-status" role="status" class="mt-3" hidden></p>
<div id="source-choices" aria-live="polite">
@include('backend.adminPage.partials.selection-import-source-choices')
</div>
@if($preview)
<form id="selection-import-review" method="POST" action="{{ route('backend.selection-import.store', $event) }}" class="card mb-3"><div class="card-body">
  @csrf
  <input type="hidden" name="finalization_id" value="{{ $finalization->id }}">
  @if($importMode === 'approved_team')
    <input type="hidden" name="import_mode" value="approved_team">
    <input type="hidden" name="source_event_id" value="{{ $sourceEvent->id }}">
    <p class="alert alert-info">Importing the approved selected team from {{ $sourceEvent->name }}, including all reserves.</p>
  @elseif($selectedRegionIds)
    <input type="hidden" name="import_mode" value="regions">
  @endif
  @foreach($selectedRegionIds as $selectedRegionId)<input type="hidden" name="source_region_ids[]" value="{{ $selectedRegionId }}">@endforeach
  <h4>{{ $preview['source']->name }}</h4><p>Finalized {{ $finalization->finalized_at->format('d M Y H:i') }}. Only selected players and reserves are included.</p>
  <label for="destination-region" class="form-label">Destination region</label><select id="destination-region" class="form-select mb-3" name="region_id" required><option value="">Choose the region these teams represent</option>@foreach($regions as $region)<option value="{{ $region->id }}" @selected((int)old('region_id') === (int)$region->id)>{{ $region->region_name }}</option>@endforeach</select>
  @if($regions->isEmpty())<div class="alert alert-warning">Add a region to this event before importing teams.</div>@endif
  @foreach($preview['groups'] as $key => $group)
  <section class="border rounded p-3 mb-3" aria-label="{{ $group['label'] }}"><h5>{{ $group['label'] }} · {{ $group['main_count'] }} team players + {{ $group['reserve_count'] }} reserves</h5>
    <p class="small">Source regions: {{ $sourceRegions->whereIn('id', $group['source_region_ids'])->pluck('region_name')->implode(', ') }}</p>
    <label class="form-label" for="destination-{{ $key }}">Destination team</label><select id="destination-{{ $key }}" name="mapping[{{ $key }}]" class="form-select mb-3" data-destination-team><option value="0">Create a team in the chosen region</option>@foreach($group['teams'] as $team)<option value="{{ $team->id }}" data-region-id="{{ $team->region_id }}" @selected((int)old('mapping.'.$key) === (int)$team->id)>{{ $team->name }}</option>@endforeach</select>
    <ol class="mb-0">@foreach($group['players'] as $player)<li>{{ $player['name'] }} @if($player['status'] === 'reserve')<span class="badge bg-label-warning">Reserve</span>@endif @if($player['profile_link_review'] ?? false)<span class="badge bg-label-info">Review profile link after import</span>@endif</li>@endforeach</ol>
  </section>
  @endforeach
  <p>Existing teams must be empty and private. New rosters start unpaid and private. Main players and reserves keep their finalized order.</p>
  <label class="d-block mb-3"><input type="checkbox" name="confirm_import" value="1" required> I have reviewed these teams and their destination region.</label>
  <p id="regions-changed" class="alert alert-warning" role="status" hidden>Regions changed. Click Review selected regions again before importing.</p>
  <button class="btn btn-primary" id="import-selected-regions" data-import-mode="{{ $importMode }}" style="min-height:44px" @disabled($regions->isEmpty())>Import teams and reserves</button>
</div></form>
@endif
@foreach($imports as $import)
<section class="card mb-3"><div class="card-body"><h4>Imported teams · {{ $import->region->region_name }}</h4><p>{{ $import->imported_at->format('d M Y H:i') }} · Source event #{{ $import->finalization->event_id }}</p>
@foreach($import->groups as $group)<h5>{{ $group['team_name'] }}</h5><ol>@foreach($group['players'] as $player)<li>{{ $player['name'] }} @if($player['status'] === 'reserve')<span class="badge bg-label-warning">Reserve</span>@endif</li>@endforeach</ol>@endforeach
<a class="btn btn-outline-primary" href="{{ route('admin.events.teams', $event) }}">Manage event rosters</a>
</div></section>
@endforeach
{{ $imports->withQueryString()->links('pagination::bootstrap-5') }}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const choices = document.getElementById('source-choices');
  const status = document.getElementById('source-choices-status');
  const sourceForm = document.getElementById('source-event-form');
  const sourceSelect = document.getElementById('source-event');
  const applyMode = () => {
    const mode = choices.querySelector('input[name="source-choice-mode"]:checked')?.value;
    choices.querySelectorAll('[data-source-mode]').forEach(panel => panel.hidden = panel.dataset.sourceMode !== mode);
  };
  applyMode();
  choices.addEventListener('change', event => {
    if (event.target.matches('input[name="source-choice-mode"]')) {
      applyMode();
      document.getElementById('selection-import-review')?.remove();
    }
    if (event.target.matches('input[name="source_region_ids[]"][type="checkbox"]')) {
      const importButton = document.getElementById('import-selected-regions');
      if (importButton?.dataset.importMode === 'approved_team') return;
      if (importButton) importButton.disabled = true;
      const notice = document.getElementById('regions-changed');
      if (notice) notice.hidden = false;
    }
  });
  let sourceRequest = 0;
  let sourceAbort;
  const loadSource = async () => {
    const request = ++sourceRequest;
    sourceAbort?.abort();
    sourceAbort = new AbortController();
    choices.replaceChildren();
    document.getElementById('selection-import-review')?.remove();
    if (!sourceSelect.value) {
      choices.removeAttribute('aria-busy');
      status.hidden = false;
      status.textContent = 'Choose a source event to see its approved team and regions.';
      return;
    }
    status.hidden = false;
    status.textContent = 'Loading source options…';
    choices.setAttribute('aria-busy', 'true');
    const url = new URL(sourceForm.action);
    url.searchParams.set('source_event_id', sourceSelect.value);
    url.searchParams.set('source_choices', '1');
    try {
      const response = await fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: sourceAbort.signal, credentials: 'same-origin', cache: 'no-store' });
      if (!response.ok || response.redirected || response.headers.get('X-Selection-Source-Choices') !== '1') throw new Error('Source options unavailable');
      const html = await response.text();
      if (request !== sourceRequest) return;
      choices.innerHTML = html;
      applyMode();
      status.textContent = 'Source options loaded. Choose the approved selected team or source regions.';
    } catch (error) {
      if (request !== sourceRequest || error.name === 'AbortError') return;
      status.textContent = 'Unable to load source options. Try again using Show source options.';
    } finally {
      if (request === sourceRequest) choices.removeAttribute('aria-busy');
    }
  };
  sourceSelect.addEventListener('change', loadSource);
  sourceForm.addEventListener('submit', event => {
    event.preventDefault();
    loadSource();
  });
  const region = document.getElementById('destination-region');
  if (!region) return;
  const filterTeams = () => document.querySelectorAll('[data-destination-team]').forEach(select => {
    Array.from(select.options).forEach(option => {
      if (!option.dataset.regionId) return;
      option.disabled = option.dataset.regionId !== region.value;
      option.hidden = option.disabled;
      if (option.selected && option.disabled) select.value = '0';
    });
  });
  region.addEventListener('change', filterTeams);
  filterTeams();
});
</script>
@endsection
