<?php
  $isTeamDraw = $draw->isTeamDraw();
  $isLocked = (bool) $draw->locked;
  $isPublished = (bool) $draw->published;
  $isSchedulePublished = (bool) $draw->oop_published;
  $canConfirmScoring = $isTeamDraw && ($draw->team_scoring_rules['undecided'] ?? false)
    && isset($draw->team_draw_selection['competition']) && !$isPublished && !$isLocked
    && auth()->user()?->can('team-draw.updateTeamDraw', $draw)
    && !$draw->teamTies()->whereNotNull('published_at')->exists();
  $individualWorkspaceUrl = $draw->needsWorkflowChoice() ? route('draw.setup.show', $draw) : route('backend.draw.roundrobin.show', $draw);
  $individualSettingsUrl = $draw->needsWorkflowChoice() ? $individualWorkspaceUrl : $individualWorkspaceUrl.'#settings';
?>
<div class="list-group-item <?php echo e(($hideDrawHeading ?? false) ? '' : 'event-draw-publication-card'); ?>">
  <div class="user-info">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hideDrawHeading ?? false)): ?>
    <h6 class="mb-2"><?php echo e($draw->drawName); ?> <span class="text-muted">— <?php echo e($draw->formatDisplayLabel()); ?></span></h6>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="event-draw-publication d-flex flex-wrap gap-2 <?php echo e(($compactOverview ?? false) ? 'd-none' : 'mb-3'); ?>" aria-live="polite">
      <span class="event-draw-status badge bg-label-<?php echo e($isPublished ? 'success' : 'warning'); ?> <?php echo e(($compactOverview ?? false) ? 'd-none' : ''); ?>"><?php echo e($isPublished ? 'Draw published' : 'Draw hidden'); ?></span>
      <span class="event-schedule-status badge bg-label-<?php echo e($isSchedulePublished ? 'success' : 'secondary'); ?>"><?php echo e($isSchedulePublished ? ($isPublished ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden'); ?></span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactOverview ?? false)): ?>
        <?php echo $__env->make('backend.draw.partials.scoring-readiness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isLocked && !($compactOverview ?? false)): ?><span class="badge bg-label-secondary">Locked</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <p class="event-publication-note small text-muted mb-3 <?php echo e(($compactOverview ?? false) ? 'd-none' : ''); ?>"><?php echo e($isSchedulePublished && ! $isPublished ? 'Schedule preview only: publish the draw to make these times public.' : ($isTeamDraw ? 'Publishing a team draw enables scoring after validation. Match times are published separately. Mark matches on court when play starts.' : 'Draws and match times are published separately.')); ?></p>
    <div class="draw-venues mb-3 <?php echo e(($compactOverview ?? false) ? 'd-none' : ''); ?>" data-draw-id="<?php echo e($draw->id); ?>">
      <small class="text-muted">Venues:</small>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <span class="badge bg-label-primary me-1"><?php echo e($venue->name); ?> <span class="text-muted">(<?php echo e($venue->pivot->num_courts); ?>)</span></span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <small class="text-muted">None assigned</small>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="draw-card-actions draw-card-primary d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw workspace">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($compactOverview ?? false) && $canConfirmScoring): ?>
        <a class="btn btn-sm btn-primary" href="<?php echo e(route('team-draw.competition.scoring', $draw)); ?>">Confirm scoring</a>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?><a data-draw-guide-open class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Continue setup</a><?php endif; ?>
      <?php else: ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?><a data-draw-guide-open class="btn btn-sm btn-primary" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Continue setup</a><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a class="btn btn-sm <?php echo e(($compactOverview ?? false) ? 'btn-outline-primary' : 'btn-primary'); ?>" href="<?php echo e($isTeamDraw ? route('backend.team-fixtures.index', ['draw_id' => $draw->id]) : $individualWorkspaceUrl); ?>"><?php echo e($isTeamDraw ? 'Open team fixtures' : 'Open singles draw'); ?></a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($compactOverview ?? false) && $isTeamDraw): ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.view', $draw)): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->team_format_snapshot !== null): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.team-draw.operations', $draw)); ?>">Team ties</a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.team-draw.standings', $draw)); ?>">Draw standings</a>
        <?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactOverview ?? false): ?>
      <div class="event-draw-scoring-actions"><?php echo $__env->make('backend.draw.partials.scoring-readiness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
      <details class="event-draw-schedule" aria-label="Match schedule">
        <summary><span class="fw-semibold">Match schedule</span><span class="small text-muted">Plan, preview and publish times</span></summary>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Schedule tools">
      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>"><i class="ti ti-calendar me-1"></i>Schedule matches</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all'])); ?>">Edit schedule / publish times</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar.preview', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all'])); ?>"><i class="ti ti-eye me-1"></i>Public schedule preview</a>
    </div>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $draw)): ?>
    <div class="draw-card-actions draw-card-publication d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Publication controls">
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish <?php echo e(($compactOverview ?? false) ? 'd-none' : ''); ?>" data-url="<?php echo e(route('draw.toggle.publish', $draw->id)); ?>" data-status="<?php echo e($isPublished ? 1 : 0); ?>"><i class="ti ti-<?php echo e($isPublished ? 'eye-off' : 'eye'); ?> me-1"></i><?php echo e($isPublished ? 'Hide draw' : 'Publish draw'); ?></button>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactOverview ?? false)): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish-schedule" data-url="<?php echo e(route('draw.toggle.publish.schedule', $draw->id)); ?>" data-status="<?php echo e($isSchedulePublished ? 1 : 0); ?>"><i class="ti ti-<?php echo e($isSchedulePublished ? 'eye-off' : 'eye'); ?> me-1"></i><?php echo e($isSchedulePublished ? 'Hide schedule' : 'Publish schedule'); ?></button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactOverview ?? false): ?>
      </details>
      <details class="event-draw-tools" aria-label="Draw settings"><summary><span class="fw-semibold">Draw settings</span><span class="small text-muted">Venues and advanced actions</span></summary>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw configuration">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $draw)): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?php echo e($isTeamDraw ? route('draws.manage', $draw->id) : $individualSettingsUrl); ?>"><i class="ti ti-edit me-1"></i>Edit draw</a>
      <button type="button" class="btn btn-sm btn-outline-info btn-add-venues" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" data-url="<?php echo e(route('backend.draw.venues.store', $draw->id)); ?>"><i class="ti ti-map-pin me-1"></i>Assign venues</button>
      <?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->can('generateFixtures', $draw) || auth()->user()?->can('delete', $draw)): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactOverview ?? false): ?>
      <details class="event-draw-advanced"><summary class="text-muted small">Advanced actions</summary>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTeamDraw): ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('generateFixtures', $draw)): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-recreate-fixtures" <?php if($isLocked || $isPublished): echo 'disabled'; endif; ?> data-url="<?php echo e(route('headoffice.recreateFixturesForDraw', $draw->id)); ?>" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>">Recreate fixtures</button>
          <?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $draw)): ?>
          <button type="button" class="btn btn-sm btn-outline-danger btn-delete-draw" <?php if($isLocked || $isPublished): echo 'disabled'; endif; ?> data-url="<?php echo e(route('draws.destroy', $draw->id)); ?>" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>">Delete draw</button>
        <?php endif; ?>
      </div>
      </details>
      <?php else: ?>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More actions</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTeamDraw): ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('generateFixtures', $draw)): ?>
          <li><button type="button" class="dropdown-item btn-recreate-fixtures" <?php if($isLocked || $isPublished): echo 'disabled'; endif; ?> data-url="<?php echo e(route('headoffice.recreateFixturesForDraw', $draw->id)); ?>" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>"><i class="ti ti-refresh me-1"></i>Recreate fixtures</button></li>
          <?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $draw)): ?>
          <li><button type="button" class="dropdown-item text-danger btn-delete-draw" <?php if($isLocked || $isPublished): echo 'disabled'; endif; ?> data-url="<?php echo e(route('draws.destroy', $draw->id)); ?>" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>"><i class="ti ti-trash me-1"></i>Delete draw</button></li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactOverview ?? false): ?>
      <p class="small text-muted mt-3 mb-0">Created <?php echo e($draw->created_at->format('d M, Y')); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactOverview ?? false): ?></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>

<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/_includes/draw_tab_team.blade.php ENDPATH**/ ?>