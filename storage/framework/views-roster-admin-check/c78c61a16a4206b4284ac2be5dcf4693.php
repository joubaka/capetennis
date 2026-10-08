<?php $__env->startComponent('mail::message'); ?>
# <?php echo app('translator')->get('Hello!'); ?>

<?php echo app('translator')->get('We detected suspicious activity on your :app account.', ['app' => config('app.name')]); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($suspiciousActivities)): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $suspiciousActivities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
- **<?php echo e($activity['type'] === 'multiple_failed_logins' ? __('Multiple Failed Logins') : ($activity['type'] === 'rapid_location_change' ? __('Rapid Location Change') : __('Unusual Login Time'))); ?>:** <?php echo e($activity['message']); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

> **<?php echo app('translator')->get('Account:'); ?>** <?php echo e($account->email); ?><br/>
> **<?php echo app('translator')->get('Time:'); ?>** <?php echo e($time->toDateTimeString()); ?><br/>
> **<?php echo app('translator')->get('IP Address:'); ?>** <?php echo e($ipAddress); ?><br/>
> **<?php echo app('translator')->get('Browser:'); ?>** <?php echo e($browser); ?><br/>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($location && $location['default'] === false): ?>
> **<?php echo app('translator')->get('Location:'); ?>** <?php echo e($location['city'] ?? __('Unknown City')); ?>, <?php echo e($location['state'] ?? __('Unknown State')); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo app('translator')->get('If this was not you, please secure your account immediately by changing your password.'); ?>

<?php echo app('translator')->get('Regards,'); ?><br/>
<?php echo e(config('app.name')); ?>

<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\wamp64\www\ct\vendor\rappasoft\laravel-authentication-log\resources\views\emails\suspicious.blade.php ENDPATH**/ ?>