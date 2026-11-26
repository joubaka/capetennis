<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($entrySummaryDetailsOnly ?? false)): ?>
<h2 <?php if (! ($entrySummaryPdf ?? false)): ?> class="h5" <?php endif; ?>>Entry and withdrawal summary</h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entrySummaryPdf ?? false): ?>
<table class="entry-summary-totals"><tbody><tr>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['recorded', 'confirmed', 'unpaid', 'withdrawn']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<td><span class="muted"><?php echo e($entrySummary['columns'][$field]); ?></span><br><strong style="font-size:17px"><?php echo e(number_format($entrySummary['totals'][$field])); ?></strong></td>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tr></tbody></table>
<?php else: ?>
<div class="row g-2 mb-3">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['recorded', 'confirmed', 'unpaid', 'withdrawn']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted"><?php echo e($entrySummary['columns'][$field]); ?></div><strong class="fs-5"><?php echo e(number_format($entrySummary['totals'][$field])); ?></strong></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<p class="small text-muted d-md-none">Swipe the division tables to see all columns.</p>
<a class="btn btn-outline-secondary btn-sm mb-3" href="<?php echo e(route('admin.events.finances', ['event' => $event, 'withdrawals' => 1])); ?>">View withdrawal records</a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($entrySummaryTotalsOnly ?? false)): ?>
<p class="<?php echo e(($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small'); ?>">Counts are entry records, not unique players. Recorded entries include active confirmed, active unpaid and withdrawals. Confirmed includes free and administrator-confirmed entries. Unfinished checkouts and removed unpaid attempts are excluded; archived withdrawals remain included.</p>
<p class="<?php echo e(($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small'); ?>">Entries in payment records measure the financial ledger, including payment history. This participation summary measures current entries and recorded withdrawals. The two counts can differ and are not a reconciliation.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entrySummary['team']): ?>
<p class="<?php echo e(($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small'); ?>">Team counts use current roster places and withdrawals of confirmed team-player registrations. Unpaid roster selections are shown separately; cancelled or expired unpaid orders are not withdrawals.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
  ['recorded', 'confirmed', 'unpaid', 'withdrawn'],
  ['withdrawn', 'completed', 'pending', 'waived', 'no_refund'],
]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fields): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
  $isWithdrawalSummary = $loop->last;
  $summaryDivisions = collect($entrySummary['divisions'])->filter(fn ($division) => $isWithdrawalSummary ? $division['withdrawn'] > 0 : (!($entrySummaryPdf ?? false) || $division['recorded'] > 0));
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?><h3 <?php if (! ($entrySummaryPdf ?? false)): ?> class="h6 mt-3" <?php endif; ?>>Withdrawal refund status</h3><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div <?php if (! ($entrySummaryPdf ?? false)): ?> class="table-responsive" <?php endif; ?>>
<table class="<?php echo e(($entrySummaryPdf ?? false) ? 'entry-summary-table' : 'table table-sm align-middle mb-2'); ?>">
  <thead><tr><th>Division</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th class="<?php echo e(($entrySummaryPdf ?? false) ? 'amount' : 'text-end'); ?>"><?php echo e($entrySummary['columns'][$field]); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
  <tbody>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $summaryDivisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr><td><?php echo e($division['name']); ?></td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td class="<?php echo e(($entrySummaryPdf ?? false) ? 'amount' : 'text-end'); ?>"><?php echo e(number_format($division[$field])); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr><td colspan="<?php echo e(count($fields) + 1); ?>"><?php echo e($isWithdrawalSummary ? 'No withdrawals recorded.' : 'No entries recorded.'); ?></td></tr>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <tr class="<?php echo e(($entrySummaryPdf ?? false) ? 'total' : 'fw-bold'); ?>"><td>Event total</td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td class="<?php echo e(($entrySummaryPdf ?? false) ? 'amount' : 'text-end'); ?>"><?php echo e(number_format($entrySummary['totals'][$field])); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
  </tbody>
</table></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\partials\entry-withdrawal-summary.blade.php ENDPATH**/ ?>