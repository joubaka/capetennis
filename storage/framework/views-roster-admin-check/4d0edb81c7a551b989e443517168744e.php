<?php $__env->startSection('title', 'Registration payment'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="card mx-auto" style="max-width: 640px">
    <div class="card-body p-4">
      <h3>Complete your registration payment</h3>
      <p class="text-muted">Your registration remains reserved. Pay the exact outstanding amount securely through PayFast.</p>
      <div class="alert alert-info"><strong>Amount due:</strong> R <?php echo e(number_format((float) $recovery->amount_due, 2)); ?></div>
      <a class="btn btn-danger btn-lg w-100" href="<?php echo e($paymentUrl); ?>">Continue to PayFast</a>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\registration-payment-recovery\checkout.blade.php ENDPATH**/ ?>