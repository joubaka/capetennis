<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingReceipts['count'] > 0): ?>
      <div class="px-3 py-3 border-top">
        <h6 class="mb-1">Clothing funds by region</h6>
        <p class="text-muted small mb-0">Regional funds only; excluded from registration income and profit. Net clothing funds deduct recorded fees and completed refunds, before payouts or recoveries. Pending refunds remain a separate liability. See Clothing payouts and recovery for the amount still payable or to recover.</p>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0" aria-label="Clothing payable by region">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Region</th>
              <th class="text-center">Paid orders</th>
              <th class="text-end">Clothing received</th>
              <th class="text-end">Recorded PayFast fee</th>
              <th class="text-end">Completed refunds (net)</th>
                <th class="text-end">Pending refunds (net)</th>
                <th class="text-end pe-3">Net clothing funds before payouts</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingReceipts['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td class="ps-3"><?php echo e($region); ?></td>
                <td class="text-center"><?php echo e($group['rows']->where('type', 'clothing_payment')->count()); ?></td>
                <td class="text-end text-nowrap">R <?php echo e(number_format($group['totals']['gross'], 2)); ?></td>
                <td class="text-end text-nowrap">&minus;R <?php echo e(number_format(abs($group['totals']['fees']), 2)); ?></td>
                <td class="text-end text-nowrap">&minus;R <?php echo e(number_format($group['totals']['completed_refund_net'], 2)); ?></td>
                  <td class="text-end text-nowrap">R <?php echo e(number_format($group['totals']['pending_refund_net'], 2)); ?></td>
                  <td class="text-end text-nowrap fw-semibold pe-3">R <?php echo e(number_format($group['totals']['net'], 2)); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
          <tfoot class="table-light fw-semibold">
            <tr>
              <td class="ps-3">Total clothing</td>
              <td class="text-center"><?php echo e($clothingReceipts['groups']->sum(fn ($group) => $group['rows']->where('type', 'clothing_payment')->count())); ?></td>
              <td class="text-end text-nowrap">R <?php echo e(number_format($clothingReceipts['totals']['gross'], 2)); ?></td>
              <td class="text-end text-nowrap">&minus;R <?php echo e(number_format(abs($clothingReceipts['totals']['fees']), 2)); ?></td>
              <td class="text-end text-nowrap">&minus;R <?php echo e(number_format($clothingReceipts['totals']['completed_refund_net'], 2)); ?></td>
                <td class="text-end text-nowrap">R <?php echo e(number_format($clothingReceipts['totals']['pending_refund_net'], 2)); ?></td>
                <td class="text-end text-nowrap pe-3">R <?php echo e(number_format($clothingReceipts['totals']['net'], 2)); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingReceipts['groups']->has('Region not recorded')): ?>
        <p class="small text-warning px-3 py-2 mb-0">Confirm the region for unassigned receipts before paying a convenor.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
  <div class="p-3 text-muted">No clothing receipts or refunds recorded for this event.</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\_clothing_finance.blade.php ENDPATH**/ ?>