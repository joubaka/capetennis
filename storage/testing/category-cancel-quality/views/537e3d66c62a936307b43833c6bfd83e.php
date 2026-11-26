<?php $__env->startSection('title', 'Review late team payment'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y">
  <a href="<?php echo e(route('backend.late-team-payments.index', $event)); ?>">Back to late payments</a>
  <h3 class="mt-3">Review registration confirmation</h3>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card"><div class="card-body">
    <h5><?php echo e($order->player?->name); ?> <?php echo e($order->player?->surname); ?></h5>
    <p><?php echo e($event->name); ?> · <?php echo e($order->team?->name); ?> · Order #<?php echo e($order->id); ?></p>
    <p>Verified receipt: <strong><?php echo e($result['pf_payment_id']); ?></strong><br>Amount received: <strong>R<?php echo e(number_format($result['amount'], 2)); ?></strong></p>
    <p>Reason: <?php echo e($data['reason']); ?></p>
    <p>All payment, payer, roster and invitation checks passed. Confirming uses the existing receipt and marks this player registered. No new charge, wallet debit or confirmation email is made.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($result['successor_order_ids'])): ?>
      <p class="alert alert-warning">The following unpaid replacement checkouts will close: <?php echo e(implode(', ', $result['successor_order_ids'])); ?>.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('backend.late-team-payments.apply', [$event, $order])); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="preview_token" value="<?php echo e($token); ?>">
      <label class="d-flex gap-2 align-items-start mb-3" style="min-height:44px"><input class="form-check-input flex-shrink-0" type="checkbox" name="confirm" value="1" required> I reviewed this payment and want to confirm this registration.</label>
      <button class="btn btn-success" style="min-height:44px" type="submit">Reconcile payment and confirm registration</button>
    </form>
  </div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\payfast-handoffs\late-team-preview.blade.php ENDPATH**/ ?>