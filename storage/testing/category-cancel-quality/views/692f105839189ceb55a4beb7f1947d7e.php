<?php $__env->startSection('title', 'Registration payment'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="card mx-auto" style="max-width: 640px">
    <div class="card-body p-4">
      <h3>Complete your registration payment</h3>
      <p class="text-muted">Your registration remains reserved. Pay the exact outstanding amount securely through PayFast.</p>
      <div class="alert alert-info"><strong>Amount due:</strong> R <?php echo e(number_format((float) $recovery->amount_due, 2)); ?></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->payfast_handed_off_at): ?>
        <div class="alert alert-info">Your payment has been sent to PayFast and is awaiting confirmation.</div>
      <?php else: ?>
        <?php echo $__env->make('frontend.partials.registration-terms', ['refundTermsEvent' => $order->items->first()?->category_event?->event], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <form method="POST" action="<?php echo e($paymentUrl); ?>">
          <?php echo csrf_field(); ?>
          <?php echo $__env->make('frontend.partials.registration-terms-acceptance', ['termsCheckboxId' => 'recovery-registration-terms'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <button type="submit" class="btn btn-danger btn-lg w-100">Continue to PayFast</button>
        </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\registration-payment-recovery\checkout.blade.php ENDPATH**/ ?>