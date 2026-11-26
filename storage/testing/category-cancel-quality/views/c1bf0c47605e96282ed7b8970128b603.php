<?php $receipt = session('clothing_refund_completed'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($receipt && (int) $receipt['region_id'] === (int) $region->id && (!$clothingEvent || (int) $receipt['event_id'] === (int) $clothingEvent->id)): ?>
<div class="modal fade" id="clothing-refund-completed" tabindex="-1" aria-labelledby="clothing-refund-completed-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="clothing-refund-completed-title">Refund completed</h5></div>
    <div class="modal-body">
      <p class="fs-5"><strong>R<?php echo e(number_format((float) $receipt['net'], 2)); ?></strong> refunded to <?php echo e($receipt['payer'] ?: 'the original payer'); ?> via <?php echo e($receipt['method'] === 'wallet' ? 'wallet credit' : 'PayFast'); ?>.</p>
      <p>Order #<?php echo e($receipt['order_id']); ?> · Refund #<?php echo e($receipt['id']); ?> · Fee: R<?php echo e(number_format((float) $receipt['fee'], 2)); ?></p>
      <ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $receipt['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($line['name']); ?> · <?php echo e($line['size']); ?> · <?php echo e($line['quantity'] > 0 ? $line['quantity'].' units' : 'Amount only'); ?> · R<?php echo e(number_format((float) $line['amount'], 2)); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($receipt['method'] === 'payfast'): ?><p class="small text-muted">PayFast confirmed the refund. Your payer’s bank may take time to reflect it.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const element = document.getElementById('clothing-refund-completed');
  if (element && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(element, { backdrop: 'static', keyboard: false }).show();
});
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\partials\refund-completed.blade.php ENDPATH**/ ?>