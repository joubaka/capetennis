<?php $__env->startComponent('mail::message'); ?>
# Registration confirmed

An administrator has registered you for the following event:

**Event:** <?php echo e($entry->categoryEvent?->event?->name ?? 'Event'); ?>


**Category:** <?php echo e($entry->categoryEvent?->category?->name ?? 'Category'); ?>


**Player:** <?php echo e(trim(($entry->players->first()?->name ?? '').' '.($entry->players->first()?->surname ?? ''))); ?>


No payment is required for this administrator-created entry.

Thanks,
Cape Tennis
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\registration\admin-created.blade.php ENDPATH**/ ?>