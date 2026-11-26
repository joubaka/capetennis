<?php $__env->startSection('title', 'Change category'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4" style="max-width:720px">
  <div class="card"><div class="card-body">
    <h1 class="h4">Change category</h1>
    <p><?php echo e($adjustment->event->name); ?></p>
    <p><?php echo e($adjustment->sourceCategory->category?->name); ?> → <?php echo e($adjustment->targetCategory->category?->name); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adjustment->applied_at): ?>
      <div class="alert alert-success">Your category change is complete.</div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) $adjustment->wallet_credit > 0): ?><p>R<?php echo e(number_format((float) $adjustment->wallet_credit, 2)); ?> credited to your wallet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php elseif($adjustment->status === 'pending'): ?>
      <p>Pay <strong>R<?php echo e(number_format((float) $adjustment->difference, 2)); ?></strong> through PayFast to complete this change.</p>
      <p>Your entry stays in its current category until payment is verified. This checkout expires at <?php echo e($adjustment->expires_at->format('d M Y H:i:s')); ?>.</p>
      <div class="d-flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($adjustment->payfast_handed_off_at)): ?>
        <form method="POST" action="<?php echo e(route('registrations.categoryAdjustment.pay', $adjustment)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary">Pay difference with PayFast</button></form>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <form method="POST" action="<?php echo e(route('registrations.categoryAdjustment.cancel', $adjustment)); ?>"><?php echo csrf_field(); ?><button class="btn btn-outline-secondary">Cancel change</button></form>
      </div>
      <p class="small text-muted mt-3">Already paid? Confirmation may take a moment. Refresh this page to check.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adjustment->payfast_handed_off_at): ?><p class="small">This attempt has already been sent to PayFast. Wait for confirmation, or cancel it before starting another attempt.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php elseif($adjustment->status === 'reconciliation_required'): ?>
      <div class="alert alert-warning">Your payment was received, but the category change could not be completed. Your entry has not moved. Please contact the organiser and quote category payment #<?php echo e($adjustment->id); ?>.</div>
    <?php else: ?>
      <div class="alert alert-info">This checkout is <?php echo e($adjustment->status); ?>. It did not change your category.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a class="btn btn-link mt-3" href="<?php echo e(route('events.show', $adjustment->event_id)); ?>">Return to event</a>
  </div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\registrations\category-adjustment.blade.php ENDPATH**/ ?>