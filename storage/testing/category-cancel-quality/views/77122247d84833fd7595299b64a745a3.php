<?php $__env->startSection('title', 'Payment status'); ?>
<?php $__env->startSection('hidePwaInstall', 'true'); ?>
<?php $__env->startSection('content'); ?>
<?php
  $retryAt = $order->payfast_handed_off_at->copy()->addMinutes(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES);
  $secondsUntilCheck = (int) ceil(now()->diffInSeconds($retryAt, false));
?>
<div class="container-xxl container-p-y">
  <div class="card mx-auto" style="max-width: 720px">
    <div class="card-body">
      <h3>Payment pending</h3>
      <p>We’re waiting for a confirmed payment for registration <strong>#<?php echo e($order->id); ?></strong>.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($secondsUntilCheck > 0): ?>
        <p>We’ll check automatically <?php echo e(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES); ?> minutes after you started payment. If the checkout expires, we’ll take you back to the event so you can pay again.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p>If you were charged or still can’t pay after <?php echo e(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES); ?> minutes, contact Cape Tennis with your order number.</p>
      <div class="d-flex flex-wrap gap-2">
        <a id="check-payment-status" class="btn btn-primary" style="min-height:44px" href="<?php echo e(route('registration.checkout', $order)); ?>">Check payment status</a>
        <a class="btn btn-outline-secondary" style="min-height:44px" href="mailto:support@capetennis.co.za?subject=Registration%20order%20<?php echo e($order->id); ?>%20payment%20assistance">Contact Cape Tennis</a>
      </div>
    </div>
  </div>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($secondsUntilCheck > 0): ?>
  <script>
    window.setTimeout(function () {
      window.location.replace(document.getElementById('check-payment-status').href);
    }, <?php echo json_encode($secondsUntilCheck * 1000, 15, 512) ?>);
  </script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\payfast\pending.blade.php ENDPATH**/ ?>