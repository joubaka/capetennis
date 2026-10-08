<?php $__env->startSection('title', 'Team invitations'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-1">Team invitations</h4><p class="text-muted mb-4">Your Platteland selection and registration status.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h5 class="mb-1"><?php echo e($invitation->selectionImport?->event?->name); ?></h5><div class="text-muted"><?php echo e($invitation->region?->region_name); ?> · <?php echo e($invitation->team?->name); ?></div></div><div class="d-flex align-items-center gap-2"><span class="badge bg-label-<?php echo e($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : 'primary'); ?>"><?php echo e(str_replace('_',' ',ucfirst($invitation->status))); ?></span><a class="btn btn-sm btn-primary" href="<?php echo e(route('team-selection.invitations.show', $invitation)); ?>">Open invitation</a></div></div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="alert alert-info">There are no active team invitations linked to your player profiles.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\team-selection\index.blade.php ENDPATH**/ ?>