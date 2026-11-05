@foreach($matchPage['rows'] as $match)
<article class="record-row">
  <time datetime="{{ $match['date'] }}">{{ $match['date'] }}</time>
  <div>
    @include('frontend.my-tennis.record-player-name', ['name' => $match['opponent'], 'rating' => $matchRatings[$match['opponent_id'] ?? 0] ?? null])
    <p>{{ $match['event'] }}</p>
    @if($match['date_basis'] === 'event start proxy')<small class="record-note">Estimated date</small>@endif
  </div>
  <div class="record-score"><span>{{ $match['won'] ? 'Win' : 'Loss' }}</span><br><span>{{ $match['score'] }}</span></div>
</article>
@endforeach
@include('frontend.my-tennis.record-pagination')
