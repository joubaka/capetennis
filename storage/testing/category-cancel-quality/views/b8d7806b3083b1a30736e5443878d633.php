
<?php $__env->startSection('title', 'Review wallet refund'); ?>
<?php $__env->startSection('content'); ?>
<div class="card"><div class="card-body">
<h4>Review refund to wallet</h4>
<p>Order #<?php echo e($order->id); ?> · <?php echo e($event->name); ?></p>
<p>Credit <strong>R<?php echo e($refund->refund_net); ?></strong> to <strong><?php echo e($refund->payer?->name); ?></strong>, the original payer’s wallet.</p>
<p>This uses the same saved refund and items. PayFast has not paid this refund. For reconciled attempts, PayFast is checked again when you confirm. Confirming credits the wallet and closes the PayFast retry.</p>
<ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $refund->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($line->item?->item_name); ?> · <?php echo e($line->quantity); ?> units · R<?php echo e($line->amount); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
<form method="POST" action="<?php echo e(route('backend.clothing.refunds.wallet.confirm', [$event, $order, $refund])); ?>"><?php echo csrf_field(); ?>
<input type="hidden" name="review_token" value="<?php echo e($reviewToken); ?>">
<label class="d-block mb-3"><input required type="checkbox" name="confirmed" value="1"> I confirm this refund should credit the original payer’s wallet.</label>
<button class="btn btn-primary">Confirm wallet refund of R<?php echo e($refund->refund_net); ?></button>
<a class="btn btn-outline-secondary" href="<?php echo e(route('backend.clothing.refunds.show', [$event, $order])); ?>">Cancel</a>
</form></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\refund-wallet-review.blade.php ENDPATH**/ ?>