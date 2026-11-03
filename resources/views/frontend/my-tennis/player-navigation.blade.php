{{-- Include only after PlayerRatingAccess has authorised this player. --}}
<style>.ct-player-navigation .btn{min-height:44px;display:inline-flex;align-items:center;color:#004177;border-color:#9ab4cf}.ct-player-navigation .btn[aria-current=page]{background:#004177;color:#fff}</style>
<nav class="ct-player-navigation record-nav d-flex flex-wrap gap-2 my-3" aria-label="Player Plus views">
  @unless($embeddedDashboard ?? false)
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}">Overview</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}#rating">Player rating</a>
  @endunless
  <a class="btn btn-outline-primary" href="{{ route('my.tennis.record', $navigationPlayer) }}" @if(($recordSection ?? null) === 'record') aria-current="page" @endif>Playing Record</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis.record', ['player' => $navigationPlayer, 'section' => 'opponents']) }}" @if(($recordSection ?? null) === 'opponents') aria-current="page" @endif>Opponent Insights</a>
  <a class="btn btn-outline-primary" data-pathway-navigation href="{{ route('my.tennis.pathway', $navigationPlayer) }}">Tennis pathway</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis.event-history', $navigationPlayer) }}">Past-event ratings</a>
</nav>
