<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" name="terms_accepted" value="1" id="<?php echo e($termsCheckboxId); ?>" required>
  <label class="form-check-label" for="<?php echo e($termsCheckboxId); ?>">I have read and accept the registration Terms &amp; Conditions, including the withdrawal and refund terms above.</label>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['terms_accepted'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger mb-2" role="alert"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\partials\registration-terms-acceptance.blade.php ENDPATH**/ ?>