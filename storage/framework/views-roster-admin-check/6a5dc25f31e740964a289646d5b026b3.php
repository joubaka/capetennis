
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
<div class="card mb-4 border border-warning">
  <div class="card-header bg-warning text-dark">
    <small class="card-text text-uppercase fw-bold">
      Admin Draw List
    </small>
  </div>

  <div class="card-body">

    
    <h6 class="fw-bold text-danger mb-3">Unpublished Draws</h6>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventDraws->where('published', false)
        ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

      <div class="fw-bold mt-2"><?php echo e($typeName); ?></div>

      <div class="d-flex flex-wrap gap-2 mt-1">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="btn-group">

            
            <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
               class="btn btn-sm btn-outline-danger">
              <?php echo e($draw->drawName); ?>

              <span class="badge bg-danger ms-1">Not published</span>
            </a>

            
            <a href="<?php echo e(route('backend.roundrobin.admin.scores', $draw->id)); ?>"
               class="btn btn-sm btn-warning">
              Enter Scores
            </a>

          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="alert alert-secondary m-0">No unpublished draws.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    
    <h6 class="fw-bold text-success mt-4 mb-3">Published Draws</h6>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventDraws->where('published', true)
        ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

      <div class="fw-bold mt-2"><?php echo e($typeName); ?></div>

      <div class="d-flex flex-wrap gap-2 mt-1">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="btn-group">

            
            <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
               class="btn btn-sm btn-<?php echo e($draw->draw_types?->btn_color ?? 'primary'); ?>">
              <?php echo e($draw->drawName); ?>

            </a>

            
            <a href="<?php echo e(route('backend.roundrobin.admin.scores', $draw->id)); ?>"
               class="btn btn-sm btn-warning">
              Enter Scores
            </a>

          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="alert alert-secondary m-0">No published draws.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\interpro-admin-drawlist.blade.php ENDPATH**/ ?>