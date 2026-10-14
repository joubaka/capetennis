@extends('layouts.backend')
@section('title', 'Import selected team - '.$event->name)
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'teams', 'eventWorkspaceSubtitle' => 'Import selected teams and reserves from a finalized event'])
<div class="card mb-3"><div class="card-body">
  <h3>Import selected team + reserves</h3>
  <p>Choose the event where you finalized the selected teams. Import the selected players and reserves in their saved order into {{ $event->name }}.</p>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="GET" action="{{ route('backend.selection-import.index', $event) }}" class="row g-3 align-items-end">
    <div class="col-12 col-md-8"><label class="form-label" for="source-event">Source event</label><select id="source-event" name="source_event_id" class="form-select" required><option value="">Choose a finalized event</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((int)request('source_event_id') === (int)$source->id)>{{ $source->name }}</option>@endforeach</select></div>
    <div class="col-12 col-md-4"><button class="btn btn-primary" style="min-height:44px">Review selected teams</button></div>
  </form>
  @if($sources->isEmpty())<p class="mt-3 mb-0">No finalized source events are available. Finalize the saved teams in the source event first.</p>@endif
  <div class="mt-3">{{ $sources->withQueryString()->links() }}</div>
</div></div>
@if($preview)
<form method="POST" action="{{ route('backend.selection-import.store', $event) }}" class="card mb-3"><div class="card-body">
  @csrf
  <input type="hidden" name="finalization_id" value="{{ $finalization->id }}">
  <h4>{{ $preview['source']->name }}</h4><p>Finalized {{ $finalization->finalized_at->format('d M Y H:i') }}. Only selected players and reserves are included.</p>
  <label for="destination-region" class="form-label">Destination region</label><select id="destination-region" class="form-select mb-3" name="region_id" required><option value="">Choose the region these teams represent</option>@foreach($regions as $region)<option value="{{ $region->id }}" @selected((int)old('region_id') === (int)$region->id)>{{ $region->region_name }}</option>@endforeach</select>
  @if($regions->isEmpty())<div class="alert alert-warning">Add a region to this event before importing teams.</div>@endif
  @foreach($preview['groups'] as $key => $group)
  <section class="border rounded p-3 mb-3" aria-label="{{ $group['label'] }}"><h5>{{ $group['label'] }} · {{ $group['main_count'] }} team players + {{ $group['reserve_count'] }} reserves</h5>
    <label class="form-label" for="destination-{{ $key }}">Destination team</label><select id="destination-{{ $key }}" name="mapping[{{ $key }}]" class="form-select mb-3" data-destination-team><option value="0">Create a team in the chosen region</option>@foreach($group['teams'] as $team)<option value="{{ $team->id }}" data-region-id="{{ $team->region_id }}" @selected((int)old('mapping.'.$key) === (int)$team->id)>{{ $team->name }}</option>@endforeach</select>
    <ol class="mb-0">@foreach($group['players'] as $player)<li>{{ $player['name'] }} @if($player['status'] === 'reserve')<span class="badge bg-label-warning">Reserve</span>@endif @if($player['profile_link_review'] ?? false)<span class="badge bg-label-info">Review profile link after import</span>@endif</li>@endforeach</ol>
  </section>
  @endforeach
  <p>Existing teams must be empty and private. New rosters start unpaid and private. Main players and reserves keep their finalized order.</p>
  <label class="d-block mb-3"><input type="checkbox" name="confirm_import" value="1" required> I have reviewed these teams and their destination region.</label>
  <button class="btn btn-primary" style="min-height:44px" @disabled($regions->isEmpty())>Import teams and reserves</button>
</div></form>
@endif
@foreach($imports as $import)
<section class="card mb-3"><div class="card-body"><h4>Imported teams · {{ $import->region->region_name }}</h4><p>{{ $import->imported_at->format('d M Y H:i') }} · Source event #{{ $import->finalization->event_id }}</p>
@foreach($import->groups as $group)<h5>{{ $group['team_name'] }}</h5><ol>@foreach($group['players'] as $player)<li>{{ $player['name'] }} @if($player['status'] === 'reserve')<span class="badge bg-label-warning">Reserve</span>@endif</li>@endforeach</ol>@endforeach
<a class="btn btn-outline-primary" href="{{ route('admin.events.teams', $event) }}">Manage event rosters</a>
</div></section>
@endforeach
{{ $imports->withQueryString()->links() }}
<script>
document.addEventListener('DOMContentLoaded', () => {
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
