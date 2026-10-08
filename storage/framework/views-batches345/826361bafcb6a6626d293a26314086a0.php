<h2>Cape Tennis payment/registration failure</h2>
<p>A payment or registration operation failed. Reference: <strong><?php echo e($details['reference'] ?? 'unknown'); ?></strong></p>
<table>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr><th style="text-align:left;padding-right:16px;vertical-align:top"><?php echo e($key); ?></th><td><?php echo e(is_scalar($value) ? $value : json_encode($value, JSON_PRETTY_PRINT)); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</table>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\payment-failure-alert.blade.php ENDPATH**/ ?>