@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Ranking Audit')

@section('page-style')
<style>
  .audit-ok   { color: #28a745; }
  .audit-warn { color: #ffc107; }
  .audit-fail { color: #dc3545; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Ranking Audit</h4>
        <div class="text-muted">{{ $series->name }} ({{ $series->year }})</div>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('ranking.series.list', $series) }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Back to Ranking List
        </a>
        <a href="{{ route('series.show', $series) }}" class="btn btn-outline-secondary">
          <i class="ti ti-home me-1"></i> Series Home
        </a>
      </div>
    </div>
  </div>

  {{-- SUMMARY STATS --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0">{{ $eventSummary->count() }}</h2>
          <small class="text-muted">Events in Series</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0">{{ $categorySummary->count() }}</h2>
          <small class="text-muted">Unique Categories (merged)</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0">{{ count($pointsMap) }}</h2>
          <small class="text-muted">Points Positions Defined</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0 {{ $totalRankingRows > 0 ? 'audit-ok' : 'audit-fail' }}">
            {{ $totalRankingRows }}
          </h2>
          <small class="text-muted">Current Ranking Rows</small>
        </div>
      </div>
    </div>
  </div>

  {{-- POINTS MAP --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-list-numbers me-1"></i> Points Map</h5>
    </div>
    <div class="card-body">
      @if(empty($pointsMap))
        <div class="alert alert-danger mb-0">
          <i class="ti ti-alert-circle me-1"></i>
          No points defined for this series. Rankings cannot be calculated.
        </div>
      @else
        <div class="d-flex flex-wrap gap-2">
          @foreach(collect($pointsMap)->sortKeys() as $position => $points)
            <span class="badge bg-label-primary">Pos {{ $position }}: {{ $points }} pts</span>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- EVENTS SUMMARY --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-calendar-event me-1"></i> Events & Results</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Results</th>
            <th>Categories with Results</th>
          </tr>
        </thead>
        <tbody>
          @foreach($eventSummary as $es)
            <tr>
              <td>
                <a href="{{ route('admin.events.overview', $es['event']) }}" target="_blank">
                  {{ $es['event']->name }}
                </a>
              </td>
              <td>{{ optional($es['event']->start_date)->format('d M Y') ?? '—' }}</td>
              <td>
                @if($es['has_results'])
                  <span class="badge bg-success"><i class="ti ti-check me-1"></i>{{ $es['result_rows'] }} rows</span>
                @else
                  <span class="badge bg-danger"><i class="ti ti-x me-1"></i>No results</span>
                @endif
              </td>
              <td>
                @foreach($es['categories'] as $cat)
                  <span class="badge bg-label-secondary me-1">
                    {{ $cat['name'] }} ({{ $cat['players'] }} players)
                  </span>
                @endforeach
                @if($es['categories']->isEmpty())
                  <span class="text-muted fst-italic">None</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- CATEGORY AUDIT --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-trophy me-1"></i> Category Ranking Audit</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Category (merged key)</th>
            <th>Players</th>
            <th>Events</th>
            <th>Positions in Data</th>
            <th>Missing Points Config</th>
            <th>Ranked Players</th>
          </tr>
        </thead>
        <tbody>
          @foreach($categorySummary as $cs)
            @php
              $rankedCount = isset($rankingsByCategory[$cs['category_id']])
                ? $rankingsByCategory[$cs['category_id']]->count()
                : 0;
              $hasMissing = $cs['missing_points']->isNotEmpty();
            @endphp
            <tr>
              <td>
                <strong>{{ $cs['category_name'] }}</strong><br>
                <small class="text-muted">{{ $cs['category_key'] }}</small>
              </td>
              <td>{{ $cs['player_count'] }}</td>
              <td>{{ $cs['events_represented'] }}</td>
              <td>
                @foreach($cs['position_counts'] as $pos => $count)
                  <span class="badge bg-label-primary me-1">Pos {{ $pos }} (×{{ $count }})</span>
                @endforeach
              </td>
              <td>
                @if($hasMissing)
                  @foreach($cs['missing_points'] as $pos)
                    <span class="badge bg-danger me-1">Pos {{ $pos }}</span>
                  @endforeach
                @else
                  <span class="badge bg-success">All OK</span>
                @endif
              </td>
              <td>
                @if($rankedCount > 0)
                  <span class="badge bg-success">{{ $rankedCount }}</span>
                @else
                  <span class="badge bg-warning text-dark">0 – not ranked yet</span>
                @endif
              </td>
            </tr>
          @endforeach
          @if($categorySummary->isEmpty())
            <tr>
              <td colspan="6" class="text-center text-muted py-3">No category results found for any event in this series.</td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- CURRENT RANKINGS SNAPSHOT --}}
  @if($rankingsByCategory->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="ti ti-list me-1"></i> Current Rankings Snapshot</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          @foreach($rankingsByCategory as $categoryId => $rows)
            <div class="col-md-6">
              <div class="card border">
                <div class="card-header bg-light py-2">
                  <strong>{{ optional($rows->first()->category)->name ?? 'Category '.$categoryId }}</strong>
                  <span class="badge bg-secondary float-end">{{ $rows->count() }} players</span>
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <thead class="table-light">
                      <tr>
                        <th width="50">#</th>
                        <th>Player</th>
                        <th width="80">Points</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($rows->sortBy('rank_position') as $row)
                        <tr>
                          <td>{{ $row->rank_position }}</td>
                          <td>{{ optional($row->player)->name }} {{ optional($row->player)->surname }}</td>
                          <td><strong>{{ $row->total_points }}</strong></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

</div>
@endsection
