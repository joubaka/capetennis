<?php
  $isFlexible = (bool) $draw->is_flexible;
  $scheduleState = $draw->schedule_publication_state ?? ['saved' => (int) $draw->order_of_play_count, 'published' => $draw->oop_published ? (int) $draw->order_of_play_count : 0, 'pending' => $draw->oop_published ? 0 : (int) $draw->order_of_play_count, 'status' => $draw->oop_published ? 'Published' : ($draw->order_of_play_count > 0 ? 'Unpublished' : 'Not scheduled')];
  $hasPublicTimes = $scheduleState['published'] > 0;
  $timesVisible = $draw->published && ($draw->event->draw_hub_publicly_visible ?? $draw->event->published);
  $needsSchedulePublication = $scheduleState['saved'] > 0 && ($scheduleState['pending'] > 0 || ! $hasPublicTimes);
  $drawUrl = route('backend.draw.roundrobin.show', $draw->id);
  $format = \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0]
    ?? ($isFlexible ? 'Custom Monrad' : null);
  $publishUrl = $isFlexible
    ? route('flexible-monrad.publish', $draw->id)
    : route('draw.toggle.publish', $draw->id);
  $publishRevision = $draw->relationLoaded('flexibleMonrad') ? ($draw->flexibleMonrad?->revision ?? 0) : 0;
  $flexibleGraph = $draw->relationLoaded('flexibleMonrad') ? ($draw->flexibleMonrad?->graph ?? []) : [];
  $pendingLateWithdrawalCount = $draw->getAttribute('pending_late_withdrawal_count');
  $pendingLateWithdrawalCount ??= count(array_diff(
      array_map('intval', array_keys($flexibleGraph['late_withdrawals'] ?? [])),
      array_map('intval', $flexibleGraph['withdrawn'] ?? [])
    ));
  $roundRobinTotal = (int) ($draw->getAttribute('round_robin_fixture_count') ?? 0);
  $roundRobinCompleted = (int) ($draw->getAttribute('round_robin_completed_count') ?? 0);
  $roundRobinRemaining = max(0, $roundRobinTotal - $roundRobinCompleted);
  $playoffFixtureCount = (int) ($draw->getAttribute('playoff_fixture_count') ?? 0);
  $progressReady = $roundRobinTotal > 0 && $roundRobinRemaining === 0 && ! $draw->locked;
  $progressLabel = $draw->locked
    ? 'Locked'
    : ($roundRobinTotal === 0
      ? 'Awaiting fixtures'
      : ($roundRobinRemaining > 0
        ? $roundRobinRemaining.' '.Str::plural('result', $roundRobinRemaining).' left'
        : ($playoffFixtureCount > 0 ? 'Refresh playoffs' : 'Review & progress')));
  if ($draw->isTwoStageRoundRobin()) {
    $sourceIds = $draw->groups()->where('round_robin_phase', 1)->pluck('id');
    $roundRobinTotal = $draw->drawFixtures()->where('stage', 'RR')->whereIn('draw_group_id', $sourceIds)->count();
    $roundRobinCompleted = $draw->drawFixtures()->where('stage', 'RR')->whereIn('draw_group_id', $sourceIds)->whereHas('fixtureResults')->count();
    $roundRobinRemaining = max(0, $roundRobinTotal - $roundRobinCompleted);
    $secondCreated = $draw->groups()->where('round_robin_phase', 2)->exists();
    $progressReady = !$draw->locked && !$secondCreated && $roundRobinTotal > 0 && $roundRobinRemaining === 0;
    $progressLabel = $secondCreated ? 'Stage 2 created' : ($progressReady ? 'Review & progress' : 'Stage 1 incomplete');
  }
  $progressHelp = $draw->locked
    ? 'Unlock this draw before progressing it.'
    : ($roundRobinTotal === 0
      ? 'Create the round-robin fixtures before progressing this draw.'
      : ($roundRobinRemaining > 0
        ? 'Complete all round-robin results first. '.$roundRobinRemaining.' of '.$roundRobinTotal.' matches still need a result.'
        : ($draw->isTwoStageRoundRobin() ? 'Review and confirm the first-stage standings before creating the second round robin.' : 'Review and confirm the final standings before creating the playoffs.')));
?>
<article class="draw-overview-row" data-draw-id="<?php echo e($draw->id); ?>"
         data-name="<?php echo e($draw->drawName); ?>" data-format="<?php echo e($format ?? ''); ?>" data-published="<?php echo e($draw->published ? '1' : '0'); ?>"
         data-schedule="<?php echo e($hasPublicTimes ? '1' : '0'); ?>" data-has-schedule="<?php echo e($scheduleState['saved'] > 0 ? '1' : '0'); ?>" data-schedule-pending="<?php echo e($needsSchedulePublication ? '1' : '0'); ?>"
         data-schedulable="<?php echo e(!$draw->locked && !$draw->published ? '1' : '0'); ?>">
  <div class="draw-overview-info">
    <label class="draw-row-selector" for="draw-select-<?php echo e($draw->id); ?>">
      <input class="form-check-input draw-select" id="draw-select-<?php echo e($draw->id); ?>" type="checkbox" value="<?php echo e($draw->id); ?>">
      <span class="visually-hidden">Select <?php echo e($draw->drawName); ?></span>
    </label>
    <span class="draw-division-mark"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'bracket'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <div class="draw-division-text">
      <h3 class="h6 mb-0 draw-overview-title"><a href="<?php echo e($drawUrl); ?>" class="draw-overview-name"><?php echo e($draw->drawName); ?></a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingLateWithdrawalCount > 0): ?>
          <a href="<?php echo e($drawUrl); ?>" class="draw-attention-badge" aria-label="<?php echo e($pendingLateWithdrawalCount); ?> late <?php echo e(Str::plural('withdrawal', $pendingLateWithdrawalCount)); ?> <?php echo e($pendingLateWithdrawalCount === 1 ? 'requires' : 'require'); ?> attention in <?php echo e($draw->drawName); ?>" title="Open draw to review the late withdrawal">
            <i class="ti ti-alert-triangle-filled" aria-hidden="true"></i>
            <?php echo e($pendingLateWithdrawalCount); ?> <?php echo e(Str::plural('late withdrawal', $pendingLateWithdrawalCount)); ?>

          </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </h3>
      <div class="draw-division-state"><span class="draw-publication <?php echo e($draw->published ? 'is-published' : 'is-draft'); ?>"><span class="draws-status-dot" aria-hidden="true"></span><?php echo e($draw->published ? 'Published' : 'Draft'); ?></span>
      <span class="draw-publication <?php echo e($scheduleState['status'] === 'Published' && $timesVisible ? 'is-published' : 'is-draft'); ?>">
        <i class="ti ti-calendar-event" aria-hidden="true"></i>
        <?php echo e($scheduleState['status'] === 'Published' ? ($timesVisible ? 'Schedule published' : 'Times prepared · preview only') : $scheduleState['status']); ?><?php echo e($hasPublicTimes && !$timesVisible && $scheduleState['status'] !== 'Published' ? ' · preview only' : ''); ?>

      </span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked): ?><span class="draw-lock"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'lock'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Locked</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
    </div>
  </div>
  <div class="draw-format-cell"><span class="draw-column-label">Format</span><span class="<?php echo e($format ? 'draw-format-name' : 'draw-format-unset'); ?>"><?php echo e($format ?? 'Not specified'); ?></span></div>
  <div class="draw-match-cell"><span class="draw-column-label">Matches</span><span class="draw-match-count" aria-label="<?php echo e($draw->draw_fixtures_count); ?> <?php echo e(Str::plural('match', $draw->draw_fixtures_count)); ?>"><?php echo e($draw->draw_fixtures_count); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->hasRoundRobinProgression() && $roundRobinTotal > 0): ?>
      <span class="d-block small text-muted"><?php echo e($roundRobinCompleted); ?>/<?php echo e($roundRobinTotal); ?> RR results</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="draw-venue-cell">
      <span class="draw-column-label">Venue</span>
      <span class="draw-venues" data-draw-id="<?php echo e($draw->id); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <span><?php echo e($venue->name); ?> (<?php echo e($venue->pivot->num_courts); ?> <?php echo e(Str::plural('court', $venue->pivot->num_courts)); ?>)<?php echo e(!$loop->last ? ', ' : ''); ?></span>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          Venues not set
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </span>
  </div>
  <div class="draw-overview-actions">
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$draw->setup_complete): ?><a data-draw-guide-open class="btn draws-button draw-open-button" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Continue setup</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?>
    <a class="btn draws-button draw-open-button" href="<?php echo e($drawUrl); ?>">Open draw <?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'arrow'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></a>
    <a class="btn draws-button draw-schedule-button" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'calendar'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <span>Schedule</span></a>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?>
      <a class="btn draws-button draws-button-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all'])); ?>">Schedule by day</a>
    <?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->hasRoundRobinProgression()): ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
        <button type="button" class="btn draws-button draws-button-primary progress-draw"
                data-url="<?php echo e(route('backend.draw.progress', $draw)); ?>"
                data-review-url="<?php echo e(route('backend.draw.progress-review', $draw)); ?>"
                data-draw-name="<?php echo e($draw->drawName); ?>"
                data-progress-ready="<?php echo e($progressReady ? '1' : '0'); ?>"
                <?php if(! $progressReady): echo 'disabled'; endif; ?>
                title="<?php echo e($progressHelp); ?>"
                aria-label="<?php echo e($progressReady ? 'Review and progress' : 'Cannot progress'); ?> <?php echo e($draw->drawName); ?>: <?php echo e($progressHelp); ?>">
          <i class="ti ti-player-track-next" aria-hidden="true"></i>
          <span><?php echo e($progressLabel); ?></span>
        </button>
      <?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $draw)): ?>
      <button type="button" class="btn draws-button draw-publish-button <?php echo e($draw->published ? 'draws-button-secondary' : 'draws-button-primary'); ?> toggle-publish"
              data-url="<?php echo e($publishUrl); ?>" data-draw-name="<?php echo e($draw->drawName); ?>"
              data-published="<?php echo e($draw->published ? '1' : '0'); ?>"
              <?php if($isFlexible): ?> data-revision="<?php echo e($publishRevision); ?>" <?php endif; ?>
              <?php if($draw->published && $draw->locked): ?> disabled title="Unlock this draw before unpublishing it" <?php endif; ?>
              <?php if(!$draw->published && !$format): ?> disabled title="Select a draw format before publishing" <?php endif; ?>
              aria-label="<?php echo e($draw->published ? 'Unpublish' : 'Publish'); ?> <?php echo e($draw->drawName); ?>">
        <i class="ti ti-<?php echo e($draw->published ? 'eye-off' : 'eye'); ?>" aria-hidden="true"></i>
        <span><?php echo e($draw->published ? 'Unpublish' : 'Publish'); ?></span>
      </button>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $draw)): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduleState['saved'] > 0 || $hasPublicTimes): ?>
        <button type="button"
                class="btn draws-button <?php echo e($draw->oop_published ? 'draws-button-secondary' : 'draw-schedule-button'); ?> toggle-schedule-publication"
                data-url="<?php echo e(route('draw.toggle.publish.schedule', $draw)); ?>"
                data-draw-name="<?php echo e($draw->drawName); ?>"
                data-draw-published="<?php echo e($timesVisible ? '1' : '0'); ?>"
                data-published="<?php echo e($hasPublicTimes && !$needsSchedulePublication ? '1' : '0'); ?>" data-action="<?php echo e($needsSchedulePublication ? 'publish' : 'unpublish'); ?>">
          <i class="ti ti-clock" aria-hidden="true"></i>
          <span><?php echo e($needsSchedulePublication ? ($hasPublicTimes ? 'Publish updates' : 'Publish times') : 'Unpublish times'); ?></span>
        </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPublicTimes && $needsSchedulePublication): ?>
          <button type="button" class="btn draws-button draws-button-secondary toggle-schedule-publication" data-url="<?php echo e(route('draw.toggle.publish.schedule', $draw)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" data-draw-published="<?php echo e($timesVisible ? '1' : '0'); ?>" data-published="1" data-action="unpublish">Unpublish times</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?>
    <div class="dropdown">
      <button type="button" class="btn draws-button draw-more-button" data-bs-toggle="dropdown"
              aria-expanded="false" aria-label="More actions for <?php echo e($draw->drawName); ?>"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'dots'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->setup_complete): ?><li><a data-draw-guide-open class="dropdown-item" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Review setup</a></li><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
        <li><a class="dropdown-item" href="<?php echo e($drawUrl); ?>#settings"><i class="ti ti-settings me-2" aria-hidden="true"></i>Draw settings</a></li>
        <li><a class="dropdown-item" href="<?php echo e($drawUrl); ?>#groups">Players</a></li>
        <li><a class="dropdown-item" href="<?php echo e($drawUrl); ?>#print">Print this draw</a></li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published || $draw->oop_published): ?><li><a class="dropdown-item" href="<?php echo e($draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('public.roundrobin.show', $draw)); ?>" target="_blank" rel="noopener"><?php echo e($draw->published ? 'Open public draw' : 'Preview times on front page'); ?></a></li><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?php echo e(route('engine.draw.show', $draw->id)); ?>"><i class="ti ti-tool me-2" aria-hidden="true"></i>Engine diagnostics</a></li>
        <?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$draw->locked && !$draw->published): ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $draw)): ?>
            <li><hr class="dropdown-divider"></li>
            <li><button type="button" class="dropdown-item text-danger btn-delete-draw" data-url="<?php echo e(route('draws.destroy', $draw->id)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>"><i class="ti ti-trash me-2" aria-hidden="true"></i>Delete draw</button></li>
          <?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </div>
  </div>
</article>

<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/headOffice/partials/individual-draw-row.blade.php ENDPATH**/ ?>