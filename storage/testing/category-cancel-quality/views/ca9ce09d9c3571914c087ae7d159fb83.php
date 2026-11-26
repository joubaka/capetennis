<?php $__env->startSection('title', 'Your event proposal'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y" style="max-width:950px;overflow-wrap:anywhere">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="card"><div class="card-body"><span class="badge bg-label-primary">Proposal #<?php echo e($application->id); ?> · <?php echo e(ucfirst($application->status)); ?></span><h1 class="h4 mt-3"><?php echo e($application->event_name); ?></h1>
<p><?php echo e($application->academy); ?> · <?php echo e($application->venue); ?>, <?php echo e($application->town); ?></p><p><?php echo e($application->start_date->format('d M Y')); ?>–<?php echo e($application->end_date->format('d M Y')); ?> · <?php echo e($application->courts); ?> courts proposed</p>
<p>Cape Tennis reviews proposals before preparing an event. Approval is separate from certification, publication and opening registration.</p>
<?php echo $__env->make('frontend.host-events.court-plan', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($application->event_id && $application->event?->published): ?><a class="btn btn-primary" href="<?php echo e(route('events.show', $application->event_id)); ?>">View published event</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<a class="btn btn-outline-secondary" href="<?php echo e(route('host-events.index')); ?>">Host programme</a>
</div></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\host-events\show.blade.php ENDPATH**/ ?>