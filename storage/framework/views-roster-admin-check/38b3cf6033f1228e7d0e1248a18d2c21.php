

<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header"><a href="<?php echo e(URL::previous()); ?>" class="btn btn-primary">Back</a></div>
    
</div>
<div class="card">
  

</div>



<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\player\player_details.blade.php ENDPATH**/ ?>