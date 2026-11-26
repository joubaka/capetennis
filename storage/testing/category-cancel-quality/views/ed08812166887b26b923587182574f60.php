<?php $__env->startSection('title', 'Review original-source refund'); ?>
<?php $__env->startSection('content'); ?>
<style>
  .team-refund-review .btn { min-height:44px; max-width:100%; white-space:normal; }
  .team-refund-review .form-control { min-height:44px; }
</style>
<div class="container-xl py-3 team-refund-review" style="max-width:760px">
  <h1 class="h4">Review original-source refund · Order #<?php echo e($order->id); ?></h1>
  <p><?php echo e($event->name); ?> · Original payer: <?php echo e($order->user?->name); ?></p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['success', 'warning']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session($message)): ?><div class="alert alert-<?php echo e($message); ?>" role="status"><?php echo e(session($message)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card"><div class="card-body">
    <p>The player is withdrawn and the roster place is free. Refund status: <strong><?php echo e($order->refund_status); ?></strong>. PayFast confirmation: <strong><?php echo e($attempt->status); ?></strong>.</p>
    <dl class="row"><dt class="col-7">Verified original payment</dt><dd class="col-5 text-end">R<?php echo e(number_format($attempt->gross, 2)); ?></dd>
      <dt class="col-7">Handling fee</dt><dd class="col-5 text-end">R<?php echo e(number_format($attempt->fee, 2)); ?></dd>
      <dt class="col-7">Original PayFast source</dt><dd class="col-5 text-end">R<?php echo e(number_format($attempt->amount, 2)); ?></dd>
      <dt class="col-7">Original payer wallet</dt><dd class="col-5 text-end">R<?php echo e(number_format($attempt->wallet_amount, 2)); ?></dd></dl>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->refund_status !== 'completed'): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($attempt->status, ['ready', 'confirmed'], true)): ?>
        <p><?php echo e($attempt->status === 'confirmed' ? 'PayFast already confirmed. Finish the local refund record and any wallet portion without sending another provider refund.' : 'The request is saved but has not been sent. Continue the original-source refund.'); ?></p>
        <form method="POST" action="<?php echo e(route('backend.team.withdrawal.refund.recover', [$event, $order])); ?>"><?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="resume"><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">Confirm continuing this saved refund.</span></label>
          <button class="btn btn-primary"><?php echo e($attempt->status === 'confirmed' ? 'Finish local refund' : 'Continue saved refund'); ?></button>
        </form>
      <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attempt->status === 'unavailable'): ?><div class="alert alert-info">PayFast did not offer the requested amount back to its original source. No refund was submitted by this request. The refund remains pending; finance support must resolve it before another payout.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="alert alert-warning">PayFast has not confirmed this refund. Check PayFast history for payment <?php echo e($attempt->pf_payment_id); ?>. Do not issue another refund or credit the wallet separately. If the exact refund is independently confirmed, record its evidence below. If PayFast cannot return it to its original source, resolve the pending case with finance support.</div>
        <form method="POST" action="<?php echo e(route('backend.team.withdrawal.refund.recover', [$event, $order])); ?>"><?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="reconcile">
          <label class="form-label w-100">Original PayFast payment ID<input class="form-control" name="pf_payment_id" required maxlength="120" value="<?php echo e(old('pf_payment_id')); ?>"></label>
          <label class="form-label w-100">Confirmed provider refund amount<input class="form-control" name="amount" type="number" min="0.01" step="0.01" required value="<?php echo e(old('amount')); ?>"></label>
          <label class="form-label w-100">Provider refund reference<input class="form-control" name="reference" required maxlength="120" value="<?php echo e(old('reference')); ?>"></label>
          <label class="form-label w-100">Provider confirmation date and time<input class="form-control" name="confirmed_at" type="datetime-local" required value="<?php echo e(old('confirmed_at')); ?>"></label>
          <label class="form-label w-100">Evidence and reason<textarea class="form-control" name="reason" minlength="10" maxlength="1000" required><?php echo e(old('reason')); ?></textarea></label>
          <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="externally_confirmed" value="1" required><span class="form-check-label">I independently verified this exact refund to the original PayFast source.</span></label>
          <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">Record this confirmation and finish the local refund. No provider refund will be sent.</span></label>
          <button class="btn btn-primary">Record confirmed refund</button>
        </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
  <a class="btn btn-outline-secondary mt-3" href="<?php echo e(route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $order->team?->region_id])); ?>">Back to team players</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\team-withdrawal-refund.blade.php ENDPATH**/ ?>