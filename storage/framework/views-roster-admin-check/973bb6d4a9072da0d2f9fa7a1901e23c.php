

<?php $__env->startSection('title', 'Event Not Available'); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-misc.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y">
  <div class="misc-wrapper text-center py-5">

    <div class="mb-4">
      <i class="ti ti-calendar-off" style="font-size: 5rem; color: #a0aec0;"></i>
    </div>

    <h2 class="mb-2">Event Not Available</h2>

    <p class="mb-1 text-muted">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event) && $event->name): ?>
        <strong><?php echo e($event->name); ?></strong> is not publicly available yet.
      <?php else: ?>
        This event is not publicly available yet.
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>

    <p class="mb-4 text-muted">
      It may still be in preparation. Please check back later or contact the organiser.
    </p>

    <a href="<?php echo e(url('/')); ?>" class="btn btn-primary">
      <i class="ti ti-home me-1"></i> Back to Home
    </a>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\unavailable.blade.php ENDPATH**/ ?>