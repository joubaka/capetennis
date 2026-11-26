
<?php $__env->startSection('title', 'Clothing refunds'); ?>
<?php $__env->startSection('content'); ?>
<?php
  $refundAllowed = app(\App\Services\Clothing\ClothingRefundService::class)->canRefund($event, $order, auth()->user());
  $refundDeadline = app(\App\Services\Clothing\ClothingRefundService::class)->deadline($event, $order);
?>
<div class="card clothing-refund-workspace"><div class="card-body">
<h4>Clothing refunds — <?php echo e($order->player?->full_name); ?></h4>
<p>Order #<?php echo e($order->id); ?> · <?php echo e($event->name); ?> · Original payer: <?php echo e($order->user?->name); ?></p>
<a class="btn btn-outline-secondary mb-3" href="<?php echo e(route('backend.region.clothing.orders', array_filter(['region' => $order->team->region_id, 'event_id' => request()->boolean('all_events') && auth()->user()->hasRole('super-user') ? null : $event->id, 'sort' => in_array(request('sort'), ['rank', 'name', 'team', 'age_group', 'date_desc', 'date_asc'], true) ? request('sort') : 'rank', 'search' => request('search', ''), 'team' => request('team', '')], fn ($value) => $value !== null))); ?>">Back to clothing orders</a>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundAllowed): ?>
<?php echo $__env->make('backend.clothing.partials.refund-selection', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?><p>Only a super-user can refund after the clothing deadline or while no deadline is set.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<h5 class="mt-4">Refund history</h5>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $order->refunds->sortByDesc('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="border rounded p-3 mb-3">
<p><strong>R<?php echo e($refund->refund_net); ?> · <?php echo e(ucfirst($refund->refund_method)); ?> · <?php echo e(ucfirst($refund->refund_status)); ?></strong></p>
<p><?php echo e($refund->reason); ?></p>
<p class="text-muted">Requested by <?php echo e($refund->requested_by_name ?? $refund->requestedBy?->name ?? 'Not recorded'); ?> · <?php echo e($refund->created_at?->format('d M Y H:i')); ?><br>
Completed by <?php echo e($refund->completed_by_name ?? $refund->completedBy?->name ?? 'Not recorded'); ?><?php echo e($refund->refunded_at ? ' · '.$refund->refunded_at->format('d M Y H:i') : ''); ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->refund_method === 'payfast' && $refund->refund_status === 'completed' && ! $refund->completed_by): ?>
<p class="small text-muted">PayFast completion is recorded; the person who completed it was not recorded. The requester above is the recorded initiator.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.clothing.partials.historical-refund-source', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->late_refund_note): ?>
<div class="alert alert-warning"><strong>Late refund — clothing payout requires reconciliation</strong><p class="mb-1">Potential payout adjustment: R<?php echo e($refund->refund_net); ?>. If already paid to the region or supplier, reconcile that payout separately.</p><p class="mb-1"><?php echo e($refund->late_refund_note); ?></p><p class="mb-0">Status: <?php echo e($refund->reconciliation_status); ?></p></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->reconciliation_status === 'resolved'): ?><p>Reconciled: <?php echo e($refund->reconciliation_note); ?> · <?php echo e($refund->reconciled_at?->format('d M Y H:i')); ?></p><?php elseif(auth()->user()->hasRole('super-user') && $refund->refund_status === 'completed'): ?>
<form method="POST" action="<?php echo e(route('backend.clothing.refunds.reconcile', [$event, $order, $refund])); ?>"><?php echo csrf_field(); ?><label class="form-label" for="reconcile-<?php echo e($refund->id); ?>">How was the clothing payout reconciled? Include the adjustment or evidence reference.</label><textarea required class="form-control mb-2" maxlength="1000" name="reconciliation_note" id="reconcile-<?php echo e($refund->id); ?>"></textarea><button class="btn btn-outline-primary mb-2">Record reconciliation</button></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $refund->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($line->item?->item_name); ?> · <?php echo e($line->quantity); ?> units · R<?php echo e($line->amount); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->refund_status === 'pending' && $providerAttempts->has($refund->id)): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($providerAttempts->get($refund->id)->status === 'confirmed' && $refundAllowed): ?>
    <p>PayFast confirmed this refund. Finish recording the confirmed result locally.</p>
    <form method="POST" action="<?php echo e(route('backend.clothing.refunds.retry', [$event, $order, $refund])); ?>"><?php echo csrf_field(); ?> <?php echo $__env->make('backend.clothing.partials.late-note', ['noteKey' => 'retry-late-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="btn btn-outline-primary">Finish recording confirmed refund</button></form>
  <?php elseif(in_array($providerAttempts->get($refund->id)->status, ['not_dispatched', 'reconciled_ready'], true)): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($providerAttempts->get($refund->id)->status === 'reconciled_ready'): ?>
    <p>PayFast history was reconciled and showed no refund. This saved refund is ready to retry.</p>
    <?php else: ?>
    <p>No refund was submitted to PayFast. This amount remains reserved for the saved refund.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundAllowed): ?>
    <form method="POST" action="<?php echo e(route('backend.clothing.refunds.retry', [$event, $order, $refund])); ?>">
      <?php echo csrf_field(); ?>
      <?php echo $__env->make('backend.clothing.partials.refund-bank-details', ['bankFieldPrefix' => 'retry-bank-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo $__env->make('backend.clothing.partials.late-note', ['noteKey' => 'retry-late-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <button class="btn btn-outline-danger">Retry saved PayFast refund of R<?php echo e($refund->refund_net); ?></button>
    </form>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($providerAttempts->get($refund->id)->status, ['not_dispatched', 'reconciled_ready'], true) && $providerAttempts->get($refund->id)->confirmed_at === null && $refund->refund_method === 'payfast'): ?>
    <form class="mt-2" method="POST" action="<?php echo e(route('backend.clothing.refunds.wallet.review', [$event, $order, $refund])); ?>"><?php echo csrf_field(); ?>
      <?php echo $__env->make('backend.clothing.partials.late-note', ['noteKey' => 'wallet-late-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <button class="btn btn-outline-primary">Refund to wallet instead</button>
    </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php else: ?>
    <p class="text-warning">PayFast submission needs reconciliation. This amount stays reserved. Do not submit another payment or refund for it.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($providerAttempts->get($refund->id)->status === 'uncertain' && $refundAllowed && auth()->user()->hasRole('super-user')): ?>
    <form method="POST" action="<?php echo e(route('backend.clothing.refunds.check-payfast', [$event, $order, $refund])); ?>" data-check-payfast><?php echo csrf_field(); ?>
      <p class="small text-muted">Check PayFast's current refund history. This check does not pay a refund or credit a wallet.</p>
      <button class="btn btn-outline-primary">Check PayFast</button>
    </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->external_reference): ?><p>Payment reference: <?php echo e($refund->external_reference); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundAllowed && $refund->refund_status === 'pending' && ! $providerAttempts->has($refund->id) && in_array($refund->refund_method, ['bank', 'payfast'], true)): ?>
<p>Complete the refund to the original payer in <?php echo e($refund->refund_method === 'payfast' ? 'PayFast' : 'your bank'); ?> first. Recording it here does not move money.</p>
<form method="POST" action="<?php echo e(route('backend.clothing.refunds.complete', [$event, $order, $refund])); ?>"><?php echo csrf_field(); ?>
<label class="form-label" for="reference-<?php echo e($refund->id); ?>">Payment reference (no banking details)</label><input required class="form-control mb-2" name="reference" id="reference-<?php echo e($refund->id); ?>" minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}">
<label class="d-block mb-2"><input type="checkbox" name="paid_to_original_payer" value="1" required> I confirm this refund was paid to the original payer.</label>
<?php echo $__env->make('backend.clothing.partials.late-note', ['noteKey' => 'manual-late-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<button class="btn btn-outline-danger">Record completed refund</button></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No refunds yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php echo $__env->make('backend.clothing.partials.refund-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.clothing.partials.refund-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\refunds.blade.php ENDPATH**/ ?>