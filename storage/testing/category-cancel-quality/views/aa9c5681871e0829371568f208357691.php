<?php ($disconnected = ($estimate['comparable'] ?? true) === false || ($estimate['connection_status'] ?? null) === 'disconnected'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($disconnected): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_numeric($estimate['score'] ?? null)): ?>
<p class="h2">All-age rating unavailable — insufficient connecting results</p>
<p>Comparison group <?php echo e($estimate['component']); ?> is not connected to the shared all-age reference.</p>
<p>Within-group score only: <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['overall' => true,'score' => $estimate['score'],'local' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['overall' => true,'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($estimate['score']),'local' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>. This is not an all-age rating.</p>
<?php else: ?>
<p class="h2">All-age rating unavailable</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_numeric($estimate['score'] ?? null)): ?><p><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['overall' => true,'score' => $estimate['score']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['overall' => true,'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($estimate['score'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></p><?php else: ?><p class="h2">Shared rating withheld</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p>One singles rating across age groups · provisional · comparison group <?php echo e($estimate['component']); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['reason'] ?? null): ?><p role="status"><?php echo e($estimate['reason']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p>Evidence confidence: <?php echo e($estimate['confidence_label'] ?? 'Low'); ?>. <?php echo e($estimate['played'] ?? 0); ?> recorded matches against <?php echo e($estimate['direct_opponents'] ?? 0); ?> distinct opponents.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($disconnected && is_numeric($estimate['score'] ?? null)): ?><p class="alert alert-warning" role="status"><strong>Provisional within-group rating · low confidence.</strong> This estimate is not calibrated against other comparison groups. A matching number elsewhere does not establish equal ability.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($performanceDetailPage ?? false)): ?>
<p class="small"><?php echo e($estimate['confidence_explanation'] ?? ''); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($estimate['historical_connection'])): ?>
<p class="alert alert-warning text-dark" role="status"><strong>Low confidence — historical connection.</strong> This comparison needs a match link from <?php echo e($estimate['connection_reference_date'] ?? 'an older date'); ?> (<?php echo e($estimate['connection_age_days'] ?? '?'); ?> days old). Recent matches within the player's own group do not make that link recent.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($performanceDetailPage ?? false): ?>
<details class="mb-3">
    <summary class="text-primary py-2">See rating dates and confidence details</summary>
    <p class="small mt-2"><?php echo e($estimate['confidence_explanation'] ?? ''); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p>Recorded age categories: <?php echo e(implode(', ', $estimate['age_groups'] ?? []) ?: 'Not yet established'); ?>.</p>
<p class="small">Last direct match: <?php echo e($estimate['last_direct_match'] ?? 'None'); ?>. Evidence as of <?php echo e($estimate['confidence_as_of'] ?? 'Pending'); ?>. Confidence describes the available evidence, not an accuracy percentage.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($performanceDetailPage ?? false): ?>
</details>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (isset($component)) { $__componentOriginalf900df063c5677d5e8b51f684b5153a9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf900df063c5677d5e8b51f684b5153a9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rating-explanation','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rating-explanation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf900df063c5677d5e8b51f684b5153a9)): ?>
<?php $attributes = $__attributesOriginalf900df063c5677d5e8b51f684b5153a9; ?>
<?php unset($__attributesOriginalf900df063c5677d5e8b51f684b5153a9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf900df063c5677d5e8b51f684b5153a9)): ?>
<?php $component = $__componentOriginalf900df063c5677d5e8b51f684b5153a9; ?>
<?php unset($__componentOriginalf900df063c5677d5e8b51f684b5153a9); ?>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\all-age-rating.blade.php ENDPATH**/ ?>