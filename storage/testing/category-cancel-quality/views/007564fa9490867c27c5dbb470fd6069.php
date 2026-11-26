

<?php $__env->startSection('title', 'Choose Refund Option'); ?>

<?php $__env->startSection('content'); ?>


<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="toast align-items-center text-bg-success border-0 show"
         role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <?php echo e(session('success')); ?>

        </div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="toast align-items-center text-bg-danger border-0 show"
         role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <?php echo e($errors->first()); ?>

        </div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>



<div class="container mt-4" style="max-width: 520px;">

  <div class="card shadow-sm">
    <div class="card-header">
      <h5 class="mb-0">
        Refund for <?php echo e($registration->display_name); ?>

      </h5>
    </div>

    <div class="card-body">

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingBank): ?>
        <div class="alert alert-warning">
          Your bank refund is pending administrator review. This does not confirm that money has been returned.
          You can correct the bank details below. Saving them will not retry the payment.
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      
      <div class="mb-3">
        <p class="mb-1">
          <strong>Total Paid:</strong> R<?php echo e(number_format($gross, 2)); ?>

        </p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($walletPaid ?? 0) > 0): ?>
          <p class="mb-1 text-muted small">
            <i class="ti ti-wallet me-1"></i> Wallet: R<?php echo e(number_format($walletPaid, 2)); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($payfastGross ?? 0) > 0): ?>
              &nbsp;|&nbsp;
              <i class="ti ti-credit-card me-1"></i> PayFast: R<?php echo e(number_format($payfastGross, 2)); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="mb-1 text-muted">
          <strong>Withdrawal fee (10%):</strong> R<?php echo e(number_format($fee, 2)); ?>

        </p>
        <p class="fs-5 mt-2">
          You will receive:
          <span class="text-success fw-bold">
            R<?php echo e(number_format($net, 2)); ?>

          </span>
        </p>
      </div>

      <hr>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$registration->isRefundPending() && !$registration->isRefundCompleted()): ?>
      <form method="POST"
            action="<?php echo e(route('registrations.refund.request', $registration)); ?>"
            class="mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="method" value="wallet">

        <button type="submit"
                class="btn btn-success w-100"
                onclick="this.disabled=true; this.form.submit();">
          <i class="ti ti-wallet me-1"></i>
          Refund to Wallet (Instant)
        </button>

        <small class="text-muted d-block mt-1">
          Wallet refunds are processed immediately. Category differences already returned to your wallet are excluded from this refund.
        </small>
      </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($pendingBank || ($payfastGross ?? 0) > 0) && !$registration->isRefundCompleted()): ?>
      
      <button type="button"
              class="btn btn-outline-primary w-100"
              data-bs-toggle="modal"
              data-bs-target="#bankRefundModal">
        <i class="ti ti-building-bank me-1"></i>
        <?php echo e($pendingBank ? 'Correct Bank Details' : 'Refund PayFast Portion'); ?>

      </button>

      <small class="text-muted d-block mt-1">
        Bank details must match the account used for the original payment. If the automatic refund fails, an administrator must review the request.
      </small>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>
</div>



<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($pendingBank || ($payfastGross ?? 0) > 0) && !$registration->isRefundCompleted()): ?>
<div class="modal fade" id="bankRefundModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form method="POST"
            action="<?php echo e(route('registrations.refund.request', $registration)); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="method" value="bank">

        <div class="modal-header">
          <h5 class="modal-title">Bank Refund Details</h5>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <p class="text-muted">Use the original payer's bank account. PayFast may reject details that do not match the original transaction.</p>

          <div class="mb-3">
            <label class="form-label">Account Holder Name</label>
            <input type="text"
                   name="account_name"
                   value="<?php echo e(old('account_name', $registration->refund_account_name)); ?>"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Bank Name</label>
            <select name="bank_name" class="form-select" required>
              <option value="">Select bank</option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bankNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>" <?php if(old('bank_name', $registration->refund_bank_name) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Number</label>
            <input type="text"
                   name="account_number"
                   value="<?php echo e(old('account_number', $registration->refund_account_number)); ?>"
                   class="form-control"
                   inputmode="numeric"
                   maxlength="12"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Branch Code</label>
            <input type="text"
                   name="branch_code"
                   value="<?php echo e(old('branch_code', $registration->refund_branch_code)); ?>"
                   class="form-control"
                   inputmode="numeric"
                   maxlength="6"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Type</label>
            <select name="account_type"
                    class="form-select"
                    required>
              <option value="">Select</option>
              <option value="current" <?php if(old('account_type', $registration->refund_account_type) === 'current'): echo 'selected'; endif; ?>>Current / Cheque</option>
              <option value="savings" <?php if(old('account_type', $registration->refund_account_type) === 'savings'): echo 'selected'; endif; ?>>Savings</option>
            </select>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>

          <button type="submit"
                  class="btn btn-primary"
                  onclick="this.disabled=true; this.form.submit();">
            <?php echo e($pendingBank ? 'Save Corrected Bank Details' : 'Submit Refund Request'); ?>

          </button>
        </div>

      </form>

    </div>
  </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\registrations\choose-refund.blade.php ENDPATH**/ ?>