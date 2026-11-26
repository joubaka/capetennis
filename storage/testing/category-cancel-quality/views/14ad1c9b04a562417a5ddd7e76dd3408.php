<?php $__env->startSection('title', 'CTR campaign drafts'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.marketing.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><h2 class="h5">Campaign drafts</h2><a class="btn btn-primary" href="<?php echo e(route('superadmin.marketing.campaigns.create')); ?>">Create campaign draft</a></div>
<p class="text-muted">Prepare email and WhatsApp copy. Saving a draft never sends or schedules a message.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Campaign</th><th>Status</th><th>Last saved</th><th>Action</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($campaign->title); ?></td><td><span class="badge bg-label-secondary">Draft</span></td><td><?php echo e($campaign->updated_at->format('d M Y H:i')); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('superadmin.marketing.campaigns.edit', $campaign)); ?>">Edit draft</a></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4">No drafts saved. Create a draft to start with the CTR host invitation template.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table></div><div class="mt-3"><?php echo e($campaigns->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\marketing\campaigns.blade.php ENDPATH**/ ?>