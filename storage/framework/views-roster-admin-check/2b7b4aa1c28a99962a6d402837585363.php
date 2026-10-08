<?php
  $coverageOrder = $invitation->order;
  $transferTargets = $teamInvitations->filter(fn ($candidate) => in_array($candidate->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true) && $candidate->roster_rank && $candidate->player_id != $invitation->player_id);
  $transferUnavailable = $activeImport->status !== 'sent' ? 'Payment transfer requires an active invitation campaign.'
    : (!$coverageOrder || !$coverageOrder->pay_status ? 'No settled payment order is attached. Paid Confirmed alone is not proof of payment.'
    : ($coverageOrder->collection_status === 'paid_privately' ? 'Payment collected privately cannot be transferred: there is no verified wallet or PayFast settlement.'
    : ($coverageOrder->withdrawn_at || $coverageOrder->hasRefund() ? 'Payment has withdrawal or refund history and cannot be transferred.'
    : ($transferTargets->isEmpty() ? 'No selected unpaid player is available. Activate a reserve first.' : null))));
  $transferFormOpen = (int) old('transfer_invitation_id') === (int) $invitation->id;
?>
<details id="invitation-payment-transfer-<?php echo e($invitation->id); ?>" class="mt-2" style="max-width:22rem;white-space:normal;" data-invitation-payment-transfer <?php if($transferFormOpen): ?> open <?php endif; ?>>
  <summary class="small text-primary">Move payment to another player</summary>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferFormOpen && $errors->any()): ?><div class="alert alert-danger mt-2 small" role="alert"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($message); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferUnavailable): ?>
    <p class="small text-muted mt-2 mb-0" data-payment-transfer-unavailable><?php echo e($transferUnavailable); ?></p>
  <?php else: ?>
    <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.payment.transfer', [$event, $activeImport, $invitation])); ?>" class="mt-2">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="transfer_invitation_id" value="<?php echo e($invitation->id); ?>">
      <input type="hidden" name="order_id" value="<?php echo e($coverageOrder->id); ?>">
      <input type="hidden" name="expected_player_id" value="<?php echo e($invitation->player_id); ?>">
      <label class="small" for="invitation-transfer-target-<?php echo e($invitation->id); ?>">Unpaid player in this team</label>
      <select id="invitation-transfer-target-<?php echo e($invitation->id); ?>" name="target_player_id" class="form-select form-select-sm" required>
        <option value="">Choose player</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transferTargets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($candidate->player_id); ?>" <?php if($transferFormOpen && old('target_player_id') == $candidate->player_id): echo 'selected'; endif; ?>><?php echo e($candidate->player?->full_name); ?> · Rank <?php echo e($candidate->roster_rank); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <div class="small text-muted mt-1">Selected unpaid players can receive this payment without starting a checkout. Activate reserves first.</div>
      <label class="small mt-2" for="invitation-transfer-reason-<?php echo e($invitation->id); ?>">Reason</label>
      <input id="invitation-transfer-reason-<?php echo e($invitation->id); ?>" name="reason" maxlength="1000" class="form-control form-control-sm" value="<?php echo e($transferFormOpen ? old('reason') : ''); ?>" required>
      <p class="small text-muted mt-2">Use this payment for the selected player. Any later refund goes to the original payer.</p>
      <p class="small text-muted">The original player remains selected and becomes unpaid. No team place is released.</p>
      <label class="small d-flex gap-2 my-2"><input type="checkbox" class="flex-shrink-0" id="confirm-invitation-transfer-<?php echo e($invitation->id); ?>" name="confirm_transfer" value="1" required><span>Confirm this player becomes unpaid and the selected player becomes paid.</span></label>
      <button class="btn btn-sm btn-outline-primary">Move payment</button>
    </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</details>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-selection\_invitation-payment-transfer.blade.php ENDPATH**/ ?>