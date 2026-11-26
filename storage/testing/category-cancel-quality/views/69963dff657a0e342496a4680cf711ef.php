<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['playerId', 'context' => null, 'svg' => false]));

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

foreach (array_filter((['playerId', 'context' => null, 'svg' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!request()->attributes->get('public_schedule_preview') && \App\Services\Performance\PlayerRatingBadgeService::visible()): ?>
    <?php ($rating = app(\App\Services\Performance\PlayerRatingBadgeService::class)->forPlayer((int) $playerId, $context)); ?>
    <?php ($markerContext = ['draw' => $context instanceof \App\Models\Draw ? $context->id : 0,
        'field' => $context instanceof \App\Models\CategoryEvent ? $context->id : 0,
        'category' => $context instanceof \App\Models\Category ? $context->id : 0]); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svg): ?>
        <tspan data-rating-done="1" data-rating-player="<?php echo e($playerId); ?>" data-rating-svg="1" data-rating-draw="<?php echo e($markerContext['draw']); ?>" data-rating-field="<?php echo e($markerContext['field']); ?>" data-rating-category="<?php echo e($markerContext['category']); ?>">
    <?php else: ?>
        <span data-rating-done="1" data-rating-player="<?php echo e($playerId); ?>" data-rating-draw="<?php echo e($markerContext['draw']); ?>" data-rating-field="<?php echo e($markerContext['field']); ?>" data-rating-category="<?php echo e($markerContext['category']); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svg): ?>
            <tspan class="player-rating-badge" dx="4" style="font-size:10px;fill:#075985;font-weight:700"><title><?php echo e($rating['title']); ?></title>[<?php echo e($rating['display_label']); ?>]</tspan>
        <?php else: ?>
            <span class="player-rating-badge" style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:.25rem;vertical-align:middle">
                <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'inline','class' => 'badge player-rating-badge ms-1','score' => $rating['score'],'title' => $rating['title'],'overall' => ($rating['rating_scope'] ?? null) === 'all_age','local' => !in_array($rating['rating_scope'] ?? null, ['all_age', 'ball'], true) || ($rating['comparable'] ?? true) === false,'stage' => $rating['ball_type'] ?? null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'inline','class' => 'badge player-rating-badge ms-1','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['score']),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['title']),'overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($rating['rating_scope'] ?? null) === 'all_age'),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(!in_array($rating['rating_scope'] ?? null, ['all_age', 'ball'], true) || ($rating['comparable'] ?? true) === false),'stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['ball_type'] ?? null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
                <span class="small" style="font-size:.65rem;font-weight:500;white-space:normal"><?php echo e($rating['confidence_label'] ?? $rating['confidence_band'] ?? 'Low'); ?> confidence <?php echo e(($rating['evidence_basis'] ?? null) === 'finish_only' ? '· final placings only' : ''); ?> <?php echo e(!empty($rating['historical_connection']) ? '· old link' : ''); ?></span>
            </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svg): ?></tspan><?php else: ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\player-rating.blade.php ENDPATH**/ ?>