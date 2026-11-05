@extends(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout')
@section('title', $recordSection === 'opponents' ? 'Opponent Insights' : 'Playing Record')
@section('content')
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-record ct-player-workspace">
  <style>
    .ct-record{color:#172e45;min-width:0}.ct-record *{min-width:0}.ct-record a{color:#004177}.ct-record .record-banner{border-left:5px solid #ff9f43;background:#172e45;padding:clamp(1rem,3vw,2rem);border-radius:0 18px 18px 0;color:#fff;margin:1rem 0}.ct-record .record-banner h1{color:#fff;font-size:clamp(1.6rem,4vw,2.3rem);margin:.5rem 0}.ct-record .record-banner p{color:#dce8f4;margin:0}.ct-record .record-eyebrow{color:#ffb367;font-weight:700;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase}.ct-record .record-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin:1.25rem 0}.ct-record .record-nav a{padding:.75rem 1rem;border:1px solid #cbd8e6;border-radius:9px;min-height:44px}.ct-record .record-nav a[aria-current=page]{background:#004177;color:#fff;border-color:#004177}.ct-record .record-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1.25rem 0}.ct-record .record-metrics div{border-bottom:3px solid #ff9f43;background:#edf3fa;padding:1rem;border-radius:10px 10px 0 0}.ct-record .record-metrics strong{display:block;font-size:1.8rem;color:#004177}.ct-record .record-sheet{padding:1.25rem;background:#fff;border:1px solid #dce5ef;border-radius:14px;margin-bottom:1.25rem;overflow-wrap:anywhere}.ct-record .record-sheet h2{font-size:1.15rem;color:#172e45}.ct-record .record-row{display:grid;grid-template-columns:7rem minmax(0,1fr) auto;gap:1rem;padding:1rem 0;border-top:1px solid #e1e9f1;align-items:start}.ct-record .record-row p{margin:0}.ct-record .record-row time,.ct-record .record-note{font-size:.8rem;color:#52677b}.ct-record .record-score{text-align:right;color:#004177;font-weight:600}.ct-record .record-filter{display:flex;align-items:end;flex-wrap:wrap;gap:.75rem}.ct-record .record-filter select{min-width:10rem}.ct-record .record-filter .btn,.ct-record .record-page a{min-height:44px}.ct-record .record-page{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-top:1rem}.ct-record .record-opponent{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:center;padding:1rem 0;border-top:1px solid #e1e9f1}.ct-record .record-opponent a{padding:.6rem 0;display:inline-block}.ct-record .record-season{display:flex;gap:1rem;justify-content:space-between;padding:.7rem 0;border-top:1px solid #e1e9f1}.ct-record a:focus-visible{outline:3px solid #b85b00;outline-offset:3px}@media(max-width:575px){.ct-record .record-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.ct-record .record-row{grid-template-columns:minmax(0,1fr) auto;gap:.5rem}.ct-record .record-row time{grid-column:1/-1}.ct-record .record-sheet{padding:1rem}.ct-record .record-score{max-width:7rem}}
    .ct-record .record-section-heading { display:flex; flex-wrap:wrap; gap:.3rem 1rem; align-items:baseline; justify-content:space-between; }
    .ct-record .record-section-heading h2 { margin:0; }
    .ct-record .record-section-heading a, .ct-record .record-fixture-link { display:inline-flex; align-items:center; min-height:44px; font-size:.85rem; text-underline-offset:3px; }
    .ct-record .record-upcoming-row time strong { white-space:nowrap; }
    .ct-record .record-rating-comparison { margin:.4rem 0 0; }
    .ct-record .record-rating-comparison summary { min-height:44px; padding:.6rem .15rem; color:#004177; cursor:pointer; }
    .ct-record .record-rating-comparison summary span { margin-inline-start:.45rem; color:#52677b; font-size:.8rem; }
    .ct-record .record-rating-comparison-body { background:#f6f8fa; border:1px solid #dce5ef; border-radius:8px; padding:.8rem; }
    .ct-record :is(.record-coverage, .record-rating-comparison) summary:focus-visible { outline:3px solid #b85b00; outline-offset:2px; }
    .ct-record .record-coverage { margin:0 0 1rem; }
    .ct-record .record-coverage summary { cursor:pointer; padding:.6rem 0; width:fit-content; min-height:44px; color:#004177; }
    .ct-record #past-opponents, .ct-record #opponent-meetings { scroll-margin-top:6rem; }
    @media(max-width:575px) {
      .ct-record .record-upcoming-row { grid-template-columns:minmax(0,1fr); gap:.25rem; }
      .ct-record .record-upcoming-row .record-fixture-link { justify-self:start; }
    }
  </style>
  @include('frontend.my-tennis.profile-header', ['workspaceTab' => $recordSection, 'ratingPlayer' => $recordPlayer])
  <h2 class="h5">{{ $recordSection === 'opponents' ? 'Opponent Insights' : 'Playing Record' }}</h2>
  @if($recordSection === 'opponents' && isset($upcoming))
    @include('frontend.my-tennis.record-upcoming')
  @endif
  @if($record['reason'])
    <div class="record-sheet" role="status">{{ $record['reason'] }}</div>
  @else
    @if($recordSection === 'opponents')
      <h2 id="past-opponents" class="h5">Past opponents</h2>
    @endif
    <form method="get" class="record-filter mb-3" action="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => $recordSection]) }}{{ $recordSection === 'opponents' ? '#past-opponents' : '' }}">
      @if($record['opponent'] || request()->integer('opponent'))<input type="hidden" name="opponent" value="{{ $record['opponent']['id'] ?? request()->integer('opponent') }}">@endif
      @foreach(['first', 'second'] as $comparisonSide)@if($record['comparison'][$comparisonSide.'_year'] !== '')<input type="hidden" name="compare_{{ $comparisonSide }}" value="{{ $record['comparison'][$comparisonSide.'_year'] }}">@endif @endforeach
      <div><label for="record-year" class="form-label">Season</label><select id="record-year" name="year" class="form-select"><option value="">All seasons</option>@foreach($record['years'] as $year)<option value="{{ $year }}" @selected((string)$year === $record['year'])>{{ $year }}</option>@endforeach</select></div>
      <button class="btn btn-outline-primary" type="submit">Apply</button>
    </form>
    @if($record['stale'])<p class="record-note" role="status">An update is pending. These are the last saved results.</p>@endif
    @if(!$record['played'])<div class="record-sheet" role="status">No saved match results for this season.</div>@endif
    @if($recordSection === 'record')
      <div class="record-metrics" aria-label="Selected season totals">
        <div><strong>{{ $record['played'] }}</strong>Matches</div><div><strong>{{ $record['played'] ? round(100 * $record['wins'] / $record['played']) . '%' : '—' }}</strong>Win rate · {{ $record['wins'] }}W / {{ $record['played'] - $record['wins'] }}L</div><div><strong>{{ $record['events'] }}</strong>Events</div><div><strong>{{ $record['opponent_count'] }}</strong>Opponents</div>
      </div>
      @include('frontend.my-tennis.record-comparison')
      @if($record['form'])<section class="record-sheet"><h2>Latest five results</h2><div class="d-flex flex-wrap gap-2">@foreach($record['form'] as $formMatch)<span class="badge p-2" style="background:{{ $formMatch['won'] ? '#004177' : '#172e45' }};color:#fff" aria-label="{{ $formMatch['won'] ? 'Win' : 'Loss' }} against {{ $formMatch['opponent'] }} on {{ $formMatch['date'] }}">{{ $formMatch['won'] ? 'W' : 'L' }}</span>@endforeach</div><p class="record-note mt-2 mb-0">Ordered by recorded date within the selected season. Estimated dates and same-date results do not establish exact playing order.</p></section>@endif
      @if($record['first'])
      <section class="record-sheet"><h2>First recorded matches</h2><p><strong>Earliest recorded match:</strong> {{ $record['first']['date'] }} · {{ $record['first']['opponent'] }} · {{ $record['first']['event'] }}</p>
      <p class="record-note">{{ $record['first']['date_basis'] }}. This covers all seasons with saved results.</p>
      @if($record['first_win'])<p class="mb-0"><strong>Earliest recorded win:</strong> {{ $record['first_win']['date'] }} · {{ $record['first_win']['opponent'] }} <span class="record-note">({{ $record['first_win']['date_basis'] }})</span></p>@endif</section>
      <section class="record-sheet"><h2>Results by season</h2><p class="record-note">Choose a year to see its matches. Blue shows wins and orange shows losses.</p>@foreach($record['seasons'] as $season)<div class="record-season"><a href="{{ request()->fullUrlWithQuery(['year' => $season['year'], 'page' => null]) }}">{{ $season['year'] }}</a><div style="flex:1;max-width:30rem"><span>{{ $season['played'] }} matches · {{ $season['wins'] }} wins · {{ $season['played'] - $season['wins'] }} losses</span><div aria-hidden="true" style="display:flex;height:8px;border-radius:5px;overflow:hidden;background:#ff9f43;margin-top:.5rem"><span style="background:#004177;width:{{ 100 * $season['wins'] / $season['played'] }}%"></span></div></div></div>@endforeach</section>
      @endif
      <section class="record-sheet"><h2>Match results</h2>@include('frontend.my-tennis.record-matches', ['matchPage' => $record['results'], 'pageKey' => 'page'])</section>
    @else
      @if($record['opponent'])
      <section class="record-sheet" id="opponent-meetings" tabindex="-1" style="border-top:3px solid #ff9f43" aria-labelledby="matchup-heading">
        <span class="record-note">Selected matchup{{ $record['year'] !== '' ? ' · '.$record['year'] : ' · all available seasons' }}</span>
        <h2 id="matchup-heading" class="mt-2">{{ $recordPlayer->full_name }} vs {{ $record['opponent']['name'] }}</h2>
        <p><strong>{{ $record['opponent']['wins'] }} wins · {{ $record['opponent']['played'] - $record['opponent']['wins'] }} losses</strong> across {{ $record['opponent']['played'] }} recorded meetings.</p>
        <p class="record-note">These are your saved results against this player.</p>
        @include('frontend.my-tennis.record-opponent-trend')
        <h3 class="h6 mt-3">Match history</h3>
        <p class="record-note">Newest dates first. The order of matches on the same day may be unknown.</p>
        @include('frontend.my-tennis.record-matches', ['matchPage' => $record['meetings'], 'pageKey' => 'meeting_page'])
      </section>
      @elseif(request()->filled('opponent'))<p id="opponent-meetings" role="status">No saved meetings with this opponent for the selected season.</p>@endif
      @if($record['opponents']['rows'])
      <section class="record-sheet"><h2>Who you’ve played</h2><p class="record-note">Players you’ve met most often appear first.</p>
        @foreach($record['opponents']['rows'] as $opponent)<a class="record-opponent text-decoration-none" href="{{ request()->fullUrlWithQuery(['opponent' => $opponent['id'], 'meeting_page' => null]) }}#opponent-meetings" aria-label="View matchup: {{ $recordPlayer->full_name }} vs {{ $opponent['name'] }}" @if(($record['opponent']['id'] ?? null) === $opponent['id']) aria-current="true" @endif><div><strong>{{ $opponent['name'] }}</strong><p class="record-note mb-0">{{ $opponent['played'] }} meetings · latest {{ $opponent['last'] }}</p></div><span>{{ $opponent['wins'] }} wins<br>{{ $opponent['played'] - $opponent['wins'] }} losses<br><small>View matchup →</small></span></a>@endforeach
        @include('frontend.my-tennis.record-pagination', ['matchPage' => $record['opponents'], 'pageKey' => 'opponent_page'])
      </section>
      @endif
    @endif
    <details class="record-coverage">
      <summary>About these results</summary>
      <p class="record-note mb-0">Saved results{{ $record['coverage_start'] ? ' from '.$record['coverage_start'] : '' }} through {{ $record['as_of'] }}. Some matches may be missing. Final placings are separate from played matches. Estimated dates are marked.@unless(auth()->user()?->hasRole('super-user')) Connected TeamSheet match details have limited access.@endunless</p>
    </details>
  @endif
</div>
@endsection

@include('frontend.my-tennis.workspace-assets')
