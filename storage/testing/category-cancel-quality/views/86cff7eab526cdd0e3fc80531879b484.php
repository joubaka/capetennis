<?php
  $selectedLabel = $ballType === 'overall' ? 'Overall CTR' : \App\Services\Performance\BallRatingModel::LABELS[$ballType].' CTR';
  $localRating = ($selectedRating['comparable'] ?? false) !== true || ($selectedRating['connection_status'] ?? null) === 'disconnected' || isset($selectedRating['within_group_score']);
?>
<h2 class="h4 mb-3"><?php echo e($selectedLabel); ?> · singles</h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ability['reason']): ?>
  <p class="alert alert-warning text-dark" role="status"><?php echo e($ability['reason']); ?></p>
<?php elseif($selectedRating): ?>
  <div class="d-flex flex-wrap align-items-center gap-3 p-3 mb-3 rounded border" style="background:#eff8f7">
    <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['overall' => $ballType === 'overall','stage' => $ballType === 'overall' ? null : $ballType,'score' => $selectedRating['within_group_score'] ?? $selectedRating['score'],'local' => $localRating]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ballType === 'overall'),'stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ballType === 'overall' ? null : $ballType),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedRating['within_group_score'] ?? $selectedRating['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($localRating)]); ?>
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
    <div>
      <p class="fw-semibold mb-1"><?php echo e($localRating ? 'Rating within this group' : 'Singles playing strength'); ?></p>
      <?php if (isset($component)) { $__componentOriginale805b5da6e8d897845a3f39ddf861d9c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale805b5da6e8d897845a3f39ddf861d9c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-confidence-badge','data' => ['rating' => $selectedRating]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-confidence-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rating' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedRating)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale805b5da6e8d897845a3f39ddf861d9c)): ?>
<?php $attributes = $__attributesOriginale805b5da6e8d897845a3f39ddf861d9c; ?>
<?php unset($__attributesOriginale805b5da6e8d897845a3f39ddf861d9c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale805b5da6e8d897845a3f39ddf861d9c)): ?>
<?php $component = $__componentOriginale805b5da6e8d897845a3f39ddf861d9c; ?>
<?php unset($__componentOriginale805b5da6e8d897845a3f39ddf861d9c); ?>
<?php endif; ?>
      <p class="mb-0 mt-2">Confidence reflects how strong and recent the results behind this rating are.</p>
    </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($localRating): ?><p class="alert alert-warning text-dark" role="status">Within-group estimate only. This rating is not calibrated against other comparison groups.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($selectedRating['evidence_basis'] ?? null) === 'finish_only'): ?><p><strong>Published final placings only · low confidence.</strong> No eligible played singles are recorded for this ball rating.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($selectedRating['historical_connection'])): ?><p class="text-warning-emphasis">Low confidence — historical connection. Reference date: <?php echo e($selectedRating['connection_reference_date'] ?? 'Unknown'); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <dl class="row g-2 mb-3">
    <div class="col-6"><dt class="fw-normal">Recorded matches</dt><dd class="h4 mb-0"><?php echo e($selectedRating['played'] ?? 0); ?></dd></div>
    <div class="col-6"><dt class="fw-normal">Different opponents</dt><dd class="h4 mb-0"><?php echo e($selectedRating['direct_opponents'] ?? 0); ?></dd></div>
  </dl>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRating['reason'] ?? null): ?><p role="status"><?php echo e($selectedRating['reason']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
  <p class="h3"><?php echo e($selectedLabel); ?> unavailable</p><p>No eligible saved rating is available in this selected context. A rating from another ball level is not substituted.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<details class="mb-3">
  <summary class="text-primary">How to read this rating</summary>
  <p class="mt-2">Compare players on the same rating scale and in the same comparison group. Red, Orange, Green/Yellow and Overall CTR each have their own scale.</p>
  <p>CTR estimates playing strength and is separate from the official rankings. Evidence confidence describes the strength and freshness of the results behind it, not the chance of winning or the accuracy of the rating.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$ability['reason'] && $selectedRating): ?>
    <p>Comparison group: <?php echo e($selectedRating['component']); ?></p>
    <p><?php echo e($selectedRating['confidence_explanation'] ?? ''); ?></p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</details>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-outline-primary" href="<?php echo e(route('backend.player-performance.ratings', ['ball_type' => $ballType, 'comparison_group' => $comparisonGroup !== '' ? $comparisonGroup : null])); ?>">Back to <?php echo e($selectedLabel); ?> list</a>
  <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.player-performance.show', ['player' => $player->id, 'ball_type' => 'overall'])); ?>">View Overall CTR</a>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\selected-rating.blade.php ENDPATH**/ ?>