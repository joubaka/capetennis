<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unscheduledPrerequisites->isNotEmpty()): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $unscheduledPrerequisites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prerequisite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $prerequisiteDraw = $prerequisite->draw;
          [$prerequisiteHome, $prerequisiteAway] = $prerequisite->scoring_participant_labels;
          $prerequisiteTimed = $prerequisiteDraw->settings?->score_format === 'timed';
          $prerequisiteTiming = null;
          try { $prerequisiteTiming = app(\App\Services\Scheduling\MatchTimingService::class)->forBookedFixture($prerequisite); }
          catch (\InvalidArgumentException $exception) { /* An unbooked timed match requires valid draw defaults. */ }
          $prerequisiteTarget = $prerequisite->scoring_prerequisite_anchor;
        ?>
        <article class="card match-card" data-unscheduled-prerequisites data-unscheduled-prerequisite="<?php echo e($prerequisite->id); ?>" data-score-state="outstanding" data-score-timing="unscheduled">
          <div class="card-body">
          <span class="badge bg-label-warning mb-2">Unscheduled prerequisite</span>
          <div class="small text-muted"><?php echo e($prerequisiteDraw->drawName); ?> · Match <?php echo e($prerequisite->match_nr ?: $prerequisite->id); ?> · Feeds <?php echo e($prerequisiteTarget['label'] ?: 'Match '.$prerequisiteTarget['match_nr']); ?></div>
          <div class="small text-muted">No court or time assigned.</div>
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
          <p class="fw-semibold my-2"><?php echo e($prerequisiteHome); ?> vs <?php echo e($prerequisiteAway); ?></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$prerequisiteTiming): ?>
            <p class="small text-warning mb-0">Review timed settings before entering this unscheduled match.
              <a href="<?php echo e(route('backend.draw.roundrobin.show', $prerequisiteDraw)); ?>#settings">Open draw settings</a></p>
          <?php elseif($prerequisite->registration1_id && $prerequisite->registration2_id): ?>
            <?php echo $__env->make('frontend.scoring.score-button', [
              'match' => $prerequisite, 'draw' => $prerequisiteDraw, 'home' => $prerequisiteHome, 'away' => $prerequisiteAway,
              'engine' => 'standard', 'hasScore' => false, 'isFlexible' => false, 'isTimed' => $prerequisiteTimed,
              'isTeamFixture' => false, 'isWalkover' => false, 'isRetired' => false,
              'normalStore' => route('api.draws.fixtures.score.store', ['draw' => $prerequisiteDraw->id, 'fixture' => $prerequisite->id]),
              'normalDelete' => route('api.draws.fixtures.score.delete', ['draw' => $prerequisiteDraw->id, 'fixture' => $prerequisite->id]),
              'matchTiming' => $prerequisiteTiming, 'sets' => collect(),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php else: ?>
            <span class="small text-muted">Waiting for players. Complete its earlier qualification matches first.</span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          </div>
        </article>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\scoring\unscheduled-prerequisites.blade.php ENDPATH**/ ?>