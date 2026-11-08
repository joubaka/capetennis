@if($ratingTopTen['visible'] ?? false)
@php
  $canBrowse = $ratingTopTen['can_browse'] ?? false;
  $ageLabel = $ratingTopTen['age_options'][$ratingTopTen['selected_age']] ?? 'Age group unavailable';
  $genderLabel = \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$ratingTopTen['filters']['rating_gender']] ?? 'Gender unavailable';
  $positionLabels = $canBrowse
      ? ['age' => 'Overall CTR · '.$ageLabel.' · all genders', 'overall' => 'Overall CTR · all ages and genders']
      : ['age' => 'Overall CTR · '.$ageLabel.' · '.$genderLabel];
  $hasRatingPosition = !empty($ratingTopTen['summary']['age']['player']) || ($canBrowse && !empty($ratingTopTen['summary']['overall']['player']));
@endphp
<section class="mt-4" aria-labelledby="player-top-ten-heading" data-player-rating-top-ten>
  <h3 class="h5" id="player-top-ten-heading"><x-ctr-mark class="me-2" />Your rating positions</h3>
  <style>
    .ctr-top-lists { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem; }
    .ctr-top-list { min-width:0;border:1px solid #dbe4ef;border-radius:14px;padding:1rem;background:#fff; }
    .ctr-position-card { background:#f1f5fa; }
    .ctr-position-card h4 { color:#172e45;margin-bottom:.5rem; }
    .ctr-position-value { font-size:1.5rem;line-height:1.2;color:#004177;font-variant-numeric:tabular-nums; }
    .ctr-member-leaderboard { padding:clamp(.75rem,2vw,1.25rem); }
    .ctr-member-leaderboard li[aria-current="true"] { background:#eef4fb;box-shadow:inset 3px 0 #004177; }
    @media(max-width:575.98px) { .ctr-top-lists { grid-template-columns:1fr; } }
  </style>
  @if($hasRatingPosition)
  <div class="{{ $canBrowse ? 'ctr-top-lists ' : '' }}mb-3">
    @foreach($positionLabels as $scope => $label)
      @php
        $summary = $ratingTopTen['summary'][$scope] ?? null;
      @endphp
      <section class="ctr-top-list ctr-position-card">
        <h4 class="h6">{{ $label }}</h4>
        @if($summary && $summary['player'])
          <div class="d-flex align-items-center flex-wrap gap-2">
            <p class="mb-0"><span class="visually-hidden">Rating position </span><strong class="ctr-position-value">#{{ $summary['player']['position'] }}</strong> <span class="small text-muted">of {{ $summary['total'] }}</span></p>
            <x-ctr-badge size="inline" :score="$summary['player']['score']" :overall="true" />
          </div>
        @else
          <p class="small mb-0">Position unavailable.</p>
        @endif
      </section>
    @endforeach
  </div>
  @else
    <p class="small text-muted mb-3">No comparable position yet.</p>
  @endif
  @if($ratingTopTen['as_of'])<p class="small text-muted">Saved through {{ $ratingTopTen['as_of'] }}.@if($ratingTopTen['stale']) Latest saved estimates; an update is pending.@endif</p>@endif
  @if(!$canBrowse)
    <section class="ctr-top-list ctr-member-leaderboard" aria-labelledby="ctr-age-top-ten-heading">
    <h4 class="h6" id="ctr-age-top-ten-heading">Your age-group Top 10</h4>
    <p class="small text-muted">{{ $positionLabels['age'] }}</p>
    @if($ratingTopTen['selected_age'] === 'unknown')
      <p class="small text-muted">A valid birth date is needed to show your player's age-group Top 10.</p>
    @else
      <ol class="list-group list-group-flush mb-0" aria-label="Your age-group Top 10 ratings" data-plus-age-top-ten>
        @forelse($ratingTopTen['lists']['overall']['players'] ?? [] as $member)
          <li class="list-group-item d-flex align-items-start gap-2" @if($member['id'] === (int) $ratingPlayer->id) aria-current="true" @endif>
            <span>{{ $member['position'] }}.</span><span class="flex-grow-1 text-break">{{ $member['name'] }}@if($member['id'] === (int) $ratingPlayer->id)<small class="d-block text-muted">Your selected player</small>@endif</span><span class="d-inline-flex flex-column align-items-center" style="width:4rem;flex-shrink:0"><x-ctr-badge size="inline" :score="$member['score']" :overall="true" /></span>
          </li>
      @empty
        <li class="list-group-item text-muted"><span role="status">{{ $ratingTopTen['reason'] ? 'Rating comparisons unavailable.' : 'No ratings for this age group.' }}</span></li>
      @endforelse
      </ol>
    @endif
    </section>
  @else
  <h4 class="h6">Explore ratings · Full list</h4>
  <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="ctr-filters mb-3">
    @foreach(['month', 'scope', 'scale'] as $historyFilter)<input type="hidden" name="{{ $historyFilter }}" value="{{ $ratingHistory[$historyFilter] ?? '' }}">@endforeach
    <div data-rating-age-filter @if($ratingTopTen['filters']['rating_level'] !== 'overall') hidden @endif><label for="ctr-rating-age">Overall age group</label><select id="ctr-rating-age" name="rating_age" class="form-select"><option value="">All ages</option>@foreach($ratingTopTen['age_options'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_age'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-gender">Gender</label><select id="ctr-rating-gender" name="rating_gender" class="form-select"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_gender'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-level">Rating level</label><select id="ctr-rating-level" name="rating_level" class="form-select">@foreach(['red' => 'Red', 'orange' => 'Orange', 'overall' => 'Overall CTR'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_level'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-per-page">Players per page</label><select id="ctr-rating-per-page" name="rating_per_page" class="form-select">@foreach(\App\Services\Performance\PlayerRatingTopTen::PAGE_SIZES as $size)<option value="{{ $size }}" @selected(($ratingTopTen['filters']['rating_per_page'] ?? \App\Services\Performance\PlayerRatingTopTen::PER_PAGE) === $size)>{{ $size }}</option>@endforeach</select></div>
    @if($ratingFragment ?? true)<noscript>@endif
    <button type="submit" class="btn btn-primary">Show ratings</button>
    @if($ratingFragment ?? true)</noscript>@endif
  </form>
  <style>
    .ctr-full-list li { padding:.7rem 0;border-top:1px solid #edf1ef;overflow-wrap:anywhere; }
    .ctr-full-list .ctr-player-row { display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.5rem;align-items:center; }
    .ctr-full-list .ctr-top-score { font-variant-numeric:tabular-nums;color:#004177; }
    .ctr-full-list small { display:block;color:#62766e; }
  </style>
  <div class="ctr-full-list">
    @foreach($ratingTopTen['lists'] as $key => $list)
      <section class="ctr-top-list" aria-label="{{ $list['label'] }} ratings" data-top-ten-cohort="{{ $key }}">
        <h4 class="h6" tabindex="-1" data-rating-list-focus>Rating positions</h4>
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Displayed rating group">
          <span class="badge rounded-pill bg-success">Rating: {{ $list['label'] }}</span>
          @if($key === 'overall')<span class="badge rounded-pill bg-light text-dark border text-wrap text-start">Age group: {{ $ratingTopTen['age_options'][$ratingTopTen['filters']['rating_age']] ?? 'All ages' }}</span>@endif
          <span class="badge rounded-pill bg-light text-dark border">{{ \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$ratingTopTen['filters']['rating_gender']] ?? 'All genders' }}</span>
        </div>
        <ol class="list-unstyled mb-0">
          @forelse($list['players'] as $member)
            <li class="ctr-player-row" value="{{ $member['position'] }}" @if($member['id'] === (int) $ratingPlayer->id) aria-current="true" @endif><span aria-hidden="true">{{ $member['position'] }}</span><span>{{ $member['name'] }}@if($member['id'] === (int) $ratingPlayer->id)<small>Your selected player</small>@endif<small>{{ $member['confidence'] }} confidence</small></span><span class="ctr-top-score d-inline-flex flex-column align-items-center" style="width:4rem"><x-ctr-badge size="inline" :score="$member['score']" :overall="$key === 'overall'" :stage="$key === 'overall' ? null : $key" /></span></li>
          @empty
            <li class="small text-muted"><span role="status">{{ $ratingTopTen['reason'] ? 'Rating comparisons unavailable.' : 'No ratings for these filters.' }}</span></li>
          @endforelse
        </ol>
        @if(isset($list['pagination']) && $list['pagination']['total'] > 0)
          @php
            $pagination = $list['pagination'];
            $pageQuery = array_intersect_key($ratingHistory, array_flip(['month', 'scope', 'scale']));
            if (isset($ratingHistory['evidence']['page'])) $pageQuery['page'] = $ratingHistory['evidence']['page'];
            $pageQuery += $ratingTopTen['filters'];
            $pageUrl = fn ($page) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + $pageQuery + ['rating_page' => $page]);
          @endphp
          <p class="small text-muted mt-3">Showing {{ ($pagination['page'] - 1) * $pagination['per_page'] + 1 }}–{{ min($pagination['page'] * $pagination['per_page'], $pagination['total']) }} of {{ $pagination['total'] }} players</p>
          @if($pagination['pages'] > 1)
            <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2" aria-label="Rating list pages">
              @if($pagination['page'] > 1)<a id="ctr-rating-previous" class="btn btn-outline-primary" href="{{ $pageUrl($pagination['page'] - 1) }}" data-rating-link>Previous players</a>@endif
              <span class="small text-muted">Page {{ $pagination['page'] }} of {{ $pagination['pages'] }}</span>
              @if($pagination['page'] < $pagination['pages'])<a id="ctr-rating-next" class="btn btn-outline-primary" href="{{ $pageUrl($pagination['page'] + 1) }}" data-rating-link>Next players</a>@endif
            </nav>
          @endif
        @endif
      </section>
    @endforeach
  </div>
  @endif
  <details class="small mt-3">
    <summary>Who’s included?</summary>
    <p class="mt-2 mb-1">Positions use comparable ratings from published series standings. They are separate from official rankings. Junior age groups include younger players, based on the age they reach this calendar year.</p>
    @if($ratingTopTen['can_browse'] ?? false)
      <p class="mb-0">Red and Orange have separate ratings and include all ages. Green/Yellow uses Overall CTR. Filters change positions without changing rating scores. Players with equal displayed scores share a position.</p>
    @endif
  </details>
</section>
@endif
