@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Provisional Rankings')

@section('content')
<div class="container py-4">
  <div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h2 class="mb-1">{{ $series->name }}</h2>
          <div class="text-muted">Provisional rankings · {{ $series->year }}</div>
        </div>
        <span class="badge {{ $reviewOpen ? 'bg-warning text-dark' : 'bg-success' }} fs-6">
          {{ $reviewOpen ? 'Participant review open' : 'Review closed' }}
        </span>
      </div>
      <div class="alert {{ $reviewOpen ? 'alert-info' : 'alert-secondary' }} mt-3 mb-0">
        @if($reviewOpen)
          Please check your position and points. Reply to the ranking email before
          <strong>{{ $campaign->cutoff_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</strong>
          if anything needs attention.
        @else
          The participant feedback cutoff was
          <strong>{{ $campaign->cutoff_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</strong>.
        @endif
      </div>
    </div>
  </div>

  @foreach($categories as $category)
    @php($rows = $rankings->where('category_id', $category->id)->sortBy('rank_position'))
    <div class="card mb-4 shadow-sm">
      <div class="card-header"><h5 class="mb-0">{{ $category->name }}</h5></div>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th style="width:90px">Rank</th><th>Player</th><th class="text-end">Points</th></tr></thead>
          <tbody>
            @foreach($rows as $row)
              <tr>
                <td class="fw-bold">{{ $row->rank_position }}</td>
                <td>{{ $row->player?->full_name ?? 'Unknown player' }}</td>
                <td class="text-end fw-semibold">{{ number_format($row->total_points, 0) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach
</div>
@endsection
