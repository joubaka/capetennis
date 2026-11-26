<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Income and expense breakdown</title>
<style>
@page { margin: 30px; } body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #26374a; }
h1 { font-size: 19px; margin-bottom: 5px; } h2 { font-size: 13px; margin-top: 22px; border-bottom: 1px solid #b7d6d0; padding-bottom: 6px; } h2, h3 { page-break-after: avoid; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; } th { background: #edf4f3; text-align: left; } th, td { padding: 7px; border-bottom: 1px solid #e2e7eb; } tr { page-break-inside: avoid; } thead { display: table-header-group; } .amount { text-align: right; white-space: nowrap; } .muted { color: #68798a; } .total { font-weight: bold; } .payee { page-break-inside: avoid; }
.entry-summary-table { table-layout: fixed; }
.entry-summary-table th, .entry-summary-table td { white-space: normal; overflow-wrap: break-word; }
.entry-summary-table th:first-child, .entry-summary-table td:first-child { width: 29%; }
</style></head><body>
<h1><?php echo e($event->name); ?></h1><strong>Income and expense breakdown</strong>
<p class="muted">Generated <?php echo e(now()->timezone('Africa/Johannesburg')->format('d M Y H:i')); ?> SAST · Private financial report</p>
<?php echo $__env->make('backend.event.partials.entry-withdrawal-summary', ['entrySummaryPdf' => true, 'entrySummaryTotalsOnly' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<h2>Registration income and expenses</h2>
<table><tbody>
<tr><td>Registration gross income</td><td class="amount">R <?php echo e(number_format($totalGross, 2)); ?></td></tr>
<tr><td>PayFast fees</td><td class="amount">R <?php echo e(number_format($totalPayfastFees, 2)); ?></td></tr>
<tr><td>Cape Tennis fees</td><td class="amount">R <?php echo e(number_format($totalCapeTennisFees, 2)); ?></td></tr>
<tr><td>Completed registration refund adjustments</td><td class="amount">R <?php echo e(number_format($registrationRefundImpact, 2)); ?></td></tr>
<tr><td>Registration net income (after fees and refunds)</td><td class="amount">R <?php echo e(number_format($netRegistrationIncome, 2)); ?></td></tr>
<tr><td>Operational expenses</td><td class="amount">R <?php echo e(number_format($totalExpenses, 2)); ?></td></tr>
<tr class="total"><td>Registration <?php echo e($netProfit < 0 ? 'loss' : 'profit'); ?></td><td class="amount">R <?php echo e(number_format($netProfit, 2)); ?></td></tr>
<tr><td>Entries in payment records</td><td class="amount"><?php echo e($totalEntries); ?></td></tr>
</tbody></table>
<p class="muted">System fees are already deducted from registration income. Clothing and other event income are kept separate from registration profit.</p>
<?php echo $__env->make('backend.event.partials.entry-withdrawal-summary', ['entrySummaryPdf' => true, 'entrySummaryDetailsOnly' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<h2>Operational expense detail</h2>
<table><thead><tr><th>Expense</th><th>Paid by</th><th class="amount">Amount</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $expenses->reject(fn($expense) => in_array($expense->expense_type, ['payfast', 'cape_tennis_fee'])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><?php echo e($expenseTypes[$expense->expense_type] ?? $expense->expense_type); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->description): ?><br><span class="muted"><?php echo e($expense->description); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td><?php echo e($expense->paidByConvenor?->user?->name ?? $expense->convenor_name ?? '—'); ?></td><td class="amount">R <?php echo e(number_format($expense->calculatedAmount(), 2)); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="3">No operational expenses recorded.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($incomeItems->isNotEmpty()): ?>
<h2>Other event income (separate)</h2><table><thead><tr><th>Income</th><th class="amount">Amount</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $incomeItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($item->label); ?></td><td class="amount">R <?php echo e(number_format($item->calculatedTotal(), 2)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<tr class="total"><td>Total other income</td><td class="amount">R <?php echo e(number_format($totalIncomeItems, 2)); ?></td></tr></tbody></table>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recon->isNotEmpty()): ?><h2>Convenor payout breakdown</h2>
<p class="muted">Projected amounts only. Payments and recoveries must still be recorded when settled. Clothing adjustments do not change registration profit.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php ($projection = ($convenorPayoutProjection['cards'] ?? collect())->get($row['convenor']->id)); ?>
<div class="payee"><h3><?php echo e($row['convenor']->user?->name ?? 'Unknown'); ?></h3><table><tbody>
<tr><td>Expenses paid out</td><td class="amount">R <?php echo e(number_format($row['total_paid'], 2)); ?></td></tr>
<tr><td>Already reimbursed</td><td class="amount">R <?php echo e(number_format($row['reimbursed'], 2)); ?></td></tr>
<tr><td>Expenses before general payments</td><td class="amount">R <?php echo e(number_format($row['owed_back'] - $row['reimbursed'], 2)); ?></td></tr>
<tr><td>Profit share (<?php echo e(number_format($row['profit_share_pct'], 1)); ?>%)</td><td class="amount">R <?php echo e(number_format($row['profit_share_amount'], 2)); ?></td></tr>
<tr><td>General payments already made</td><td class="amount">R <?php echo e(number_format($row['director_paid'] ?? 0, 2)); ?></td></tr>
<tr class="total"><td>Registration subtotal</td><td class="amount">R <?php echo e(number_format($row['final_payout'], 2)); ?></td></tr>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $projection['regions'] ?? collect(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td>Clothing — <?php echo e($region['region_name']); ?> (<?php echo e($region['contribution_cents'] < 0 ? 'proposed recovery' : 'still payable after pending refund reserve'); ?>)</td><td class="amount">R <?php echo e(number_format($region['contribution_cents'] / 100, 2)); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<tr class="total"><td>Projected total (negative means recover)</td><td class="amount">R <?php echo e(number_format($projection ? $projection['combined_cents'] / 100 : $row['final_payout'], 2)); ?></td></tr>
</tbody></table></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($convenorPayoutProjection['unmatched_regions'] ?? collect())->isNotEmpty()): ?>
<h2>Regional clothing kept separate</h2><table><thead><tr><th>Region</th><th>Note</th><th class="amount">Projected amount</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenorPayoutProjection['unmatched_regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($region['region_name']); ?></td><td><?php echo e($region['reason']); ?></td><td class="amount">R <?php echo e(number_format($region['projected_cents'] / 100, 2)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueEntrySummary->isNotEmpty()): ?><h2>Venue entry summary</h2><table><thead><tr><th>Venue</th><th class="amount">Entries</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueEntrySummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($venue->name); ?></td><td class="amount"><?php echo e($venue->entry_count); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\pdf\finance-breakdown.blade.php ENDPATH**/ ?>