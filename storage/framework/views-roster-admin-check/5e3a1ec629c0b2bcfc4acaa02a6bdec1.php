<?php $__env->startSection('title', ($draw->drawName ?? 'Tournament') . ' matches'); ?>

<?php $__env->startSection('content'); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
    <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div <?php if($draw->published): ?> data-live-results="<?php echo e($draw->isTeamDraw() ? 'team-fixtures' : 'individual-fixtures'); ?>" <?php endif; ?>>
  <div class="card" data-live-results-empty>
    <div class="card-body">
      <h3 class="mb-2"><?php echo e($draw->drawName); ?></h3>
      <p class="text-muted"><?php echo e($event->name); ?></p>
      <div class="alert alert-info" role="status">
        No matches are available to view yet. Please check back for updates.
      </div>
      <a href="<?php echo e(route('events.show', $event)); ?>" class="btn btn-outline-secondary">Back to tournament</a>
    </div>
  </div>
  </div>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\empty.blade.php ENDPATH**/ ?>