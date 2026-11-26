<?php $__env->startSection('title', 'Cancel category'); ?>
<?php $__env->startSection('content'); ?>
<div class="card"><div class="card-body">
  <h1 class="h4">Cancel <?php echo e($categoryEvent->category->name); ?></h1>
  <p><?php echo e($event->name); ?></p>
  <a href="<?php echo e(route('admin.events.entries.new', $event)); ?>" class="btn btn-outline-secondary mb-3">Back to entries</a>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="mb-1"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryEvent->isCancelled()): ?>
    <div class="alert alert-info">This category was cancelled on <?php echo e($categoryEvent->cancelled_at->format('d M Y H:i')); ?>. It is closed to new entries.</div>
    <p><?php echo e($categoryEvent->cancellation['snapshot']['reason'] ?? ''); ?></p>
    <p><?php echo e($categoryEvent->cancellation['entries'] ?? 0); ?> entries · R<?php echo e(number_format($categoryEvent->cancellation['total'] ?? 0, 2)); ?> refunded to original payer wallets.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryEvent->cancellation['emails_suppressed'] ?? false): ?>
      <p class="alert alert-info">Emails were deliberately not sent or queued for this cancellation.</p>
    <?php elseif($categoryEvent->cancellation['batch_id'] ?? null): ?>
      <a href="<?php echo e(route('backend.event-communications.index', $event)); ?>?batch=<?php echo e($categoryEvent->cancellation['batch_id']); ?>">View email report</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php elseif(!$preview): ?>
    <p>Review the affected players and contacts before cancelling. Confirmation closes this category, withdraws its entries, refunds full verified entry fees to the original payers’ wallets with no handling fee, and queues the notices. This option is available before a draw is created; unresolved payments require finance review first.</p>
    <form method="POST" action="<?php echo e(route('admin.category.cancel.preview', [$event, $categoryEvent])); ?>"><?php echo csrf_field(); ?>
      <label class="form-label" for="reason">Reason for cancellation</label>
      <textarea id="reason" name="reason" class="form-control mb-3" required maxlength="2000" rows="4"><?php echo e(old('reason', 'Unfortunately, we have not received sufficient entries to run this category.')); ?></textarea>
      <input type="hidden" name="send_emails" value="0">
      <label class="d-block mb-1"><input type="checkbox" name="send_emails" value="1" <?php if(old('send_emails', true)): echo 'checked'; endif; ?>> Send cancellation and refund emails</label>
      <p class="small text-muted">Clear this if players have already been notified. No cancellation notices or refund receipts will be sent or queued.</p>
      <button class="btn btn-danger">Review cancellation</button>
    </form>
  <?php else: ?>
    <h2 class="h5">Review cancellation</h2>
    <p><strong><?php echo e(count($preview['rows'])); ?> entries · R<?php echo e(number_format($preview['total'], 2)); ?> full wallet refunds</strong></p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Player</th><th>Wallet credit</th><th>Original payer</th><th><?php echo e($preview['send_emails'] ? 'Notification contacts' : 'Contacts on record (not emailed)'); ?></th></tr></thead><tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($row['player']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['status'] === 'withdrawn'): ?><small>(already withdrawn)</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td>R<?php echo e(number_format($row['amount'], 2)); ?></td><td><?php echo e($row['funding']['payer_name'] ?? 'No captured payment — no refund'); ?><br><?php echo e($row['funding']['to'] ?? ''); ?></td><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['emails']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $email): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($email); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody></table></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['send_emails']): ?>
    <p>Player contacts receive a notification-only cancellation notice, once per address. Each original payer receives their own refund confirmation. Entries without a captured payment receive no wallet credit.</p>
    <h2 class="h5">Email preview</h2><p><strong><?php echo e($preview['subject']); ?></strong></p><div class="border rounded p-3 mb-3" style="white-space:pre-wrap"><?php echo e($preview['message']); ?></div>
    <p class="small text-muted">From: <?php echo e($preview['sender']['from_name']); ?> · Reply to: <?php echo e($preview['sender']['reply_to']); ?></p>
    <?php else: ?>
      <div class="alert alert-warning"><strong>No emails will be sent or queued.</strong> Cancellation notices and refund receipts are both disabled for this confirmed cancellation. Existing emails are not repeated or changed.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('admin.category.cancel.store', [$event, $categoryEvent])); ?>"><?php echo csrf_field(); ?>
      <input type="hidden" name="preview_token" value="<?php echo e($preview['token']); ?>">
      <input type="hidden" name="reason" value="<?php echo e($preview['reason']); ?>">
      <input type="hidden" name="send_emails" value="<?php echo e($preview['send_emails'] ? '1' : '0'); ?>">
      <label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> <?php echo e($preview['send_emails'] ? 'I confirm cancellation, full wallet refunds and the emails shown above.' : 'I confirm cancellation and full wallet refunds with no emails sent or queued.'); ?></label>
      <button class="btn btn-danger">Cancel category and refund wallets</button>
      <a href="<?php echo e(route('admin.category.cancel.show', [$event, $categoryEvent])); ?>" class="btn btn-outline-secondary">Edit reason</a>
    </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\individual\cancel-category.blade.php ENDPATH**/ ?>