

<?php $__env->startSection('title', 'View Agreement'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1"><?php echo e($agreement->title); ?></h4>
        <span class="text-muted">Version: <strong><?php echo e($agreement->version); ?></strong></span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($agreement->is_active): ?>
          <span class="badge bg-success ms-2">Active</span>
        <?php else: ?>
          <span class="badge bg-secondary ms-2">Inactive</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <a href="<?php echo e(route('backend.agreements.index')); ?>" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left"></i> Back
      </a>
    </div>
  </div>

  
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Agreement Content</h5>
    </div>
    <div class="card-body" style="max-height: 500px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 1.5rem;">
      <?php echo $agreement->content; ?>

    </div>
  </div>

  
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Acceptances (<?php echo e($acceptances->count()); ?>)</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Player</th>
            <th>Accepted By</th>
            <th>Guardian</th>
            <th>IP Address</th>
            <th>Accepted At</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $acceptances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acceptance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($acceptance->player->name ?? 'N/A'); ?> <?php echo e($acceptance->player->surname ?? ''); ?></td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($acceptance->accepted_by_type === 'guardian'): ?>
                  <span class="badge bg-info">Guardian</span>
                <?php else: ?>
                  <span class="badge bg-primary">Player</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($acceptance->guardian_name): ?>
                  <?php echo e($acceptance->guardian_name); ?><br>
                  <small class="text-muted"><?php echo e($acceptance->guardian_email); ?></small><br>
                  <small class="text-muted"><?php echo e($acceptance->guardian_relationship); ?></small>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td><small><?php echo e($acceptance->ip_address); ?></small></td>
              <td><?php echo e($acceptance->accepted_at->format('d M Y H:i')); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">No acceptances yet.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\agreements\show.blade.php ENDPATH**/ ?>