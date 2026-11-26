
<?php $__env->startSection('title', 'Review Trials messages'); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php ($nomineeEmail = ! empty($preview->options['nominee_email'])); ?>
<div data-mail-review>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomineeEmail): ?>
    <h4>Review nominee email — <?php echo e($event->name); ?></h4>
<?php else: ?>
    <h4>Review exact recipients and messages — <?php echo e($event->name); ?></h4>
    <p><?php echo e(count($preview->recipients)); ?> combined emails. Preview expires <?php echo e($preview->expires_at); ?>. Changed contacts or statuses require a new preview.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.partials.email-sample', ['mailRecipients' => $preview->recipients, 'mailSender' => $preview->options, 'mailSampleHeading' => $nomineeEmail ? 'Email to send' : 'Example email'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($preview->excluded)): ?><div class="alert alert-warning"><strong>Contacts requiring follow-up</strong><ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview->excluded; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $excluded): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($excluded['player']); ?> — <?php echo e($excluded['reason']); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p class="small text-muted">Sending submits the reviewed emails for background delivery. Delivery may continue after you leave this page.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomineeEmail): ?>
    <form class="d-flex flex-wrap gap-2" method="post" action="<?php echo e(route('backend.interprovincial-trials.communications.send', $event)); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo e($preview->token); ?>">
        <button class="btn btn-primary" <?php if(! count($preview->recipients)): echo 'disabled'; endif; ?>>Send <?php echo e(count($preview->recipients)); ?> <?php echo e(Str::plural('email', count($preview->recipients))); ?></button>
        <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.interprovincial-trials.communications.nominees.compose', ['event' => $event, 'draft' => $preview->id])); ?>">Edit email</a>
    </form>
<?php else: ?>
<form method="post" action="<?php echo e(route('backend.interprovincial-trials.communications.send',$event)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="token" value="<?php echo e($preview->token); ?>"><label class="d-block mb-3"><input type="checkbox" required> I confirm the recipients and example email are correct.</label><button class="btn btn-primary" <?php if(!count($preview->recipients)): echo 'disabled'; endif; ?>>Confirm and send <?php echo e(count($preview->recipients)); ?> emails now</button><a class="btn btn-outline-secondary" href="<?php echo e(route('backend.interprovincial-trials.communications.index',$event)); ?>">Return to editing</a></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($nomineeEmail)): ?>
<hr><h5>Or save a reminder for later review</h5><p>Nothing sends automatically. Each occurrence requires a fresh exact recipient and message preview followed by manual approval.</p><form method="post" action="<?php echo e(route('backend.interprovincial-trials.communications.schedules',$event)); ?>" class="card card-body"><?php echo csrf_field(); ?><input type="hidden" name="token" value="<?php echo e($preview->token); ?>"><label class="form-label">First review due</label><input type="datetime-local" name="start" class="form-control mb-2" required><label class="form-label">Repeat every number of hours (blank for one reminder)</label><input type="number" name="repeat_hours" min="1" max="8760" class="form-control mb-2"><label class="form-label">Stop reminders after (optional)</label><input type="datetime-local" name="stop" class="form-control mb-3"><button class="btn btn-outline-primary">Save reminder — do not send</button></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\interprovincial-trials\communication-preview.blade.php ENDPATH**/ ?>