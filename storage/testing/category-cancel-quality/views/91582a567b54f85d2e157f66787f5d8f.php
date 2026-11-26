<?php $__env->startSection('title', 'Review clothing refund'); ?>
<?php $__env->startSection('content'); ?>
<div class="card clothing-refund-workspace"><div class="card-body">
  <h4>Review clothing refund — order #<?php echo e($order->id); ?></h4>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p>Original payer: <strong><?php echo e($order->user?->name); ?></strong> · <?php echo e($event->name); ?></p>
  <div class="table-responsive"><table class="table clothing-refund-lines">
    <thead><tr><th>Item / size</th><th>Purchased / paid</th><th>Prior refunds or pending</th><th>Selected for this refund</th><th>Excluded from this refund</th><th>Refund amount</th></tr></thead>
    <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $quote['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr>
      <td data-label="Item / size"><?php echo e($line['name']); ?> · <?php echo e($line['size']); ?></td><td data-label="Purchased / paid"><?php echo e($line['purchased']); ?> units · R<?php echo e(number_format($line['paid_cents'] / 100, 2)); ?></td>
      <td data-label="Prior refunds / pending"><?php echo e($line['used_quantity']); ?> units · R<?php echo e(number_format($line['used_cents'] / 100, 2)); ?></td>
      <td data-label="Selected for refund"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['selected_cents'] > 0): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($validated['mode'] ?? '') === 'amount'): ?>Amount only; quantity unchanged <?php else: ?> <?php echo e($line['selected_quantity']); ?> units <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php else: ?> Not selected <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
      <td data-label="Excluded from refund"><?php echo e($line['remaining_quantity'] - $line['selected_quantity']); ?> available units · R<?php echo e(number_format(($line['paid_cents'] - $line['used_cents'] - $line['selected_cents']) / 100, 2)); ?></td>
      <td data-label="Refund amount">R<?php echo e(number_format($line['selected_cents'] / 100, 2)); ?></td>
    </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
  </table></div>
  <div class="alert alert-info"><strong><?php echo e($quote['kind']); ?></strong>
    <p class="fs-5 mb-1">Refund amount: <strong>R<?php echo e(number_format($quote['total_cents'] / 100, 2)); ?></strong></p>
    <p class="mb-1">Fee: R0.00 · Net to original payer: R<?php echo e(number_format($quote['total_cents'] / 100, 2)); ?> via <?php echo e($validated['method'] === 'wallet' ? 'wallet credit' : 'PayFast'); ?>.</p>
    <p class="mb-0">R<?php echo e(number_format(($quote['remaining_cents'] - $quote['total_cents']) / 100, 2)); ?> remains refundable on this order after this refund.</p>
  </div>
  <p>Reason: <?php echo e($validated['reason']); ?></p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($validated['late_refund_note'] ?? null): ?><p>Late refund note: <?php echo e($validated['late_refund_note']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="POST" action="<?php echo e(route('backend.clothing.refunds.store', [$event, $order])); ?>">
    <?php echo csrf_field(); ?><input type="hidden" name="review_token" value="<?php echo e($reviewToken); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($validated['method'] === 'payfast'): ?>
      <?php echo $__env->make('backend.clothing.partials.refund-bank-details', ['bankFieldPrefix' => 'refund'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button class="btn btn-danger" type="submit">Confirm refund of R<?php echo e(number_format($quote['total_cents'] / 100, 2)); ?></button>
    <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.clothing.refunds.show', [$event, $order, 'sort' => $validated['return_sort'] ?? 'rank', 'search' => $validated['return_search'] ?? '', 'team' => $validated['return_team'] ?? '', 'all_events' => $validated['return_all_events'] ?? 0])); ?>">Change selection</a>
  </form>
</div></div>
<?php echo $__env->make('backend.clothing.partials.refund-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\refund-review.blade.php ENDPATH**/ ?>