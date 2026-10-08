<?php $__env->startSection('title', 'Write email'); ?>
<?php $__env->startSection('content'); ?>
<div data-mail-compose-fragment>
<form method="POST" action="<?php echo e(route('backend.event-communications.preview', $event)); ?>" class="modal-content" data-mail-compose>
<?php echo csrf_field(); ?>
<div class="modal-header"><h5 class="modal-title"><?php echo e($event->name); ?> — Write email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_scalar($value)): ?><input type="hidden" name="<?php echo e($field); ?>" value="<?php echo e($value); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<label class="form-label w-100">Subject<input name="subject" class="form-control" value="<?php echo e($subject); ?>" required maxlength="200"></label>
<label class="form-label w-100">Message<textarea name="body" class="form-control" required rows="7" maxlength="30000"><?php echo e($body); ?></textarea></label>
<p class="form-text">Review the exact recipients and one example before approving.</p>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Preview email</button></div>
</form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\communications\compose.blade.php ENDPATH**/ ?>