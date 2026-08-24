@extends('layouts/layoutMaster')

@section('title', $series->name . ' Rankings')

@section('page-style')
<style>
  .public-ranking-shell { width: 100%; max-width: 1280px; margin: 0 auto; padding-inline: clamp(.75rem, 2vw, 1.5rem); }
  .public-ranking-card { border: 1px solid var(--bs-border-color); border-radius: .75rem; box-shadow: 0 .125rem .75rem rgba(47, 43, 61, .06); overflow: hidden; }
  .public-ranking-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 1.5rem; padding: 1.75rem 1.5rem; color: #fff; background: linear-gradient(135deg, #5548c8 0%, #7367f0 62%, #8f84ff 100%); }
  .public-ranking-hero h1 { color: #fff !important; font-size: clamp(1.35rem, 2.2vw, 1.8rem); }
  .public-ranking-hero__eyebrow { display: block; margin-bottom: .4rem; color: rgba(255,255,255,.72); font-size: .72rem; font-weight: 700; letter-spacing: .08rem; text-transform: uppercase; }
  .public-ranking-hero__icon { display: grid; width: 4rem; height: 4rem; flex: 0 0 4rem; place-items: center; border: 1px solid rgba(255,255,255,.22); border-radius: 50%; background: rgba(255,255,255,.12); font-size: 1.75rem; }
  .public-ranking-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; padding: 1rem 1.35rem; border-bottom: 1px solid var(--bs-border-color); background: rgba(75, 70, 92, .035); }
  .public-ranking-stat { padding: .65rem .85rem; border-radius: .5rem; background: var(--bs-body-bg); }
  .public-ranking-stat__value { display: block; color: var(--bs-heading-color); font-size: 1.1rem; font-weight: 700; }
  .public-ranking-stat__label { color: var(--bs-secondary-color); font-size: .72rem; }
  .public-ranking-tools { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.35rem 0; }
  .public-ranking-filter { max-width: 20rem; }
  .public-ranking-tools { align-items: end; }
  .public-ranking-division-picker { min-width: min(100%, 24rem); }
  .public-ranking-division-picker label { display: block; margin-bottom: .35rem; color: var(--bs-secondary-color); font-size: .72rem; font-weight: 700; letter-spacing: .04rem; text-transform: uppercase; }
  .public-ranking-division-picker select { min-height: 2.5rem; border-color: rgba(115,103,240,.45); font-weight: 600; }
  .public-ranking-category + .public-ranking-category { border-top: 1px solid var(--bs-border-color); }
  .public-ranking-category__heading { margin: 0; padding: 1.5rem 1.35rem 1rem; font-size: 1rem; font-weight: 700; }
  .public-ranking-table-wrap { padding: 0 1.35rem 1.5rem; }
  .public-ranking-table { margin: 0; color: var(--bs-body-color); }
  .public-ranking-table thead th { padding: .85rem 1rem; border-bottom: 0; background: rgba(75, 70, 92, .12); color: var(--bs-secondary-color); font-size: .72rem; font-weight: 700; letter-spacing: .055rem; text-transform: uppercase; white-space: nowrap; }
  .public-ranking-table tbody td { padding: .55rem 1rem; border-color: var(--bs-border-color); vertical-align: middle; }
  .public-ranking-table tbody tr:last-child td { border-bottom: 0; }
  .public-ranking-table tbody tr:hover { background: rgba(115,103,240,.035); }
  .public-ranking-rank { width: 76px; font-weight: 700; white-space: nowrap; }
  .public-ranking-player { min-width: 230px; font-weight: 500; }
  .public-ranking-player a { display: inline-flex; align-items: center; gap: .5rem; color: var(--bs-heading-color) !important; font-weight: 600; }
  .public-ranking-player a:hover { color: #7367f0 !important; }
  .public-ranking-player__action { display: inline-flex; align-items: center; gap: .2rem; padding: .22rem .45rem; border: 1px solid rgba(115,103,240,.28); border-radius: 999px; color: #7367f0; background: rgba(115,103,240,.08); font-size: .65rem; font-weight: 700; line-height: 1; white-space: nowrap; }
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
  .public-ranking-category.is-hidden, .public-ranking-row.is-hidden { display: none; }
  .public-ranking-table-wrap { overflow-x: auto; }

  @media (max-width: 767.98px) {
    .public-ranking-category__heading { padding: 1.15rem 1rem .75rem; }
    .public-ranking-hero { align-items: flex-start; padding: 1.35rem 1rem; }
    .public-ranking-hero__icon { display: none; }
    .public-ranking-summary { padding: .75rem 1rem; }
    .public-ranking-stat { padding: .55rem .65rem; }
    .public-ranking-tools { align-items: stretch; flex-direction: column; padding: .85rem 1rem 0; }
    .public-ranking-filter { max-width: none; }
    .public-ranking-tab { flex: 0 0 auto; }
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
    .public-ranking-table { min-width: 0; }
    .public-ranking-player a { display: inline-block; max-width: 100%; overflow-wrap: anywhere; }
    .public-ranking-score { padding-inline: .5rem; font-size: .7rem; }
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
    <header class="public-ranking-hero">
      <div>
        <span class="public-ranking-hero__eyebrow">Cape Tennis leaderboard</span>
        <h1 class="mb-1">{{ $seriesLabel }}</h1>
        <p class="mb-0 text-white-50">Official published positions and event scores.</p>
      </div>
      <span class="public-ranking-hero__icon" aria-hidden="true"><i class="ti ti-trophy"></i></span>
    </header>

    @php
      $totalPlayers = $rankings->pluck('player_id')->filter()->unique()->count();
      $totalScores = $displayLegsByRanking->flatten(1)->count();
    @endphp
    <div class="public-ranking-summary" aria-label="Ranking summary">
      <div class="public-ranking-stat"><span class="public-ranking-stat__value">{{ $categories->count() }}</span><span class="public-ranking-stat__label">Divisions</span></div>
      <div class="public-ranking-stat"><span class="public-ranking-stat__value">{{ $totalPlayers }}</span><span class="public-ranking-stat__label">Players ranked</span></div>
      <div class="public-ranking-stat"><span class="public-ranking-stat__value">{{ $totalScores }}</span><span class="public-ranking-stat__label">Event scores shown</span></div>
    </div>

    <div class="public-ranking-tools">
      <div class="public-ranking-division-picker">
        <label for="ranking-division-select">View division</label>
        <select id="ranking-division-select" class="form-select form-select-sm">
          <option value="all" selected>All divisions</option>
          @foreach($categories as $category)
            <option value="category-{{ $category->id }}">{{ $category->name }}</option>
          @endforeach
        </select>
        <div id="ranking-view-status" class="small text-muted mt-1" aria-live="polite"></div>
      </div>
      <div class="small text-muted"><i class="ti ti-info-circle me-1" aria-hidden="true"></i>Click a player’s name to review their scores per event.</div>
      <label class="public-ranking-filter input-group input-group-sm" for="ranking-player-search">
        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
        <input id="ranking-player-search" class="form-control" type="search" placeholder="Search players" autocomplete="off">
      </label>
    </div>
    @forelse($categories as $category)
      @php
        $rows = $rankings->where('category_id', $category->id)->sortBy('rank_position')->values();
      @endphp

      @if($rows->isNotEmpty())
        <section class="public-ranking-category" data-ranking-category="category-{{ $category->id }}" aria-labelledby="ranking-category-{{ $category->id }}">
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
                  <tr class="public-ranking-row" data-player-name="{{ strtolower($row->player?->full_name ?? $row->player?->name ?? 'Unknown Player') }}">
                    <td class="public-ranking-rank">#{{ $row->rank_position }}</td>
                    <td class="public-ranking-player">
                      @if($row->player)
                        <a href="{{ route('frontend.ranking.player-detail', [$series, $row->player]) }}" class="text-body text-decoration-none" title="Review {{ $row->player->full_name ?? ($row->player->name ?? 'player') }}'s event scores">
                          {{ $row->player->full_name ?? ($row->player->name ?? 'Unknown Player') }}
                          <span class="public-ranking-player__action">View scores <i class="ti ti-arrow-up-right" aria-hidden="true"></i></span>
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
                                title="{{ ($leg['status'] ?? 'counted') === 'dropped' ? 'Not counted' : (!empty($leg['synthetic']) ? 'Automatic award' : 'Counted') }}{{ isset($leg['event_id']) ? ' · Event ' . $leg['event_id'] : '' }}">
                            {{ $points }}
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

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('ranking-player-search');
    const divisionSelect = document.getElementById('ranking-division-select');
    const viewStatus = document.getElementById('ranking-view-status');
    const categories = document.querySelectorAll('[data-ranking-category]');
    const apply = function () {
      const query = (search?.value || '').trim().toLowerCase();
      const active = divisionSelect?.value || 'all';
      const activeLabel = divisionSelect?.options[divisionSelect.selectedIndex]?.text || 'All divisions';
      categories.forEach(function (category) {
        const matchesCategory = active === 'all' || category.dataset.rankingCategory === active;
        let visibleRows = 0;
        category.querySelectorAll('.public-ranking-row').forEach(function (row) {
          const visible = matchesCategory && (!query || row.dataset.playerName.includes(query));
          row.classList.toggle('is-hidden', !visible);
          if (visible) visibleRows++;
        });
        category.classList.toggle('is-hidden', !matchesCategory || visibleRows === 0);
      });
      if (viewStatus) {
        viewStatus.textContent = active === 'all' ? 'Showing all published divisions.' : `Showing ${activeLabel}.`;
      }
    };
    divisionSelect?.addEventListener('change', apply);
    search?.addEventListener('input', apply);
    apply();
  });
</script>
@endsection
@endsection
