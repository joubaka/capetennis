@if($ratingTopTen['visible'] ?? false)
<section class="mt-4" aria-labelledby="player-top-ten-heading" data-player-rating-top-ten>
  <h3 class="h5" id="player-top-ten-heading">CTR Top 10 · all players</h3>
  <p class="small text-muted">Across ages and genders, among players in currently published series standings. Each list uses its own comparable shared rating group. These estimates are not official rankings; scores from different lists cannot be compared.</p>
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
