<?php $__env->startSection('title', ($draw->drawName ?? 'Tournament') . ' matches'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('frontend.fixture.partials.team-schedule', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(($liveResultsFragment ?? false) ? 'frontend.fixtures.partials.live-results-layout' : 'layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixture\draw-fixtures-show-team.blade.php ENDPATH**/ ?>