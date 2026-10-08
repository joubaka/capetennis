<?php
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Error - Pages'); ?>

<?php $__env->startSection('page-style'); ?>
<!-- Page -->
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-misc.css')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<!-- Error -->
<div class="container-xxl container-p-y">
  <div class="misc-wrapper">
    <h2 class="mb-1 mt-4">Page Not Found :(</h2>
    <p class="mb-4 mx-2">Oops! 😖 The requested URL was not found on this server.</p>
    <a href="<?php echo e(url('/')); ?>" class="btn btn-primary mb-4">Back to home</a>
    <div class="mt-4">
      <img src="<?php echo e(asset('assets/img/illustrations/page-misc-error.png')); ?>" alt="page-misc-error" width="225" class="img-fluid">
    </div>
  </div>
</div>
<div class="container-fluid misc-bg-wrapper">
  <img src="<?php echo e(asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png')); ?>" alt="page-misc-error" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
</div>
<!-- /Error -->
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\content\pages\pages-misc-error.blade.php ENDPATH**/ ?>