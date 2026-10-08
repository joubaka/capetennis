<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Player ratings'); ?>
<?php $__env->startSection('content'); ?>
<div class="card"><div class="card-body">
    <h1 class="h3">All players · performance pilot</h1>
    <p>Choose a player to calculate their private provisional score. Players without eligible results remain unrated.</p>
    <form method="get" class="mb-3">
        <label for="search" class="form-label">Player name</label>
        <input id="search" name="search" class="form-control" maxlength="100" value="<?php echo e($search); ?>">
        <button class="btn btn-primary mt-2">Search players</button>
    </form>
    <p><?php echo e($players->total()); ?> players</p>
    <ul class="list-group mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <li class="list-group-item"><a href="<?php echo e(route('backend.player-performance.show', $player->id)); ?>"><?php echo e($player->name); ?> <?php echo e($player->surname); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?></a> <span class="text-muted">#<?php echo e($player->id); ?></span></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <li class="list-group-item">No matching players.</li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
    <?php echo e($players->links()); ?>

    <a href="<?php echo e(route('backend.player-performance.index')); ?>">Compare a manually selected cohort</a>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\player-performance\directory.blade.php ENDPATH**/ ?>