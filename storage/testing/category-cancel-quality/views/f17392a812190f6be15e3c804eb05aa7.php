

<?php $__env->startSection('title', 'Wallet Details'); ?>

<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="container">
  <!-- Back button -->
  <div class="mb-3">
    <a href="<?php echo e(URL::previous()); ?>" class="btn btn-outline-primary">
      <i class="ti ti-arrow-left"></i> Back
    </a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('wallet_success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="ti ti-check-circle me-1"></i><?php echo e(session('wallet_success')); ?>

      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <!-- Wallet Summary Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><?php echo e($user->name); ?>'s Wallet</h5>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="badge bg-success fs-6 p-2">
          Balance: R<?php echo e(number_format($wallet->balance, 2)); ?>

        </span>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
        <button type="button" class="btn btn-primary btn-sm btn-wallet-add-tx" data-super-admin-only
                data-user-id="<?php echo e($user->id); ?>"
                data-user-name="<?php echo e($user->name); ?>"
                data-wallet-balance="R<?php echo e(number_format($wallet->balance, 2)); ?>">
          <i class="ti ti-plus"></i> Add Transaction
        </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <p class="mb-0 text-muted">Recent transactions are shown one page at a time. The balance includes the complete wallet ledger. Existing entries are permanent; record a new credit or debit with a reference when an adjustment is needed.</p>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="card shadow-sm">
    <div class="card-header bg-light">
      <h6 class="mb-0">Transaction History</h6>
    </div>

    <p class="small text-muted px-3 mt-2 mb-2 d-sm-none">Scroll the table sideways to see all transaction details.</p>
    <div class="table-responsive">
      <table class="table table-hover table-striped table-bordered mb-0" style="min-width:640px">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Reference</th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td class="text-nowrap"><small class="text-muted"><?php echo e($tx->id); ?></small></td>
              <td class="text-nowrap"><?php echo e($tx->created_at->format('d M Y H:i')); ?></td>
              <td>
                <span class="badge <?php echo e($tx->type === 'credit' ? 'bg-success' : 'bg-danger'); ?>">
                  <?php echo e(ucfirst($tx->type)); ?>

                </span>
              </td>
              <td class="fw-bold text-nowrap <?php echo e($tx->type === 'credit' ? 'text-success' : 'text-danger'); ?>">
                R<?php echo e(number_format($tx->amount, 2)); ?>

              </td>
              <td><?php echo e($tx->meta['reference'] ?? '-'); ?></td>
              <td><small class="text-muted"><?php echo e($tx->source_type ?? '-'); ?></small></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="6" class="text-center text-muted">No transactions found.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3"><?php echo e($transactions->links()); ?></div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>

<div class="modal fade" id="modal-wallet-add-tx" aria-labelledby="wallet-add-title" tabindex="-1" aria-hidden="true" data-super-admin-only>
  <div class="modal-dialog modal-dialog-centered">
    <form id="form-wallet-add-tx" method="POST" action="<?php echo e(route('superadmin.wallets.transaction.store', $user)); ?>" class="modal-content">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="wallet_form" value="add">
      <input type="hidden" name="idempotency_key" value="<?php echo e(old('idempotency_key', (string) \Illuminate\Support\Str::uuid())); ?>">
      <div class="modal-header">
        <h5 id="wallet-add-title" class="modal-title"><i class="ti ti-plus-circle me-1 text-success"></i>Add Transaction</h5>
        <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">
          <i class="ti ti-user me-1"></i><strong><?php echo e($user->name); ?></strong>
          &mdash; Balance: <span class="text-success fw-bold">R<?php echo e(number_format($wallet->balance, 2)); ?></span>
        </p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Type</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="credit" id="tx-type-credit" <?php if(old('type', 'credit') === 'credit'): echo 'checked'; endif; ?>>
              <label class="form-check-label text-success" for="tx-type-credit"><i class="ti ti-arrow-up me-1"></i>Credit</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="debit" id="tx-type-debit" <?php if(old('type') === 'debit'): echo 'checked'; endif; ?>>
              <label class="form-check-label text-danger" for="tx-type-debit"><i class="ti ti-arrow-down me-1"></i>Debit</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="wallet-add-amount">Amount (R)</label>
          <input id="wallet-add-amount" type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00" value="<?php echo e(old('amount')); ?>">
        </div>
        <div class="mb-3">
          <label for="wallet-add-reference" class="form-label">Reference <small class="text-muted">(optional)</small></label>
          <input id="wallet-add-reference" type="text" name="reference" class="form-control" placeholder="e.g. Admin top-up" value="<?php echo e(old('reference')); ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Save Transaction</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
<script>
$(function () {
  var addTxModal  = new bootstrap.Modal(document.getElementById('modal-wallet-add-tx'));

  <?php if($errors->any() && old('wallet_form') === 'add'): ?>
  addTxModal.show();
  <?php endif; ?>

  $('.btn-wallet-add-tx').on('click', function () {
    addTxModal.show();
  });




});
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\wallet\wallet-show.blade.php ENDPATH**/ ?>