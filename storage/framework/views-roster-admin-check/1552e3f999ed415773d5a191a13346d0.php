

<?php $__env->startSection('title', 'Cancel'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/bs-stepper/bs-stepper.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/rateyo/rateyo.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>" />

<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?> " />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/bs-stepper/bs-stepper.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/rateyo/rateyo.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/wizard-ex-checkout.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<script src="<?php echo e(asset('assets/js/wizard-ex-checkout.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/forms-selects.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<h4 class="fw-bold py-3 mb-4">
  <span class="text-muted fw-light">Cancelled</span> 
</h4>
<div class="text-center mt-4">
 <img src="<?php echo e(asset('assets/img/pages/cancelled.png')); ?>" alt="paid">
</div>
<div class="text-center mt-4">
<h1><a href="<?php echo e(route('home')); ?>">Return to Home Page</a></h1>
</div>


<?php echo $__env->make('_partials/_modals/modal-add-new-address', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\cancel.blade.php ENDPATH**/ ?>