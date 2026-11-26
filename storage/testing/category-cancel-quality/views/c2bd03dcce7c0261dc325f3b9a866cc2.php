<?php $__env->startSection('title', 'PayFast checkout recovery'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y">
  <h3>PayFast checkout recovery</h3>
  <p>Select an event to review registration checkouts awaiting a payment result.</p>
  <div class="list-group mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <a class="list-group-item list-group-item-action py-3" href="<?php echo e(route('backend.payfast-handoffs.index', $event)); ?>"><?php echo e($event->name); ?> <span class="text-muted">#<?php echo e($event->id); ?></span></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p>No unresolved registration checkouts found.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php echo e($events->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\payfast-handoffs\events.blade.php ENDPATH**/ ?>