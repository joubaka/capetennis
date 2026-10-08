

<?php $__env->startSection('title', 'Admin Refund – ' . $event->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'entries',
    'eventWorkspaceIcon' => 'ti-cash-banknote',
    'eventWorkspaceSubtitle' => 'Issue an administrative refund',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="card" style="max-width:600px;">
    <div class="card-header bg-warning text-dark d-flex align-items-center gap-2">
      <i class="ti ti-cash-banknote fs-5"></i>
      <strong>Issue Refund</strong>
    </div>

    <div class="card-body">

      

      
      <dl class="row mb-4">
        <dt class="col-sm-4">Event</dt>
        <dd class="col-sm-8"><?php echo e($event->name); ?></dd>

        <dt class="col-sm-4">Category</dt>
        <dd class="col-sm-8"><?php echo e($category); ?></dd>

        <dt class="col-sm-4">Player(s)</dt>
        <dd class="col-sm-8">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php echo e(trim($p->name . ' ' . $p->surname)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->last)): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            —
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </dd>

        <dt class="col-sm-4">Amount Paid</dt>
        <dd class="col-sm-8 fw-bold text-success">R<?php echo e(number_format($gross, 2)); ?></dd>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($walletPaid > 0): ?>
          <dt class="col-sm-4 text-muted">‌ ↳ Wallet</dt>
          <dd class="col-sm-8 text-muted">R<?php echo e(number_format($walletPaid, 2)); ?></dd>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfastGross > 0): ?>
          <dt class="col-sm-4 text-muted">‌ ↳ PayFast</dt>
          <dd class="col-sm-8 text-muted">
            R<?php echo e(number_format($payfastGross, 2)); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pfPaymentId): ?>
              <code class="ms-1"><?php echo e($pfPaymentId); ?></code>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </dd>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <dt class="col-sm-4">Refund Status</dt>
        <dd class="col-sm-8">
          <span class="badge bg-secondary"><?php echo e($registration->refund_status ?? 'not_refunded'); ?></span>
        </dd>
      </dl>

      <hr>

      
      <form method="POST"
            action="<?php echo e(route('admin.registration.refund.store', [$event, $registration])); ?>"
            onsubmit="return confirm('Issue this refund now?');">
        <?php echo csrf_field(); ?>

        <p class="fw-semibold mb-3">Choose a refund method:</p>

        <div class="mb-3">

          
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="method" id="method_wallet" value="wallet"
                   <?php echo e(old('method') === 'wallet' ? 'checked' : ''); ?> required>
            <label class="form-check-label" for="method_wallet">
              <i class="ti ti-wallet me-1 text-primary"></i>
              <strong>Wallet</strong> — credit R<?php echo e(number_format($gross, 2)); ?> to
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payer): ?>
                <strong><?php echo e(trim($payer->name . ' ' . $payer->surname)); ?></strong>'s Cape Tennis wallet
              <?php else: ?>
                the payer's Cape Tennis wallet
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </label>
          </div>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pfPaymentId): ?>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="method" id="method_payfast" value="payfast"
                     <?php echo e(old('method') === 'payfast' ? 'checked' : ''); ?>>
              <label class="form-check-label" for="method_payfast">
                <i class="ti ti-credit-card me-1 text-success"></i>
                <strong>PayFast</strong> — refund R<?php echo e(number_format($payfastGross, 2)); ?> back to the original payment card/account
                <small class="text-muted d-block ms-4">PayFast ID: <code><?php echo e($pfPaymentId); ?></code></small>
              </label>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="method" id="method_none" value="none"
                   <?php echo e(old('method') === 'none' ? 'checked' : ''); ?>>
            <label class="form-check-label" for="method_none">
              <i class="ti ti-ban me-1 text-danger"></i>
              <strong>No Refund</strong> — record withdrawal only, no money returned
            </label>
          </div>

          
          <div class="mt-3" id="no-refund-reason-block" style="display:none;">
            <label for="reason" class="form-label fw-semibold">Reason for no refund <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text"
                   id="reason"
                   name="reason"
                   class="form-control"
                   maxlength="255"
                   placeholder="e.g. Late withdrawal, post-deadline, event already started"
                   value="<?php echo e(old('reason')); ?>">
          </div>

        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-warning">
            <i class="ti ti-check me-1"></i> Process Refund
          </button>
          <button type="button" class="btn btn-outline-secondary" id="cancel-withdraw-btn">
            <i class="ti ti-x me-1"></i> Cancel
          </button>
        </div>

      </form>

      <form method="POST"
            id="cancel-withdraw-form"
            action="<?php echo e(route('admin.registration.refund.cancel', [$event, $registration])); ?>"
            style="display:none;">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
      </form>

    </div>
  </div>

</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
document.getElementById('cancel-withdraw-btn').addEventListener('click', function () {
  if (confirm('Cancel this withdrawal? The player will be restored to active status.')) {
    document.getElementById('cancel-withdraw-form').submit();
  }
});

// Show/hide reason field based on "No Refund" selection
document.querySelectorAll('input[name="method"]').forEach(function (radio) {
  radio.addEventListener('change', function () {
    var block = document.getElementById('no-refund-reason-block');
    block.style.display = this.value === 'none' ? 'block' : 'none';
  });
});

// Show on page load if "none" already selected (e.g. validation error)
(function () {
  var selected = document.querySelector('input[name="method"]:checked');
  if (selected && selected.value === 'none') {
    document.getElementById('no-refund-reason-block').style.display = 'block';
  }
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\admin-refund.blade.php ENDPATH**/ ?>