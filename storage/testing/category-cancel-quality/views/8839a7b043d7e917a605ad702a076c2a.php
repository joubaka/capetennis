<?php $__env->startSection('title', 'Review team withdrawal'); ?>
<?php $__env->startSection('content'); ?>
<style>
  .team-withdrawal-review .btn { min-height:44px; max-width:100%; white-space:normal; }
  .team-withdrawal-review .form-select { min-height:44px; }
</style>
<div class="container-xl py-3 team-withdrawal-review" style="max-width:760px">
  <p class="small text-muted mb-1"><?php echo e($event->name); ?> · <?php echo e($team->name); ?></p>
  <h1 class="h4 mb-3">Withdraw <?php echo e($slot->player?->full_name); ?></h1>
  <div class="card"><div class="card-body">
    <p class="small text-muted mb-2">Roster rank <?php echo e($slot->rank); ?> · Order #<?php echo e($review['order']->id); ?></p>
    <p>Paid by <strong><?php echo e($review['order']->user->name); ?></strong></p>
    <p>Withdrawing frees this roster spot and removes the player from upcoming matches. Resolve any completed matches first. You can choose a replacement separately.</p>
    <section class="border-top pt-3 mt-3" aria-labelledby="withdrawal-payment-heading">
    <h2 class="h5" id="withdrawal-payment-heading">Payment and refund</h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review['deadlinePassed']): ?>
      <div class="alert alert-warning">The withdrawal deadline has passed. <?php echo e($review['deadlineOverride'] ? 'You’ve chosen to make an exception. Enter the reason below.' : 'Choose whether to keep the original payment or make an exception and issue a refund.'); ?></div>
      <form method="GET" class="mb-3">
        <label for="withdrawal-deadline-override" class="form-label">Refund after the deadline</label>
        <select id="withdrawal-deadline-override" name="deadline_override" class="form-select mb-2" onchange="this.form.requestSubmit()">
          <option value="0" <?php if(!$review['deadlineOverride']): echo 'selected'; endif; ?>>Withdraw without refund</option>
          <option value="1" <?php if($review['deadlineOverride']): echo 'selected'; endif; ?>>Allow refund after the deadline</option>
        </select>
        <button class="btn btn-outline-primary btn-sm">Update withdrawal preview</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review['refundAvailable']): ?>
      <form method="GET" class="mb-3">
        <input type="hidden" name="deadline_override" value="<?php echo e((int) $review['deadlineOverride']); ?>">
        <label for="withdrawal-destination" class="form-label">Refund destination</label>
        <select id="withdrawal-destination" name="destination" class="form-select mb-3" onchange="this.form.requestSubmit()">
          <option value="wallet" <?php if($review['destination'] === 'wallet'): echo 'selected'; endif; ?>>Original payer wallet</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review['order']->payfast_paid): ?><option value="payfast" <?php if($review['destination'] === 'payfast'): echo 'selected'; endif; ?>>Original PayFast payment source</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <label for="withdrawal-fee-mode" class="form-label">Handling fee</label>
        <select id="withdrawal-fee-mode" name="fee_mode" class="form-select mb-3" onchange="this.form.requestSubmit()">
          <option value="current" <?php if($review['feeMode'] === 'current'): echo 'selected'; endif; ?>>Deduct the withdrawal fee (R<?php echo e(number_format($review['currentFee'], 2)); ?>)</option>
          <option value="full" <?php if($review['feeMode'] === 'full'): echo 'selected'; endif; ?>>Full refund · no handling fee</option>
        </select>
        <button class="btn btn-outline-primary btn-sm">Update refund preview</button>
      </form>
      <div class="border rounded p-3 mb-3">
      <h3 class="h6">Refund summary</h3>
      <dl class="row mb-0"><dt class="col-7">Amount paid</dt><dd class="col-5 text-end">R<?php echo e(number_format($review['amounts']['gross'], 2)); ?></dd>
        <dt class="col-7">Withdrawal fee</dt><dd class="col-5 text-end">R<?php echo e(number_format($review['amounts']['fee'], 2)); ?></dd>
        <dt class="col-7">Total refund</dt><dd class="col-5 text-end fw-bold">R<?php echo e(number_format($review['amounts']['net'], 2)); ?></dd>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review['destination'] === 'payfast'): ?><dt class="col-7">To original PayFast source</dt><dd class="col-5 text-end">R<?php echo e(number_format($review['amounts']['payfastNet'], 2)); ?></dd>
          <dt class="col-7">To original payer wallet</dt><dd class="col-5 text-end">R<?php echo e(number_format($review['amounts']['walletNet'], 2)); ?></dd><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </dl>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review['destination'] === 'wallet'): ?><p>The refund goes to the original payer’s wallet.</p>
      <?php else: ?><p>PayFast must confirm the refund to the original payment source. Any amount paid from the wallet goes back to the payer’s wallet after that confirmation. If PayFast can’t confirm it, the refund stays pending for review.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
      <p class="mb-0"><strong>No refund.</strong> The original payment is kept.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
    <section class="border-top pt-3 mt-3" aria-labelledby="withdrawal-confirm-heading">
    <h2 class="h5" id="withdrawal-confirm-heading">Confirm withdrawal</h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('backend.team.withdrawal.store', [$event, $team, $slot])); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="deadline_override" value="<?php echo e((int) $review['deadlineOverride']); ?>">
      <input type="hidden" name="order_id" value="<?php echo e($review['order']->id); ?>"><input type="hidden" name="fingerprint" value="<?php echo e($review['fingerprint']); ?>">
      <input type="hidden" name="destination" value="<?php echo e($review['destination']); ?>"><input type="hidden" name="fee_mode" value="<?php echo e($review['feeMode']); ?>">
      <label class="form-label w-100"><?php echo e($review['deadlineOverride'] ? 'Reason for refund after the deadline' : 'Reason'); ?><textarea class="form-control" name="reason" required minlength="3" maxlength="1000" rows="3"><?php echo e(old('reason')); ?></textarea></label>
      <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label"><?php echo e($review['refundAvailable'] ? 'Confirm withdrawal and the displayed refund to the selected original payer destination.' : 'Confirm withdrawal without a refund.'); ?></span></label>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger"><?php echo e($review['refundAvailable'] ? ($review['destination'] === 'payfast' ? 'Withdraw and refund original PayFast source' : 'Withdraw and refund to wallet') : 'Withdraw without refund'); ?></button>
        <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id])); ?>">Cancel</a></div>
    </form>
    </section>
  </div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\team-withdrawal.blade.php ENDPATH**/ ?>