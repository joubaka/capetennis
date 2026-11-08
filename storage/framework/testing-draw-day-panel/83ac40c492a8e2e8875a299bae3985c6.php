<?php $__env->startSection('title', 'Set up draw – '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.draw.partials.guide-content', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/guide.blade.php ENDPATH**/ ?>