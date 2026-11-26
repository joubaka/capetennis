<?php $__env->startSection('title', 'Player Plus payment reviews'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y" style="max-width:1000px">
  <a href="<?php echo e(route('superadmin.player-plus.manage.index')); ?>">← Player Plus management</a>
  <h1>Player Plus payment reviews</h1>
  <p>Review late and additional payments against the original payer and every provider receipt. This queue records evidence of an external resolution; it does not execute refunds, transfer a purchase or activate membership.</p>
  <div class="list-group">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php ($resolution = $review->reviews($order)->where('metadata->payment_fingerprint', $review->fingerprint($order))->first()); ?>
      <a class="list-group-item list-group-item-action py-3" href="<?php echo e(route('superadmin.player-plus.show', $order)); ?>">
        <strong>P<?php echo e($order->id); ?> · <?php echo e($order->player?->full_name ?? 'Player unavailable'); ?></strong>
        <span class="d-block"><?php echo e($resolution ? 'External resolution evidence recorded' : 'Needs review'); ?> · <?php echo e($order->user?->name ?? 'Payer unavailable'); ?></span>
        <span class="d-block"><?php echo e($order->reconciliation_reason); ?></span>
      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="alert alert-info">No Plus payments require review.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="mt-3"><?php echo e($orders->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-plus\index.blade.php ENDPATH**/ ?>