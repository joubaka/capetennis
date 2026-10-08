

<div class="mb-3 d-flex justify-content-between align-items-center">
  <div>
    <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#createDrawModal">
      <i class="fas fa-plus"></i> Create Draw
    </button>
    <button class="btn btn-danger">
      <i class="fas fa-sort"></i> Change Draw Order
    </button>
  </div>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="border rounded p-3 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div class="flex-grow-1">
        <h5 class="mb-2"><?php echo e($draw->drawName); ?></h5>

        
        <div class="mb-2">
          <span class="badge bg-warning">Individual</span>
          <span class="badge bg-primary">Tennis (Singles)</span>
          <span class="badge bg-danger"><?php echo e($draw->registrations_count); ?> players</span>
          <span class="badge bg-dark"><?php echo e($draw->gender ?? 'Mixed'); ?></span>
          <span class="badge bg-<?php echo e($draw->locked ? 'warning' : 'info'); ?>">
            <?php echo e($draw->locked ? '🔒 Locked' : '🔓 Unlocked'); ?>

          </span>
        </div>

        
        <div class="text-primary small fw-bold mb-1">
          <?php echo e($draw->completion_percent ?? '0%'); ?> Complete
        </div>
        <div class="progress" style="height: 6px; max-width: 300px;">
          <div class="progress-bar bg-primary" role="progressbar"
               style="width: <?php echo e($draw->completion_percent ?? '0%'); ?>%;"></div>
        </div>
      </div>

      
     
<div class="d-flex align-items-start gap-2 flex-wrap">
  <a href="<?php echo e(route('engine.draw.show', $draw->id)); ?>" class="btn btn-sm btn-danger">
    <i class="fas fa-cogs"></i> Engine
  </a>
  <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw->id)); ?>#settings" class="btn btn-sm btn-warning">
    <i class="fas fa-cog"></i> Settings
  </a>
  <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw->id)); ?>#groups" class="btn btn-sm btn-primary">
    <i class="fas fa-users"></i> Players
  </a>
  <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw->id)); ?>#matrix" class="btn btn-sm btn-success">
    <i class="fas fa-eye"></i> View Draw
  </a>
</div>


    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="modal fade" id="createDrawModal" tabindex="-1" aria-labelledby="createDrawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="<?php echo e(route('draws.generate.from.modal')); ?>">
      <?php echo csrf_field(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
         <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">

          <div class="mb-3">
            <label for="draw_name" class="form-label">Draw Name</label>
            <input type="text" name="draw_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="draw_format_id" class="form-label">Draw Format</label>
            <select name="draw_format_id" class="form-select" required>
              <option value="1">Knockout</option>
              <option value="2">Feed-In</option>
              <option value="3">Round Robin</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Create Draw</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\partials\draws.blade.php ENDPATH**/ ?>