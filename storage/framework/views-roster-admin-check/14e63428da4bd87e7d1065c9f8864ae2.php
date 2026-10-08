<?php
  $isTeamDraw = $draw->isTeamDraw();
  $isLocked = (bool) $draw->locked;
  $isPublished = (bool) $draw->published;
  $isSchedulePublished = (bool) $draw->oop_published;
  $individualWorkspaceUrl = $draw->needsWorkflowChoice() ? route('draw.setup.show', $draw) : route('backend.draw.roundrobin.show', $draw);
  $individualSettingsUrl = $draw->needsWorkflowChoice() ? $individualWorkspaceUrl : $individualWorkspaceUrl.'#settings';
?>
<div class="list-group-item <?php echo e(($hideDrawHeading ?? false) ? '' : 'event-draw-publication-card'); ?>">
  <div class="user-info">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hideDrawHeading ?? false)): ?>
    <h6 class="mb-2"><?php echo e($draw->drawName); ?> <span class="text-muted">— <?php echo e(optional($draw->draw_types)->drawTypeName ?? 'Type'); ?></span></h6>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="event-draw-publication d-flex flex-wrap gap-2 mb-3" aria-live="polite">
      <span class="event-draw-status badge bg-label-<?php echo e($isPublished ? 'success' : 'warning'); ?>"><?php echo e($isPublished ? 'Draw published' : 'Draw hidden'); ?></span>
      <span class="event-schedule-status badge bg-label-<?php echo e($isSchedulePublished ? 'success' : 'secondary'); ?>"><?php echo e($isSchedulePublished ? ($isPublished ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden'); ?></span>
      <?php echo $__env->make('backend.draw.partials.scoring-readiness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isLocked): ?><span class="badge bg-label-secondary">Locked</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <p class="event-publication-note small text-muted mb-3"><?php echo e($isSchedulePublished && ! $isPublished ? 'Schedule preview only: publish the draw to make these times public.' : ($isTeamDraw ? 'Publishing a team draw enables scoring after validation. Match times are published separately. Mark matches on court when play starts.' : 'Draws and match times are published separately.')); ?></p>
    <div class="draw-venues mb-3" data-draw-id="<?php echo e($draw->id); ?>">
      <small class="text-muted">Venues:</small>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <span class="badge bg-label-primary me-1"><?php echo e($venue->name); ?> <span class="text-muted">(<?php echo e($venue->pivot->num_courts); ?>)</span></span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <small class="text-muted">None assigned</small>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="draw-card-actions draw-card-primary d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw workspace">
      <a class="btn btn-sm btn-primary" href="<?php echo e($isTeamDraw ? route('backend.team-fixtures.index', ['draw_id' => $draw->id]) : $individualWorkspaceUrl); ?>"><?php echo e($isTeamDraw ? 'Open team fixtures' : 'Open singles draw'); ?></a>
      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>"><i class="ti ti-calendar me-1"></i>Schedule matches</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all'])); ?>">Review / publish times</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar.preview', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all'])); ?>"><i class="ti ti-eye me-1"></i>Public schedule preview</a>
    </div>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $draw)): ?>
    <div class="draw-card-actions draw-card-publication d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Publication controls">
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish" data-url="<?php echo e(route('draw.toggle.publish', $draw->id)); ?>" data-status="<?php echo e($isPublished ? 1 : 0); ?>"><i class="ti ti-<?php echo e($isPublished ? 'eye-off' : 'eye'); ?> me-1"></i><?php echo e($isPublished ? 'Hide draw' : 'Publish draw'); ?></button>
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish-schedule" data-url="<?php echo e(route('draw.toggle.publish.schedule', $draw->id)); ?>" data-status="<?php echo e($isSchedulePublished ? 1 : 0); ?>"><i class="ti ti-<?php echo e($isSchedulePublished ? 'eye-off' : 'eye'); ?> me-1"></i><?php echo e($isSchedulePublished ? 'Hide schedule' : 'Publish schedule'); ?></button>
    </div>
    <?php endif; ?>
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw configuration">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $draw)): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?php echo e($isTeamDraw ? route('draws.manage', $draw->id) : $individualSettingsUrl); ?>"><i class="ti ti-edit me-1"></i>Edit draw</a>
      <button type="button" class="btn btn-sm btn-outline-info btn-add-venues" data-draw-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" data-url="<?php echo e(route('backend.draw.venues.store', $draw->id)); ?>"><i class="ti ti-map-pin me-1"></i>Assign venues</button>
      <?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->can('generateFixtures', $draw) || auth()->user()?->can('delete', $draw)): ?>
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
    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\_includes\draw_tab_team.blade.php ENDPATH**/ ?>