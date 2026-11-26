<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingOriginalSourceRefunds->isNotEmpty()): ?>
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">PayFast refunds awaiting confirmation</h2>
    <p class="small text-muted">These withdrawn orders have saved original-source refund claims. Review confirmation or recovery before taking any further refund action.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pendingOriginalSourceRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pendingOrder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom py-2">
        <span><?php echo e($pendingOrder->event?->name); ?> · Order #<?php echo e($pendingOrder->id); ?> · <?php echo e($pendingOrder->user?->name); ?> · R<?php echo e(number_format($pendingOrder->refund_net, 2)); ?></span>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.team.withdrawal.refund.show', [$pendingOrder->event_id, $pendingOrder])); ?>">Review original-source refund</a>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="mt-3"><?php echo e($pendingOriginalSourceRefunds->links('pagination::bootstrap-5')); ?></div>
  </div></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\refunds\_pending-original-source.blade.php ENDPATH**/ ?>