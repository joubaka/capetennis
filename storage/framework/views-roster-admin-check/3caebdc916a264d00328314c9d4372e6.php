

<?php $__env->startSection('title', 'Super Admin – Financial Dashboard'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<style>
  .balance-positive { color: #28a745; font-weight: 600; }
  .balance-negative { color: #dc3545; font-weight: 600; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => 'Financial dashboard','eyebrow' => 'Administration','subtitle' => 'Financial years, balances and event statements.','icon' => 'ti-report-money']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Financial dashboard','eyebrow' => 'Administration','subtitle' => 'Financial years, balances and event statements.','icon' => 'ti-report-money']); ?>
   <?php $__env->slot('actions', null, []); ?> <a href="<?php echo e(route('backend.superadmin.index')); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
      </a> <?php $__env->endSlot(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>

  
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="text-muted me-1 small fw-semibold">Financial Year:</span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableFYs->reverse(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(request()->fullUrlWithQuery(['fy' => $fy])); ?>"
             class="btn btn-sm <?php echo e($fy === $currentFY ? 'btn-warning' : 'btn-outline-secondary'); ?>">
            <?php echo e($fy); ?>

          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  </div>

  
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-start border-success border-3">
        <div class="card-body">
          <small class="text-muted">Total Received</small>
          <h5 class="text-success">R <?php echo e(number_format($financeSummary['total_gross'], 2)); ?></h5>
          <small class="text-muted">FY <?php echo e($currentFY); ?></small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-primary border-3">
        <div class="card-body">
          <small class="text-muted">Total Net Income</small>
          <h5>R <?php echo e(number_format($financeSummary['total_income'], 2)); ?></h5>
          <small class="text-muted">FY <?php echo e($currentFY); ?></small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-danger border-3">
        <div class="card-body">
          <small class="text-muted">Total Paid Out</small>
          <h5 class="text-danger">R <?php echo e(number_format($financeSummary['total_paid_out'], 2)); ?></h5>
          <small class="text-muted">FY <?php echo e($currentFY); ?></small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-info border-3">
        <div class="card-body">
          <small class="text-muted">Balance (unpaid)</small>
          <h5 class="<?php echo e($financeSummary['balance'] < 0 ? 'text-danger' : 'text-success'); ?>">
            R <?php echo e(number_format($financeSummary['balance'], 2)); ?>

          </h5>
          <small class="text-muted">FY <?php echo e($currentFY); ?></small>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Per-Event Financial Summary &mdash; FY <?php echo e($currentFY); ?></h5>
    </div>
    <div class="table-responsive">
      <table id="financeTable" class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-end">Received</th>
            <th class="text-end">Net Income</th>
            <th class="text-end">Paid Out</th>
            <th class="text-end">Balance</th>
            <th class="text-center">Entries</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $financeByEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $isPast    = $row['event']->start_date && $row['event']->start_date->isPast();
              $noTx      = ! $row['has_transactions'];
              $showAlert = $isPast && $noTx;
            ?>
            <tr>
              <td>
                <a href="<?php echo e(route('superadmin.finances.event', $row['event'])); ?>" class="fw-semibold text-primary">
                  <?php echo e($row['event']->name); ?>

                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAlert): ?>
                  <span class="badge bg-label-secondary ms-1"
                        title="No PayFast transactions found for this past event"
                        aria-label="No PayFast transactions found for this past event">
                    <i class="ti ti-alert-circle me-1"></i>No transactions
                  </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td data-order="<?php echo e($row['event']->start_date ?? '0000-00-00'); ?>">
                <small class="text-muted">
                  <?php echo e($row['event']->start_date ? \Carbon\Carbon::parse($row['event']->start_date)->format('d M Y') : '—'); ?>

                </small>
              </td>
              <td class="text-end text-success" data-order="<?php echo e($row['total_gross']); ?>">R <?php echo e(number_format($row['total_gross'], 2)); ?></td>
              <td class="text-end" data-order="<?php echo e($row['total_income']); ?>">R <?php echo e(number_format($row['total_income'], 2)); ?></td>
              <td class="text-end text-danger" data-order="<?php echo e($row['total_paid_out']); ?>">
                <?php echo e($row['total_paid_out'] > 0 ? 'R ' . number_format($row['total_paid_out'], 2) : '—'); ?>

              </td>
              <td class="text-end <?php echo e($row['balance'] < 0 ? 'balance-negative' : 'balance-positive'); ?>" data-order="<?php echo e($row['balance']); ?>">
                R <?php echo e(number_format($row['balance'], 2)); ?>

              </td>
              <td class="text-center" data-order="<?php echo e($row['total_entries']); ?>"><?php echo e(number_format($row['total_entries'])); ?></td>
              <td>
                <a href="<?php echo e(route('superadmin.finances.event', $row['event'])); ?>"
                   class="btn btn-icon btn-sm btn-outline-warning" title="View Transactions & Payouts">
                  <i class="ti ti-report-money"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-3">No events found for FY <?php echo e($currentFY); ?>.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="2">Totals</td>
            <td class="text-end text-success">R <?php echo e(number_format($financeSummary['total_gross'], 2)); ?></td>
            <td class="text-end">R <?php echo e(number_format($financeSummary['total_income'], 2)); ?></td>
            <td class="text-end text-danger">R <?php echo e(number_format($financeSummary['total_paid_out'], 2)); ?></td>
            <td class="text-end">R <?php echo e(number_format($financeSummary['balance'], 2)); ?></td>
            <td class="text-center"><?php echo e(number_format($financeSummary['total_entries'])); ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(function () {
  $('#financeTable').DataTable({
    order: [[3, 'desc']],  // Net Income DESC — events with actual transactions appear first
    columnDefs: [{ orderable: false, targets: [7] }],
    pageLength: 25,
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\finances.blade.php ENDPATH**/ ?>