<?php
  $isTeamEvent = optional($draw->event)->eventType == 3;
?>

<div class="card mb-3 shadow-sm border draw-card">
  <div class="card-body py-3">
    
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <h6 class="mb-0">
          <i class="ti ti-tournament me-1 text-primary"></i>
          <?php echo e($draw->drawName); ?>

        </h6>
        <span class="badge bg-label-secondary"><?php echo e(optional($draw->draw_types)->drawTypeName ?? 'Type'); ?></span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
          <span class="badge bg-success"><i class="ti ti-eye ti-xs me-1"></i>Published</span>
        <?php else: ?>
          <span class="badge bg-label-danger"><i class="ti ti-eye-off ti-xs me-1"></i>Draft</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>

    
    <div class="draw-venues mb-3" data-draw-id="<?php echo e($draw->id); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->venues && $draw->venues->count() > 0): ?>
        <small class="text-muted me-1"><i class="ti ti-map-pin ti-xs"></i> Venues:</small>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <span class="badge bg-label-primary me-1">
            <?php echo e($venue->name); ?> <span class="text-muted">(<?php echo e($venue->pivot->num_courts); ?> <?php echo e(Str::plural('court', $venue->pivot->num_courts)); ?>)</span>
          </span>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?>
        <small class="text-muted"><i class="ti ti-map-pin-off ti-xs me-1"></i>No venues assigned</small>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-danger"
         href="<?php echo e(route('engine.draw.show', $draw->id)); ?>">
        <i class="ti ti-engine me-1"></i>Engine
      </a>

      <a class="btn btn-sm btn-warning"
         href="<?php echo e($isTeamEvent
                  ? route('backend.team-fixtures.index', ['draw_id' => $draw->id])
                  : route('backend.draw.roundrobin.show', $draw->id)); ?>">
        <i class="ti ti-list-details me-1"></i><?php echo e($isTeamEvent ? 'Team Fixtures' : 'Fixtures'); ?>

      </a>

      <a class="btn btn-sm btn-info"
         href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>">
        <i class="ti ti-calendar-event me-1"></i>Schedule
      </a>

      <a class="btn btn-sm btn-outline-primary"
         href="<?php echo e(route('draws.manage', $draw->id)); ?>">
        <i class="ti ti-edit me-1"></i>Edit draw
      </a>

      <button type="button"
              class="btn btn-sm btn-secondary btn-add-venues"
              data-draw-id="<?php echo e($draw->id); ?>"
              data-draw-name="<?php echo e($draw->drawName); ?>"
              data-url="<?php echo e(route('backend.draw.venues.store', $draw->id)); ?>">
        <i class="ti ti-map-pin me-1"></i>Venues
      </button>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->usesFlexibleMonrad()): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#matrix">Draw publication</a>
      <?php else: ?>
      <button type="button"
              class="btn btn-sm toggle-publish <?php echo e($draw->published ? 'btn-success' : 'btn-danger'); ?>"
              data-url="<?php echo e(route('draw.toggle.publish', $draw->id)); ?>"
              data-status="<?php echo e($draw->published ? 1 : 0); ?>">
        <i class="ti ti-<?php echo e($draw->published ? 'eye-off' : 'eye'); ?> me-1"></i><?php echo e($draw->published ? 'Unpublish' : 'Publish'); ?>

      </button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <button type="button"
              class="btn btn-sm btn-outline-danger btn-delete-draw ms-auto"
              data-url="<?php echo e(route('draws.destroy', $draw->id)); ?>"
              data-draw-id="<?php echo e($draw->id); ?>"
              data-draw-name="<?php echo e($draw->drawName); ?>">
        <i class="ti ti-trash me-1"></i>Delete
      </button>
    </div>
  </div>
</div>

<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\_includes\draw_tab_interpro.blade.php ENDPATH**/ ?>