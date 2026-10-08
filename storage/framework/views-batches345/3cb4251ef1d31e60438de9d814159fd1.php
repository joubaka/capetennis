<?php $__env->startComponent('mail::message'); ?>
<?php echo $__env->make('emails._capez-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
# Disciplinary Violation Recorded

A disciplinary violation has been recorded for **<?php echo e($player->full_name); ?>**.

<?php $__env->startComponent('mail::table'); ?>
| Field | Value |
|:------|:------|
| **Player** | <?php echo e($player->full_name); ?> |
| **Violation Type** | <?php echo e($violation->violationType->name ?? '—'); ?> |
| **Category** | <?php echo e(ucfirst(str_replace('_', ' ', $violation->violationType->category ?? '—'))); ?> |
| **Date** | <?php echo e($violation->violation_date->format('d M Y')); ?> |
| **Points Assigned** | <?php echo e($violation->points_assigned); ?> |
| **Penalty Type** | <?php echo e($violation->penalty_type ? ucfirst($violation->penalty_type) : '—'); ?> |
| **Recorded By** | <?php echo e($recorder?->name ?? '—'); ?> |
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($violation->event_id): ?>
| **Event** | <?php echo e($violation->event?->name ?? 'Event #' . $violation->event_id); ?> |
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($violation->notes): ?>
| **Notes** | <?php echo e($violation->notes); ?> |
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->renderComponent(); ?>

<?php $__env->startComponent('mail::button', ['url' => url('/backend/disciplinary/player/' . $player->id), 'color' => 'red']); ?>
View Player Disciplinary Record
<?php echo $__env->renderComponent(); ?>

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
Cape Tennis
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\disciplinary\violation-notification.blade.php ENDPATH**/ ?>