<?php $__env->startSection('title', $event->name.' - Payout requests'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Event completion and payout requests'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Request an event payout</h2>
<p>Provisional remaining registration funds: <strong>R<?php echo e(number_format($snapshot['balance_cents'] / 100, 2)); ?></strong>. Clothing funds are excluded.</p>
<p>This is an event-level request for review. It does not establish a personal entitlement or choose a recipient. Super User must reconcile expenses, reimbursements, profit shares and refunds before any payment is recorded separately.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['pending_refunds']): ?><div class="alert alert-warning">Resolve <?php echo e($snapshot['pending_refunds']); ?> pending registration refund(s) before requesting a payout.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$snapshot['completed']): ?>
<form method="POST" action="<?php echo e(route('admin.events.payout-requests.complete', $event)); ?>" class="border rounded p-3 mb-3">
<?php echo csrf_field(); ?><h3 class="h6">Complete event</h3>
<p>Confirm the tournament has finished to unlock the final payout request. This does not publish results or change registration settings.</p>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required id="completion-confirm"><label class="form-check-label" for="completion-confirm">I confirm this event has finished.</label></div>
<button class="btn btn-outline-primary" type="submit">Complete event and continue</button>
</form>
<?php else: ?><p class="badge bg-success">Event completion recorded</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstanding): ?><div class="alert alert-info">An outstanding request is already with Super User. Reviewed means reviewed only; no payment is recorded by this workflow.</div>
<?php elseif(!$snapshot['pending_refunds'] && $snapshot['balance_cents'] > 0): ?>
<form method="POST" action="<?php echo e(route('admin.events.payout-requests.store', $event)); ?>">
<?php echo csrf_field(); ?><input type="hidden" name="request_token" value="<?php echo e(old('request_token', (string) Illuminate\Support\Str::uuid())); ?>"><input type="hidden" name="preview_token" value="<?php echo e($previewToken); ?>">
<div class="mb-3"><label class="form-label" for="request-kind">Payment request</label><select class="form-select" id="request-kind" name="kind"><option value="interim" <?php if(old('kind', $snapshot['completed'] ? 'final' : 'interim') === 'interim'): echo 'selected'; endif; ?>>Interim payment</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['completed']): ?><option value="final" <?php if(old('kind', $snapshot['completed'] ? 'final' : 'interim') === 'final'): echo 'selected'; endif; ?>>Final payment - full remaining balance</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="mb-3" id="interim-amount-field"><label class="form-label" for="request-amount">Interim amount (rand)</label><input class="form-control" id="request-amount" name="amount" type="number" min="0.01" step="0.01" value="<?php echo e(old('amount')); ?>" aria-describedby="amount-help"><div class="form-text" id="amount-help">Enter an amount below the remaining balance for an interim request. Final requests use the full remaining balance calculated by Cape Tennis; leave this field blank.</div></div>
<div class="mb-3"><label class="form-label" for="request-reason">Reason / payment details for review</label><textarea required maxlength="2000" class="form-control" name="reason" id="request-reason"><?php echo e(old('reason')); ?></textarea></div>
<p id="final-amount" class="fw-semibold">Final amount for review: R<?php echo e(number_format($snapshot['balance_cents'] / 100, 2)); ?></p><button class="btn btn-primary" type="submit">Request payment</button>
</form>
<?php elseif(!$outstanding && !$snapshot['pending_refunds']): ?><p>No positive registration balance is available to request.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Request history</h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><article class="border rounded p-3 mb-3"><strong><?php echo e(ucfirst($item->kind)); ?> - R<?php echo e(number_format($item->amount, 2)); ?></strong><p class="mb-1"><?php echo e(ucfirst($item->status)); ?> - <?php echo e($item->created_at->format('d M Y H:i')); ?></p><p><?php echo e($item->reason); ?></p><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->review_note): ?><p class="mb-0">Review: <?php echo e($item->review_note); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No payout requests yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo e($requests->links()); ?></div></div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script>
(function () {
  const kind = document.getElementById('request-kind');
  if (!kind) return;
  const amount = document.getElementById('request-amount');
  const sync = () => {
    const final = kind.value === 'final';
    document.getElementById('interim-amount-field').hidden = final;
    document.getElementById('final-amount').hidden = !final;
    amount.disabled = final;
    amount.required = !final;
  };
  kind.addEventListener('change', sync); sync();
})();
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\payout-requests.blade.php ENDPATH**/ ?>