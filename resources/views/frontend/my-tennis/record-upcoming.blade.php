<section class="record-sheet" aria-labelledby="upcoming-heading">
  <div class="record-section-heading">
    <h2 id="upcoming-heading">Upcoming matches</h2>
    @if(!$record['reason'])<a href="#past-opponents">Past opponents ↓</a>@endif
  </div>
  @if($upcoming['rows'])<p class="record-note">Open an opponent’s name to see how your ratings compare.</p>@endif
  @php($ownRating = $upcoming['ratings'][$recordPlayer->id] ?? null)
  @forelse($upcoming['rows'] as $match)
    @php($matchTime = \Carbon\Carbon::parse($match['date']))
    <article class="record-row record-upcoming-row">
      <time datetime="{{ $matchTime->toIso8601String() }}">{{ $matchTime->format('j M Y') }} <strong>{{ $matchTime->format('H:i') }}</strong></time>
      <div>
        <strong>{{ $match['event'] }}</strong>
        <p class="record-note">{{ $match['draw'] }}{{ $match['team'] ? ' · '.$match['team'] : '' }}</p>
        <p class="record-note">{{ $match['venue'] ?: 'Venue to be confirmed' }} · {{ $match['court'] ? 'Court '.$match['court'] : 'Court to be confirmed' }}</p>
        @forelse($match['opponents'] as $opponent)
          @php($opponentRating = $upcoming['ratings'][$opponent['id'] ?? 0] ?? null)
          <details class="record-rating-comparison">
            <summary><strong>{{ $opponent['name'] }}</strong><span>{{ $opponentRating ? 'Overall CTR '.number_format($opponentRating['score'], 1) : 'Rating unavailable' }}</span></summary>
            <div class="record-rating-comparison-body">
              <h3 class="h6">{{ $recordPlayer->full_name }} vs {{ $opponent['name'] }}</h3>
              @foreach([['name' => $recordPlayer->full_name, 'rating' => $ownRating], ['name' => $opponent['name'], 'rating' => $opponentRating]] as $side)
                <p>{{ $side['name'] }}: <strong>{{ $side['rating'] ? 'Overall CTR '.number_format($side['rating']['score'], 1) : 'Rating unavailable' }}</strong>
                  @if($side['rating'])
                    · {{ $side['rating']['confidence'] }} confidence
                  @endif
                </p>
              @endforeach
              <p class="record-note mt-2 mb-0">Ratings estimate playing strength and can’t predict who will win. We only show ratings that can be compared.</p>
              @if($opponent['id'])<a class="d-inline-block mt-2 py-2" href="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => 'opponents', 'opponent' => $opponent['id']]) }}#opponent-meetings">See past meetings →</a>@endif
            </div>
          </details>
        @empty
          <p class="record-note mt-2">Opponent players to be confirmed.</p>
        @endforelse
      </div>
      <a href="{{ $match['url'] }}" class="record-fixture-link">View fixtures →</a>
    </article>
  @empty
    <p class="mb-0" role="status">No upcoming match times have been published yet.</p>
  @endforelse
  @include('frontend.my-tennis.record-pagination', ['matchPage' => $upcoming, 'pageKey' => 'upcoming_page'])
</section>
