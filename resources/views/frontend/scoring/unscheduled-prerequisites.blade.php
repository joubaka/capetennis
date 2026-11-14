@if($unscheduledPrerequisites->isNotEmpty())
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
        <article class="card match-card" data-unscheduled-prerequisites data-unscheduled-prerequisite="{{ $prerequisite->id }}" data-score-state="outstanding" data-score-timing="unscheduled">
          <div class="card-body">
          <span class="badge bg-label-warning mb-2">Unscheduled prerequisite</span>
          <div class="small text-muted">{{ $prerequisiteDraw->drawName }} · Match {{ $prerequisite->match_nr ?: $prerequisite->id }} · Feeds {{ $prerequisiteTarget['label'] ?: 'Match '.$prerequisiteTarget['match_nr'] }}</div>
          <div class="small text-muted">No court or time assigned.</div>
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
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
          </div>
          </div>
        </article>
      @endforeach
@endif
