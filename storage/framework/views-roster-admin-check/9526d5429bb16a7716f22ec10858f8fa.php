

<?php $__env->startSection('title', 'Create Agreement'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body">
      <h4 class="mb-0">Create New Agreement</h4>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><?php echo e($error); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form action="<?php echo e(route('backend.agreements.store')); ?>" method="POST">
        <?php echo csrf_field(); ?>

        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="<?php echo e(old('title', 'Code of Conduct')); ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Version <span class="text-danger">*</span></label>
          <input type="text" name="version" class="form-control" value="<?php echo e(old('version', 'v1')); ?>" required placeholder="e.g. v1, v2">
        </div>

        <div class="mb-3">
          <label class="form-label">Content (HTML) <span class="text-danger">*</span></label>
          <textarea name="content" class="form-control" rows="15" required><?php echo e(old('content')); ?></textarea>
          <small class="text-muted">You may use HTML for formatting.</small>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-check"></i> Create Agreement
          </button>
          <a href="<?php echo e(route('backend.agreements.index')); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\agreements\create.blade.php ENDPATH**/ ?>