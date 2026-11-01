<div class="my-tennis-overview-badge" data-overview-rating-badge>
  @if(($canViewRating ?? false) && ($overviewBadge ?? null))
    <a href="{{ route('my.tennis', ['player' => $selectedPlayer->id]) }}#rating" data-overview-rating-link class="d-flex align-items-center gap-2 text-decoration-none bg-white rounded p-2" aria-label="View player rating">
      <x-ctr-badge size="small" :stage="$overviewBadge['stage']" :score="$overviewBadge['score']" :local="$overviewBadge['local']" />
    </a>
  @else
    <a href="{{ route('my.tennis', ['player' => $selectedPlayer->id]) }}#rating" data-overview-rating-link class="d-flex align-items-center gap-2 text-decoration-none" aria-label="Preview player rating with Player Plus">
      <span aria-hidden="true" style="filter:blur(5px);pointer-events:none;user-select:none"><x-ctr-badge size="small" /></span>
      <span class="small text-white">Unlock CTR<br>Player Plus</span>
    </a>
  @endif
</div>
