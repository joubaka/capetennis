

<?php $__env->startSection('title', 'Bank Refund Details'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mt-4">

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Bank Refund #<?php echo e($registration->id); ?></h5>

      <a href="<?php echo e(route('admin.registration.refunds.bank.index')); ?>"
         class="btn btn-sm btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Back to Refund List
      </a>
    </div>

    <div class="card-body">

      <p><strong>Player:</strong> <?php echo e($registration->display_name); ?></p>
      <p><strong>Amount:</strong> R<?php echo e(number_format($registration->refund_net, 2)); ?></p>

      <hr>

      <p><strong>Account Name:</strong> <?php echo e($registration->refund_account_name); ?></p>
      <p><strong>Bank:</strong> <?php echo e($registration->refund_bank_name); ?></p>
      <p><strong>Account Number:</strong> <?php echo e($registration->refund_account_number); ?></p>
      <p><strong>Branch Code:</strong> <?php echo e($registration->refund_branch_code); ?></p>
      <p><strong>Account Type:</strong> <?php echo e(ucfirst($registration->refund_account_type)); ?></p>

      <hr>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'pending'): ?>
      <form method="POST"
            action="<?php echo e(route('admin.registration.refunds.bank.complete', $registration)); ?>">
        <?php echo csrf_field(); ?>
        <button class="btn btn-success"
                onclick="this.disabled=true; this.form.submit();">
          Mark as Completed
        </button>
      </form>
      <?php else: ?>
        <span class="badge bg-success">
          Refund Completed
        </span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\admin\refunds\bank-show.blade.php ENDPATH**/ ?>