<?php $__env->startSection('title', $draw->drawName ?? 'Tournament draw'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>



<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view', $draw)): ?>
    <div class="m-2">
      <a href="<?php echo e(route('frontend.bracket.fixtures', $draw->id)); ?>" class="btn btn-primary btn-sm">
        Manage fixtures
      </a>
    </div>
  <?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="mb-3">
  <a href="<?php echo e(route('events.show', $draw->event_id)); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ti ti-arrow-left me-1"></i>Back to tournament
  </a>
</div>
<div class="alert <?php echo e($draw->oop_published ? 'alert-success' : 'alert-info'); ?> py-2">
  <strong><?php echo e($draw->drawName ?? 'Tournament draw'); ?></strong> —
  <?php echo e($draw->oop_published ? 'draw and match times published' : 'draw published; match times to follow'); ?>.
</div>
<div>
  <?php echo $__env->make('frontend.draw.print', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\draw\show.blade.php ENDPATH**/ ?>