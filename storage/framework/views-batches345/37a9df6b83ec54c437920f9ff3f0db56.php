<?php ($invitation = $presentation['invitation']); ?>
<div class="interpro-nomination-row" data-nomination-row data-nomination-state="<?php echo e($presentation['filter_key']); ?>">
  <div class="interpro-nomination-row__identity">
    <div class="fw-semibold"><?php echo e($nomination->display_name); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id === null): ?><span class="badge bg-label-warning">Profile required</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id === null): ?>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.email', [$event, $categoryEvent, $nomination])); ?>" class="mt-2">
        <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <label class="form-label small" for="nominee-email-<?php echo e($nomination->id); ?>">Invitation email (optional)</label>
        <input id="nominee-email-<?php echo e($nomination->id); ?>" type="email" name="nominee_email" value="<?php echo e($nomination->nominee_email); ?>" maxlength="255" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-primary mt-1">Save email</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="text-muted small"><?php echo e($categoryEvent->category?->name ?? 'Category unavailable'); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation?->recipient_email): ?>
      <a class="small interpro-email" href="mailto:<?php echo e(rawurlencode($invitation->recipient_email)); ?>"><?php echo e($invitation->recipient_email); ?></a>
    <?php elseif($invitation): ?>
      <span class="small text-danger">No linked account email</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__status">
    <span class="badge bg-label-<?php echo e($presentation['tone']); ?>"><?php echo e($presentation['label']); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation && in_array($invitation->status, ['queued', 'sending', 'sent'], true)): ?>
      <span class="small text-muted"><?php echo e(str($invitation->status)->title()); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__timeline small">
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
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$invitation || (!$invitation->queued_at && !$invitation->sent_at && !$invitation->accepted_at && !$invitation->paid_at && !$invitation->withdrawn_at)): ?>
      <span class="text-muted">No lifecycle timestamps yet</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="interpro-nomination-row__actions">
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
      <span class="small text-muted">History retained</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\interprovincial-trials\_nomination-row.blade.php ENDPATH**/ ?>