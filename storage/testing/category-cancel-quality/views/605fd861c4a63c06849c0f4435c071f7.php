<div class="d-flex flex-wrap align-items-center gap-2">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedCategoryDraw->pending_late_withdrawal_count > 0): ?>
  <a class="btn btn-warning" href="<?php echo e(route('backend.draw.roundrobin.show', $savedCategoryDraw)); ?>">Review <?php echo e($savedCategoryDraw->pending_late_withdrawal_count); ?> late <?php echo e(Str::plural('withdrawal', $savedCategoryDraw->pending_late_withdrawal_count)); ?>: <?php echo e($savedCategoryDraw->drawName); ?></a>
<?php elseif($savedCategoryDraw->published || $savedCategoryDraw->locked): ?>
  <a class="btn btn-primary" href="<?php echo e(route('backend.draw.roundrobin.show', $savedCategoryDraw)); ?>">Open draw: <?php echo e($savedCategoryDraw->drawName); ?></a>
<?php else: ?>
  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
    <a data-draw-guide-open class="btn btn-primary" href="<?php echo e(route('draw.guide.show', [$event, $savedCategoryDraw])); ?>">Continue setup: <?php echo e($savedCategoryDraw->drawName); ?></a>
  <?php else: ?>
    <a class="btn btn-outline-primary" href="<?php echo e(route('backend.draw.roundrobin.show', $savedCategoryDraw)); ?>">Open draw: <?php echo e($savedCategoryDraw->drawName); ?></a>
  <?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $savedCategoryDraw)): ?>
  <button type="button" class="btn btn-outline-danger btn-delete-draw"
          data-url="<?php echo e(route('draws.destroy', $savedCategoryDraw->id)); ?>"
          data-draw-name="<?php echo e($savedCategoryDraw->drawName); ?>"
          aria-label="Delete draw and fixtures: <?php echo e($savedCategoryDraw->drawName); ?>">
    Delete draw &amp; fixtures
  </button>
<?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\headOffice\partials\category-draw-next-action.blade.php ENDPATH**/ ?>