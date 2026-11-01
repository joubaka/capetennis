@if($ratingTopTen['visible'] ?? false)
<section class="mt-4" aria-labelledby="player-top-ten-heading" data-player-rating-top-ten>
  <h3 class="h5" id="player-top-ten-heading">Your rating positions</h3>
  <p class="small text-muted">Rating positions among players in currently published series standings. These estimates are not official rankings. Age groups use age reached this calendar year; the default includes all genders.</p>
  <div class="ctr-top-lists mb-3">
    @foreach(['age' => 'Green/Yellow ball CTR · '.($ratingTopTen['age_options'][$ratingTopTen['selected_age']] ?? 'Age group').' · all genders', 'overall' => 'Overall system rating · all ages and genders'] as $scope => $label)
      @php($summary = $ratingTopTen['summary'][$scope] ?? null)
      <section class="ctr-top-list"><h4 class="h6">{{ $label }}</h4>
        @if($summary && $summary['player'])<strong>{{ number_format($summary['player']['score'], 1) }} CTR</strong><p class="mb-0">Rating position #{{ $summary['player']['position'] }} of {{ $summary['total'] }}</p>
        @else<p class="mb-0">No comparable published-player rating position available.</p>@endif
      </section>
    @endforeach
  </div>
  <h4 class="h6">Explore ratings · Top 10</h4>
  <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="ctr-filters mb-3">
    @foreach(['month', 'scope', 'scale'] as $historyFilter)<input type="hidden" name="{{ $historyFilter }}" value="{{ $ratingHistory[$historyFilter] ?? '' }}">@endforeach
    <div @if($ratingTopTen['filters']['rating_level'] !== 'standard') hidden @endif><label for="ctr-rating-age">Green/Yellow age group</label><select id="ctr-rating-age" name="rating_age" class="form-select"><option value="">All ages</option>@foreach($ratingTopTen['age_options'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_age'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-gender">Gender</label><select id="ctr-rating-gender" name="rating_gender" class="form-select"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_gender'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ctr-rating-level">Rating level</label><select id="ctr-rating-level" name="rating_level" class="form-select">@foreach(['red' => 'Red', 'orange' => 'Orange', 'standard' => 'Green/Yellow ball CTR'] as $value => $label)<option value="{{ $value }}" @selected($ratingTopTen['filters']['rating_level'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <noscript><button type="submit" class="btn btn-primary">Show ratings</button></noscript>
  </form>
  <p class="small text-muted">Each rating level uses its own comparable shared group; scores from different levels cannot be compared. Filters change the displayed players, not their rating scores.</p>
  @if($ratingTopTen['as_of'])<p class="small text-muted">Saved through {{ $ratingTopTen['as_of'] }}.@if($ratingTopTen['stale']) Latest saved estimates; an update is pending.@endif</p>@endif
  @if($ratingTopTen['reason'])<p role="status">{{ $ratingTopTen['reason'] }}</p>@endif
  <style>.ctr-top-lists{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ctr-top-list{min-width:0;border:1px solid #dce7e2;border-radius:14px;padding:1rem;background:#fff}.ctr-top-list li{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.5rem;align-items:baseline;padding:.7rem 0;border-top:1px solid #edf1ef;overflow-wrap:anywhere}.ctr-top-list .ctr-top-score{font-variant-numeric:tabular-nums;color:#176b59}.ctr-top-list small{display:block;color:#62766e}@media(max-width:767.98px){.ctr-top-lists{grid-template-columns:1fr}}</style>
  <div class="ctr-top-lists">
    @foreach($ratingTopTen['lists'] as $key => $list)
      <section class="ctr-top-list" aria-label="{{ $list['label'] }} Top 10" data-top-ten-cohort="{{ $key }}"><h4 class="h6">{{ $list['label'] }}</h4>
        <ol class="list-unstyled mb-0">
          @forelse($list['players'] as $member)
            <li value="{{ $member['position'] }}" @if($member['id'] === (int) $ratingPlayer->id) aria-current="true" @endif><span aria-hidden="true">{{ $member['position'] }}</span><span>{{ $member['name'] }}@if($member['id'] === (int) $ratingPlayer->id)<small>Your selected player</small>@endif<small>{{ $member['confidence'] }} confidence</small></span><strong class="ctr-top-score">{{ number_format($member['score'], 1) }}</strong></li>
          @empty<li class="small text-muted">No comparable published-player ratings available.</li>@endforelse
        </ol>
      </section>
    @endforeach
  </div>
  <p class="small text-muted mt-2 mb-0">Equal displayed scores share a position. Each list shows at most ten players; equal scores are ordered by name. Within-group-only and nonconverged estimates are excluded.</p>
</section>
@endif
