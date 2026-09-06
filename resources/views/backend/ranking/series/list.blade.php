@extends('layouts.backend')

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
  .ranking-event-score {
    min-width: 170px;
    border: 1px solid var(--bs-border-color);
    border-left-width: 4px;
    border-radius: .5rem;
    padding: .45rem .6rem;
    color: inherit;
    background: var(--bs-body-bg);
    transition: box-shadow .15s ease, transform .15s ease;
  }
  .ranking-event-score:hover { box-shadow: 0 .2rem .65rem rgba(0, 0, 0, .1); transform: translateY(-1px); }
  .ranking-event-score--counted { border-left-color: var(--bs-success); }
  .ranking-event-score--dropped { border-left-color: var(--bs-danger); opacity: .82; }
  .ranking-event-score--automatic { border-left-color: var(--bs-warning); background: rgba(var(--bs-warning-rgb), .08); }
  .ranking-event-name { max-width: 220px; }
  .head-to-head-note { background: rgba(var(--bs-info-rgb), .08) !important; }
  .tiebreak-note { font-size: .75rem; }

  @media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; break-inside: avoid; }
    .card-header { background: #eee !important; color: #000 !important; }
    .badge { border: 1px solid #ccc; color: #000 !important; background: #f0f0f0 !important; }
    .badge.bg-success { background: #d4edda !important; border-color: #28a745; }
    .badge.bg-danger { background: #f8d7da !important; border-color: #dc3545; }
    .badge.bg-warning { background: #fff3cd !important; border-color: #ffc107; }
    .ranking-event-score { min-width: 0; box-shadow: none !important; transform: none !important; }
    a { color: #000 !important; text-decoration: none !important; }
    .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f9f9f9 !important; }
  }
</style>
@endsection

@section('content')
<div class="container-xl print-area">

  {{-- HEADER --}}
  <div class="card mb-4 no-print">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Ranking List</h4>
        <div class="text-muted">
          {{ $series->name }} ({{ $series->year }})
        </div>
        <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
          <span class="badge bg-label-{{ $activeStatus === 'published' ? 'success' : ($activeStatus === 'reviewed' ? 'info' : ($activeStatus === 'calculated' ? 'warning' : 'secondary')) }}">
            {{ $activeStatus ? ucfirst($activeStatus) : 'No ranking run' }}
          </span>
          @if($activeRunId)
            <small class="text-muted">Run {{ $activeRunId }}</small>
          @endif
        </div>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ route('series.show', $series) }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Back to Series
        </a>

        <a href="{{ route('ranking.series.audit', $series) }}" class="btn btn-outline-info">
          <i class="ti ti-clipboard-check me-1"></i> Audit Rankings
        </a>

        <button id="print-ranking" class="btn btn-outline-dark" onclick="window.print()">
          <i class="ti ti-printer me-1"></i> Print Rankings
        </button>

        <button id="rebuild-ranking" class="btn btn-warning">
          <i class="ti ti-refresh me-1"></i> Rebuild Rankings
        </button>

        @if($activeStatus === 'calculated')
          <button class="btn btn-info ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.review', $series) }}"
                  data-confirm="Mark this complete run as reviewed?">
            <i class="ti ti-check me-1"></i> Mark Reviewed
          </button>
        @elseif($activeStatus === 'reviewed')
          <button class="btn btn-success ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.publish', $series) }}"
                  data-confirm="Publish this reviewed run to the public leaderboard?">
            <i class="ti ti-world-upload me-1"></i> Publish
          </button>
        @elseif($activeStatus === 'published' && $hasArchivedSnapshot)
          <button class="btn btn-outline-danger ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.rollback', $series) }}"
                  data-confirm="Roll back to the previous published snapshot?">
            <i class="ti ti-history me-1"></i> Roll Back
          </button>
        @endif
      </div>
    </div>
  </div>

  {{-- PRINT HEADER (only shown when printing) --}}
  <div class="d-none d-print-block mb-4">
    <h2>{{ $series->name }} ({{ $series->year }}) – Ranking List</h2>
    <small class="text-muted">Printed: {{ now()->format('d M Y H:i') }}</small>
    <hr>
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
              @foreach($rows as $rowIndex => $row)
                @php
                  $legs = collect($scoreDetails[$row->id] ?? []);
                  $meta = is_array($row->meta_json) ? $row->meta_json : [];
                  $tieKey = $category->id.':'.$row->total_points;
                  $nextRow = $rows->get($rowIndex + 1);
                  $isLastInTie = !$nextRow || (int) $nextRow->total_points !== (int) $row->total_points;
                  $headToHead = $isLastInTie ? ($headToHeadAdvisories[$tieKey] ?? null) : null;
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
                    <div class="d-flex gap-2 flex-wrap">
                      @foreach($legs as $leg)
                        @php
                          $event = $leg['event'];
                          $isAuto = $leg['synthetic'];
                          $scoreClass = $isAuto
                            ? 'ranking-event-score--automatic'
                            : ($leg['counted'] ? 'ranking-event-score--counted' : 'ranking-event-score--dropped');
                          $fragment = $leg['category_event_id'] ? '#category-event-'.$leg['category_event_id'] : '';
                          $statusLabel = $isAuto ? 'Automatic award' : ($leg['counted'] ? 'Counted' : 'Third score');
                        @endphp
                        @if($event)
                          <a
                            href="{{ route('admin.events.results.individual', $event).$fragment }}"
                            class="ranking-event-score {{ $scoreClass }} text-decoration-none d-block"
                            title="Open {{ $event->name }} final positions"
                          >
                            <span class="d-flex justify-content-between gap-2 align-items-start">
                              <span class="ranking-event-name small fw-semibold text-truncate">{{ $event->name }}</span>
                              <i class="ti ti-external-link small" aria-hidden="true"></i>
                            </span>
                            <span class="d-block mt-1">
                              <strong>{{ $leg['points'] }} pts</strong>
                              <span class="text-muted">·
                                @if($isAuto)
                                  Automatic #{{ $leg['ranking_position'] ?? 1 }}
                                @elseif($leg['actual_position'])
                                  Finished #{{ $leg['actual_position'] }}
                                @else
                                  Position unavailable
                                @endif
                              </span>
                            </span>
                            @if(!$isAuto && $leg['actual_position'] && $leg['ranking_position'] && $leg['actual_position'] !== $leg['ranking_position'])
                              <span class="d-block small text-warning-emphasis">Ranking points position #{{ $leg['ranking_position'] }}</span>
                            @endif
                            <span class="badge mt-1 {{ $isAuto ? 'bg-warning text-dark' : ($leg['counted'] ? 'bg-label-success' : 'bg-label-danger') }}">
                              {{ $statusLabel }}
                            </span>
                          </a>
                        @else
                          <span class="ranking-event-score {{ $scoreClass }} d-block">
                            <strong>{{ $leg['points'] }} pts</strong>
                            <span class="d-block small text-muted">{{ $statusLabel }} · Event unavailable</span>
                          </span>
                        @endif
                      @endforeach
                    </div>
                    @foreach($meta['tiebreak_notes'] ?? [] as $tiebreakNote)
                      <div class="tiebreak-note text-muted mt-1">
                        <i class="ti ti-scale me-1" aria-hidden="true"></i>{{ $tiebreakNote }}
                      </div>
                    @endforeach
                  </td>
                </tr>

                @if($headToHead)
                  <tr class="head-to-head-note">
                    <td></td>
                    <td colspan="3">
                      <div class="d-flex gap-2 align-items-start py-1">
                        <span class="badge {{ $headToHead['applied'] ? 'bg-success' : 'bg-info' }} mt-1">
                          {{ $headToHead['applied'] ? 'Head-to-head used' : 'Head-to-head review' }}
                        </span>
                        <div>
                          <div class="fw-semibold">
                            @if($headToHead['applied'])
                              The latest recorded head-to-head resolved players still tied after their third-event score.
                            @else
                              A recorded head-to-head is available, but it is not marked as applied in this ranking run.
                            @endif
                          </div>
                          @foreach($headToHead['matches'] as $match)
                            @php
                              $matchEvent = $series->events->firstWhere('id', $match['event_id']);
                              $matchFragment = $match['category_event_id'] ? '#category-event-'.$match['category_event_id'] : '';
                            @endphp
                            <div class="small mt-1">
                              <strong>{{ $match['winner_name'] }}</strong> beat {{ $match['loser_name'] }}
                              @if($match['score'])({{ $match['score'] }})@endif
                              at
                              @if($matchEvent)
                                <a href="{{ route('admin.events.results.individual', $matchEvent).$matchFragment }}">{{ $match['event_name'] }}</a>
                              @else
                                {{ $match['event_name'] }}
                              @endif
                            </div>
                          @endforeach
                          <div class="small text-muted mt-1">The most recent match is used only after the best-two total and third-event score remain tied.</div>
                        </div>
                      </div>
                    </td>
                  </tr>
                @endif
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
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}'
    }
  })
  .then(async response => {
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || 'Failed to rebuild rankings');
    return payload;
  })
  .then(r => {
    toastr.success(r.message);
    location.reload();
  })
  .catch(error => {
    toastr.error(error.message || 'Failed to rebuild rankings');
    btn.disabled = false;
  });
});

document.querySelectorAll('.ranking-lifecycle-action').forEach(button => {
  button.addEventListener('click', async () => {
    if (!window.confirm(button.dataset.confirm)) return;

    button.disabled = true;
    try {
      const response = await fetch(button.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Ranking action failed');
      toastr.success(payload.message);
      location.reload();
    } catch (error) {
      toastr.error(error.message || 'Ranking action failed');
      button.disabled = false;
    }
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
