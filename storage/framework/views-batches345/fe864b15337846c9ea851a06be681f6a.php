

<?php $__env->startSection('title', 'System Check'); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .check-row { transition: background 0.15s; }
  .check-row:hover { background: rgba(0,0,0,.02); }
  .badge-ok      { background-color: #28a745; color: #fff; }
  .badge-warning { background-color: #fd7e14; color: #fff; }
  .badge-fail    { background-color: #dc3545; color: #fff; }
  .check-detail  { font-size: .875rem; color: #555; word-break: break-word; }
  .summary-ok      { border-left: 4px solid #28a745; }
  .summary-warning { border-left: 4px solid #fd7e14; }
  .summary-fail    { border-left: 4px solid #dc3545; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php
  $summaryClass = 'summary-ok';
  $summaryIcon  = '✅';
  $summaryText  = 'All checks passed';

  if ($failCount > 0) {
    $summaryClass = 'summary-fail';
    $summaryIcon  = '❌';
    $summaryText  = "{$failCount} failure(s)";
    if ($warningCount > 0) $summaryText .= ", {$warningCount} warning(s)";
  } elseif ($warningCount > 0) {
    $summaryClass = 'summary-warning';
    $summaryIcon  = '⚠️';
    $summaryText  = "{$warningCount} warning(s)";
  }
?>

<div class="container-xxl flex-grow-1 container-p-y">

  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="ti ti-stethoscope me-2"></i>System Check</h4>
      <small class="text-muted">Ran at <?php echo e(now()->format('Y-m-d H:i:s')); ?> (server time)</small>
    </div>
    <a href="<?php echo e(route('super-admin.system-check')); ?>" class="btn btn-primary">
      <i class="ti ti-refresh me-1"></i> Re-run Checks
    </a>
  </div>

  
  <div class="card mb-4 ps-3 <?php echo e($summaryClass); ?>">
    <div class="card-body py-3">
      <span class="fs-5 fw-semibold"><?php echo e($summaryIcon); ?> <?php echo e($summaryText); ?></span>
      <span class="text-muted ms-2">— <?php echo e(count($checks)); ?> checks total</span>
    </div>
  </div>

  
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-checklist me-1"></i> Check Results</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:220px">Check</th>
            <th style="width:100px">Status</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $checks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr class="check-row">
            <td class="fw-semibold align-middle"><?php echo e($check['name']); ?></td>
            <td class="align-middle">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($check['status'] === 'ok'): ?>
                <span class="badge badge-ok px-2 py-1">✅ OK</span>
              <?php elseif($check['status'] === 'warning'): ?>
                <span class="badge badge-warning px-2 py-1">⚠️ Warning</span>
              <?php else: ?>
                <span class="badge badge-fail px-2 py-1">❌ Fail</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td class="align-middle check-detail"><?php echo e($check['detail']); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  
  <?php
    $pendingRefundCheck = collect($checks)->firstWhere('name', 'Pending Bank Refunds');
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRefundCheck && $pendingRefundCheck['status'] !== 'ok'): ?>
    <div class="alert alert-warning mt-4">
      <i class="ti ti-alert-triangle me-1"></i>
      There are pending bank refunds.
      <a href="<?php echo e(route('admin.refunds.bank.index')); ?>" class="alert-link ms-1">Go to Bank Refunds →</a>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\system\check.blade.php ENDPATH**/ ?>