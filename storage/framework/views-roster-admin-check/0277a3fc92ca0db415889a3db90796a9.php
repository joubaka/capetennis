

<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header"><a href="<?php echo e(URL::previous()); ?>" class="btn btn-primary">Back</a></div>

</div>
<div class="card">
    <div class="card-header"><h3><?php echo e($player->getFullNameAttribute()); ?></h3></div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $results['fixture']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    
<p><?php echo e($result->created_at->format('j F , Y')); ?> <span class="badge bg-label-success"> <?php echo e($result->fixture->draw->events->name); ?></span> <?php echo e($result->team1->getFullNameAttribute()); ?> vs <?php echo e($result->team2->getFullNameAttribute()); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $result->fixture->teamResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php echo e($r->team1_score. '-'.$r->team2_score.';'); ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> </p>
   
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>   
    </div>
   
</div>



<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\player\player_results.blade.php ENDPATH**/ ?>