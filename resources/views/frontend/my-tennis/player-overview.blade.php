<style>
.ct-overview{color:#172e45;margin-bottom:1.5rem}.ct-overview *{min-width:0}.ct-overview a{color:#004177}.ct-overview .ov-hero{padding:clamp(1.25rem,3vw,2rem);border-radius:16px;background:linear-gradient(120deg,#172e45,#004177);color:white;margin-bottom:1rem}.ct-overview .ov-hero h2{color:white;margin:.3rem 0 .6rem;font-size:clamp(1.5rem,3vw,2rem)}.ct-overview .ov-eyebrow{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#ffb66c;font-weight:700}.ct-overview .ov-hero p{max-width:44rem;color:#e0ebf5}.ct-overview .ov-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.ct-overview .ov-stat,.ct-overview .ov-panel{padding:1.25rem;border:1px solid #dce5ef;border-radius:14px;background:#fff}.ct-overview .ov-stat strong{display:block;font-size:1.8rem;color:#004177;line-height:1.3}.ct-overview .ov-stat span,.ct-overview .ov-note{font-size:.85rem;color:#52677b}.ct-overview .ov-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}.ct-overview h3{font-size:1.1rem;color:#172e45}.ct-overview .ov-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}.ct-overview .ov-actions a{display:inline-flex;align-items:center;min-height:44px;padding:.6rem .9rem;border:1px solid #bdcfe0;border-radius:8px;text-decoration:none}.ct-overview .ov-form{display:flex;flex-wrap:wrap;gap:.35rem;margin:.7rem 0}.ct-overview .ov-form span{display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:#004177;color:white;font-weight:700}.ct-overview .ov-form .ov-loss{background:#172e45}.ct-overview .ov-month{border-top:4px solid #ff9f43}.ct-overview .plus-sample dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.ct-overview .plus-sample dl>div{background:#f1f5fa;border-radius:8px;padding:.75rem}.ct-overview .plus-sample dd{font-weight:700;color:#004177;font-size:1.15rem;margin:0}.ct-overview a:focus-visible{outline:3px solid #b85b00;outline-offset:3px}@media(max-width:650px){.ct-overview .ov-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ct-overview .ov-panels{grid-template-columns:1fr}.ct-overview .ov-panel{padding:1rem}}
</style>
<section class="ct-overview" aria-labelledby="player-overview-heading" data-player-overview>
  <div class="ov-hero">
    <span class="ov-eyebrow">Your tennis story</span>
    <h2 id="player-overview-heading">Every match adds to your story.</h2>
    <p class="mb-0">See your season at a glance, revisit your recent results and choose your next step.</p>
  </div>
  @if(($canViewRating ?? false) && $overviewRecord)
    @php
      $record = $overviewRecord;
      $report = $record['month_report'];
      $monthLabel = \Carbon\CarbonImmutable::parse($report['month'].'-01')->format('F Y');
      $latestRating = !empty($ratingSnapshot['points']) ? $ratingSnapshot['points'][array_key_last($ratingSnapshot['points'])] : null;
      $recordUrl = route('my.tennis.record', ['player' => $selectedPlayer, 'year' => $record['year']]);
    @endphp
    @if($record['reason'])
      <div class="ov-panel" role="status"><h3>Your season report is not available yet</h3><p>{{ $record['reason'] }}</p><p class="ov-note mb-0">Saved results will appear here when available. A missing record is not a zero-match season.</p></div>
    @else
      <div class="ov-grid" aria-label="{{ $record['year'] }} recorded season">
        <div class="ov-stat"><strong>{{ $record['played'] }}</strong><span>Recorded matches · {{ $record['year'] }}</span></div>
        <div class="ov-stat"><strong>{{ $record['wins'] }}–{{ $record['played'] - $record['wins'] }}</strong><span>Wins–losses</span></div>
        <div class="ov-stat"><strong>{{ $record['events'] }}</strong><span>Recorded {{ \Illuminate\Support\Str::plural('event', $record['events']) }}</span></div>
        <div class="ov-stat"><strong>{{ $record['played'] ? round(100 * $record['wins'] / $record['played']).'%' : '—' }}</strong><span>Recorded win rate</span></div>
      </div>
      <div class="ov-panels">
        <section class="ov-panel ov-month" aria-labelledby="monthly-report-heading" data-monthly-report>
          <span class="ov-note">Your monthly tennis report · month to date</span>
          <h3 id="monthly-report-heading">{{ $monthLabel }}</h3>
          <p class="ov-note">Saved results through {{ $record['as_of'] ?? 'an unavailable date' }}.@if($record['stale']) Update pending.@endif</p>
          @if(!$report['covered'])
            <p>This month’s report is awaiting saved results coverage. The saved record ends before {{ $monthLabel }}.</p>
          @elseif($report['summary']['played'])
            <p><strong>{{ $report['summary']['played'] }} recorded {{ \Illuminate\Support\Str::plural('match', $report['summary']['played']) }}</strong> · {{ $report['summary']['wins'] }} {{ \Illuminate\Support\Str::plural('win', $report['summary']['wins']) }} · {{ $report['summary']['losses'] }} {{ \Illuminate\Support\Str::plural('loss', $report['summary']['losses']) }}</p>
            <p>{{ $report['summary']['events'] }} {{ \Illuminate\Support\Str::plural('event', $report['summary']['events']) }} · {{ $report['summary']['opponents'] }} different {{ \Illuminate\Support\Str::plural('opponent', $report['summary']['opponents']) }}</p>
          @else
            <p>No matches recorded for {{ $monthLabel }} in this saved record.</p>
          @endif
          @if($report['covered'])<p class="ov-note">Previous calendar month: {{ $report['previous']['played'] }} recorded {{ \Illuminate\Support\Str::plural('match', $report['previous']['played']) }}, {{ $report['previous']['wins'] }} {{ \Illuminate\Support\Str::plural('win', $report['previous']['wins']) }}. Month to date; participation counts do not measure playing strength.</p>@endif
          @if($report['estimated_dates'])<p class="ov-note">Some match dates are estimated from event dates.</p>@endif
          <div class="ov-actions"><a data-my-tennis-link href="{{ route('my.tennis.record', ['player' => $selectedPlayer, 'year' => $record['year'], 'record_month' => $report['month']]) }}">Explore this month →</a></div>
        </section>
        <section class="ov-panel" aria-labelledby="overview-form-heading">
          <h3 id="overview-form-heading">Your recent form</h3>
          @if($record['form'])
            <div class="ov-form" aria-label="Latest five saved results, newest first">
              @foreach($record['form'] as $match)<span @class(['ov-loss' => !$match['won']]) title="{{ $match['date'] }} · {{ $match['opponent'] }}" aria-label="{{ $match['won'] ? 'Win' : 'Loss' }} against {{ $match['opponent'] }} on {{ $match['date'] }}">{{ $match['won'] ? 'W' : 'L' }}</span>@endforeach
            </div>
            <p class="ov-note">Latest saved results in {{ $record['year'] }}, newest first. Order may be approximate.</p>
            @php($latestMatch = $record['form'][0])
            <p><strong>Latest recorded result</strong><br>{{ $latestMatch['won'] ? 'Won' : 'Lost' }} against {{ $latestMatch['opponent'] }} · {{ $latestMatch['score'] }}<br><span class="ov-note">{{ $latestMatch['event'] }} · {{ $latestMatch['date'] }}</span></p>
          @else
            <p>Your recent results will appear here as eligible singles matches are recorded.</p>
          @endif
          <div class="ov-actions"><a data-my-tennis-link href="{{ $recordUrl }}">Explore your season →</a></div>
        </section>
      </div>
      <p class="ov-note mt-3 mb-0">Available saved singles records, not a complete career archive.@if($record['as_of']) Saved through {{ $record['as_of'] }}.@endif @if($record['stale'])<span role="status">An update is pending; recent matches may not be included yet.</span>@endif</p>
    @endif
    <div class="ov-panels">
      <section class="ov-panel"><h3>Your playing strength</h3>
        @if($latestRating)
          <p><strong style="font-size:1.8rem;color:#004177">{{ number_format($latestRating['score'], 1) }} CTR</strong><br>{{ $ratingSnapshot['label'] }} · {{ $latestRating['confidence_label'] }} confidence</p>
          <p class="ov-note">Saved {{ $latestRating['date'] }}. Provisional estimate, not an official ranking. Explore rating evidence and comparison limits before interpreting changes.</p>
          @if($latestRating['connection_status'] === 'disconnected')<p class="ov-note">Within-group estimate only; not calibrated against other groups.</p>@endif
          @if($latestRating['historical_connection'])<p class="ov-note">Low confidence · depends on a historical connection.</p>@endif
          @if($latestRating['evidence_basis'] === 'finish_only')<p class="ov-note">Low confidence · final placings only.</p>@endif
        @else<p>A rating needs enough suitable evidence. Your saved rating will appear when available.</p>@endif
        @if($ratingSnapshot['stale'] ?? false)<p class="ov-note">Rating update pending.</p>@endif
        <div class="ov-actions"><a data-my-tennis-link href="{{ route('my.tennis', ['player' => $selectedPlayer, 'tab' => 'rating']) }}">Understand your rating →</a></div>
      </section>
      <section class="ov-panel"><h3>Make your next match count</h3><p>Revisit previous meetings with opponents, check your published match times, or explore events for your stage.</p><div class="ov-actions"><a data-my-tennis-link href="{{ route('my.tennis.record', ['player' => $selectedPlayer, 'section' => 'opponents']) }}">Explore opponents</a><a data-my-tennis-link href="{{ route('my.tennis.pathway', $selectedPlayer) }}">Find your next event</a><a data-my-tennis-link href="{{ route('my.tennis', ['player' => $selectedPlayer, 'tab' => 'activity']) }}">Match times & entries</a></div></section>
    </div>
  @else
    <div class="ov-panel" data-overview-plus-preview>
      @include('frontend.my-tennis.feature-preview', ['ratingPlayer' => $selectedPlayer, 'previewFeature' => 'record', 'showSample' => true])
      <p class="ov-note mb-0 mt-3">Plus brings your overview, monthly report, rating history and opponent insights together. Available evidence varies by player.</p>
    </div>
  @endif
</section>
