<?php $__env->startSection('title', 'Choose players category — '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto" style="max-width:650px">
  <a href="<?php echo e(route('draw.setup.show', $draw)); ?>" class="btn btn-sm btn-outline-secondary mb-4">Back to draw format</a>
  <p class="text-primary mb-1"><?php echo e($label); ?></p>
  <h3>Which category will play?</h3>
  <p class="text-muted">Choose a category from this event, then place its players in the bracket.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="POST" action="<?php echo e(route('draw.setup.store', $draw)); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="workflow" value="<?php echo e($workflow); ?>">
    <label for="setup-category" class="form-label">Player category</label>
    <select id="setup-category" name="category_event_id" class="form-select mb-3" required>
      <option value="">Choose a category</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($category->id); ?>">
          <?php echo e($category->category?->name ?? 'Category #'.$category->id); ?> · <?php echo e($category->eligible_draw_entries_count); ?> paid active <?php echo e(Str::plural('player', $category->eligible_draw_entries_count)); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <p class="form-text">Player placement starts with paid, active entries in the selected category.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->isEmpty()): ?><p class="alert alert-warning">Add a player category to the event first.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button class="btn btn-primary" <?php if($categories->isEmpty()): echo 'disabled'; endif; ?>>Continue to player placement →</button>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\setup-category.blade.php ENDPATH**/ ?>