@extends('layouts.backend')

@section('title', 'Preview ranked-player import')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-between align-items-start gap-2 mb-4"><div><h4 class="mb-1">Preview ranked-player import</h4><p class="text-muted mb-0">{{ $source->region?->region_name }} · {{ $source->series?->name }}</p></div><a href="{{ route('backend.team-selection.index', $event) }}" class="btn btn-outline-secondary">Back</a></div>
  <div class="alert alert-info">Published ranking snapshot: <code>{{ $preview['run_id'] }}</code>. Confirming will fill each configured team and create {{ $source->reserve_count }} reserves per team.</div>
  @foreach($preview['warnings'] as $warning)<div class="alert alert-warning py-2">{{ $warning }}</div>@endforeach
  @foreach($preview['mappings'] as $mapping)
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">{{ $mapping['team']->name }}</h5><span class="text-muted small">{{ $mapping['capacity'] }} selected + {{ $mapping['reserve_count'] }} reserves · {{ $mapping['skipped_count'] }} ineligible skipped</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Place</th><th>Player</th><th>Ranking</th><th>Points</th><th>Events played</th></tr></thead><tbody>@foreach($mapping['players'] as $index => $row)<tr><td><span class="badge bg-label-{{ $index < $mapping['capacity'] ? 'primary' : 'secondary' }}">{{ $index < $mapping['capacity'] ? 'Team #'.($index+1) : 'Reserve '.($index-$mapping['capacity']+1) }}</span></td><td>{{ $row->player?->full_name }}</td><td>#{{ $row->rank_position }}</td><td>{{ $row->total_points }}</td><td>{{ data_get($row->meta_json, 'events_played', '—') }}</td></tr>@endforeach</tbody></table></div></div>
  @endforeach
  <form method="POST" action="{{ route('backend.team-selection.import', [$event, $source]) }}">@csrf<button class="btn btn-primary" {{ $preview['warnings'] ? 'disabled' : '' }}>Confirm and import ranked players</button></form>
</div>
@endsection
