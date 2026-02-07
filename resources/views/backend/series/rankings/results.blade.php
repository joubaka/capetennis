{{-- resources/views/backend/series/rankings/results.blade.php --}}
@extends('layouts/layoutMaster')

@section('title', 'Series Results: ' . $series->name)

@section('content')
<div class="container-xxl py-3">
  <div class="row g-4">
    @forelse($results as $cid => $block)
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $block['category']->name ?? 'Category' }}</h5>
            @if(!empty($block['bestOf']))
              <small class="text-muted">
                <i class="ti ti-star me-1"></i>Best {{ $block['bestOf'] }}
              </small>
            @endif
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="width:70px">Rank</th>
                    <th>Player</th>
                    <th style="width:100px" class="text-end">Total</th>
                    <th>Legs</th>
                  </tr>
                </thead>
                <tbody>
                @forelse($block['rows'] as $row)
                  <tr>
                    <td class="fw-bold">{{ $row['rank'] }}</td>
                    <td>{{ $row['player'] }}</td>
                    <td class="text-end fw-semibold">{{ $row['total'] }}</td>
                    <td>
                      @foreach($row['legs'] as $leg)
                        <span class="badge bg-label-secondary me-1">
                          {{ $leg['event'] }} — {{ $leg['score'] }}@if($leg['is_bonus']) * @endif
                        </span>
                      @endforeach
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">No results yet.</td>
                  </tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="alert alert-info mb-0">
          No categories have results for this series yet.
        </div>
      </div>
    @endforelse
  </div>
</div>
@endsection
