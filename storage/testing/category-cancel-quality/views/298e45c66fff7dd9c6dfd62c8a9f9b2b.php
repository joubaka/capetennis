<?php
  $invitation = $presentation['invitation'];
  $hasNominationHistory = $nomination->archived_at || $nomination->removed_at
    || ($invitation && ($invitation->queued_at || $invitation->sent_at || $invitation->accepted_at || $invitation->paid_at || $invitation->withdrawn_at));
?>
<div class="interpro-nomination-row" role="row" data-nomination-row data-nomination-state="<?php echo e($presentation['filter_key']); ?>">
  <div class="interpro-nomination-row__identity" role="cell">
    <div class="fw-semibold"><?php echo e($nomination->display_name); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id === null): ?><span class="badge bg-label-warning">Profile required</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id === null && !($presentation['historical'] ?? false)): ?>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.email', [$event, $categoryEvent, $nomination])); ?>" class="mt-2">
        <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <label class="form-label small" for="nominee-email-<?php echo e($nomination->id); ?>">Invitation email (optional)</label>
        <input id="nominee-email-<?php echo e($nomination->id); ?>" type="email" name="nominee_email" value="<?php echo e($nomination->nominee_email); ?>" maxlength="255" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-primary mt-1">Save email</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="text-muted small interpro-row-category"><?php echo e($categoryEvent->category?->name ?? 'Category unavailable'); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation?->recipient_email): ?>
      <a class="small interpro-email" href="mailto:<?php echo e(rawurlencode($invitation->recipient_email)); ?>"><?php echo e($invitation->recipient_email); ?></a>
    <?php elseif($invitation): ?>
      <span class="small text-muted">Email not available</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__status" role="cell">
    <span class="interpro-cell-label" aria-hidden="true">Status</span>
    <span class="badge bg-label-<?php echo e($presentation['tone']); ?>"><?php echo e($invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id ? 'Paid privately' : $presentation['label']); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->archived_at): ?><span class="badge bg-label-secondary">Archived nomination</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation?->status === 'paid_confirmed' && $invitation->registration_id && !$invitation->order_id): ?><span class="small text-muted">Private collection; not reconciled</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation && in_array($invitation->status, ['queued', 'sending', 'sent'], true)): ?>
      <span class="small text-muted"><?php echo e(str($invitation->status)->title()); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__timeline small" role="cell">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasNominationHistory): ?>
    <span class="interpro-cell-label" aria-hidden="true">Registration history</span>
    <details class="interpro-history"><summary>View history</summary><div class="interpro-history-content">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
        'Queued' => $invitation->queued_at,
        'Sent' => $invitation->sent_at,
        'Accepted' => $invitation->accepted_at,
        'Paid' => $invitation->paid_at,
        'Withdrawn' => $invitation->withdrawn_at,
      ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $timestampLabel => $timestamp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timestamp): ?><span><span class="text-muted"><?php echo e($timestampLabel); ?>:</span> <?php echo e($timestamp->format('d M Y H:i')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->archived_at): ?><span>Archived: <?php echo e($nomination->archived_at->format('d M Y H:i')); ?></span><span class="text-muted"><?php echo e($nomination->archive_reason); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->removed_at): ?><span>Removed: <?php echo e(\Illuminate\Support\Carbon::parse($nomination->removed_at)->format('d M Y H:i')); ?></span><span class="text-muted"><?php echo e($nomination->removed_by_name ?: 'Actor not recorded'); ?> · <?php echo e($nomination->removal_reason ?: 'Reason not recorded'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__actions" role="cell">
    <span class="interpro-cell-label w-100" aria-hidden="true">Actions</span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['reactivation_revision'] ?? null): ?>
      <details><summary class="btn btn-sm btn-outline-primary interpro-row-action">Re-add nomination</summary>
        <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.reactivate', [$event, $categoryEvent, $nomination->id])); ?>" class="mt-2">
          <?php echo csrf_field(); ?><input type="hidden" name="revision" value="<?php echo e($presentation['reactivation_revision']); ?>">
          <label class="form-label small" for="reactivation-reason-<?php echo e($nomination->id); ?>">Reason for re-adding</label>
          <input id="reactivation-reason-<?php echo e($nomination->id); ?>" name="reason" class="form-control form-control-sm" minlength="5" maxlength="1000" required>
          <p class="small text-muted">Re-adds this player to the same category. If nominations are public, the player will appear publicly again. Previous history is retained. An exact existing active private registration is reconnected without creating another entry or changing its private collection note. Otherwise registration and payment must be completed separately. No email is sent. Cancelled unpaid history is retained; other registration history needs review in Entries.</p>
          <a class="small d-block mb-2" href="<?php echo e(route('admin.events.entries.new', $event)); ?>">Manage registration in Entries</a>
          <label class="small d-block"><input type="checkbox" name="confirm" value="1" required> Confirm the player is available again</label>
          <button class="btn btn-sm btn-primary mt-2 interpro-row-action">Confirm re-add</button>
        </form>
      </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['historical'] ?? false): ?>
      <span class="small text-muted">Retained history</span>
    <?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['can_administer']): ?>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.administer', [$event, $categoryEvent, $nomination])); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="decline">
        <button class="btn btn-sm btn-outline-secondary interpro-row-action">Decline</button>
      </form>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id): ?>
        <details><summary class="btn btn-sm btn-outline-success interpro-row-action">Mark paid manually</summary>
          <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.administer', [$event, $categoryEvent, $nomination])); ?>" class="mt-2">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="paid_privately">
            <p class="small text-muted">Creates an entry with private collection marked paid. No payment amount or reconciled receipt is recorded.</p>
            <button class="btn btn-sm btn-success interpro-row-action">Confirm paid privately</button>
          </form>
        </details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php elseif($invitation?->status === 'accepted_pending_payment' && $invitation->order_id): ?>
      <details><summary class="btn btn-sm btn-outline-success interpro-row-action">Mark paid manually</summary>
        <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.orders.mark-paid', [$event, $invitation->order_id])); ?>" class="mt-2">
          <?php echo csrf_field(); ?><label class="form-label small" for="manual-reference-<?php echo e($nomination->id); ?>">Payment reference</label>
          <input id="manual-reference-<?php echo e($nomination->id); ?>" name="reference" class="form-control form-control-sm" minlength="3" maxlength="120" required>
          <p class="small text-muted">Records the exact checkout amount as received. Payments already in progress cannot be marked manually.</p>
          <button class="btn btn-sm btn-success interpro-row-action">Record payment received</button>
        </form>
      </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['can_resend']): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary interpro-row-action" data-send-preview-mode="individual" data-invitation-id="<?php echo e($invitation->id); ?>">Resend invitation</button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['can_retry_follow_up']): ?>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.invitations.retry-follow-up', [$event, $invitation])); ?>">
        <?php echo csrf_field(); ?>
        <button class="btn btn-sm btn-outline-primary interpro-row-action">Retry follow-up</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation?->status === 'failed'): ?>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.invitations.retry', [$event, $invitation->batch_id, $invitation])); ?>">
        <?php echo csrf_field(); ?>
        <button class="btn btn-sm btn-outline-primary interpro-row-action">Retry</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presentation['can_remove']): ?>
      <form class="nomination-remove-form" method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.destroy', [$event, $categoryEvent, $nomination])); ?>">
        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
        <button class="btn btn-sm btn-outline-danger interpro-row-action">Remove</button>
      </form>
    <?php else: ?>
      <a class="small" href="<?php echo e(route('admin.events.entries.new', $event)); ?>">Manage registration in Entries</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\interprovincial-trials\_nomination-row.blade.php ENDPATH**/ ?>