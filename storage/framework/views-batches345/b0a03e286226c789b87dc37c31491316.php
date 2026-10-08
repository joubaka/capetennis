<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->isAdminEntry()): ?>
  <?php $adminCollectionPaid = $reg->admin_payment_status === 'paid'; ?>
  <div class="d-flex flex-column align-items-center gap-1" data-admin-payment-note>
    <span class="badge <?php echo e($adminCollectionPaid ? 'bg-success' : 'bg-warning text-dark'); ?>" data-admin-payment-badge>
      Private collection <?php echo e($adminCollectionPaid ? 'noted paid' : 'not marked paid'); ?> (not reconciled)
    </span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->status !== 'withdrawn'): ?>
      <button type="button"
              class="btn btn-xs <?php echo e($adminCollectionPaid ? 'btn-outline-warning' : 'btn-outline-success'); ?> admin-payment-toggle-btn"
              data-url="<?php echo e(route('admin.entry.admin-payment-status', $reg)); ?>"
              data-next-paid="<?php echo e($adminCollectionPaid ? '0' : '1'); ?>">
        <?php echo e($adminCollectionPaid ? 'Mark note unpaid' : 'Mark note paid'); ?>

      </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
<?php else: ?>
  <span class="badge <?php echo e($reg->payment_status_id == 1 ? 'bg-success' : 'bg-warning text-dark'); ?>">
    <?php echo e($reg->payment_status_id == 1 ? 'Paid' : 'Unpaid'); ?>

  </span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\admin-payment-note.blade.php ENDPATH**/ ?>