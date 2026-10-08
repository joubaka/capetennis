<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email preview · <?php echo e($subject); ?></title>
</head>
<body style="margin:0;background:#e9edf2;font-family:Arial,Helvetica,sans-serif;color:#263b50">
  <div data-mail-review>
  <div style="max-width:680px;margin:22px auto 0;padding:0 12px">
    <div style="background:#fff;border:2px solid #16876f;border-radius:10px;padding:16px 18px;box-sizing:border-box">
      <strong style="color:#16876f"><?php echo e(($previewOnly ?? true) ? 'Preview only — no email has been sent' : 'Saved invitation email — read-only campaign snapshot'); ?></strong>
      <div style="margin-top:10px"><span style="color:#777180">Sample player:</span> <?php echo e($invitation->player?->full_name ?? 'Player'); ?></div>
      <div style="margin-top:5px"><span style="color:#777180">Subject:</span> <strong><?php echo e($subject); ?></strong></div>
      <div style="margin-top:5px"><strong>From:</strong> <?php echo e($campaign['from_name'] ?? config('mail.from.name')); ?> · <strong>Reply-to:</strong> <?php echo e($campaign['reply_to'] ?? ''); ?></div>
      <div style="margin-top:5px"><span style="color:#777180">Version:</span> <?php echo e(substr($campaign['hash'], 0, 12)); ?></div>
      <p style="margin:10px 0 0;font-size:13px;color:#777180"><?php echo e(($previewOnly ?? true) ? 'Sending is unlocked only for this exact preview. Any change requires a new preview.' : 'This is the exact saved campaign content used for this invitation. Rank and recipient status may have changed since the campaign was first sent.'); ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($recipients)): ?>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #dce3e9"><details><summary>Review all <?php echo e(count($recipients)); ?> recipients</summary><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div style="margin-top:5px"><?php echo e($recipient['name']); ?> · <?php echo e($recipient['email']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></details></div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
  <?php ($sample = collect($previewVariants ?? [])->first()); ?>
  <h5>Example email</h5>
  <iframe title="Example invitation email" sandbox="" srcdoc="<?php echo e(view('emails.team-selection.invitation', ['invitation' => $sample['invitation'] ?? $invitation, 'campaign' => $campaign, 'kind' => $sample['kind'] ?? $kind])->render()); ?>" style="width:100%;height:420px;border:1px solid #ddd"></iframe>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($customSend)): ?>
    <div style="max-width:680px;margin:0 auto 28px;padding:0 12px">
      <form method="POST" action="<?php echo e($customSend['route']); ?>" style="background:#fff;border-radius:10px;padding:16px 18px;box-sizing:border-box">
        <?php echo csrf_field(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $customSend['sender'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($field); ?>" value="<?php echo e($value); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $customSend['invitation_ids']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitationId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="invitation_ids[]" value="<?php echo e($invitationId); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <input type="hidden" name="email_subject" value="<?php echo e($customSend['email_subject']); ?>">
        <textarea name="email_message" hidden><?php echo e($customSend['email_message']); ?></textarea>
        <input type="hidden" name="preview_hash" value="<?php echo e($customSend['preview_hash']); ?>">
        <input type="hidden" name="preview_token" value="<?php echo e($customSend['preview_token']); ?>">
        <label style="display:block"><input type="checkbox" name="confirm_recipients" value="1" required> I confirm these exact recipients and this custom email.</label>
        <button type="submit" style="margin-top:12px;border:0;border-radius:6px;background:#16876f;color:#fff;padding:10px 16px;cursor:pointer">Approve and queue <?php echo e(count($customSend['invitation_ids'])); ?> emails</button>
      </form>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($campaignSend)): ?>
    <form method="POST" action="<?php echo e($campaignSend['route']); ?>">
      <?php echo csrf_field(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $campaignSend['data']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_scalar($value)): ?><input type="hidden" name="<?php echo e($field); ?>" value="<?php echo e($value); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <label><input type="checkbox" required> I confirm the recipients and example invitation are correct.</label>
      <button class="btn btn-primary" type="submit">Approve and queue invitations</button>
    </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-selection\email-preview.blade.php ENDPATH**/ ?>