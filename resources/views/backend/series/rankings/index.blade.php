@extends('layouts/layoutMaster')

@section('title', 'Series Rankings — Best ' . $topN . ' Scores Per Category')

@section('content')
<div class="container-xxl py-3">

  @forelse($lists as $list)
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $list['category'] }}</h5>
        <small class="text-muted">Best {{ $list['topN'] }} scores counted</small>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:50px;">#</th>
                <th>Player</th>
                <th style="width:100px;">Points</th>
                <th>Counted Legs</th>
                <th>Dropped Legs</th>
              </tr>
            </thead>
            <tbody>
              @forelse($list['ranking'] as $i => $row)
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td>{{ $row['name'] }}</td>
                  <td><strong>{{ $row['points'] }}</strong></td>
                  <td>
                    @foreach($row['counted_legs'] as $leg)
                      <span class="badge bg-label-success me-1 mb-1">
                        {{ $leg['event'] }} — Pos {{ $leg['pos'] }} ({{ $leg['pts'] }} pts)
                      </span>
                    @endforeach
                  </td>
                  <td>
                    @foreach($row['dropped_legs'] as $leg)
                      <span class="badge bg-label-secondary me-1 mb-1">
                        {{ $leg['event'] }} — Pos {{ $leg['pos'] }} ({{ $leg['pts'] }} pts)
                      </span>
                    @endforeach
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center text-muted">No results yet</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @empty
    <div class="alert alert-info">No categories found for this series.</div>
  @endforelse

</div>
@endsection
