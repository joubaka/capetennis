<form method="POST" action="<?php echo e(route('backend.clothing.refunds.review', [$event, $order])); ?>" data-clothing-refund-selection>
  <?php echo csrf_field(); ?>
  <input type="hidden" name="request_token" value="<?php echo e(old('request_token', (string) str()->uuid())); ?>">
  <input type="hidden" name="return_sort" value="<?php echo e(in_array(request('sort'), ['rank', 'name', 'team', 'age_group', 'date_desc', 'date_asc'], true) ? request('sort') : 'rank'); ?>">
  <input type="hidden" name="return_search" value="<?php echo e(request('search', '')); ?>">
  <input type="hidden" name="return_team" value="<?php echo e(request('team', '')); ?>">
  <input type="hidden" name="return_all_events" value="<?php echo e(request()->boolean('all_events') ? '1' : '0'); ?>">
  <h5>Select items to refund</h5>
  <p>All purchased items are shown below. Nothing is selected by default. Choose the items and quantities, then review the combined amount before confirming.</p>
  <p class="small text-muted">Selecting an item starts with all its remaining units; reduce the quantity for a partial refund.</p>
  <button class="btn btn-outline-primary mb-2" type="button" data-refund-select-all>Select all remaining items</button>
  <div class="table-responsive">
    <table class="table align-middle clothing-refund-lines">
      <thead><tr><th>Select</th><th>Item / size</th><th>Purchased / paid</th><th>Previously refunded or pending</th><th>Available</th><th>Quantity to refund</th></tr></thead>
      <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('backend.clothing.partials.refund-item', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
    </table>
  </div>
  <details class="mb-3"><summary>Advanced: refund a partial rand amount for one item</summary>
    <label for="refund-mode" class="form-label">Refund size</label>
    <select name="mode" id="refund-mode" class="form-select mb-2"><option value="quantity" <?php if(old('mode', 'quantity') === 'quantity'): echo 'selected'; endif; ?>>Selected items and quantities</option><option value="amount" <?php if(old('mode') === 'amount'): echo 'selected'; endif; ?>>Partial rand amount</option></select>
    <div data-refund-amount-fields hidden>
      <label for="refund-amount-item" class="form-label">Item</label><select id="refund-amount-item" name="item_id" class="form-select mb-2" disabled><option value="">Choose an item</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->id); ?>" <?php if((string) old('item_id') === (string) $item->id): echo 'selected'; endif; ?>><?php echo e($item->item_name); ?> · <?php echo e($item->size_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
      <label for="refund-amount" class="form-label">Amount (R)</label><input id="refund-amount" name="amount" type="number" min="0.01" step="0.01" value="<?php echo e(old('amount')); ?>" class="form-control" disabled>
      <p class="small text-muted">An amount refund leaves the quantity unchanged. The review shows all included and excluded items.</p>
    </div>
  </details>
  <label for="refund-method" class="form-label">Refund to original payer: <?php echo e($order->user?->name); ?></label>
  <select id="refund-method" name="method" class="form-select mb-2"><option value="wallet" <?php if(old('method') === 'wallet'): echo 'selected'; endif; ?>>Wallet — credit after confirmation</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->payfast_paid && $order->payfast_pf_payment_id): ?><option value="payfast" <?php if(old('method') === 'payfast'): echo 'selected'; endif; ?>>PayFast — submit after confirmation</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
  <label for="refund-reason" class="form-label">Reason</label><input id="refund-reason" name="reason" required maxlength="500" value="<?php echo e(old('reason')); ?>" class="form-control mb-2">
  <?php echo $__env->make('backend.clothing.partials.late-note', ['noteKey' => 'order-refund-late'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <p class="small text-muted">No money is moved when you review. Clothing refunds have no withdrawal fee.</p>
  <button class="btn btn-primary" type="submit">Review selected refund</button>
</form>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\partials\refund-selection.blade.php ENDPATH**/ ?>