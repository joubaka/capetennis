<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php ($placesRemaining = app(\App\Services\DivisionCapacityService::class)->remaining($value)); ?>
  <option value="<?php echo e($value->id); ?>"
          data-price="<?php echo e($value->entry_fee); ?>"
          data-name="<?php echo e($value->category->name); ?>"
          <?php if(($preselectedCategoryId ?? null) == $value->id): echo 'selected'; endif; ?>
          <?php if($placesRemaining === 0): echo 'disabled'; endif; ?>>
    <?php echo e($value->category->name); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($placesRemaining !== null): ?> — <?php echo e($placesRemaining === 0 ? 'Full' : $placesRemaining.' '.\Illuminate\Support\Str::plural('spot', $placesRemaining).' left'); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </option>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\division-entry-options.blade.php ENDPATH**/ ?>