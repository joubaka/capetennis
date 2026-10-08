

<?php $__env->startSection('title', 'Bank Refunds'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mt-4">

  
  <div class="card mb-4">
    <div class="card-header bg-warning">
      <h5 class="mb-0">Pending Bank Refunds</h5>
    </div>

    <div class="card-body">

      
      <div class="mb-3">
        <span class="badge bg-info">Registration pending: <?php echo e($pendingRefunds->count() ?? 0); ?></span>
        <span class="badge bg-primary">Team pending: <?php echo e($pendingTeamRefunds->count() ?? 0); ?></span>
      </div>

      
      <form id="bulk-complete-form"
            method="POST"
            action="<?php echo e(route('admin.refunds.bank.bulk-complete')); ?>"
            onsubmit="return confirm('Mark all selected registrations as completed?');">
        <?php echo csrf_field(); ?>
        
      </form>

      <div class="mb-2">
        <button type="submit" form="bulk-complete-form" class="btn btn-sm btn-success">
          <i class="ti ti-checks me-1"></i> Mark Selected as Completed
        </button>
      </div>

      <table class="table table-bordered">
        <thead>
          <tr>
            <th style="width:36px;">
              <input type="checkbox" id="select-all" title="Select all">
            </th>
            <th>ID</th>
            <th>Player</th>
            <th>PayFast ID</th>
            <th>Amount</th>
            <th>Account Name</th>
            <th>Bank</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pendingRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><input type="checkbox" name="registration_ids[]" value="<?php echo e($refund->id); ?>" form="bulk-complete-form" class="reg-checkbox"></td>
            <td>R-REG-<?php echo e($refund->id); ?></td>
            <td><?php echo e($refund->display_name); ?></td>
            <td><code><?php echo e($refund->pf_transaction_id ?? '—'); ?></code></td>
            <td>R<?php echo e(number_format($refund->refund_net, 2)); ?></td>
            <td><?php echo e($refund->refund_account_name); ?></td>
            <td><?php echo e($refund->refund_bank_name); ?></td>
            <td>
              <a href="<?php echo e(route('admin.registration.refunds.bank.show', $refund)); ?>" class="btn btn-sm btn-primary">View</a>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count()): ?>
            <tr>
              <td colspan="7"><strong>Team Refunds</strong></td>
            </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pendingTeamRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td>R-TEAM-<?php echo e($t->id); ?></td>
                <td><?php echo e(optional($t->player)->name ?? 'Player #' . ($t->player_id ?? 'N/A')); ?></td>
                <td><code><?php echo e($t->payfast_pf_payment_id ?? '—'); ?></code></td>
                <td>R<?php echo e(number_format($t->refund_net, 2)); ?></td>
                <td><?php echo e($t->refund_account_name); ?></td>
                <td><?php echo e($t->refund_bank_name); ?></td>
                <td>
                  <form method="POST" action="<?php echo e(route('admin.registration.refunds.bank.complete.team', $t)); ?>" onsubmit="return confirm('Mark this team bank refund as paid?');">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-sm btn-success">Mark Paid</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRefunds->isEmpty()): ?>
              <tr>
                <td colspan="7" class="text-center">No pending refunds</td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>

    </div>
  </div>


  
  <div class="card">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0">Completed Bank Refunds</h5>
    </div>

    <div class="card-body">

      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Player</th>
            <th>Amount</th>
            <th>Completed At</th>
          </tr>
        </thead>

        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $completedRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><?php echo e($refund->id); ?></td>
            <td><?php echo e($refund->display_name); ?></td>
            <td>R<?php echo e(number_format($refund->refund_net, 2)); ?></td>
            <td><?php echo e($refund->refunded_at?->format('d M Y H:i')); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="4" class="text-center">No completed refunds</td>
          </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($completedTeamRefunds) && $completedTeamRefunds->count()): ?>
        <hr>
        <h6 class="mt-3">Completed Team Refunds</h6>
        <table class="table table-bordered mt-2">
          <thead>
            <tr>
              <th>ID</th>
              <th>Player</th>
              <th>Amount</th>
              <th>Completed At</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completedTeamRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td>R-TEAM-<?php echo e($t->id); ?></td>
                <td><?php echo e(optional($t->player)->name ?? 'Player #' . ($t->player_id ?? '')); ?></td>
                <td>R<?php echo e(number_format($t->refund_net, 2)); ?></td>
                <td><?php echo e(optional($t->refunded_at)->format('d M Y H:i')); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
document.getElementById('select-all')?.addEventListener('change', function () {
  document.querySelectorAll('.reg-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\admin\refunds\bank-index.blade.php ENDPATH**/ ?>