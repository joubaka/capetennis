@if($matchPage['pages'] > 1)
<nav class="record-page" aria-label="{{ $pageKey === 'opponent_page' ? 'Opponent pages' : 'Match pages' }}">
  @if($matchPage['page'] > 1)<a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery([$pageKey => $matchPage['page'] - 1]) }}{{ $pageKey === 'meeting_page' ? '#opponent-meetings' : '' }}">Previous</a>@endif
  <span>Page {{ $matchPage['page'] }} of {{ $matchPage['pages'] }}</span>
  @if($matchPage['page'] < $matchPage['pages'])<a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery([$pageKey => $matchPage['page'] + 1]) }}{{ $pageKey === 'meeting_page' ? '#opponent-meetings' : '' }}">Next</a>@endif
</nav>
@endif
