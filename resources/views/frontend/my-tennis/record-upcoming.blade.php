<section class="record-sheet" aria-labelledby="upcoming-heading">
  <h2 id="upcoming-heading">Upcoming matches</h2>
  <p class="record-note">Published match times, soonest first. Select an opponent's name to compare available Overall CTR ratings. These matches are independent of the past-results season filter.</p>
  @forelse($upcoming['rows'] as $match)
    <article class="record-row">
      <time datetime="{{ \Carbon\Carbon::parse($match['date'])->toIso8601String() }}">{{ \Carbon\Carbon::parse($match['date'])->format('j M Y') }}<br>{{ \Carbon\Carbon::parse($match['date'])->format('H:i') }}</time>
      <div>
        <strong>{{ $match['event'] }}</strong>
        <p class="record-note">{{ $match['draw'] }}{{ $match['team'] ? ' · '.$match['team'] : '' }}</p>
        <p class="record-note">{{ $match['venue'] ?: 'Venue to be confirmed' }} · {{ $match['court'] ? 'Court '.$match['court'] : 'Court to be confirmed' }}</p>
        @forelse($match['opponents'] as $opponent)
          @php($opponentRating = $upcoming['ratings'][$opponent['id'] ?? 0] ?? null)
          @php($ownRating = $upcoming['ratings'][$recordPlayer->id] ?? null)
          <details class="mt-2">
            <summary style="cursor:pointer;padding:.65rem 0;color:#004177"><strong>{{ $opponent['name'] }}</strong> · {{ $opponentRating ? 'Overall CTR '.number_format($opponentRating['score'], 1) : 'Rating unavailable' }}</summary>
            <div class="p-3" style="background:#edf3fa;border-radius:8px">
              <h3 class="h6">{{ $recordPlayer->full_name }} vs {{ $opponent['name'] }}</h3>
              @foreach([['name' => $recordPlayer->full_name, 'rating' => $ownRating], ['name' => $opponent['name'], 'rating' => $opponentRating]] as $side)
                <p>{{ $side['name'] }}: <strong>{{ $side['rating'] ? 'Overall CTR '.number_format($side['rating']['score'], 1) : 'Rating unavailable' }}</strong>
                  @if($side['rating'])
                    · {{ $side['rating']['confidence'] }} confidence
                  @endif
                </p>
              @endforeach
              <p class="record-note mb-0">Saved published, comparable ratings. A rating is not a prediction; missing or non-comparable evidence remains unavailable.</p>
              @if($opponent['id'])<a class="d-inline-block mt-2 py-2" href="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => 'opponents', 'opponent' => $opponent['id']]) }}#opponent-meetings">Compare past meetings →</a>@endif
            </div>
          </details>
        @empty
          <p class="record-note mt-2">Opponent players to be confirmed.</p>
        @endforelse
      </div>
      <a href="{{ $match['url'] }}" class="py-2">View fixtures →</a>
    </article>
  @empty
    <p class="mb-0" role="status">No upcoming published match times found.</p>
  @endforelse
  @include('frontend.my-tennis.record-pagination', ['matchPage' => $upcoming, 'pageKey' => 'upcoming_page'])
</section>
