
<?php $__env->startSection('title', 'Decline invitation'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4"><div class="row justify-content-center"><div class="col-lg-6">
  <div class="card"><div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation->status === \App\Models\TeamSelectionInvitation::DECLINED): ?>
      <h4>Invitation declined</h4>
      <p class="mb-0">Your decline for <?php echo e($invitation->player?->full_name); ?> at <?php echo e($invitation->selectionImport?->event?->name); ?> has been recorded.</p>
    <?php elseif(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)): ?>
      <h4>Decline invitation?</h4>
      <p><?php echo e($invitation->player?->full_name); ?> · <?php echo e($invitation->selectionImport?->event?->name); ?></p>
      <p>Confirm that you are unavailable. Your place will be released and a reserve may be invited.</p>
      <form method="POST" action="<?php echo e(route('team-selection.invitations.decline', $invitation)); ?>"><?php echo csrf_field(); ?>
        <label for="decline-reason" class="form-label">Reason (optional)</label>
        <textarea id="decline-reason" name="reason" maxlength="1000" rows="3" class="form-control mb-3"><?php echo e(old('reason')); ?></textarea>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger mb-3"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger">Confirm decline</button><a class="btn btn-outline-secondary" href="<?php echo e(route('events.show', $invitation->event_id)); ?>">Keep invitation</a></div>
      </form>
    <?php else: ?>
      <h4>Invitation unavailable</h4><p class="mb-0">This invitation is <?php echo e(str_replace('_', ' ', $invitation->status)); ?>.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
</div></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\team-selection\decline.blade.php ENDPATH**/ ?>