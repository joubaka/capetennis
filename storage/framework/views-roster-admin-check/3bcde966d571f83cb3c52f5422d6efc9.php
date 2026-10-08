<?php $registrations = $registrations ?? collect(); ?>

<div class="d-flex justify-content-end mb-2">
    <button class="btn btn-danger btn-sm" id="clear-draw-players" data-draw-id="<?php echo e($draw->id); ?>">
        Remove All Players
    </button>
</div>

<ul class="list-group">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $registration->players,'context' => $draw]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($registration->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?></span>
            <button class="btn btn-outline-danger btn-sm remove-player"
                    data-reg-id="<?php echo e($registration->id); ?>"
                    data-draw-id="<?php echo e($draw->id); ?>">
                Remove
            </button>
        </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <li class="list-group-item text-muted">No players added yet.</li>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ul>


<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\draw-players.blade.php ENDPATH**/ ?>