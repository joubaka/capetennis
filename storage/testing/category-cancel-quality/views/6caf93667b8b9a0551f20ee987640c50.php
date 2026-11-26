<?php $__env->startComponent('mail::message'); ?>
# Category change receipt

<?php echo e($adjustment->event->name); ?>


<?php echo e($adjustment->sourceCategory->category?->name); ?> → <?php echo e($adjustment->targetCategory->category?->name); ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) $adjustment->difference > 0): ?>
Additional payment received through PayFast: **R<?php echo e(number_format((float) $adjustment->difference, 2)); ?>**.
<?php elseif((float) $adjustment->wallet_credit > 0): ?>
Credited to your wallet: **R<?php echo e(number_format((float) $adjustment->wallet_credit, 2)); ?>**.
<?php else: ?>
No additional payment or wallet credit was required.
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

Amount paid toward this entry: **R<?php echo e(number_format((float) ($adjustment->payment_after['total_paid'] ?? 0), 2)); ?>**.

Reference: Category change #<?php echo e($adjustment->id); ?>.
<?php echo $__env->renderComponent(); ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\category-adjustment-receipt.blade.php ENDPATH**/ ?>