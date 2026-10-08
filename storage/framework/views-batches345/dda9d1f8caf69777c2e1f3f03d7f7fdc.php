<?php
$breadcrumbs = [['link' => 'home', 'name' => 'Home'], ['name' => 'API Tokens']];
?>

<?php $__env->startSection('title', 'API Tokens'); ?>


<?php $__env->startSection('page-style'); ?>
  
  <link rel="stylesheet" href="<?php echo e(asset(mix('assets/vendor/css/pages/page-auth.css'))); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\api\index.blade.php ENDPATH**/ ?>