<?php $__env->startSection('title', 'Late team payments'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y">
  <a href="<?php echo e(route('admin.events.finances', $event)); ?>">Back to event finances</a>
  <h3 class="mt-3"><?php echo e($event->name); ?>: late team payments</h3>
  <p>These payments were received after checkout closed. Registration stays unconfirmed until an administrator reviews the original receipt and current roster. Do not ask the payer to pay again.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-3"><div class="card-body">
      <h5><?php echo e($order->player?->name); ?> <?php echo e($order->player?->surname); ?></h5>
      <p><?php echo e($order->team?->name); ?> · Order #<?php echo e($order->id); ?> · Original total R<?php echo e(number_format($order->total_amount, 2)); ?></p>
      <form method="POST" action="<?php echo e(route('backend.late-team-payments.preview', [$event, $order])); ?>">
        <?php echo csrf_field(); ?>
        <label class="form-label" for="reason-<?php echo e($order->id); ?>">Reason for reconciliation</label>
        <textarea class="form-control mb-3" id="reason-<?php echo e($order->id); ?>" name="reason" required minlength="10" maxlength="1000" rows="2"><?php echo e(old('reason')); ?></textarea>
        <button class="btn btn-primary" style="min-height:44px" type="submit">Check payment and preview registration</button>
      </form>
    </div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p>No team payments awaiting reconciliation for this event.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo e($orders->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\payfast-handoffs\late-team.blade.php ENDPATH**/ ?>