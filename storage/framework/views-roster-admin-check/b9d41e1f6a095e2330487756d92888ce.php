<?php $__env->startSection('content'); ?>
<div class="container-xxl"><div class="card"><div class="card-body">
<h4>Choose the source team</h4>
<p>Use the replacement wizard to preserve completed matches and financial history.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<a class="d-block p-2 border rounded mb-2" href="<?php echo e(route('backend.team-substitutions.show', $team)); ?>"><?php echo e($team->name); ?> · <?php echo e($team->category->event->name); ?></a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo e($teams->links()); ?>

</div></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\substitution-teams.blade.php ENDPATH**/ ?>