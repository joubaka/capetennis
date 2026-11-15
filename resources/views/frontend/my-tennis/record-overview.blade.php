<section class="results-overview" aria-labelledby="results-overview-heading" data-results-overview>
  <style>
    .ct-record .results-overview{background:#142d50;color:#fff;border-radius:20px;padding:clamp(1rem,2.5vw,1.75rem);margin:1rem 0 1.25rem;border-top:4px solid #ff9f43;box-shadow:0 9px 24px #142d5012;overflow-wrap:anywhere}
    .ct-record .results-overview h2,.ct-record .results-overview h3{color:#fff}
    .ct-record .overview-heading{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem}
    .ct-record .overview-heading h2{font-size:clamp(1.4rem,3vw,1.8rem);margin:0 0 .25rem;font-weight:700}
    .ct-record .overview-eyebrow{display:block;color:#ffc182;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.4rem}
    .ct-record .overview-saved-status{color:#d2deed;font-size:.8rem;margin:0}
    .ct-record .overview-filter{align-items:end;gap:.5rem}.ct-record .overview-filter label{color:#d2deed;display:block;font-size:.75rem;margin:0 0 .3rem}
    .ct-record .overview-filter select{min-height:44px;min-width:8rem;max-width:100%;border:1px solid #e1e8ef;border-radius:8px;background:#fff;color:#142d50}
    .ct-record .overview-filter .btn{background:#ffb066;border-color:#ffb066;color:#142d50;font-weight:700;min-height:44px}
    .ct-record .overview-body{display:grid;gap:1.5rem}
    .ct-record .overview-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem;margin:0}
    .ct-record .overview-metric{padding:.75rem 0;border-top:1px solid #ffffff30}
    .ct-record .overview-metric dt{font-size:.76rem;color:#cfdbeb;font-weight:500;margin-bottom:.35rem}
    .ct-record .overview-metric dd{font-size:clamp(1.75rem,4vw,2.6rem);line-height:1.15;font-weight:700;font-variant-numeric:tabular-nums;margin:0;letter-spacing:-.04em;color:#fff}
    .ct-record .overview-metric:last-child dd{color:#ffc182}
    .ct-record .overview-reach{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;margin:.75rem 0 1.1rem;font-size:.85rem;color:#d2deed}
    .ct-record .overview-reach strong{color:#fff;font-size:1.05rem}
    .ct-record .overview-form-heading{font-size:.8rem;font-weight:600;margin:0 0 .5rem;color:#e4edf8}
    .ct-record .overview-form{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}
    .ct-record .overview-form-mark{display:inline-flex;align-items:center;justify-content:center;width:2.2rem;height:2.2rem;border-radius:8px;font-weight:700;font-size:.85rem;background:#fff;color:#142d50;border:1px solid #ffffff50}
    .ct-record .overview-form-mark[data-result="loss"]{background:#ffb066;border-color:#ffb066;color:#142d50}
    .ct-record .overview-form-note{font-size:.72rem;color:#c1d0e3;margin:.5rem 0 0}
    .ct-record .overview-evidence{border-top:1px solid #ffffff25;margin-top:1.2rem;padding-top:.2rem;font-size:.8rem;color:#d2deed}
    .ct-record .overview-evidence summary{min-height:44px;cursor:pointer;align-content:center;width:fit-content;color:#fff}
    .ct-record .overview-evidence p{max-width:64rem;line-height:1.6;margin:0 0 .5rem}
    .ct-record .overview-evidence summary:focus-visible{outline:3px solid #ffb066;outline-offset:2px}
    .ct-record .overview-chart{border-radius:14px;padding:1rem;background:#fff;color:#172e45;align-self:start}
    .ct-record .overview-chart h3{color:#142d50;font-size:1rem;margin:0 0 .2rem;font-weight:700}
    .ct-record .overview-chart .record-note{font-size:.74rem;line-height:1.5}
    .ct-record .overview-chart .record-month-chart{list-style:none;padding:0;margin:.9rem 0 0;gap:.25rem}
    .ct-record .overview-chart .record-month-chart li{min-width:0}
    .ct-record .overview-chart .record-month-link{display:block;text-decoration:none;color:#52677b;border-radius:5px;padding:.15rem 0;min-height:44px}
    .ct-record .overview-chart a.record-month-link:hover,.ct-record .overview-chart a.record-month-link[aria-current="true"]{background:#eef3f9;box-shadow:0 0 0 2px #145c8d}
    .ct-record .overview-chart .record-month-count{display:block;color:#142d50;font-size:.7rem;font-weight:600;min-height:1.2rem;font-variant-numeric:tabular-nums}
    .ct-record .overview-chart .record-month-column{height:6.25rem;border-color:#d2dce8}
    .ct-record .overview-chart .record-month-chart small{display:block;visibility:visible;font-size:.61rem;letter-spacing:-.02em;margin-top:.3rem}
    .ct-record .overview-chart .record-month-bars{width:70%;margin-inline:auto;display:flex;flex-direction:column;justify-content:flex-end;height:100%;overflow:hidden;border-radius:4px 4px 0 0}
    .ct-record .overview-chart details summary{cursor:pointer;min-height:44px;align-content:center;font-size:.76rem;color:#004177}
    .ct-record .overview-chart .table{font-size:.75rem}
    @media(min-width:992px){.ct-record .overview-body{grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:2rem}.ct-record .overview-metrics{gap:1rem}.ct-record .overview-chart{margin-top:0}}
    @media(max-width:575px){.ct-record .overview-heading{align-items:start}.ct-record .overview-filter{width:100%;display:grid;grid-template-columns:minmax(0,1fr) auto}.ct-record .overview-filter select{width:100%;min-width:0}.ct-record .overview-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:.3rem 1rem}.ct-record .overview-metric dt{font-size:.72rem}.ct-record .overview-metric dd{font-size:1.75rem}.ct-record .overview-chart{padding:.85rem .65rem}.ct-record .overview-form-mark{width:2rem;height:2rem}}
  </style>
  <div class="overview-heading">
    <div>
      <span class="overview-eyebrow">Your season in numbers</span>
      <h2 id="results-overview-heading">Results overview</h2>
      <p class="overview-saved-status">{{ $record['year'] ?: 'All seasons' }} · Saved singles @if($record['as_of']) through {{ $record['as_of'] }}@endif @if($record['stale']) · Update pending @endif</p>
    </div>
    <form method="get" class="record-filter overview-filter" action="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => 'record']) }}">
      @if($record['opponent'] || request()->integer('opponent'))<input type="hidden" name="opponent" value="{{ $record['opponent']['id'] ?? request()->integer('opponent') }}">@endif
      @foreach(['first', 'second'] as $side)@if($record['comparison'][$side.'_year'] !== '')<input type="hidden" name="compare_{{ $side }}" value="{{ $record['comparison'][$side.'_year'] }}">@endif @endforeach
      <div><label for="record-year">Season</label><select id="record-year" name="year" class="form-select"><option value="">All seasons</option>@if($record['year'] !== '' && !in_array($record['year'], array_map('strval', $record['years']), true))<option value="{{ $record['year'] }}" selected>{{ $record['year'] }} · no saved results</option>@endif @foreach($record['years'] as $year)<option value="{{ $year }}" @selected((string) $year === $record['year'])>{{ $year }}</option>@endforeach</select></div>
      <button class="btn" type="submit">Apply</button>
    </form>
  </div>
  @if($record['reason'])
    <p role="status" class="mb-0">{{ $record['reason'] }}</p>
  @else
    <div class="overview-body">
      <div>
        <dl class="overview-metrics" aria-label="Saved season statistics">
          @foreach(['Matches' => $record['played'], 'Wins' => $record['wins'], 'Losses' => $record['played'] - $record['wins'], 'Win rate' => $record['played'] ? round(100 * $record['wins'] / $record['played']).'%' : '—'] as $label => $value)
            <div class="overview-metric"><dt>{{ $label }}</dt><dd data-overview-metric="{{ \Illuminate\Support\Str::slug($label) }}" data-results-stat="{{ ['Matches' => 'played', 'Wins' => 'wins', 'Losses' => 'losses', 'Win rate' => 'rate'][$label] }}">{{ $value }}</dd></div>
          @endforeach
        </dl>
        <p class="overview-reach"><span><strong>{{ $record['events'] }}</strong> {{ \Illuminate\Support\Str::plural('event', $record['events']) }}</span><span><strong>{{ $record['opponent_count'] }}</strong> {{ \Illuminate\Support\Str::plural('opponent', $record['opponent_count']) }}</span></p>
        <h3 class="overview-form-heading">Latest five saved results</h3>
        @if($record['form'])
          <div class="overview-form" data-results-form aria-label="Latest saved form, newest first">
            @foreach($record['form'] as $match)<span class="overview-form-mark" data-result="{{ $match['won'] ? 'win' : 'loss' }}" aria-label="{{ $match['won'] ? 'Win' : 'Loss' }} against {{ $match['opponent'] }} on {{ $match['date'] }}">{{ $match['won'] ? 'W' : 'L' }}</span>@endforeach
          </div>
          <p class="overview-form-note">Newest first · same-day order may be approximate.</p>
        @else
          <p class="overview-form-note" role="status">No saved match results for this season.</p>
        @endif
      </div>
      @include('frontend.my-tennis.record-monthly')
    </div>
  @endif
  <details class="overview-evidence">
    <summary>About these saved results</summary>
    <p>Saved results{{ $record['coverage_start'] ? ' from '.$record['coverage_start'] : '' }}@if($record['as_of']) through {{ $record['as_of'] }}@endif. Season statistics cover the selected season, or all saved seasons when selected. The activity chart covers the selected year or the latest 12 saved months. A month link filters the match history without changing the season totals.</p>
    <p>Some matches may be missing. Final placings are separate from played matches. Estimated match dates use the event start date. The order of matches on the same day may be unknown. Latest results below show scores posted or updated in the past seven days and may arrive before the saved record updates.@unless(auth()->user()?->hasRole('super-user')) Connected TeamSheet match details have limited access.@endunless</p>
  </details>
</section>
