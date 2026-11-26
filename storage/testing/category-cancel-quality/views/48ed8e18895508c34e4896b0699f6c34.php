<?php $__env->startSection('title', 'PayFast checkout recovery'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y">
  <a href="<?php echo e(route('backend.payfast-handoffs.events')); ?>">All events</a>
  <h3 class="mt-3"><?php echo e($event->name); ?>: PayFast checkout recovery</h3>
  <div class="alert alert-warning">Check the exact order in PayFast before cancelling checkout. Only cancel an attempt confirmed failed, cancelled, or never created, with no payment received and no pending or payable attempt. A parent returning or reporting no payment is insufficient. Completed payments need reconciliation instead. Cancelling releases the wallet reservation so the parent can start registration again.</div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-3"><div class="card-body">
      <h5>Order #<?php echo e($order->id); ?></h5>
      <p><?php echo e($order->user?->name); ?> · <?php echo e($order->user?->email); ?><br>Sent <?php echo e($order->payfast_handed_off_at->format('d M Y H:i:s')); ?> · PayFast R<?php echo e(number_format($order->payfast_amount_due, 2)); ?> · Wallet reserved R<?php echo e(number_format($order->wallet_reserved, 2)); ?></p>
      <form method="POST" action="<?php echo e(route('backend.payfast-handoffs.release', [$event, $order])); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="handed_off_at" value="<?php echo e($order->payfast_handed_off_at->toISOString()); ?>">
        <label class="form-label" for="evidence-<?php echo e($order->id); ?>">Provider evidence reference</label>
        <input class="form-control mb-3" id="evidence-<?php echo e($order->id); ?>" name="evidence_reference" required minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}" placeholder="PayFast case or reconciliation reference">
        <label class="d-flex gap-2 align-items-start mb-3" style="min-height:44px"><input class="form-check-input flex-shrink-0" type="checkbox" name="provider_attempt_closed" value="1" required> I verified this exact attempt is failed, cancelled, or never created; no payment was received and no pending or payable attempt remains.</label>
        <button class="btn btn-warning" style="min-height:44px" type="submit">Cancel checkout so parent can start again</button>
      </form>
    </div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p>No unresolved registration checkouts for this event.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo e($orders->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\payfast-handoffs\index.blade.php ENDPATH**/ ?>