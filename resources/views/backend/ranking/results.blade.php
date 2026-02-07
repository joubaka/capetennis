{{-- resources/views/backend/series/rankings/index.blade.php --}}
@extends('layouts/layoutMaster')

@section('title', 'Rankings — ' . $series->name)

@section('page-style')
<style>
  .rank-card .card-header {
    background:#e9ecef;
    border-bottom:1px solid #dee2e6
  }
  .rank-card .card {
    border-radius:.75rem;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.05)
  }
  .rank-card table th {
    text-transform:uppercase;
    letter-spacing:.04em;
    font-size:.75rem;
    color:#6c757d
  }
  .rank-card .total {
    font-weight:700
  }
  .rank-card small.event-points {
    color:#6c757d;
    font-size:.75rem;
    display:block
  }

  /* ✅ limit width of Events & Points column (th + td) */

</style>
@endsection

@section('content')

<div class="container-xxl py-3">
  @forelse($series->ranking_lists->chunk(2) as $chunk)
    <div class="row g-4">
      @foreach($chunk as $list)
     <div class="{{ $series->id == 16 ? 'col-md-9' : 'col-md-6' }}">

          <div class="card rank-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="mb-0">
                <span class="badge rounded-pill bg-primary">
                  {{ $list->name ?? ($list->category->name ?? 'Ranking List') }}
                </span>
              </h4>
              <small class="text-muted">{{ $list->category->name ?? 'No category' }}</small>
            </div>

            <div class="card-body p-0">
              @if($list->ranking_scores->count())
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px">#</th>
                        <th>Player</th>
                        <th class="legs-col">Events & Points</th>
                        <th style="width:100px" class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @php $rank = 1; @endphp
                      @foreach(($list->ranking_scores ?? collect())->sortByDesc('total_points') as $score)
                        <tr>
                          <td>{{ $rank++ }}</td>
                          <td>
                            {{ $score->player?->fullName ?? 'Unknown' }}
                            @if($score->primarySchool)
                              <span class="badge bg-info ms-1">U/13</span>
                            @endif
                          </td>

                          <td class="legs-col">
                            @foreach($score->legs as $leg)
                              <span class="badge bg-label-primary me-1 mb-1">
                                {{ $leg->event_name }}: {{ $leg->points }}
                                <small class="text-muted">({{ $leg->position }})</small>
                              </span>
                            @endforeach
                          </td>

                          <td class="text-end total">{{ $score->total_points }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="p-3 text-muted">No scores yet.</div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-info">No ranking lists yet.</div>
  @endforelse
</div>
@endsection
