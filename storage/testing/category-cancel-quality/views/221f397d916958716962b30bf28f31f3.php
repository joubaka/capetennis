

<?php $__env->startSection('title', 'Bank Refund Details'); ?>

<?php $__env->startSection('content'); ?>
<?php
  $paymentInfo = $registration->paymentInfo();
  $hasPayFastPayment = !empty($paymentInfo['pf_payment_id']);
?>
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h4 class="mb-0">Bank Refund #<?php echo e($registration->id); ?></h4>
    <a href="<?php echo e(route('admin.refunds.bank.index')); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i> Back to Refund List
    </a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo e($errors->first()); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo e(session('success')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Player &amp; Event</h6></div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Player</dt>
        <dd class="col-sm-9"><?php echo e($registration->display_name); ?></dd>

        <dt class="col-sm-3">Event</dt>
        <dd class="col-sm-9"><?php echo e(optional($registration->categoryEvent->event)->name ?? '—'); ?></dd>

        <dt class="col-sm-3">Category</dt>
        <dd class="col-sm-9"><?php echo e(optional($registration->categoryEvent->category)->name ?? '—'); ?></dd>

        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'completed'): ?>
            <span class="badge bg-success">Processed</span>
          <?php elseif($registration->refund_status === 'waived'): ?>
            <span class="badge bg-secondary">Waived without payment</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">Pending</span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </dd>

        <dt class="col-sm-3">Recorded refund amount</dt><dd class="col-sm-9 fw-bold">R<?php echo e(number_format($registration->refund_net, 2)); ?></dd>
        <dt class="col-sm-3">PayFast Transaction</dt>
        <dd class="col-sm-9"><code><?php echo e($paymentInfo['pf_payment_id'] ?? '—'); ?></code></dd>
      </dl>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Refund Amount</h6></div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Gross</dt>
        <dd class="col-sm-9">R<?php echo e(number_format($registration->refund_gross, 2)); ?></dd>

        <dt class="col-sm-3">Fee</dt>
        <dd class="col-sm-9 text-danger">R<?php echo e(number_format($registration->refund_fee, 2)); ?></dd>

        <dt class="col-sm-3">Net</dt>
        <dd class="col-sm-9 fw-bold text-success">R<?php echo e(number_format($registration->refund_net, 2)); ?></dd>

        <dt class="col-sm-3">Withdrawn At</dt>
        <dd class="col-sm-9"><?php echo e(optional($registration->withdrawn_at)->format('Y-m-d H:i') ?? '—'); ?></dd>
      </dl>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0">Banking Details</h6>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user') && $registration->refund_status === 'pending'): ?>
        <button class="btn btn-sm btn-outline-primary" type="button"
                data-bs-toggle="collapse" data-bs-target="#editBankForm">
          <i class="ti ti-pencil me-1"></i>Edit / Enter Details
        </button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$registration->refund_account_name): ?><p class="text-warning">Bank details missing. Enter or review details before payment.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="small text-muted">Saving bank details does not process a refund.</p>
      <dl class="row mb-0">
        <dt class="col-sm-3">Account Name</dt>
        <dd class="col-sm-9"><?php echo e($registration->refund_account_name ?? '—'); ?></dd>

        <dt class="col-sm-3">Bank</dt>
        <dd class="col-sm-9"><?php echo e($registration->refund_bank_name ?? '—'); ?></dd>

        <dt class="col-sm-3">Account Number</dt>
        <dd class="col-sm-9"><?php echo e($registration->refund_account_number ?? '—'); ?></dd>

        <dt class="col-sm-3">Branch Code</dt>
        <dd class="col-sm-9"><?php echo e($registration->refund_branch_code ?? '—'); ?></dd>

        <dt class="col-sm-3">Account Type</dt>
        <dd class="col-sm-9"><?php echo e(ucfirst($registration->refund_account_type ?? '—')); ?></dd>
      </dl>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user') && $registration->refund_status === 'pending'): ?>
        <?php
          $bankNames = [
            'absa'         => 'ABSA',
            'capitec'      => 'Capitec',
            'fnb'          => 'FNB',
            'investec'     => 'Investec',
            'nedbank'      => 'Nedbank',
            'standard'     => 'Standard Bank',
            'african'      => 'African Bank',
            'discovery'    => 'Discovery Bank',
            'sasfin'       => 'Sasfin',
            'tyme'         => 'TymeBank',
            'other'        => 'Other',
          ];
        ?>
        <div class="collapse mt-4 <?php echo e(!$registration->refund_account_name || $errors->hasAny(['refund_account_name', 'refund_bank_name', 'refund_account_number', 'refund_branch_code', 'refund_account_type']) ? 'show' : ''); ?>" id="editBankForm">
          <hr>
          <h6 class="text-muted mb-3"><i class="ti ti-shield-lock me-1"></i>Superadmin — Enter Bank Details on Behalf of User</h6>
          <form method="POST" action="<?php echo e(route('admin.refunds.bank.save-bank-details', $registration)); ?>">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
                <input type="text" name="refund_account_name"
                       class="form-control <?php $__errorArgs = ['refund_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       value="<?php echo e(old('refund_account_name', $registration->refund_account_name)); ?>"
                       placeholder="Name exactly as on bank account" required>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['refund_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Bank <span class="text-danger">*</span></label>
                <select name="refund_bank_name" class="form-select <?php $__errorArgs = ['refund_bank_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                  <option value="">— Select bank —</option>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bankNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($val); ?>" <?php echo e(old('refund_bank_name', $registration->refund_bank_name) === $val ? 'selected' : ''); ?>>
                      <?php echo e($label); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['refund_bank_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                <input type="text" name="refund_account_number"
                       class="form-control <?php $__errorArgs = ['refund_account_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       value="<?php echo e(old('refund_account_number', $registration->refund_account_number)); ?>"
                       placeholder="e.g. 1234567890" required maxlength="30">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['refund_account_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Branch Code <span class="text-danger">*</span></label>
                <input type="text" name="refund_branch_code"
                       class="form-control <?php $__errorArgs = ['refund_branch_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       value="<?php echo e(old('refund_branch_code', $registration->refund_branch_code)); ?>"
                       placeholder="e.g. 632005" required maxlength="20" inputmode="numeric">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['refund_branch_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
                <select name="refund_account_type" class="form-select <?php $__errorArgs = ['refund_account_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                  <option value="">— Select —</option>
                  <option value="current" <?php echo e(old('refund_account_type', $registration->refund_account_type) === 'current' ? 'selected' : ''); ?>>Current</option>
                  <option value="savings" <?php echo e(old('refund_account_type', $registration->refund_account_type) === 'savings' ? 'selected' : ''); ?>>Savings</option>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['refund_account_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="ti ti-device-floppy me-1"></i>Save Bank Details
                </button>
              </div>
            </div>
          </form>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($walletConversionPreview)): ?>
    <section class="card border-primary mb-4"><div class="card-body">
      <h5>Review wallet refund</h5>
      <dl class="row">
        <dt class="col-sm-3">Original payer</dt><dd class="col-sm-9"><?php echo e($walletConversionPreview['payer_name']); ?> (<?php echo e($walletConversionPreview['payer_email']); ?>)</dd>
        <dt class="col-sm-3">Wallet credit</dt><dd class="col-sm-9 fw-bold">R<?php echo e(number_format($walletConversionPreview['amount_cents'] / 100, 2)); ?> · no deduction</dd>
        <dt class="col-sm-3">Original PayFast payment</dt><dd class="col-sm-9"><?php echo e($walletConversionPreview['provider_id']); ?></dd>
        <dt class="col-sm-3">Reason</dt><dd class="col-sm-9"><?php echo e($walletConversionPreview['reason']); ?></dd>
      </dl>
      <p>This credits the original payer's Cape Tennis wallet and closes this pending bank refund. The registration stays withdrawn. No refund is sent to PayFast or a bank.</p>
      <form method="POST" action="<?php echo e(route('admin.refunds.bank.wallet-conversion.complete', [$registration->categoryEvent->event_id, $registration])); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="preview_token" value="<?php echo e($walletConversionPreview['token']); ?>">
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">Credit R<?php echo e(number_format($walletConversionPreview['amount_cents'] / 100, 2)); ?> to <?php echo e($walletConversionPreview['payer_name']); ?>'s wallet</button>
          <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.refunds.bank.show', $registration)); ?>">Cancel review</a></div>
      </form>
    </div></section>
  <?php elseif($registration->refund_status === 'pending' && $registration->refund_method === 'bank' && $registration->payment_method === 'payfast'
    && (float) $registration->refund_fee === 0.0 && (float) $registration->refund_gross === (float) $registration->refund_net): ?>
    <section class="card mb-4"><div class="card-body"><h6>Change this pending bank refund to wallet credit</h6>
      <p>Review a full refund to the original payer's wallet after independently confirming that no external refund was paid or is pending.</p>
      <form method="POST" action="<?php echo e(route('admin.refunds.bank.wallet-conversion.preview', [$registration->categoryEvent->event_id, $registration])); ?>">
        <?php echo csrf_field(); ?>
        <div class="mb-3"><label class="form-label" for="conversion-provider-id">Confirm the original PayFast payment reference</label><input id="conversion-provider-id" class="form-control" name="provider_id" value="<?php echo e(old('provider_id')); ?>" maxlength="120" required autocomplete="off"></div>
        <div class="mb-3"><label class="form-label" for="conversion-reason">Reason and verification note</label><textarea id="conversion-reason" name="reason" class="form-control" minlength="10" maxlength="1000" rows="3" required><?php echo e(old('reason')); ?></textarea></div>
        <div class="form-check mb-3"><input id="conversion-verified" name="verified_not_refunded" value="1" type="checkbox" class="form-check-input" required>
          <label class="form-check-label" for="conversion-verified">I independently verified in PayFast that no refund was paid or is pending for this payment, and confirmed that no bank refund was paid.</label></div>
        <p class="small text-muted">A failed or unavailable PayFast query does not confirm that no refund occurred.</p>
        <button class="btn btn-outline-primary">Review wallet refund</button>
      </form>
    </div></section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPayFastPayment && !isset($walletConversionPreview)): ?>
  <section class="card mb-4"><div class="card-body"><h6>Check provider status</h6><p class="small text-muted">Check PayFast settlement separately from Cape Tennis processing records.</p><a href="<?php echo e(route('admin.refunds.bank.payfast-query', $registration)); ?>" class="btn btn-outline-secondary">Query PayFast Status</a></div></section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'pending' && !isset($walletConversionPreview)): ?>
  <section class="card mb-4"><div class="card-body"><h6><?php echo e($hasPayFastPayment ? 'Submit refund to PayFast' : 'Record a manual bank payment'); ?></h6><p class="small text-muted"><?php echo e($hasPayFastPayment ? 'This submits a refund to the payment provider. The PayFast-funded portion is submitted to the provider; a wallet-funded portion is credited separately. Confirm the details before proceeding.' : 'Use only after you have paid the refund through your bank. This records that payment in Cape Tennis.'); ?></p>
    <form method="POST" action="<?php echo e(route('admin.refunds.bank.complete', $registration)); ?>"
          onsubmit="return confirm(<?php echo e(Js::from($hasPayFastPayment ? 'Submit this refund to PayFast? Confirm the player and refund amount before proceeding.' : 'Confirm you have already paid this bank refund. Record the manual payment?')); ?>);">
      <?php echo csrf_field(); ?>
      <button class="btn <?php echo e($hasPayFastPayment ? 'btn-warning' : 'btn-success'); ?>"><?php echo e($hasPayFastPayment ? 'Submit to PayFast' : 'Record manual payment'); ?></button>
    </form>
  </div></section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\refunds\bank-show.blade.php ENDPATH**/ ?>