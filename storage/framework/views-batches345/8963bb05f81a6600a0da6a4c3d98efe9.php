

<?php $__env->startSection('title', 'Ranking Details'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card mb-4">
    <!-- Notifications -->
    <h5 class="card-header pb-1"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></h5>
    <div class="card-body">
        <span>Results for series</span>
    </div>
    <div class="table-responsive text-nowrap">
        <table class="table table-striped border-top">
            <thead>
                <tr>
                    <th class="text-nowrap">ID</th>
                    <th class="text-nowrap text-center">Event</th>
                    <th class="text-nowrap text-center">Score</th>

                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $results; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $position): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="text-nowrap"><?php echo e($position->id); ?></td>
                    <td>
                        <div class="form-check d-flex justify-content-center">
                            <?php echo e($position->category_event->event->name); ?>

                        </div>
                    </td>
                    <td>
                        <div class="form-check d-flex justify-content-center">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->rank_type == 'participation'): ?>
                            <?php echo e($position->round_robin_score); ?> points
                            <?php else: ?>
                           <span class="badge bg-label-success"> <?php echo e($position->position); ?></span>  -   <?php echo e($position->point->score); ?> points 
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </div>
                    </td>
                    <td>
                        <div class="form-check d-flex justify-content-center">

                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- /Notifications -->
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\details.blade.php ENDPATH**/ ?>