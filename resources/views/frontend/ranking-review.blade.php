@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Provisional Rankings')

@section('page-style')
<style>
  .ranking-review-table th { white-space: nowrap; }
  .ranking-review-rank { width: 5.5rem; }
  .ranking-review-total { width: 8rem; white-space: nowrap; }
  .ranking-review-player { min-width: 12rem; }
  .ranking-review-events { min-width: 18rem; }
  .ranking-review-event-list { display: flex; flex-wrap: wrap; gap: .5rem; }
  .ranking-review-event {
    min-width: 11rem;
    padding: .55rem .65rem;
    border: 1px solid var(--bs-border-color);
    border-left: .25rem solid var(--bs-success);
    border-radius: .45rem;
    background: var(--bs-body-bg);
  }
  .ranking-review-event--dropped { border-left-color: var(--bs-danger); opacity: .82; }
  .ranking-review-event--automatic { border-left-color: var(--bs-warning); }
  .ranking-review-event-name { color: var(--bs-heading-color); font-weight: 600; overflow-wrap: anywhere; }
  .ranking-review-legend { display: flex; flex-wrap: wrap; gap: .75rem; padding: 0 1rem 1rem; color: var(--bs-secondary-color); font-size: .78rem; }
  .ranking-review-list > summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    cursor: pointer;
    list-style: none;
    user-select: none;
  }
  .ranking-review-list > summary::-webkit-details-marker { display: none; }
  .ranking-review-list > summary:hover { background: var(--bs-tertiary-bg); }
  .ranking-review-list > summary:focus-visible { outline: .2rem solid rgba(var(--bs-primary-rgb), .3); outline-offset: -.2rem; }
  .ranking-review-toggle-icon { transition: transform .2s ease; }
  .ranking-review-list[open] .ranking-review-toggle-icon { transform: rotate(180deg); }
  .ranking-review-filter-wrap { border-top: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); padding: .85rem 1rem; background: var(--bs-tertiary-bg); }
  .ranking-review-filter { max-width: 28rem; }
  .ranking-review-player-row[hidden] { display: none !important; }

  @media (max-width: 767.98px) {
    .ranking-review-table thead { display: none; }
    .ranking-review-table,
    .ranking-review-table tbody,
    .ranking-review-table tr,
    .ranking-review-table td { display: block; width: 100%; }
    .ranking-review-table tbody tr { display: grid; grid-template-columns: 2.75rem minmax(0, 1fr) auto; gap: .3rem .6rem; padding: .8rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
    .ranking-review-table tbody td { padding: 0; border: 0; }
    .ranking-review-rank { grid-column: 1; width: auto; }
    .ranking-review-player { grid-column: 2; min-width: 0; overflow-wrap: anywhere; }
    .ranking-review-total { grid-column: 3; width: auto; }
    .ranking-review-total::before { content: 'Points: '; color: var(--bs-secondary-color); font-size: .7rem; font-weight: 500; }
    .ranking-review-events { grid-column: 1 / -1; min-width: 0; padding-top: .4rem !important; }
    .ranking-review-event-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ranking-review-event { min-width: 0; }
  }

  @media (max-width: 420px) {
    .ranking-review-event-list { grid-template-columns: 1fr; }
  }
</style>
@endsection

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
          Please check your position, total, and each event score. Reply to the ranking email before
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
    @php
      $rows = $rankings->where('category_id', $category->id)->sortBy('rank_position');
    @endphp
    <details class="card mb-3 shadow-sm ranking-review-list" data-ranking-list="{{ $category->id }}">
      <summary aria-label="Open or close {{ $category->name }} ranking list">
        <span class="d-flex align-items-center gap-2">
          <span class="h5 mb-0">{{ $category->name }}</span>
          <span class="badge bg-label-secondary">{{ $rows->count() }} {{ Str::plural('player', $rows->count()) }}</span>
        </span>
        <span class="d-flex align-items-center gap-2 text-muted small">
          <span class="ranking-review-toggle-label">Click to open</span>
          <i class="ti ti-chevron-down ranking-review-toggle-icon" aria-hidden="true"></i>
        </span>
      </summary>
      <div class="ranking-review-filter-wrap">
        <label class="visually-hidden" for="ranking-filter-{{ $category->id }}">Filter players in {{ $category->name }}</label>
        <div class="input-group ranking-review-filter">
          <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
          <input type="search" class="form-control ranking-review-filter-input" id="ranking-filter-{{ $category->id }}"
            placeholder="Filter players in this ranking list…" autocomplete="off">
          <button class="btn btn-outline-secondary ranking-review-filter-clear d-none" type="button">Clear</button>
        </div>
        <div class="small text-muted mt-2 ranking-review-filter-status" role="status" aria-live="polite">Showing all {{ $rows->count() }} players</div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0 ranking-review-table">
          <thead><tr><th>Rank</th><th>Player</th><th class="text-end">Total points</th><th>Scores per event</th></tr></thead>
          <tbody>
            @foreach($rows as $row)
              @php
                $legs = collect($scoreDetails[$row->id] ?? []);
              @endphp
              <tr class="ranking-review-player-row" data-player-search="{{ Str::lower(($row->player?->full_name ?? 'Unknown player').' '.$row->rank_position) }}">
                <td class="fw-bold ranking-review-rank">#{{ $row->rank_position }}</td>
                <td class="ranking-review-player">{{ $row->player?->full_name ?? 'Unknown player' }}</td>
                <td class="text-end fw-semibold ranking-review-total">{{ number_format($row->total_points, 0) }}</td>
                <td class="ranking-review-events">
                  <div class="ranking-review-event-list">
                    @forelse($legs as $leg)
                      @php
                        $event = $leg['event'];
                        $isAutomatic = $leg['synthetic'];
                        $statusLabel = $isAutomatic ? 'Automatic award' : ($leg['counted'] ? 'Counted' : 'Not counted');
                        $eventClass = $isAutomatic
                          ? 'ranking-review-event--automatic'
                          : ($leg['counted'] ? '' : 'ranking-review-event--dropped');
                      @endphp
                      <div class="ranking-review-event {{ $eventClass }}">
                        <div class="ranking-review-event-name">{{ $event?->name ?? 'Event unavailable' }}</div>
                        <div class="mt-1">
                          <strong>{{ number_format($leg['points'], 0) }} pts</strong>
                          <span class="text-muted">·
                            @if($isAutomatic)
                              Automatic #{{ $leg['ranking_position'] ?? 1 }}
                            @elseif($leg['actual_position'])
                              Finished #{{ $leg['actual_position'] }}
                            @else
                              Position unavailable
                            @endif
                          </span>
                        </div>
                        @if(!$isAutomatic && $leg['actual_position'] && $leg['ranking_position'] && $leg['actual_position'] !== $leg['ranking_position'])
                          <div class="small text-warning-emphasis">Ranking points position #{{ $leg['ranking_position'] }}</div>
                        @endif
                        <span class="badge mt-1 {{ $isAutomatic ? 'bg-warning text-dark' : ($leg['counted'] ? 'bg-label-success' : 'bg-label-danger') }}">{{ $statusLabel }}</span>
                      </div>
                    @empty
                      <span class="small text-muted">No event score details available</span>
                    @endforelse
                  </div>
                  @php
                    $tiebreakNotes = is_array($row->meta_json) ? ($row->meta_json['tiebreak_notes'] ?? []) : [];
                  @endphp
                  @foreach($tiebreakNotes as $tiebreakNote)
                    <div class="small text-muted mt-2"><i class="ti ti-scale me-1" aria-hidden="true"></i>{{ $tiebreakNote }}</div>
                  @endforeach
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="alert alert-secondary m-3 ranking-review-filter-empty d-none" role="status">No players match this filter.</div>
      <div class="ranking-review-legend">
        <span><span class="badge bg-label-success">Counted</span> contributes to the total</span>
        <span><span class="badge bg-label-danger">Not counted</span> shown for review only</span>
        <span><span class="badge bg-warning text-dark">Automatic award</span> system-applied score</span>
      </div>
    </details>
  @endforeach
</div>
@endsection

@section('page-script')
<script>
document.querySelectorAll('.ranking-review-list').forEach(function (list) {
  var input = list.querySelector('.ranking-review-filter-input');
  var clear = list.querySelector('.ranking-review-filter-clear');
  var rows = Array.from(list.querySelectorAll('.ranking-review-player-row'));
  var status = list.querySelector('.ranking-review-filter-status');
  var empty = list.querySelector('.ranking-review-filter-empty');
  var toggleLabel = list.querySelector('.ranking-review-toggle-label');

  list.addEventListener('toggle', function () {
    toggleLabel.textContent = list.open ? 'Click to close' : 'Click to open';
  });

  function applyFilter() {
    var query = input.value.trim().toLocaleLowerCase();
    var visible = 0;
    rows.forEach(function (row) {
      var matches = query === '' || (row.dataset.playerSearch || '').includes(query);
      row.hidden = !matches;
      if (matches) visible++;
    });
    clear.classList.toggle('d-none', query === '');
    empty.classList.toggle('d-none', visible !== 0);
    status.textContent = query === ''
      ? 'Showing all ' + rows.length + ' players'
      : 'Showing ' + visible + ' of ' + rows.length + ' players';
  }

  input.addEventListener('input', applyFilter);
  clear.addEventListener('click', function () {
    input.value = '';
    applyFilter();
    input.focus();
  });
});
</script>
@endsection
