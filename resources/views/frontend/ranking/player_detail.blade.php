@extends('layouts/layoutMaster')

@section('title', ($player->full_name ?? $player->name) . ' — Ranking Detail')

@section('vendor-style')
<style>
  .detail-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; }
  .detail-card .card-header { background: linear-gradient(90deg, rgba(0,123,255,0.06), rgba(13,110,253,0.02)); }
  .status-counted  { background-color: #198754; color: #fff; }
  .status-dropped  { background-color: #dc3545; color: #fff; }
  .status-auto     { background-color: #ffc107; color: #000; }
  .points-total    { font-weight: 700; font-size: 1.1rem; }
</style>
@endsection

@section('content')
<div class="col-12">

  {{-- Back link --}}
  <div class="mb-3">
    <a href="{{ route('frontend.ranking.show', $series) }}" class="btn btn-outline-secondary btn-sm">
      &larr; Back to Rankings
    </a>
  </div>

  {{-- Header --}}
  <div class="card detail-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">{{ $player->full_name ?? $player->name }}</h5>
        <div class="text-muted small">{{ $series->name }}{{ $series->year ? ' ' . $series->year : '' }}</div>
      </div>
      @if($rankingRecord)
        <div class="text-end">
          <div class="text-muted small">Overall Rank</div>
          <span class="badge bg-primary fs-6">#{{ $rankingRecord->rank_position }}</span>
        </div>
      @endif
    </div>

    <div class="card-body">
      @if(!$rankingRecord)
        <div class="alert alert-warning mb-0">
          No ranking data found for this player in {{ $series->name }}.
        </div>
      @else
        {{-- Summary row --}}
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Category</div>
              <strong>{{ $rankingRecord->category->name ?? '—' }}</strong>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Rank Position</div>
              <strong>#{{ $rankingRecord->rank_position }}</strong>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Total Points</div>
              <strong class="points-total">{{ $rankingRecord->total_points }}</strong>
            </div>
          </div>
        </div>

        {{-- Per-event breakdown --}}
        @if($legs->isNotEmpty())
          <h6 class="mb-3">Event Breakdown</h6>
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Event</th>
                  <th>Date</th>
                  <th class="text-center">Position</th>
                  <th class="text-center">Points</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                @php $countedTotal = 0; @endphp
                @foreach($legs as $leg)
                  @php
                    $colour  = $leg['colour'] ?? 'grey';
                    $status  = $leg['status'] ?? 'dropped';
                    $isAuto  = !empty($leg['is_auto']);
                    $badgeClass = $isAuto ? 'status-auto' : ($status === 'counted' ? 'status-counted' : 'status-dropped');
                    $label   = $isAuto ? 'Auto-award' : ucfirst($status);
                    if ($status === 'counted') { $countedTotal += (int)($leg['points'] ?? 0); }
                  @endphp
                  <tr>
                    <td>{{ $leg['event_name'] }}</td>
                    <td class="text-nowrap text-muted small">
                      {{ $leg['event_date'] ? \Carbon\Carbon::parse($leg['event_date'])->format('d M Y') : '—' }}
                    </td>
                    <td class="text-center">{{ $leg['position'] ?? '—' }}</td>
                    <td class="text-center fw-bold">{{ $leg['points'] ?? 0 }}</td>
                    <td class="text-center">
                      <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td colspan="3" class="text-end fw-bold">Counted Total</td>
                  <td class="text-center fw-bold points-total">{{ $countedTotal }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-3 d-flex gap-2 flex-wrap">
            <span class="badge status-counted">Counted — contributes to ranking</span>
            <span class="badge status-dropped">Dropped — not counted</span>
            <span class="badge status-auto">Auto-award — awarded by rule</span>
          </div>
        @else
          <div class="alert alert-info mb-0">No per-event breakdown available.</div>
        @endif

      @endif
    </div>
  </div>

</div>
@endsection
