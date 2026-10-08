

<?php $__env->startSection('title', 'Simple Draw – ' . $draw->drawName); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
    <div class="card-header">
        <h4><?php echo e($draw->drawName); ?> – Simple Draw</h4>
        <p class="text-muted"><?php echo e($event->name); ?></p>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>Player 1</th>
                    <th></th>
                    <th>Player 2</th>
                    <th>Score</th>
                </tr>
            </thead>

            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($fixture->id); ?></td>

                        <td><?php echo e($fixture->match_nr); ?></td>

                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration1): ?>
                                <?php echo e($fixture->registration1->players[0]->getFullNameAttribute()); ?>

                            <?php else: ?>
                                BYE
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>

                        <td class="text-center">vs</td>

                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration2): ?>
                                <?php echo e($fixture->registration2->players[0]->getFullNameAttribute()); ?>

                            <?php else: ?>
                                BYE
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>

                        <td><?php echo e($fixture->fixtureResults->map(fn($r) => $r->score_line)->join(', ')); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\individual\simple-draw-layout.blade.php ENDPATH**/ ?>