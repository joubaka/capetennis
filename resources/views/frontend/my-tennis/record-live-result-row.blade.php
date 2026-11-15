    <div class="record-row" data-recent-results-row>
      <time datetime="{{ $match['recorded_at'] ? \Carbon\CarbonImmutable::parse($match['recorded_at'])->toIso8601String() : null }}">
        {{ $match['recorded_at'] ? \Carbon\CarbonImmutable::parse($match['recorded_at'])->format('j M, H:i') : '' }}
      </time>
      <div>
        @forelse($match['opponent_players'] ?? [] as $opponent)
          @include('frontend.my-tennis.record-player-name', [
            'name' => ($loop->first ? 'vs ' : '').$opponent['name'],
            'rating' => $pastRatings[$opponent['player_id'] ?? 0] ?? null,
          ])
        @empty
          <strong>{{ $match['opponents'] ? 'vs '.$match['opponents'] : $match['draw'] }}</strong>
        @endforelse
        <p><a href="{{ $match['url'] }}">{{ $match['event'] }}</a></p>
      </div>
      <div class="record-score">{{ $match['outcome'] }}<br>{{ $match['score'] }}</div>
    </div>
