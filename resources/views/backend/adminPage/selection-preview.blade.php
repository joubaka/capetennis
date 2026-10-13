@extends('layouts.backend')
@section('title', 'Preview selections - '.$event->name)
@section('content')
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h3>Preview selections</h3><p class="mb-0">{{ $event->name }} · Private selection audit</p></div><a class="btn btn-outline-primary" href="{{ route('event.admin.main', $event->id) }}?roster_tab=result-rank">Back to Result Ranks</a></div>
  <p>Compare current source-team rosters side by side with the saved selected team. Green source names are selected in that group's saved draft. Each selected player includes their source team. This preview does not publish selections.</p>
  <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Choose an age group">
    @foreach($ages as $option)<a @class(['btn', 'btn-primary' => $age === $option, 'btn-outline-primary' => $age !== $option]) href="{{ route('backend.selection-preview.show', ['event' => $event, 'age' => $option]) }}" @if($age === $option) aria-current="page" @endif>u/{{ $option }}</a>@endforeach
  </nav>
  @forelse($groups as $group)
    <section class="mb-4" aria-label="{{ $group['name'] }}"><h4>{{ $group['name'] }}</h4>
      <p>{{ $group['draft'] ? 'Saved draft version '.$group['draft']->version.' · '.$group['draft']->updated_at->format('d M Y H:i') : 'No saved draft. Candidate regions use the event settings; no players are marked selected.' }}</p>
      @if($group['missing']->isNotEmpty())<div class="alert alert-warning">Saved selections no longer found in these current roster positions: {{ $group['missing']->pluck('name')->implode(', ') }}. They remain visible in the saved selected team; review roster or identity changes in Result Ranks.</div>@endif
      <div class="d-flex gap-3 overflow-auto pb-3" tabindex="0" aria-label="{{ $group['name'] }} source teams and selected team">
        @foreach($group['teams'] as $team)
          <article class="card flex-shrink-0" style="width: 280px"><div class="card-header"><h5 class="mb-1">{{ $team['name'] }}</h5><span>{{ $team['region'] }}</span></div><ol class="list-group list-group-flush" style="list-style: none" aria-label="{{ $team['name'] }} roster">
            @foreach($team['rows'] as $row)<li class="list-group-item d-flex gap-2"><span class="text-muted">{{ $row['rank'] }}.</span><div class="flex-grow-1">
              @forelse($row['players'] as $player)<div><strong @if($player['selected']) class="text-success" aria-label="{{ $player['name'] }} selected" @endif>{{ $player['name'] }}</strong><span class="text-muted d-block">{{ $player['team'] }}</span></div>@empty<span class="text-muted">—</span>@endforelse
            </div></li>@endforeach
          </ol></article>
        @endforeach
        <article class="card border-success flex-shrink-0" style="width: 280px"><div class="card-header"><h5 class="mb-1">Selected team</h5><span>{{ $group['selected']->count() }} selected · Saved draft order</span></div><ol class="list-group list-group-flush" style="list-style: none" aria-label="{{ $group['name'] }} selected team">
          @forelse($group['selected'] as $player)<li class="list-group-item d-flex gap-2"><span class="text-muted">{{ $loop->iteration }}.</span><div><strong class="text-success">{{ $player['name'] }}</strong><span class="text-muted d-block">{{ implode(', ', $player['teams'] ?? []) }}</span></div></li>@empty<li class="list-group-item text-muted">No saved selections.</li>@endforelse
        </ol></article>
      </div>
    </section>
  @empty<p>No age-group teams configured for this event.</p>@endforelse
</div>
@endsection