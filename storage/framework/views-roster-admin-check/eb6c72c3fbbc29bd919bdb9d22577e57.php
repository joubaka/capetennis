<?php $__env->startSection('title', 'Mail history'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="h3 mb-1">Mail history</h1><p class="text-muted mb-0">Check recipients, sending progress and acceptance evidence.</p></div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.superadmin.index')); ?>">Dashboard</a>
</div>
  <?php echo $__env->make('backend.superadmin.partials.mail-history', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\mail-history.blade.php ENDPATH**/ ?>