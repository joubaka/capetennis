<div class="my-tennis-overview-badge" data-overview-rating-badge>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($canViewRating ?? false) && ($overviewBadge ?? null)): ?>
    <a href="<?php echo e(route('my.tennis', ['player' => $selectedPlayer->id])); ?>#rating" data-overview-rating-link class="d-flex align-items-center gap-2 text-decoration-none bg-white rounded p-2" aria-label="View player rating">
      <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'small','stage' => $overviewBadge['stage'],'score' => $overviewBadge['score'],'local' => $overviewBadge['local']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'small','stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($overviewBadge['stage']),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($overviewBadge['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($overviewBadge['local'])]); ?>
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
    </a>
  <?php else: ?>
    <a href="<?php echo e(route('my.tennis', ['player' => $selectedPlayer->id])); ?>#rating" data-overview-rating-link class="d-flex align-items-center gap-2 text-decoration-none" aria-label="Preview player rating with Player Plus">
      <?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['size' => 'heading']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'heading']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
      <span class="small text-white">Unlock CTR<br>Player Plus</span>
    </a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\overview-badge.blade.php ENDPATH**/ ?>