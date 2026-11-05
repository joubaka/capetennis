<section class="record-sheet" aria-labelledby="upcoming-heading">
  <div class="record-section-heading">
    <h2 id="upcoming-heading">Upcoming matches</h2>
    @if(!$record['reason'])<a href="#past-opponents">Past opponents ↓</a>@endif
  </div>
  @php
    $personalSnapshot = ($workspace['canViewRating'] ?? false) ? ($workspace['snapshot'] ?? null) : null;
    $personalPoints = $personalSnapshot['points'] ?? [];
    $personalRating = !empty($personalSnapshot['stage']) && $personalPoints ? $personalPoints[array_key_last($personalPoints)] : null;
  @endphp
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
            <summary><strong>{{ $opponent['name'] }}</strong></summary>
            <div class="record-rating-comparison-body">
              <h3 class="h6 record-rating-comparison-title">{{ $recordPlayer->full_name }} vs {{ $opponent['name'] }}</h3>
              @foreach([
                ['name' => $recordPlayer->full_name, 'rating' => $personalRating, 'stage' => $personalSnapshot['stage'] ?? null],
                ['name' => $opponent['name'], 'rating' => $opponentRating, 'stage' => $opponentRating['stage'] ?? null],
              ] as $side)
                <div class="record-rating-comparison-side">
                  @include('frontend.my-tennis.record-player-name', ['name' => $side['name'], 'rating' => $side['rating'], 'stage' => $side['stage']])
                  @if(in_array($side['rating']['confidence_label'] ?? null, ['Low', 'Medium', 'High'], true))
                    <small class="record-note">{{ $side['rating']['confidence_label'] }} confidence</small>
                  @elseif(!$side['rating'])
                    <small class="record-note">Ball rating unavailable</small>
                  @endif
                </div>
              @endforeach
              @if($opponent['id'])<a class="record-fixture-link mt-1" href="{{ route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => 'opponents', 'opponent' => $opponent['id']]) }}#opponent-meetings">See past meetings →</a>@endif
            </div>
          </details>
        @empty
          <p class="record-note mt-2">Opponent players to be confirmed.</p>
        @endforelse
      </div>
      <a href="{{ $match['url'] }}" class="record-fixture-link">{{ $match['team'] ? 'View fixtures' : 'View draw' }} →</a>
    </article>
  @empty
    <p class="mb-0" role="status">No upcoming match times have been published yet.</p>
  @endforelse
  @include('frontend.my-tennis.record-pagination', ['matchPage' => $upcoming, 'pageKey' => 'upcoming_page'])
</section>
