<?php $__env->startSection('title', 'Masters invitations'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-4">Masters invitations</h4>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-3">
      <div class="card-body">
        <h5><?php echo e($invitation->batch->event->name ?? 'Masters event'); ?></h5>
        <p class="mb-1"><?php echo e($invitation->categoryEvent->category->name ?? 'Age group'); ?></p>
        <p class="text-muted">Ranking position <?php echo e($invitation->ranking_position); ?> · <?php echo e($invitation->total_points); ?> points</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation->status === 'invited'): ?>
          <form method="POST" action="<?php echo e(route('masters.invitations.accept', $invitation)); ?>" class="d-inline"><?php echo csrf_field(); ?><button class="btn btn-primary">Register and pay</button></form>
          <form method="POST" action="<?php echo e(route('masters.invitations.decline', $invitation)); ?>" class="d-inline"><?php echo csrf_field(); ?><button class="btn btn-outline-secondary">I am unavailable</button></form>
        <?php elseif($invitation->status === 'accepted_pending_payment'): ?>
          <a class="btn btn-warning" href="<?php echo e(route('registration.checkout', $invitation->order_id)); ?>">Complete payment</a>
        <?php else: ?>
          <span class="badge bg-label-success">Confirmed</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="alert alert-info">You have no active Masters invitations.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\masters\index.blade.php ENDPATH**/ ?>