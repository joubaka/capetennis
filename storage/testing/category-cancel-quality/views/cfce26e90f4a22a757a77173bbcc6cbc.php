<?php $__env->startSection('title', 'Email nominated players'); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page">
    <?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('backend.event.partials.header', ['event' => $event], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="card card-body">
        <h4>Email nominated players</h4>
        <p class="text-muted">Cape Tennis selects all current nominees for this event and finds their player and parent email contacts automatically.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <form method="post" action="<?php echo e(route('backend.interprovincial-trials.communications.nominees.preview', $event)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label" for="nominee-email-subject">Subject</label>
                <input class="form-control" id="nominee-email-subject" name="subject" required maxlength="255" value="<?php echo e(old('subject', $draft?->subject)); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="nominee-email-body">Message</label>
                <textarea class="form-control" id="nominee-email-body" name="body" rows="12" required maxlength="50000" placeholder="Paste your email here"><?php echo e(old('body', $draft?->body)); ?></textarea>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Review email</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user') || (auth()->user()->hasRole('admin') && auth()->user()->is_event_admin($event->id))): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>">Back to nominations</a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.interprovincial-trials.communications.index', $event)); ?>">Back to communications</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\interprovincial-trials\nominee-email.blade.php ENDPATH**/ ?>