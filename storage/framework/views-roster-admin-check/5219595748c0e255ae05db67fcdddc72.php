

<?php $__env->startSection('title', 'Event Details'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/swiper/swiper.css')); ?>" />
<?php $__env->stopSection(); ?>

<!-- Page -->
<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/ui-carousel.css')); ?>" />
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/swiper/swiper.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/photo-details.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="container">
<div class="row">
   <div class="col-6 ">
        <div class="card d-flex justify-content-center">
            <img src="<?php echo e(asset('storage/photoFolder/'.$image->path)); ?>" class="w-100 shadow-1-strong rounded " alt="Boat on Calm Water" />

        
        </div>


    </div>
    <div class="col-6 ">
    <button id="buy-button" data-image="<?php echo e($image); ?>" class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEnd" aria-controls="offcanvasEnd">Buy this photo</button>



    </div>

</div>
 
</div>

<?php echo $__env->make('frontend.photo._includes.buy-photo-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\photo\photo-details.blade.php ENDPATH**/ ?>