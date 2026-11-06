<section class="record-sheet" aria-labelledby="live-results-heading" data-my-tennis-live-results="{{ route('my.tennis.record.live', $recordPlayer) }}" @if($liveResultsPrefetch ?? false) hidden @endif>
  <h2 id="live-results-heading" tabindex="-1">Latest results</h2>
  <p class="record-note mb-2">Completed scores posted or updated in the past seven days.</p>
  @forelse($liveResults as $match)
    <div class="record-row">
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
  @empty
    <p class="mb-0">No completed scores have been posted for this player in the past seven days.</p>
  @endforelse
</section>
