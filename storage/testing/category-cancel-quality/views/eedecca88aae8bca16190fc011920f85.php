<?php ($pathwayOptions = \App\Services\Performance\EventPathwayClassification::HOSTED_LEVELS); ?>
<div class="mb-3">
  <label for="<?php echo e($pathwayFieldId ?? 'event-pathway-level'); ?>" class="form-label"><?php echo e($pathwayFieldLabel ?? 'Default tournament level'); ?></label>
  <select id="<?php echo e($pathwayFieldId ?? 'event-pathway-level'); ?>" name="pathway_level" class="form-select" aria-describedby="<?php echo e($pathwayFieldId ?? 'event-pathway-level'); ?>-help">
    <option value=""><?php echo e($pathwayBlankLabel ?? 'Not classified — choose levels per category'); ?></option>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pathwayOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($code); ?>" <?php if(old('pathway_level', $pathwayValue ?? null) === $code): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </select>
  <p class="form-text" id="<?php echo e($pathwayFieldId ?? 'event-pathway-level'); ?>-help"><?php echo e($pathwayHelp ?? 'Applies to categories using the event default. Override individual categories for mixed-level events. This guides discovery and does not change match rating evidence.'); ?></p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['pathway_level'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-danger small" role="alert"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\partials\pathway-level-field.blade.php ENDPATH**/ ?>