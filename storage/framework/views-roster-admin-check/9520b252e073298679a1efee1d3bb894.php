

<?php $__env->startSection('title', 'Email record'); ?>

<?php $__env->startSection('content'); ?>

<?php echo $__env->make('backend.event.mail-log.feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<a class="btn btn-outline-secondary mb-3" href="<?php echo e(route('backend.event-mail-log.index',$event)); ?>">Back to email log</a>

<div class="card"><div class="card-body" style="overflow-wrap:anywhere"><h1 class="h4"><?php echo e(\App\Services\EventMailLogService::subject($log)); ?></h1><p><?php echo e($event->name); ?></p><dl class="row"><dt class="col-sm-3">Recipient</dt><dd class="col-sm-9"><?php echo e($log->recipient_name); ?> <?php echo e($log->recipient_email); ?></dd><dt class="col-sm-3">Sender</dt><dd class="col-sm-9"><?php echo e(data_get($log->payload,'from_name','Not recorded')); ?></dd><dt class="col-sm-3">Audience</dt><dd class="col-sm-9"><?php echo e(data_get($log->payload,'recipient_kind',\App\Services\SuperAdminMailHistory::typeLabel($log->mail_type))); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($log->payload,'region_id')): ?> · Region #<?php echo e(data_get($log->payload,'region_id')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($log->payload,'team_id')): ?> · Team #<?php echo e(data_get($log->payload,'team_id')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd><dt class="col-sm-3">Campaign</dt><dd class="col-sm-9"><?php echo e(data_get($log->payload,'campaign_key',data_get($log->payload,'event_communication_batch_id','Not recorded'))); ?></dd><dt class="col-sm-3">Outcome</dt><dd class="col-sm-9"><?php echo e($log->delivery_status_label); ?></dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['queued_at'=>'Queued','sent_at'=>'Transport completed','accepted_at'=>'Server accepted','failed_at'=>'Failed','skipped_at'=>'Skipped']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->$field): ?><dt class="col-sm-3"><?php echo e($label); ?></dt><dd class="col-sm-9"><?php echo e($log->$field->format('d M Y H:i:s')); ?> SAST</dd><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dl>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reason=\App\Services\EventMailLogService::explanation($log)): ?><div class="alert alert-warning" style="color:#513c06;background:#fff3cd;border-color:#ffe69c"><?php echo e($reason); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<h2 class="h5">Saved message text</h2>

<p>Initiated by: <?php echo e(isset($sender) && $sender ? $sender->name.' (#'.$sender->id.')' : (data_get($log->payload,'system_initiated') ? 'System initiated' : 'Not recorded')); ?></p>

<?php ($body=(data_get($log->payload,'rendered_html') ?? data_get($log->payload,'rendered_text') ?? data_get($log->payload,'body') ?? data_get($log->payload,'message'))); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($body): ?><div class="border rounded p-3" style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo e(trim(strip_tags(preg_replace('/<\/(p|div|li|h[1-6])>|<br\s*\/?\s*>/i',"\n",$body)))); ?></div><?php elseif(data_get($log->payload,'financial_metadata_only')): ?><p>This financial email retains metadata only. Payment and bank details are excluded from this log.</p><?php else: ?><p>The message body was not saved for this historical record.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<p class="text-muted mt-3">Message text is displayed without active HTML or links. A completed send or server acceptance does not prove delivery or reading.</p>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\EventMailLogService::canRetry($log)): ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($retryPreview ?? false): ?><form method="post" action="<?php echo e(route('backend.event-mail-log.retry',[$event,$log])); ?>"><?php echo csrf_field(); ?><label class="d-block mb-2"><input type="checkbox" name="confirmed" value="1" required> I reviewed this saved message and recipient and want to retry this failed email.</label><button class="btn btn-primary">Queue reviewed retry</button></form><?php else: ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-mail-log.retry-preview',[$event,$log])); ?>">Review failed email for retry</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->status==='failed' && !$log->sent_at && !$log->accepted_at): ?>

<p>Retry only after reviewing the exact message, recipient and current event access. For reviewed team and Trials messages, use the existing Communications retry preview.</p>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam() || $event->isInterprovincialTrials()): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-communications.index',$event)); ?>">Open Communications</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<h2 class="h5 mt-4">Attempt history</h2>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

<div class="border rounded p-2 mb-2"><strong>Attempt <?php echo e($entry->attempt_number); ?>  -  <?php echo e($entry->status); ?></strong>  -  <?php echo e($entry->recorded_at->format('d M Y H:i:s')); ?><p class="mb-0">Initiated by <?php echo e($entry->actor_id ? 'User #'.$entry->actor_id : 'system / actor not recorded'); ?></p>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($entry->snapshot,'error_message')): ?><p class="mb-0"><?php echo e(data_get($entry->snapshot,'error_message')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<details><summary>Saved attempt snapshot</summary><p><?php echo e(data_get($entry->snapshot,'recipient_email')); ?></p><div style="white-space:pre-wrap"><?php echo e(strip_tags((data_get($entry->snapshot,'payload.rendered_html') ?? data_get($entry->snapshot,'payload.rendered_text') ?? data_get($entry->snapshot,'payload.body') ?? data_get($entry->snapshot,'payload.message') ?? 'Message text unavailable'))); ?></div></details></div>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo e($history->links('pagination::bootstrap-5')); ?>


</div></div>

<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\mail-log\show.blade.php ENDPATH**/ ?>