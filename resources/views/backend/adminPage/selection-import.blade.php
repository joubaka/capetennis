@extends('layouts.backend')
@section('title', 'Import selected team - '.$event->name)
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'teams', 'eventWorkspaceSubtitle' => 'Import selected teams and reserves from a finalized event'])
<div class="card mb-3"><div class="card-body">
  <h3>Import selected team + reserves</h3>
  <p>Choose a source event, select the regions included in its finalized selection, then review the destination teams. Players and reserves keep their saved order.</p>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($previewErrors)<div class="alert alert-danger" role="alert"><strong>Unable to review these teams.</strong><ul class="mb-0">@foreach($previewErrors as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="GET" action="{{ route('backend.selection-import.index', $event) }}" class="row g-3 align-items-end">
    <input type="hidden" name="choose_regions" value="1">
    <div class="col-12 col-md-8"><label class="form-label" for="source-event">Source event</label><select id="source-event" name="source_event_id" class="form-select" required><option value="">Choose a source event</option>@if($sourceEvent && !$sources->contains('id', $sourceEvent->id))<option value="{{ $sourceEvent->id }}" selected>{{ $sourceEvent->name }}</option>@endif @foreach($sources as $source)<option value="{{ $source->id }}" @selected((int)request('source_event_id') === (int)$source->id)>{{ $source->name }}</option>@endforeach</select></div>
    <div class="col-12 col-md-4"><button class="btn btn-primary" style="min-height:44px">Show source regions</button></div>
  </form>
  @if($sources->isEmpty())<p class="mt-3 mb-0">No source team events are available for you to manage.</p>@endif
  <div class="mt-3">{{ $sources->withQueryString()->links('pagination::bootstrap-5') }}</div>
</div></div>
@if($sourceEvent)
<form method="GET" action="{{ route('backend.selection-import.index', $event) }}" class="card mb-3"><div class="card-body">
  <input type="hidden" name="source_event_id" value="{{ $sourceEvent->id }}">
  <input type="hidden" name="choose_regions" value="1">
  <input type="hidden" name="review_regions" value="1">
  <h4>Regions in {{ $sourceEvent->name }}</h4>
  <p>Check the regions to import. A finalized age/gender selection can combine several regions; select those regions together to keep its team and reserves intact.</p>
  @forelse($sourceRegions as $sourceRegion)
  @php($available = isset($regionGroups[$sourceRegion->id]))
  <label class="d-flex align-items-start gap-3 border rounded p-3 mb-2" for="source-region-{{ $sourceRegion->id }}">
    <input type="checkbox" class="form-check-input mt-1" id="source-region-{{ $sourceRegion->id }}" name="source_region_ids[]" value="{{ $sourceRegion->id }}" @disabled(!$available) @checked($available && in_array((int)$sourceRegion->id, array_map('intval', (array)old('source_region_ids', $selectedRegionIds)), true))>
    <span><strong>{{ $sourceRegion->region_name }}</strong><br>
      @if($available)<span class="badge bg-label-success">Included in finalized selection</span> <span class="small">{{ count($regionGroups[$sourceRegion->id]) }} age/gender {{ count($regionGroups[$sourceRegion->id]) === 1 ? 'group' : 'groups' }}</span>
      @else<span class="badge bg-label-secondary">No finalized selection</span>@endif
    </span>
  </label>
  @empty<p>No regions are linked to this source event.</p>@endforelse
  @if(!$finalization)<p class="mt-3">Finalize the saved teams in the source event to make its selections available here.</p>@endif
  <button class="btn btn-primary mt-3" style="min-height:44px" @disabled($regionGroups === [])>Review selected regions</button>
</div></form>
@endif
@if($preview)
<form method="POST" action="{{ route('backend.selection-import.store', $event) }}" class="card mb-3"><div class="card-body">
  @csrf
  <input type="hidden" name="finalization_id" value="{{ $finalization->id }}">
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
  <button class="btn btn-primary" id="import-selected-regions" style="min-height:44px" @disabled($regions->isEmpty())>Import teams and reserves</button>
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
  document.querySelectorAll('input[name="source_region_ids[]"][type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', () => {
      const importButton = document.getElementById('import-selected-regions');
      const notice = document.getElementById('regions-changed');
      if (importButton) importButton.disabled = true;
      if (notice) notice.hidden = false;
    });
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
