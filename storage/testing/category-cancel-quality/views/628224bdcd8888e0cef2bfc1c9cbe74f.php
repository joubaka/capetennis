<section class="ct-rating-snapshot" aria-label="Player Plus rating preview" data-locked-rating-snapshot>
  <h3><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>Your rating snapshot</h3>
  <div aria-hidden="true" style="filter:blur(4px);pointer-events:none;user-select:none">
    <svg viewBox="0 0 280 65" style="width:100%;height:65px;display:block"><path d="M10 48L55 35L95 42L140 25L185 30L225 16L270 20" fill="none" stroke="#c4e4ff" stroke-width="3"/></svg>
  </div>
  <p>Illustration only · no private rating shown.</p>
  <a class="btn btn-sm mt-2" href="<?php echo e(($ratingPlayer ?? null) ? route('my.tennis', ['player' => $ratingPlayer->id]).'#rating' : route('player-plus.index')); ?>" data-plus-preview-link>Explore Player Plus</a>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\locked-rating-snapshot.blade.php ENDPATH**/ ?>