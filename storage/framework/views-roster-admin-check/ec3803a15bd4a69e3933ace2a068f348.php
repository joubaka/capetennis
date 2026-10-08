

<?php $__env->startSection('title', $event->name . ' – Results'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Results</h4>
        <div class="text-muted"><?php echo e($event->name); ?></div>
      </div>
      <a href="<?php echo e(route('events.show', $event->id)); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i> Back to Event
      </a>
    </div>
  </div>

  <div class="row">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php $results = $categoryResults->get($category->category_id, collect()); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($results->count() > 0): ?>
        <div class="col-xl-3 col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-header">
              <h5 class="mb-0"><?php echo e($category->category->name); ?></h5>
            </div>
            <div class="card-body">
              <ul class="list-group list-group-flush">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $results; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <li class="list-group-item d-flex align-items-center gap-2 py-2">
                    <span class="badge bg-label-success rounded p-2"><?php echo e($result->position); ?></span>
                    <span><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $result->registration->players,'context' => $category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($result->registration->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?></span>
                  </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </ul>
            </div>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="col-12">
        <div class="alert alert-warning">No categories found for this event.</div>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\results\show.blade.php ENDPATH**/ ?>