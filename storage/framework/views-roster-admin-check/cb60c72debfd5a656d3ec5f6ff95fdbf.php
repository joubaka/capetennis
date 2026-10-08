<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['row']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['row']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = array_filter($row['participants'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $participantSide => $participantName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->first)): ?> / <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($participantName); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($row['fixture_kind'] ?? 'individual') === 'individual' && $participantSide < 2): ?>
        <?php if (isset($component)) { $__componentOriginalf7615e63d404507148635d81900de326 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf7615e63d404507148635d81900de326 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.fixture-rating','data' => ['fixtureId' => $row['fixture_id'],'side' => $participantSide + 1,'drawId' => $row['draw_id'] ?? null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('fixture-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixture-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row['fixture_id']),'side' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($participantSide + 1),'draw-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row['draw_id'] ?? null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf7615e63d404507148635d81900de326)): ?>
<?php $attributes = $__attributesOriginalf7615e63d404507148635d81900de326; ?>
<?php unset($__attributesOriginalf7615e63d404507148635d81900de326); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf7615e63d404507148635d81900de326)): ?>
<?php $component = $__componentOriginalf7615e63d404507148635d81900de326; ?>
<?php unset($__componentOriginalf7615e63d404507148635d81900de326); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    Participants determined by draw
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\components\scheduled-participants.blade.php ENDPATH**/ ?>