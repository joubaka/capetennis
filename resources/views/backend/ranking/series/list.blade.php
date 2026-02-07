@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Ranking List')

@section('vendor-style')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endsection

@section('page-style')
<style>
  .rank-pos { font-weight: 700; width: 60px; }
  .points { font-weight: 600; }
  .category-title { font-weight: 600; margin-top: 1rem; }
  .score-badge { cursor: pointer; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Ranking List</h4>
        <div class="text-muted">
          {{ $series->name }} ({{ $series->year }})
        </div>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ route('series.show', $series) }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Back to Series
        </a>

        <button id="rebuild-ranking" class="btn btn-warning">
          <i class="ti ti-refresh me-1"></i> Rebuild Rankings
        </button>
      </div>
    </div>
  </div>

  {{-- BODY --}}
  @foreach($categories as $category)
    @php
      $rows = $rankings
        ->where('category_id', $category->id)
        ->sortBy('rank_position')
        ->values();
    @endphp

    @if($rows->isNotEmpty())
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0 category-title">{{ $category->name }}</h5>
        </div>

        <div class="card-body table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="70">Rank</th>
                <th>Player</th>
                <th width="160">Total Points</th>
                <th>Scores per Event</th>
              </tr>
            </thead>

            <tbody>
              @foreach($rows as $row)
                @php
                  $meta = is_array($row->meta_json)
                    ? $row->meta_json
                    : json_decode($row->meta_json, true) ?? [];

                  $legs = collect($meta['legs'] ?? []);
                @endphp

                <tr>
                  <td class="rank-pos">#{{ $row->rank_position }}</td>

                  <td>
                    {{ $row->player->full_name
                      ?? $row->player->name
                      ?? 'Unknown Player' }}
                  </td>

                  <td class="points">{{ $row->total_points }}</td>

                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      @foreach($legs as $leg)
                        @php
  $event = $series->events->firstWhere('id', $leg['event_id']);

  $isAuto = ($leg['is_auto'] ?? false) === true;

  $badgeClass = $isAuto
    ? 'bg-warning text-dark'
    : (($leg['colour'] ?? '') === 'green'
        ? 'bg-success'
        : 'bg-danger');

  $tooltip = $isAuto
    ? 'Auto-awarded leg: Player won 2 of 3 trials and did not play this event. '
      .'This leg is awarded 1st place (1000 points). '
      .'All other players were shifted down by one position.'
    : null;
@endphp


                        @if($event)
                          <a
                            href="{{ route('admin.events.results.individual', $event) }}"
                            class="text-decoration-none"
                            title="View {{ $event->name }} – Individual Results"
                          >
                        @endif

                       <span
  class="badge score-badge {{ $badgeClass }}"
  @if($tooltip)
    data-bs-toggle="tooltip"
    data-bs-placement="top"
    title="{{ $tooltip }}"
  @endif
>
  {{ $leg['points'] }}
  <small class="opacity-75">
    ({{ $event?->short_name ?? 'E'.$leg['event_id'] }})
  </small>

  @if($isAuto)
    <small class="fw-bold ms-1">(AUTO)</small>
  @endif
</span>


                        @if($event)
                          </a>
                        @endif
                      @endforeach
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  @endforeach

</div>
@endsection

@section('page-script')
<script>
toastr.options = {
  closeButton: true,
  progressBar: true,
  positionClass: 'toast-top-right',
  timeOut: 2500
};

document.getElementById('rebuild-ranking')?.addEventListener('click', () => {
  const btn = document.getElementById('rebuild-ranking');
  btn.disabled = true;

  fetch('{{ route('ranking.series.rebuild', $series) }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}'
    }
  })
  .then(r => r.json())
  .then(r => {
    toastr.success(r.message);
    location.reload();
  })
  .catch(() => {
    toastr.error('Failed to rebuild rankings');
    btn.disabled = false;
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );

  tooltipTriggerList.forEach(el => {
    new bootstrap.Tooltip(el);
  });
});
</script>

@endsection
