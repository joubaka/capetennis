<?php
  $publicationSurface = $publicationSurface ?? 'standalone';
  $publicationFlexible = $publicationDraw->usesFlexibleMonrad();
  $publicationDropdown = $publicationDropdown ?? false;
  $publicationRevision = $publicationFlexible ? $publicationDraw->flexibleMonrad?->revision : null;
?>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $publicationDraw)): ?>
  <div class="<?php echo e($publicationDropdown ? 'dropdown draw-workspace-publication' : 'd-flex flex-wrap align-items-center gap-2'); ?>" data-draw-publication="<?php echo e($publicationDraw->id); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicationDropdown): ?>
      <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-publication-review-toggle aria-expanded="false">Publish</button>
      <div class="dropdown-menu dropdown-menu-end p-2">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="button" class="btn btn-sm <?php echo e($publicationDraw->published ? 'btn-success' : 'btn-outline-primary'); ?>"
      aria-pressed="<?php echo e($publicationDraw->published ? 'true' : 'false'); ?>"
      <?php if($publicationSurface === 'roundrobin'): ?> id="rr-publish-draw" data-url="<?php echo e(route('draw.toggle.publish', $publicationDraw)); ?>"
      <?php elseif($publicationSurface === 'flexible'): ?> data-workspace-publish-draw-proxy
      <?php else: ?> data-workspace-publish-draw="<?php echo e(route($publicationFlexible ? 'flexible-monrad.publish' : 'draw.toggle.publish', $publicationDraw)); ?>"
        data-published="<?php echo e($publicationDraw->published ? '1' : '0'); ?>"
        <?php if($publicationFlexible): ?> data-flexible-revision="<?php echo e($publicationRevision ?? 0); ?>" <?php endif; ?>
      <?php endif; ?>
      <?php if($publicationFlexible && ($publicationDraw->locked || !$publicationDraw->flexibleMonrad?->graph)): echo 'disabled'; endif; ?>>
      <?php echo e($publicationDraw->published ? 'Unpublish draw' : 'Publish draw'); ?>

    </button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($publicationByDay ?? false)): ?>
    <button type="button" class="btn btn-sm <?php echo e($publicationDraw->oop_published ? 'btn-success' : 'btn-outline-primary'); ?>"
      aria-pressed="<?php echo e($publicationDraw->oop_published ? 'true' : 'false'); ?>"
      <?php if($publicationSurface === 'roundrobin'): ?> id="rr-publish-schedule" data-url="<?php echo e(route('draw.toggle.publish.schedule', $publicationDraw)); ?>"
      <?php else: ?> data-workspace-publish-schedule="<?php echo e(route('draw.toggle.publish.schedule', $publicationDraw)); ?>" <?php endif; ?>>
      <?php echo e($publicationDraw->oop_published ? 'Unpublish schedule' : 'Publish schedule'); ?>

    </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <span class="small text-muted" role="status" aria-live="polite" data-publication-status>Draw and schedule publish separately.</span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicationDropdown): ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/partials/publication-controls.blade.php ENDPATH**/ ?>