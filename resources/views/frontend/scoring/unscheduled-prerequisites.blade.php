@if($unscheduledPrerequisites->isNotEmpty())
  <section class="card border-warning mb-3" aria-labelledby="unscheduled-prerequisites-heading" data-unscheduled-prerequisites>
    <div class="card-header">
      <h2 id="unscheduled-prerequisites-heading" class="h5 mb-1">Unscheduled prerequisites</h2>
      <p class="small text-muted mb-0">These unscored matches feed the booked matches in this view. No court or time is assigned. Enter a confirmed result here, or schedule the match through the draw workspace.</p>
    </div>
    <div class="card-body d-grid gap-3">
      @foreach($unscheduledPrerequisites as $prerequisite)
        @php
          $prerequisiteDraw = $prerequisite->draw;
          [$prerequisiteHome, $prerequisiteAway] = $prerequisite->scoring_participant_labels;
          $prerequisiteTimed = $prerequisiteDraw->settings?->score_format === 'timed';
          $prerequisiteTiming = null;
          try { $prerequisiteTiming = app(\App\Services\Scheduling\MatchTimingService::class)->forBookedFixture($prerequisite); }
          catch (\InvalidArgumentException $exception) { /* An unbooked timed match requires valid draw defaults. */ }
          $prerequisiteTarget = $prerequisite->scoring_prerequisite_anchor;
        @endphp
        <article class="border rounded p-3" data-unscheduled-prerequisite="{{ $prerequisite->id }}">
          <span class="badge bg-label-warning mb-2">Unscheduled prerequisite</span>
          <div class="small text-muted">{{ $prerequisiteDraw->drawName }} · Match {{ $prerequisite->match_nr ?: $prerequisite->id }} · Feeds {{ $prerequisiteTarget['label'] ?: 'Match '.$prerequisiteTarget['match_nr'] }}</div>
          <p class="fw-semibold my-2">{{ $prerequisiteHome }} vs {{ $prerequisiteAway }}</p>
          @if(!$prerequisiteTiming)
            <p class="small text-warning mb-0">Review timed settings before entering this unscheduled match.
              <a href="{{ route('backend.draw.roundrobin.show', $prerequisiteDraw) }}#settings">Open draw settings</a></p>
          @elseif($prerequisite->registration1_id && $prerequisite->registration2_id)
            @include('frontend.scoring.score-button', [
              'match' => $prerequisite, 'draw' => $prerequisiteDraw, 'home' => $prerequisiteHome, 'away' => $prerequisiteAway,
              'engine' => 'standard', 'hasScore' => false, 'isFlexible' => false, 'isTimed' => $prerequisiteTimed,
              'isTeamFixture' => false, 'isWalkover' => false, 'isRetired' => false,
              'normalStore' => route('api.draws.fixtures.score.store', ['draw' => $prerequisiteDraw->id, 'fixture' => $prerequisite->id]),
              'normalDelete' => route('api.draws.fixtures.score.delete', ['draw' => $prerequisiteDraw->id, 'fixture' => $prerequisite->id]),
              'matchTiming' => $prerequisiteTiming, 'sets' => collect(),
            ])
          @else
            <span class="small text-muted">Waiting for players. Complete its earlier qualification matches first.</span>
          @endif
        </article>
      @endforeach
    </div>
  </section>
@endif
