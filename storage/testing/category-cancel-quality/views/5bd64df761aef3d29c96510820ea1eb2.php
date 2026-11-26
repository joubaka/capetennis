<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Draw alerts · private membership preview'); ?>
<?php $__env->startSection('content'); ?>
<div class="mp-preview">
<?php echo $__env->make('backend.player-performance.membership-preview.chrome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.membership-preview.alerts-simulator', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\membership-preview\alerts.blade.php ENDPATH**/ ?>