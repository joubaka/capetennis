

<?php $__env->startSection('title', 'Super Admin â€“ Financial Dashboard'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<style>
  .finance-amount { white-space: nowrap; font-variant-numeric: tabular-nums; }
  #financeTable .finance-event { min-width: 230px; }
  #financeTable .finance-date { white-space: nowrap; }
  .balance-positive { color: #28a745; font-weight: 600; }
  .balance-negative { color: #dc3545; font-weight: 600; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<a class="btn btn-outline-primary mb-3" href="<?php echo e(route('superadmin.payout-requests')); ?>">Review event payout requests</a>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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

  <?php
    $category = request('category') === 'clothing' ? 'clothing' : 'registration';
    $isClothing = $category === 'clothing';
    $summaryColumns = ['received' => 'Received', 'net' => $isClothing ? 'Net income' : 'Available to convenors', 'paid_out' => $isClothing ? 'Paid out' : 'Paid to convenors', 'balance' => $isClothing ? 'Balance' : 'Owed to convenors'];
    $columns = $summaryColumns;
    unset($columns['balance']);
    if ($isClothing) $columns['recoveries'] = 'Recoveries';
    $columns['balance'] = $summaryColumns['balance'];
  ?>
  <nav class="nav nav-pills gap-2 mb-3" aria-label="Finance category">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['registration' => 'Registrations', 'clothing' => 'Clothing']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a class="nav-link <?php echo e($category === $key ? 'active' : ''); ?>" <?php echo e($category === $key ? 'aria-current=page' : ''); ?>

         href="<?php echo e(request()->fullUrlWithQuery(['category' => $key])); ?>"><?php echo e($label); ?></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </nav>
  <div class="row g-3 mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $summaryColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted"><?php echo e($isClothing ? 'Clothing' : 'Registration'); ?> <?php echo e(strtolower($label)); ?></small>
          <h5 class="finance-amount mb-1 <?php echo e($financeSummary[$category.'_'.$key] < 0 ? 'text-danger' : ''); ?>">R <?php echo e(number_format($financeSummary[$category.'_'.$key], 2)); ?></h5>
          <small class="text-muted">FY <?php echo e($currentFY); ?></small>
        </div></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <p class="small text-muted mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isClothing): ?>
      Net income includes fees and completed refunds. Pending refunds remain liabilities.
      Balance includes recorded clothing payouts and recoveries. Recoveries: <span class="finance-amount">R <?php echo e(number_format($financeSummary['clothing_recoveries'], 2)); ?></span>.
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($financeSummary['clothing_offsets'] != 0): ?>
        Net regional offsets: <span class="finance-amount">R <?php echo e(number_format($financeSummary['clothing_offsets'], 2)); ?></span>.
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      Open an event for the regional breakdown.
    <?php else: ?>
      Owed to convenors = registration receipts less PayFast fees, Cape Tennis fees, completed refunds and recorded payouts.
      Zero means fully paid; a negative amount means payouts exceed available funds.
      Payments awaiting payer reconciliation are excluded.
      Pending refunds remain separate liabilities.
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </p>
  <div class="card">
    <div class="card-header"><h5 class="mb-0"><?php echo e($isClothing ? 'Clothing' : 'Registrations'); ?> by event &mdash; FY <?php echo e($currentFY); ?></h5></div>
    <div class="table-responsive">
      <table id="financeTable" class="table table-hover mb-0">
        <thead class="table-light"><tr>
          <th>Event</th><th>Date</th>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th class="text-end"><?php echo e($label); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isClothing)): ?><th class="text-center">Entries</th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <th></th>
        </tr></thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $financeByEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="finance-event"><a href="<?php echo e(route('superadmin.finances.event', $row['event'])); ?>" class="fw-semibold text-primary"><?php echo e($row['event']->name); ?></a></td>
              <td class="finance-date" data-order="<?php echo e($row['event']->start_date?->format('Y-m-d') ?? ''); ?>"><small class="text-muted"><?php echo e($row['event']->start_date?->format('d M Y') ?? '—'); ?></small></td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($amount = $row[$category.'_'.$key]); ?>
                <td class="text-end finance-amount <?php echo e($key === 'balance' ? ($amount < 0 ? 'balance-negative' : 'balance-positive') : ''); ?>" data-order="<?php echo e(number_format($amount, 2, '.', '')); ?>">R <?php echo e(number_format($amount, 2)); ?></td>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isClothing)): ?><td class="text-center" data-order="<?php echo e($row['total_entries']); ?>"><?php echo e(number_format($row['total_entries'])); ?></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <td><a href="<?php echo e(route('superadmin.finances.event', $row['event'])); ?>" class="btn btn-icon btn-sm btn-outline-warning" title="View transactions and payouts" aria-label="View transactions and payouts"><i class="ti ti-report-money"></i></a></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold"><tr>
          <td colspan="2">Totals</td>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td class="text-end finance-amount">R <?php echo e(number_format($financeSummary[$category.'_'.$key], 2)); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isClothing)): ?><td class="text-center"><?php echo e(number_format($financeSummary['total_entries'])); ?></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <td></td>
        </tr></tfoot>
      </table>
    </div>
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
    order: [[1, 'desc']],  // Latest event dates first.
    columnDefs: [{ orderable: false, targets: [-1] }, { type: 'num', targets: [2, 3, 4, 5, 6] }],
    autoWidth: false,
    language: { emptyTable: 'No events found for this financial year.' },
    pageLength: 25,
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\superadmin\finances.blade.php ENDPATH**/ ?>