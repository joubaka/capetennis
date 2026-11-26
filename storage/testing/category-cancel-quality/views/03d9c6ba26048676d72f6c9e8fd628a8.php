<div class="card <?php echo e($directoryClass ?? ''); ?> p-3 p-sm-4">
  <div class="card-body p-0 pb-2">
    <div class="badge <?php echo e($directoryBadgeClass ?? 'bg-label-primary'); ?> mb-3" <?php if($directoryAlert ?? false): ?> role="alert" <?php endif; ?>>
      Click on a Region below to register
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regions->isNotEmpty()): ?>
    <?php echo $__env->make('frontend.event.partials._region_team_picker', ['regions' => $regions], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php else: ?>
    <div class="alert alert-secondary mt-3">
      Regions are not configured for this event.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\event-region-directory.blade.php ENDPATH**/ ?>