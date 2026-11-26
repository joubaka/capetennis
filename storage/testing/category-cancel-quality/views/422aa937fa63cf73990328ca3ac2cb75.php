<?php $__env->startSection('title', 'Team round-robin scoring'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-4">
  <h1 class="h4 mb-3">Team round-robin scoring</h1>
  <?php echo $__env->make('backend.team-draw.partials.round-robin-matrix', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <a class="btn btn-outline-primary" href="<?php echo e(route('frontend.fixtures.enter-scores', ['draw' => $draw->id, 'view' => 'matches'])); ?>">View all matches</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixtures\team-round-robin-scoring.blade.php ENDPATH**/ ?>