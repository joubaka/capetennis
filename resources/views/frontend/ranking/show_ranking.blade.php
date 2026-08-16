@extends('layouts/layoutMaster')

@section('title', $series->name . ' Rankings')

@section('page-style')
<style>
  .public-ranking-shell { max-width: 1280px; margin: 0 auto; }
  .public-ranking-card { border: 1px solid var(--bs-border-color); border-radius: .55rem; box-shadow: 0 .125rem .75rem rgba(47, 43, 61, .06); overflow: hidden; }
  .public-ranking-category + .public-ranking-category { border-top: 1px solid var(--bs-border-color); }
  .public-ranking-category__heading { margin: 0; padding: 1.5rem 1.35rem 1rem; font-size: 1rem; font-weight: 700; }
  .public-ranking-table-wrap { padding: 0 1.35rem 1.5rem; }
  .public-ranking-table { margin: 0; color: var(--bs-body-color); }
  .public-ranking-table thead th { padding: .85rem 1rem; border-bottom: 0; background: rgba(75, 70, 92, .12); color: var(--bs-secondary-color); font-size: .72rem; font-weight: 700; letter-spacing: .055rem; text-transform: uppercase; white-space: nowrap; }
  .public-ranking-table tbody td { padding: .55rem 1rem; border-color: var(--bs-border-color); vertical-align: middle; }
  .public-ranking-table tbody tr:last-child td { border-bottom: 0; }
  .public-ranking-rank { width: 76px; font-weight: 700; white-space: nowrap; }
  .public-ranking-player { min-width: 230px; font-weight: 500; }
  .public-ranking-total { width: 150px; font-weight: 700; white-space: nowrap; }
  .public-ranking-scores { display: flex; flex-wrap: wrap; gap: .3rem; }
  .public-ranking-score { display: inline-flex; align-items: center; min-height: 1.55rem; padding: .25rem .65rem; border-radius: .25rem; color: #fff; font-size: .75rem; font-weight: 700; line-height: 1; white-space: nowrap; }
  .public-ranking-score--counted { background: #28c76f; }
  .public-ranking-score--dropped { background: #ea5455; }
  .public-ranking-score--synthetic { background: #ff9f43; }
  .public-ranking-legend { display: flex; flex-wrap: wrap; gap: .85rem; padding: 0 1.35rem 1.25rem; color: var(--bs-secondary-color); font-size: .78rem; }
  .public-ranking-legend span::before { content: ''; display: inline-block; width: .55rem; height: .55rem; margin-right: .35rem; border-radius: 50%; }
  .legend-counted::before { background: #28c76f; }
  .legend-dropped::before { background: #ea5455; }
  .legend-synthetic::before { background: #ff9f43; }

  @media (max-width: 767.98px) {
    .public-ranking-category__heading { padding: 1.15rem 1rem .75rem; }
    .public-ranking-table-wrap { padding: 0 1rem 1rem; }
    .public-ranking-table thead { display: none; }
    .public-ranking-table, .public-ranking-table tbody, .public-ranking-table tr, .public-ranking-table td { display: block; width: 100%; }
    .public-ranking-table tbody tr { display: grid; grid-template-columns: 3.5rem minmax(0, 1fr) auto; gap: .3rem .7rem; padding: .8rem 0; border-bottom: 1px solid var(--bs-border-color); }
    .public-ranking-table tbody td { padding: 0; border: 0; }
    .public-ranking-rank { grid-column: 1; grid-row: 1; width: auto; }
    .public-ranking-player { grid-column: 2; grid-row: 1; min-width: 0; }
    .public-ranking-total { grid-column: 3; grid-row: 1; width: auto; }
    .public-ranking-scores-cell { grid-column: 1 / -1; grid-row: 2; padding-left: 4.2rem !important; }
    .public-ranking-total::before { content: 'Points: '; color: var(--bs-secondary-color); font-size: .7rem; font-weight: 500; }
    .public-ranking-legend { padding: 0 1rem 1rem; }
  }
</style>
@endsection

@section('content')
@php
  $seriesYear = $series->year ? (string) $series->year : null;
  $seriesLabel = $series->name;
  if ($seriesYear && !str_contains($seriesLabel, $seriesYear)) {
    $seriesLabel .= ' · ' . $seriesYear;
  }
@endphp
<div class="public-ranking-shell">
  <div class="card public-ranking-card mb-4">
    <div class="card-header pb-2">
      <h4 class="mb-1">Rankings</h4>
      <div class="text-muted">{{ $seriesLabel }}</div>
    </div>

    @forelse($categories as $category)
      @php
        $rows = $rankings->where('category_id', $category->id)->sortBy('rank_position')->values();
      @endphp

      @if($rows->isNotEmpty())
        <section class="public-ranking-category" aria-labelledby="ranking-category-{{ $category->id }}">
          <h5 class="public-ranking-category__heading" id="ranking-category-{{ $category->id }}">{{ $category->name }}</h5>

          <div class="public-ranking-table-wrap">
            <table class="table public-ranking-table">
              <thead>
                <tr>
                  <th scope="col">Rank</th>
                  <th scope="col">Player</th>
                  <th scope="col">Total points</th>
                  <th scope="col">Scores per event</th>
                </tr>
              </thead>
              <tbody>
                @foreach($rows as $row)
                  @php
                    $displayLegs = $displayLegsByRanking->get($row->id, collect());
                  @endphp
                  <tr>
                    <td class="public-ranking-rank">#{{ $row->rank_position }}</td>
                    <td class="public-ranking-player">
                      @if($row->player)
                        <a href="{{ route('frontend.ranking.player-detail', [$series, $row->player]) }}" class="text-body text-decoration-none">
                          {{ $row->player->full_name ?? ($row->player->name ?? 'Unknown Player') }}
                        </a>
                      @else
                        Unknown Player
                      @endif
                    </td>
                    <td class="public-ranking-total">{{ rtrim(rtrim(number_format((float) $row->total_points, 2, '.', ''), '0'), '.') }}</td>
                    <td class="public-ranking-scores-cell">
                      <div class="public-ranking-scores">
                        @forelse($displayLegs as $leg)
                          @php
                            $scoreClass = !empty($leg['synthetic'])
                              ? 'public-ranking-score--synthetic'
                              : (($leg['status'] ?? 'counted') === 'dropped' ? 'public-ranking-score--dropped' : 'public-ranking-score--counted');
                            $points = rtrim(rtrim(number_format((float) ($leg['points'] ?? 0), 2, '.', ''), '0'), '.');
                          @endphp
                          <span class="public-ranking-score {{ $scoreClass }}"
                                title="{{ ($leg['status'] ?? 'counted') === 'dropped' ? 'Not counted' : (!empty($leg['synthetic']) ? 'Automatic award' : 'Counted') }}">
                            {{ $points }} (E{{ $leg['event_id'] ?? '?' }})
                          </span>
                        @empty
                          <span class="text-muted small">No event scores available</span>
                        @endforelse
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </section>
      @endif
    @empty
      <div class="card-body text-muted">No published ranking results are available yet.</div>
    @endforelse

    <div class="public-ranking-legend" aria-label="Score colour key">
      <span class="legend-counted">Counted score</span>
      <span class="legend-dropped">Not counted</span>
      <span class="legend-synthetic">Automatic award</span>
    </div>
  </div>
</div>
@endsection
