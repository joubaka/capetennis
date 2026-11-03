@if($ratingTopTen['visible'] ?? false)
<section class="mt-4" aria-labelledby="player-top-ten-heading" data-player-rating-top-ten>
  <h3 class="h5" id="player-top-ten-heading">Your rating positions</h3>
  <p class="small text-muted">Your player's rating positions among players in currently published series standings. These estimates are not official rankings. Junior age groups include the selected group and younger, using age reached this calendar year. Both summaries include all genders.</p>
  <style>.ctr-top-lists{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ctr-top-list{min-width:0;border:1px solid #dce7e2;border-radius:14px;padding:1rem;background:#fff}@media(max-width:767.98px){.ctr-top-lists{grid-template-columns:1fr}}</style>
  <div class="ctr-top-lists mb-3">
    @foreach(['age' => 'Overall CTR · '.($ratingTopTen['age_options'][$ratingTopTen['selected_age']] ?? 'Age group').' · all genders', 'overall' => 'Overall CTR · all ages and genders'] as $scope => $label)
      @php
        $summary = $ratingTopTen['summary'][$scope] ?? null;
      @endphp
      <section class="ctr-top-list"><h4 class="h6">{{ $label }}</h4>
        @if($summary && $summary['player'])<strong>{{ number_format($summary['player']['score'], 1) }} CTR</strong><p class="mb-0">Rating position #{{ $summary['player']['position'] }} of {{ $summary['total'] }}</p>
        @else<p class="mb-0">No comparable published-player rating position available.</p>@endif
      </section>
    @endforeach
  </div>
  @if($ratingTopTen['as_of'])<p class="small text-muted">Saved through {{ $ratingTopTen['as_of'] }}.@if($ratingTopTen['stale']) Latest saved estimates; an update is pending.@endif</p>@endif
  @if($ratingTopTen['reason'])<p role="status">{{ $ratingTopTen['reason'] }}</p>@endif
  @if(!($ratingTopTen['can_browse'] ?? false))
    <h4 class="h6">Your age-group Top 10</h4>
    <p class="small text-muted">Overall CTR · {{ $ratingTopTen['age_options'][$ratingTopTen['selected_age']] ?? 'Age group unavailable' }} · {{ \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$ratingTopTen['filters']['rating_gender']] ?? 'Gender unavailable' }}. Only comparable ratings from published standings are included.</p>
    @if($ratingTopTen['selected_age'] === 'unknown')
      <p class="small text-muted">A valid birth date is needed to show your player's age-group Top 10.</p>
    @else
      <ol class="list-group list-group-flush mb-4" aria-label="Your age-group Top 10 ratings" data-plus-age-top-ten>
        @forelse($ratingTopTen['lists']['overall']['players'] ?? [] as $member)
          <li class="list-group-item d-flex align-items-start gap-2" @if($member['id'] === (int) $ratingPlayer->id) aria-current="true" @endif>
            <span>{{ $member['position'] }}.</span><span class="flex-grow-1 text-break">{{ $member['name'] }}@if($member['id'] === (int) $ratingPlayer->id)<small class="d-block text-muted">Your selected player</small>@endif</span><strong class="text-nowrap">{{ number_format($member['score'], 1) }} CTR</strong>
          </li>
        @empty<li class="list-group-item text-muted">No comparable published-player ratings available for this age group.</li>@endforelse
      </ol>
    @endif
    <h4 class="h6">Check a player's rating</h4>
    <p class="small text-muted">Search by name, then choose one player to view their Overall CTR. Up to 10 matching names appear alphabetically.</p>
    <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="d-flex flex-wrap gap-2 mb-3">
      @foreach(['month', 'scope', 'scale'] as $historyFilter)<input type="hidden" name="{{ $historyFilter }}" value="{{ $ratingHistory[$historyFilter] ?? '' }}">@endforeach
      <label class="visually-hidden" for="ctr-player-search">Player name</label>
      <input class="form-control" style="max-width:24rem" type="search" id="ctr-player-search" name="rating_search" minlength="2" maxlength="100" value="{{ $ratingTopTen['search']['query'] }}" placeholder="Enter at least 2 characters">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    @if(mb_strlen($ratingTopTen['search']['query']) >= 2)
      <ul class="list-unstyled" aria-label="Player rating search results">
        @forelse($ratingTopTen['search']['players'] as $match)
          @php
            $searchQuery = array_intersect_key($ratingHistory, array_flip(['month', 'scope', 'scale'])) + ['rating_search' => $ratingTopTen['search']['query'], 'rating_search_player' => $match['id']];
          @endphp
          <li class="border-top py-2 d-flex flex-wrap align-items-center gap-2"><strong class="text-break">{{ $match['name'] }}</strong><a class="btn btn-sm btn-outline-primary" style="min-height:44px" href="{{ route('my.tennis.rating', ['player' => $ratingPlayer->id] + $searchQuery) }}" data-rating-link>View rating<span class="visually-hidden"> for {{ $match['name'] }}</span></a></li>
        @empty<li class="small text-muted">No comparable published-player rating found. Try a different name.</li>@endforelse
      </ul>
    @endif
    @if($match = $ratingTopTen['search']['selected'] ?? null)
      <p class="alert alert-info" role="status" data-rating-search-result><strong>{{ $match['name'] }}</strong> · {{ number_format($match['score'], 1) }} Overall CTR <span class="small">· {{ $match['confidence'] }} confidence</span></p>
    @endif
  @else
  <h4 class="h6">Explore ratings · Full list</h4>
  <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="ctr-filters mb-3">
    @foreach(['month', 'scope', 'scale'] as $historyFilter)<input type="hidden" name="{{ $historyFilter }}" value="{{ $ratingHistory[$historyFilter] ?? '' }}">@endforeach
    <div data-rating-age-filter @if($ratingTopTen['filters']['rating_level'] !== 'overall') hidden @endif><label for="ctr-rating-age">Overall age group</label><select id="ctr-rating-age" name="rating_age" class="form-select"><option value="">All ages</option>@foreach($ratingTopTen['age_options'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_age'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-gender">Gender</label><select id="ctr-rating-gender" name="rating_gender" class="form-select"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_gender'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-level">Rating level</label><select id="ctr-rating-level" name="rating_level" class="form-select">@foreach(['red' => 'Red', 'orange' => 'Orange', 'overall' => 'Overall CTR'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_level'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-per-page">Players per page</label><select id="ctr-rating-per-page" name="rating_per_page" class="form-select">@foreach(\App\Services\Performance\PlayerRatingTopTen::PAGE_SIZES as $size)<option value="{{ $size }}" @selected(($ratingTopTen['filters']['rating_per_page'] ?? \App\Services\Performance\PlayerRatingTopTen::PER_PAGE) === $size)>{{ $size }}</option>@endforeach</select></div>
    <noscript><button type="submit" class="btn btn-primary">Show ratings</button></noscript>
  </form>
  <p class="small text-muted">Red, Orange and Overall are separate ratings. Green/Yellow uses Overall CTR. Red and Orange include all ages. Overall CTR junior age groups include younger players. Age and gender filters change ranking positions, not rating scores.</p>
  <style>.ctr-top-lists{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ctr-top-list{min-width:0;border:1px solid #dce7e2;border-radius:14px;padding:1rem;background:#fff}.ctr-top-list li{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.5rem;align-items:baseline;padding:.7rem 0;border-top:1px solid #edf1ef;overflow-wrap:anywhere}.ctr-top-list .ctr-top-score{font-variant-numeric:tabular-nums;color:#004177}.ctr-top-list small{display:block;color:#62766e}@media(max-width:767.98px){.ctr-top-lists{grid-template-columns:1fr}}</style>
  <div class="ctr-top-lists">
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
            <li value="{{ $member['position'] }}" @if($member['id'] === (int) $ratingPlayer->id) aria-current="true" @endif><span aria-hidden="true">{{ $member['position'] }}</span><span>{{ $member['name'] }}@if($member['id'] === (int) $ratingPlayer->id)<small>Your selected player</small>@endif<small>{{ $member['confidence'] }} confidence</small></span><strong class="ctr-top-score">{{ number_format($member['score'], 1) }}</strong></li>
          @empty<li class="small text-muted">No comparable published-player ratings available.</li>@endforelse
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
  <p class="small text-muted mt-2 mb-0">Browse all matching published-player ratings, {{ $ratingTopTen['filters']['rating_per_page'] ?? \App\Services\Performance\PlayerRatingTopTen::PER_PAGE }} players per page. Equal displayed scores share a position and are ordered by name. Within-group-only and nonconverged estimates are excluded.</p>
  @endif
</section>
@endif
