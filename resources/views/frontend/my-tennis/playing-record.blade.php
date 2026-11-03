@extends('layouts/contentNavbarLayout')
@section('title', $recordSection === 'opponents' ? 'Opponent Insights' : 'Playing Record')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-record">
  <style>
    .ct-record{color:#172e45;min-width:0}.ct-record *{min-width:0}.ct-record a{color:#004177}.ct-record .record-banner{border-left:5px solid #ff9f43;background:#172e45;padding:clamp(1rem,3vw,2rem);border-radius:0 18px 18px 0;color:#fff;margin:1rem 0}.ct-record .record-banner h1{color:#fff;font-size:clamp(1.6rem,4vw,2.3rem);margin:.5rem 0}.ct-record .record-banner p{color:#dce8f4;margin:0}.ct-record .record-eyebrow{color:#ffb367;font-weight:700;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase}.ct-record .record-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin:1.25rem 0}.ct-record .record-nav a{padding:.75rem 1rem;border:1px solid #cbd8e6;border-radius:9px;min-height:44px}.ct-record .record-nav a[aria-current=page]{background:#004177;color:#fff;border-color:#004177}.ct-record .record-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1.25rem 0}.ct-record .record-metrics div{border-bottom:3px solid #ff9f43;background:#edf3fa;padding:1rem;border-radius:10px 10px 0 0}.ct-record .record-metrics strong{display:block;font-size:1.8rem;color:#004177}.ct-record .record-sheet{padding:1.25rem;background:#fff;border:1px solid #dce5ef;border-radius:14px;margin-bottom:1.25rem;overflow-wrap:anywhere}.ct-record .record-sheet h2{font-size:1.15rem;color:#172e45}.ct-record .record-row{display:grid;grid-template-columns:7rem minmax(0,1fr) auto;gap:1rem;padding:1rem 0;border-top:1px solid #e1e9f1;align-items:start}.ct-record .record-row p{margin:0}.ct-record .record-row time,.ct-record .record-note{font-size:.8rem;color:#52677b}.ct-record .record-score{text-align:right;color:#004177;font-weight:600}.ct-record .record-filter{display:flex;align-items:end;flex-wrap:wrap;gap:.75rem}.ct-record .record-filter select{min-width:10rem}.ct-record .record-filter .btn,.ct-record .record-page a{min-height:44px}.ct-record .record-page{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-top:1rem}.ct-record .record-opponent{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:center;padding:1rem 0;border-top:1px solid #e1e9f1}.ct-record .record-opponent a{padding:.6rem 0;display:inline-block}.ct-record .record-season{display:flex;gap:1rem;justify-content:space-between;padding:.7rem 0;border-top:1px solid #e1e9f1}.ct-record a:focus-visible{outline:3px solid #b85b00;outline-offset:3px}@media(max-width:575px){.ct-record .record-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.ct-record .record-row{grid-template-columns:minmax(0,1fr) auto;gap:.5rem}.ct-record .record-row time{grid-column:1/-1}.ct-record .record-sheet{padding:1rem}.ct-record .record-score{max-width:7rem}}
  </style>
  <a href="{{ route('my.tennis', ['player' => $recordPlayer->id]) }}">← My Tennis</a>
  <header class="record-banner">
    <span class="record-eyebrow">Cape Tennis · Player Plus</span>
    <h1>{{ $recordSection === 'opponents' ? 'Opponent Insights' : 'Playing Record' }}</h1>
    <p>{{ $recordPlayer->full_name }} · {{ $recordSection === 'opponents' ? 'The results behind your repeat encounters.' : 'Your recorded tennis, organised by season.' }}</p>
  </header>
  @include('frontend.my-tennis.player-navigation', ['navigationPlayer' => $recordPlayer])
  @if($record['reason'])
    <div class="record-sheet" role="status">{{ $record['reason'] }}</div>
  @else
    <p class="record-note">Available results{{ $record['coverage_start'] ? ' from '.$record['coverage_start'] : '' }} through {{ $record['as_of'] }}. This is not a complete career archive. Finishing lists do not count as played matches. Estimated dates are identified with each result.</p>
    @if($record['stale'])<p class="record-note" role="status">Showing the latest saved results while an update is pending.</p>@endif
    <form method="get" class="record-filter mb-3" action="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => $recordSection]) }}">
      @if($record['opponent'] || request()->integer('opponent'))<input type="hidden" name="opponent" value="{{ $record['opponent']['id'] ?? request()->integer('opponent') }}">@endif
      @foreach(['first', 'second'] as $comparisonSide)@if($record['comparison'][$comparisonSide.'_year'] !== '')<input type="hidden" name="compare_{{ $comparisonSide }}" value="{{ $record['comparison'][$comparisonSide.'_year'] }}">@endif @endforeach
      <div><label for="record-year" class="form-label">Season</label><select id="record-year" name="year" class="form-select"><option value="">All available seasons</option>@foreach($record['years'] as $year)<option value="{{ $year }}" @selected((string)$year === $record['year'])>{{ $year }}</option>@endforeach</select></div>
      <button class="btn btn-primary" type="submit">Show results</button>
    </form>
    <div class="record-metrics" aria-label="Selected season totals">
      <div><strong>{{ $record['played'] }}</strong>Matches</div><div><strong>{{ $record['played'] ? round(100 * $record['wins'] / $record['played']) . '%' : '—' }}</strong>Win rate · {{ $record['wins'] }}W / {{ $record['played'] - $record['wins'] }}L</div><div><strong>{{ $record['events'] }}</strong>Events</div><div><strong>{{ $record['opponent_count'] }}</strong>Opponents</div>
    </div>
    @if(!$record['played'])<div class="record-sheet" role="status">No eligible saved match results are available for this selection. Other results or event documents may exist outside this saved evidence.</div>@endif
    @if($recordSection === 'record')
      @include('frontend.my-tennis.record-comparison')
      @if($record['form'])<section class="record-sheet"><h2>Latest five results</h2><div class="d-flex flex-wrap gap-2">@foreach($record['form'] as $formMatch)<span class="badge p-2" style="background:{{ $formMatch['won'] ? '#004177' : '#172e45' }};color:#fff" aria-label="{{ $formMatch['won'] ? 'Win' : 'Loss' }} against {{ $formMatch['opponent'] }} on {{ $formMatch['date'] }}">{{ $formMatch['won'] ? 'W' : 'L' }}</span>@endforeach</div><p class="record-note mt-2 mb-0">Ordered by recorded date within the selected season. Estimated dates and same-date results do not establish exact playing order.</p></section>@endif
      @if($record['first'])
      <section class="record-sheet"><h2>Early markers in the available record</h2><p><strong>Earliest recorded match:</strong> {{ $record['first']['date'] }} · {{ $record['first']['opponent'] }} · {{ $record['first']['event'] }}</p>
      <p class="record-note">{{ $record['first']['date_basis'] }}. These markers cover all available seasons.</p>
      @if($record['first_win'])<p class="mb-0"><strong>Earliest recorded win:</strong> {{ $record['first_win']['date'] }} · {{ $record['first_win']['opponent'] }} <span class="record-note">({{ $record['first_win']['date_basis'] }})</span></p>@endif</section>
      <section class="record-sheet"><h2>Season ledger</h2><p class="record-note">Blue: wins · Orange: losses. Select a season to open its match register.</p>@foreach($record['seasons'] as $season)<div class="record-season"><a href="{{ request()->fullUrlWithQuery(['year' => $season['year'], 'page' => null]) }}">{{ $season['year'] }}</a><div style="flex:1;max-width:30rem"><span>{{ $season['played'] }} matches · {{ $season['wins'] }} wins · {{ $season['played'] - $season['wins'] }} losses</span><div aria-hidden="true" style="display:flex;height:8px;border-radius:5px;overflow:hidden;background:#ff9f43;margin-top:.5rem"><span style="background:#004177;width:{{ 100 * $season['wins'] / $season['played'] }}%"></span></div></div></div>@endforeach</section>
      @endif
      <section class="record-sheet"><h2>Match register</h2>@include('frontend.my-tennis.record-matches', ['matchPage' => $record['results'], 'pageKey' => 'page'])</section>
    @else
      <section class="record-sheet"><h2>Your matchups</h2><p class="record-note">Ordered by meetings in the selected season, then most recent encounter. Counts describe recorded results, not a prediction.</p>
        @foreach($record['opponents']['rows'] as $opponent)<div class="record-opponent"><div><a href="{{ request()->fullUrlWithQuery(['opponent' => $opponent['id'], 'meeting_page' => null]) }}">{{ $opponent['name'] }}</a><p class="record-note mb-0">{{ $opponent['played'] }} meetings · latest {{ $opponent['last'] }}</p></div><span>{{ $opponent['wins'] }} wins<br>{{ $opponent['played'] - $opponent['wins'] }} losses</span></div>@endforeach
        @include('frontend.my-tennis.record-pagination', ['matchPage' => $record['opponents'], 'pageKey' => 'opponent_page'])
      </section>
      @if($record['opponent'])
      @include('frontend.my-tennis.record-opponent-trend')
      <section class="record-sheet" id="opponent-meetings"><h2>Meetings with {{ $record['opponent']['name'] }}</h2><p>{{ $record['opponent']['wins'] }} wins · {{ $record['opponent']['played'] - $record['opponent']['wins'] }} losses in this selection.</p>@include('frontend.my-tennis.record-matches', ['matchPage' => $record['meetings'], 'pageKey' => 'meeting_page'])</section>
      @elseif(request()->filled('opponent'))<p role="status">That opponent has no eligible meetings in this selection.</p>@endif
    @endif
  @endif
</div>
@endsection
