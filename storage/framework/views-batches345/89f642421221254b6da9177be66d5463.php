<?php
  $cashOrder = $invitation->order;
  $cashAmounts = $cashOrder ? app(\App\Domain\Refunds\Services\TeamRefundCalculator::class)->calculate($cashOrder) : null;
  $cashBlocked = !$cashOrder ? 'No payment order is attached. Paid Confirmed alone is not proof of money received.'
    : (!$cashOrder->payfast_paid || !$cashOrder->payfast_pf_payment_id ? 'A verified PayFast payment is required. Private collection marks use Mark as unpaid instead.'
    : ($cashOrder->withdrawn_at || $cashOrder->hasRefund() || $cashOrder->refund_waived_at ? 'This order already has withdrawal or refund history.'
    : ($cashAmounts['net'] <= 0 ? 'No amount is refundable under the current withdrawal policy.' : null)));
  $cashDeadlineExpired = now()->gt($event->withdrawalCloseAt());
  $cashOpen = (int) old('cash_refund_invitation_id') === (int) $invitation->id;
?>
<details class="p-2" data-cash-refund-action <?php if($cashOpen): ?> open <?php endif; ?>>
  <summary class="text-warning">Record cash refund</summary>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cashBlocked): ?><p class="small text-muted mt-2 mb-0"><?php echo e($cashBlocked); ?></p>
  <?php else: ?>
  <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.cash-refund', [$event, $activeImport, $invitation])); ?>" class="mt-2">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="cash_refund_invitation_id" value="<?php echo e($invitation->id); ?>"><input type="hidden" name="expected_order_id" value="<?php echo e($cashOrder->id); ?>"><input type="hidden" name="expected_player_id" value="<?php echo e($invitation->player_id); ?>"><input type="hidden" name="expected_roster_rank" value="<?php echo e($invitation->roster_rank); ?>">
    <input type="hidden" name="refund_fingerprint" value="<?php echo e(\App\Domain\Payments\Services\TeamPaymentService::cashRefundFingerprint($cashOrder, $cashAmounts)); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cashOpen && $errors->any()): ?><div class="small text-danger mb-2"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="small">Original payer: <strong><?php echo e($cashOrder->user?->name ?: 'Payer unavailable'); ?></strong> · Order #<?php echo e($cashOrder->id); ?></p>
    <p class="small">Paid R<?php echo e(number_format($cashAmounts['gross'], 2)); ?> · Fee R<?php echo e(number_format($cashAmounts['fee'], 2)); ?> · <strong>Cash refund R<?php echo e(number_format($cashAmounts['net'], 2)); ?></strong></p>
    <p class="small text-muted">This records cash already handed to the original payer. Choose whether to remove this player or keep them selected at the same rank, unpaid and requiring a fresh checkout. It does not refund through PayFast or credit a wallet.</p>
    <p class="small text-muted">This action is silent. No automatic email is sent.</p>
    <label class="form-label small" for="cash-disposition-<?php echo e($invitation->id); ?>">Player after refund</label>
    <select id="cash-disposition-<?php echo e($invitation->id); ?>" name="disposition" class="form-select form-select-sm mb-2" required>
      <option value="">Choose remove or keep</option><option value="remove" <?php if($cashOpen && old('disposition') === 'remove'): echo 'selected'; endif; ?>>Remove player and free roster place</option><option value="keep" <?php if($cashOpen && old('disposition') === 'keep'): echo 'selected'; endif; ?>>Keep selected at same rank, unpaid</option>
    </select>
    <label class="form-label small" for="cash-reference-<?php echo e($invitation->id); ?>">Cash receipt / reference</label><input id="cash-reference-<?php echo e($invitation->id); ?>" name="reference" class="form-control form-control-sm mb-2" maxlength="255" value="<?php echo e($cashOpen ? old('reference') : ''); ?>" required>
    <label class="form-label small" for="cash-reason-<?php echo e($invitation->id); ?>">Reason</label><input id="cash-reason-<?php echo e($invitation->id); ?>" name="reason" class="form-control form-control-sm mb-2" maxlength="1000" value="<?php echo e($cashOpen ? old('reason') : ''); ?>" required>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cashDeadlineExpired): ?>
    <p class="small text-warning">The refund deadline <?php echo e($event->withdrawalCloseAt()->format('d M Y H:i')); ?> has passed. Only a super admin may explicitly override it for this recorded cash refund.</p>
    <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_admin_deadline_override" value="1" required <?php if($cashOpen && old('confirm_admin_deadline_override')): echo 'checked'; endif; ?>><span class="form-check-label">Override refund deadline</span></label>
    <label class="form-label small" for="cash-override-reason-<?php echo e($invitation->id); ?>">Deadline override reason</label><input id="cash-override-reason-<?php echo e($invitation->id); ?>" name="override_reason" class="form-control form-control-sm mb-2" maxlength="1000" value="<?php echo e($cashOpen ? old('override_reason') : ''); ?>" required>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <label class="form-check small"><input class="form-check-input" type="checkbox" name="confirm_cash_paid" value="1" required><span class="form-check-label">I confirm R<?php echo e(number_format($cashAmounts['net'], 2)); ?> cash was handed to the original payer.</span></label>
    <button class="btn btn-sm btn-outline-warning mt-2">Confirm recorded cash refund</button>
  </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</details>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-selection\_cash-refund-action.blade.php ENDPATH**/ ?>