<?php $__env->startSection('title', 'Player Plus payment review'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y" style="max-width:1000px;overflow-wrap:anywhere">
  <a href="<?php echo e(route('superadmin.player-plus.index')); ?>">Back to Plus payment reviews</a>
  <h1 class="mt-3">Payment review · P<?php echo e($order->id); ?></h1>
  <a class="d-inline-block py-2" href="<?php echo e(route('superadmin.player-plus.manage.show', $order->player_id)); ?>">Manage this player's access and orders</a>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><p class="alert alert-success"><?php echo e(session('success')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="alert alert-danger"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p><strong><?php echo e($order->player?->full_name ?? 'Player unavailable'); ?></strong> · Profile #<?php echo e($order->player_id); ?><br>Original payer: <?php echo e($order->user?->name); ?> · <?php echo e($order->user?->email); ?><br>Stored checkout price: R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?><br>Status: <?php echo e($order->status); ?> · <?php echo e($order->activated_at ? 'Original membership was activated' : 'Membership was not activated'); ?></p>
  <p class="alert alert-warning"><?php echo e($order->reconciliation_reason); ?></p>
  <h2 class="h4">Verified provider receipts</h2>
  <p><?php echo e($receipts->total()); ?> verified receipts across <?php echo e($receipts->lastPage()); ?> <?php echo e($receipts->lastPage() === 1 ? 'page' : 'pages'); ?>. A resolution covers the complete receipt set, including receipts on other pages.</p>
  <div class="list-group">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $receipts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receipt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="list-group-item"><?php echo e($receipt->pf_payment_id); ?> · R<?php echo e(number_format((float) $receipt->amount_gross, 2)); ?><span class="d-block small">Receipt #<?php echo e($receipt->id); ?> · <?php echo e($receipt->created_at); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="mt-3"><?php echo e($receipts->links()); ?></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolution): ?>
    <p class="alert alert-info">External resolution evidence is recorded for this exact set of receipts. New verified receipts require a new review. Payment status and original membership history are preserved.</p>
  <?php else: ?>
    <form method="post" action="<?php echo e(route('superadmin.player-plus.store', $order)); ?>" class="card card-body mt-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="payment_fingerprint" value="<?php echo e($fingerprint); ?>">
      <h2 class="h4">Record an externally reviewed resolution</h2>
      <p>First verify the payer, all provider references, any earlier resolution and the approved remedy. Complete any authorised financial action through the established financial process. Retain its evidence before recording this note. Do not enter bank account numbers, card details or credentials.</p>
      <label class="form-label" for="plus-reference">External evidence reference</label>
      <input class="form-control mb-3" style="min-height:44px" id="plus-reference" name="reference" value="<?php echo e(old('reference')); ?>" minlength="3" maxlength="150" required>
      <label class="form-label" for="plus-note">Resolution and evidence reviewed</label>
      <textarea class="form-control mb-3" id="plus-note" name="note" rows="4" minlength="10" maxlength="1500" required><?php echo e(old('note')); ?></textarea>
      <label class="d-flex gap-2"><input type="checkbox" class="mt-1" name="confirm" value="1" required><span>I verified all <?php echo e($receipts->total()); ?> receipts across every page and retained evidence of the external resolution. This action only records that review.</span></label>
      <button class="btn btn-primary align-self-start mt-3" style="min-height:44px">Record resolution evidence</button>
    </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <h2 class="h4 mt-4">Append-only review history</h2>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="border rounded p-3 mb-2"><strong><?php echo e($item->created_at); ?> · <?php echo e($item->actor_name ?? 'Recorded actor'); ?></strong><p class="mb-1">Reference: <?php echo e($item->metadata['external_reference'] ?? ''); ?></p><p class="mb-0" style="white-space:pre-wrap"><?php echo e($item->reason); ?></p></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No resolution evidence has been recorded yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo e($history->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-plus\show.blade.php ENDPATH**/ ?>