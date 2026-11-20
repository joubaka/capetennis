@extends('layouts.backend')
@section('title', $plan->event->name.' · Camp planner')
@section('page-style')
<style>
.camp-planner {max-width:1200px}.camp-planner .camp-meta{display:flex;flex-wrap:wrap;gap:.6rem 1.5rem}.camp-planner .camp-team-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.6rem}.camp-planner label.camp-choice{display:flex;gap:.75rem;align-items:flex-start;padding:.8rem;border:1px solid #d9e2ec;border-radius:.4rem;cursor:pointer}.camp-planner input[type=checkbox]{width:1.2rem;height:1.2rem;flex-shrink:0;margin-top:.15rem}.camp-planner .camp-player{border-top:1px solid #e3e8ee;padding:.85rem 0}.camp-planner .camp-source{font-size:.85rem;color:#526477;overflow-wrap:anywhere}.camp-planner .camp-saved{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}.camp-planner .camp-saved>div{min-width:0}.camp-planner .btn{white-space:normal;min-height:44px}
</style>
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y camp-planner">
  <a href="{{ route('backend.practice-camps.index') }}">Practice camp planning</a>
  <h1 class="h3 mt-3">{{ $plan->event->name }}</h1>
  <span class="badge bg-label-secondary mb-3">Private invitation draft</span>
  <div class="card mb-4"><div class="card-body">
    <div class="camp-meta">
      <span><strong>{{ $plan->event->start_date->format('j M Y') }} – {{ $plan->event->end_date->format('j M Y') }}</strong> · provisional</span>
      <span>Venue not set</span><span>Fee not set</span><span>Capacity not set</span><span>Contact not set</span>
    </div>
    <p class="mt-3 mb-0">Choose source regions or teams, review the players, then add them to this camp’s draft. Team selection and camp entry remain separate.</p>
    <p class="small text-muted mb-0">No invitations are sent here. Coach nominations will require organiser approval before invitations.</p>
  </div></div>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">1. Choose source teams</h2>
    <form method="GET" action="{{ route('backend.practice-camps.show', $plan) }}" class="mb-3">
      <label class="form-label" for="source-q">Find a source event</label>
      <div class="d-flex gap-2"><input class="form-control" id="source-q" name="source_q" value="{{ request('source_q') }}" placeholder="Event name, for example Platteland"><button class="btn btn-outline-primary">Search</button></div>
      <p class="small text-muted">Up to 50 matching events you manage are shown.</p>
    </form>
    <form method="GET" action="{{ route('backend.practice-camps.show', $plan) }}" class="mb-3">
      <label class="form-label" for="source-event">Source event</label>
      <select class="form-select mb-2" id="source-event" name="source_event_id" required>
        <option value="">Choose event</option>
        @if($source && !$events->contains('id', $source->id))<option value="{{ $source->id }}" selected>{{ $source->name }}</option>@endif
        @foreach($events as $event)<option value="{{ $event->id }}" @selected($source?->id === $event->id)>{{ $event->name }}</option>@endforeach
      </select>
      <button class="btn btn-outline-primary">Show regions</button>
    </form>
    @if($source)
      <form method="GET" action="{{ route('backend.practice-camps.show', $plan) }}" class="mb-3">
        <input type="hidden" name="source_event_id" value="{{ $source->id }}">
        <label class="form-label" for="source-region">Region in {{ $source->name }}</label>
        <select class="form-select mb-2" id="source-region" name="region_id" required><option value="">Choose region</option>@foreach($regions as $region)<option value="{{ $region->id }}" @selected((int)request('region_id') === $region->id)>{{ $region->region_name }}</option>@endforeach</select>
        <button class="btn btn-outline-primary">Show teams</button>
      </form>
    @endif
    @if($source && request('region_id'))
      <form method="GET" action="{{ route('backend.practice-camps.show', $plan) }}">
        <input type="hidden" name="source_event_id" value="{{ $source->id }}"><input type="hidden" name="region_id" value="{{ request('region_id') }}"><input type="hidden" name="preview" value="1">
        <p>Select the whole region or individual teams. Player choices are reviewed next.</p>
        <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-camp-toggle="teams">Select all teams</button>
        <div class="camp-team-list mb-3">@forelse($teams as $team)<label class="camp-choice"><input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, array_map('intval', request('team_ids', []))))><span>{{ $team->name }}<small class="d-block">{{ $team->noProfile ? $team->team_players_no_profile->count() : $team->team_players->where('player_id', '>', 0)->count() }} roster places</small></span></label>@empty<p>No event-scoped teams found in this region.</p>@endforelse</div>
        @if($teams->isNotEmpty())<button class="btn btn-primary">Review players from selected teams</button>@endif
      </form>
    @endif
  </div></div>
  @if($preview)
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">2. Review {{ count($preview['rows']) }} candidates</h2>
    <p class="alert alert-info">{{ $preview['notice'] }}</p>
    <p>These are roster and historical selection records, not an automatic eligibility decision. Check age, original selection and any placeholder identities before choosing players.</p>
    <form method="POST" action="{{ route('backend.practice-camps.add', $plan) }}">@csrf
      <input type="hidden" name="revision" value="{{ $plan->revision }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}"><input type="hidden" name="source_event_id" value="{{ $source->id }}"><input type="hidden" name="region_id" value="{{ request('region_id') }}">
      @foreach(request('team_ids', []) as $teamId)<input type="hidden" name="team_ids[]" value="{{ $teamId }}">@endforeach
      @if(count($preview['rows']))<button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-camp-toggle="players">Select all current roster players</button>@endif
      @forelse($preview['rows'] as $row)
        <label class="camp-choice mb-2"><input type="checkbox" name="selected[]" value="{{ $row['key'] }}" data-camp-roster="{{ collect($row['sources'])->contains('kind', 'roster') ? '1' : '0' }}"><span><strong>{{ $row['name'] ?: 'Unnamed imported player' }}</strong><small class="d-block">{{ $row['identity'] }}</small>@foreach($row['sources'] as $origin)<span class="d-block camp-source">{{ $origin['team'] }} · {{ $origin['status'] }}@if($origin['original_rank'] ?? null) · original ranking {{ $origin['original_rank'] }}@endif @if($origin['manual_selection'] ?? false) · manually selected; ranking unavailable @endif</span>@endforeach</span></label>
      @empty<p>No candidates found.</p>@endforelse
      @if(count($preview['rows']))<button class="btn btn-primary mt-2">Add selected players to private draft</button>@endif
    </form>
  </div></div>
  @endif
  <div class="card"><div class="card-body">
    <h2 class="h5">Camp invitation draft · {{ count($plan->candidates) }} players</h2>
    <p>Existing player profiles are combined across sources. Imported roster identities remain separate until matched. Saved candidates are not yet approved nominees or invitations.</p>
    @forelse($plan->candidates as $row)
      <div class="camp-player camp-saved"><div><strong>{{ $row['name'] ?: 'Unnamed imported player' }}</strong><small class="d-block">{{ $row['identity'] }}</small>@foreach($row['sources'] as $origin)<div class="camp-source">{{ $origin['event'] }} · {{ $origin['region'] }} · {{ $origin['team'] }} · {{ $origin['status'] }}</div>@endforeach</div>
        <form method="POST" action="{{ route('backend.practice-camps.remove', $plan) }}">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $plan->revision }}"><input type="hidden" name="key" value="{{ $row['key'] }}"><button class="btn btn-sm btn-outline-danger" aria-label="Remove {{ $row['name'] }} from draft">Remove</button></form>
      </div>
    @empty<p class="mb-0">No players selected yet. Start with a source region above.</p>@endforelse
  </div></div>
</div>
@endsection
@section('page-script')
<script>
document.querySelectorAll('[data-camp-toggle]').forEach(button => button.addEventListener('click', () => {
  const selector = button.dataset.campToggle === 'teams' ? 'input[name="team_ids[]"]' : 'input[name="selected[]"][data-camp-roster="1"]';
  const boxes = Array.from(button.form.querySelectorAll(selector));
  const checked = boxes.some(box => !box.checked);
  boxes.forEach(box => { box.checked = checked; });
}));
</script>
@endsection
