@extends('layouts.backend')

@section('title', 'Preview ranked-player import')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-between align-items-start gap-2 mb-4"><div><h4 class="mb-1">Preview ranked-player import</h4><p class="text-muted mb-0">{{ $source->region?->region_name }} · {{ $source->series?->name }}</p></div><a href="{{ route('backend.team-selection.index', $event) }}" class="btn btn-outline-secondary">Back to team selection</a></div>
  <div class="alert alert-info">Published ranking snapshot: <code>{{ $preview['run_id'] }}</code>. Confirming will fill each team with the available eligible players, up to its configured size, and add available reserves.</div>
  @foreach($preview['warnings'] as $warning)<div class="alert alert-danger py-2"><strong>Import blocked:</strong> {{ $warning }}</div>@endforeach
  @foreach($preview['notices'] as $notice)<div class="alert alert-warning py-2"><strong>Incomplete roster:</strong> {{ $notice }}</div>@endforeach
  @foreach($preview['mappings'] as $mapping)
    @php($selectedCount = min($mapping['capacity'], $mapping['players']->count()))
    @php($emptyCount = max(0, $mapping['capacity'] - $selectedCount))
    @php($availableReserves = max(0, $mapping['players']->count() - $mapping['capacity']))
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">{{ $mapping['team']->name }}</h5><span class="text-muted small">{{ $mapping['capacity'] }} team places · {{ $selectedCount }} selected · {{ $emptyCount }} empty · {{ $availableReserves }} reserves available · {{ $mapping['skipped_count'] }} ineligible skipped</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Place</th><th>Player</th><th>Ranking</th><th>Points</th><th>Events played</th></tr></thead><tbody>@foreach($mapping['players'] as $index => $row)<tr><td><span class="badge bg-label-{{ $index < $mapping['capacity'] ? 'primary' : 'secondary' }}">{{ $index < $mapping['capacity'] ? 'Team #'.($index+1) : 'Reserve '.($index-$mapping['capacity']+1) }}</span></td><td>{{ $row->player?->full_name }}</td><td>#{{ $row->rank_position }}</td><td>{{ $row->total_points }}</td><td>{{ data_get($row->meta_json, 'events_played', '—') }}</td></tr>@endforeach @for($rank = $selectedCount + 1; $rank <= $mapping['capacity']; $rank++)<tr class="table-warning"><td><span class="badge bg-label-warning">Team #{{ $rank }}</span></td><td><em>Empty place</em></td><td>—</td><td>—</td><td>—</td></tr>@endfor</tbody></table></div></div>
  @endforeach
  <form method="POST" action="{{ route('backend.team-selection.import', [$event, $source]) }}">
    @csrf
    @if($preview['notices'])
      <div class="form-check border rounded p-3 ps-5 mb-3">
        <input class="form-check-input" type="checkbox" value="1" name="confirm_incomplete_rosters" id="confirm-incomplete-rosters" required>
        <label class="form-check-label" for="confirm-incomplete-rosters"><strong>I confirm these incomplete rosters.</strong> Import the available players and keep the remaining team and reserve places empty.</label>
      </div>
    @endif
    <button class="btn btn-primary" {{ $preview['warnings'] ? 'disabled' : '' }}>Confirm and import available players</button>
  </form>
</div>
@endsection
