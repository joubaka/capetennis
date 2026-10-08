<?php $__env->startComponent('mail::message'); ?>
<?php echo $__env->make('emails._capez-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
# Suspension Notice — <?php echo e($player->full_name); ?>


Dear <?php echo e($player->full_name); ?>,

Your cumulative disciplinary points have reached the suspension threshold. The following suspension has been placed on your account.

<?php $__env->startComponent('mail::table'); ?>
| Field | Value |
|:------|:------|
| **Player** | <?php echo e($player->full_name); ?> |
| **Suspension #** | <?php echo e($suspension->suspension_number); ?> |
| **Duration** | <?php echo e($suspension->duration_months); ?> months |
| **Starts** | <?php echo e($suspension->starts_at->format('d M Y')); ?> |
| **Ends** | <?php echo e($suspension->ends_at->format('d M Y')); ?> |
<?php echo $__env->renderComponent(); ?>

During this period you are not permitted to participate in sanctioned Cape Tennis events. If you believe this suspension has been issued in error, please contact us.

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
Cape Tennis
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\disciplinary\suspension-alert.blade.php ENDPATH**/ ?>