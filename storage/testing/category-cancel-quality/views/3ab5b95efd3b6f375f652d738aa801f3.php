<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [2, 3]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emailNumber): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="col-md-6 mb-3">
    <label class="form-label" for="<?php echo e($emailIdPrefix ?? 'player-contact'); ?>-email-<?php echo e($emailNumber); ?>">Email <?php echo e($emailNumber); ?> (optional)</label>
    <input type="email" maxlength="255" class="form-control <?php $__errorArgs = ['email_'.$emailNumber];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
           id="<?php echo e($emailIdPrefix ?? 'player-contact'); ?>-email-<?php echo e($emailNumber); ?>" name="email_<?php echo e($emailNumber); ?>"
           value="<?php echo e(old('email_'.$emailNumber, $contactPlayer?->{'email_'.$emailNumber})); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email_'.$emailNumber];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <small class="text-muted">Receives player updates and announcements. Does not grant account access.</small>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player\partials\additional-emails.blade.php ENDPATH**/ ?>