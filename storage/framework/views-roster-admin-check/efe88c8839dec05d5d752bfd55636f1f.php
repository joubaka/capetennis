

<?php $__env->startSection('content'); ?>
<div class="container py-5 text-center">
    <h3 class="text-success mb-3">✅ Registration Complete</h3>
    <p>Your registration has been successfully completed.</p>
    <a href="<?php echo e(url('/')); ?>" class="btn btn-primary mt-3">Back to Events</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\registration_success.blade.php ENDPATH**/ ?>