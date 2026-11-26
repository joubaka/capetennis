<?php if (! $__env->hasRenderedOnce('e4128cdb-3dd7-43b8-852b-beb047ce5c45')): $__env->markAsRenderedOnce('e4128cdb-3dd7-43b8-852b-beb047ce5c45'); ?>
<?php echo $__env->make('backend.draw.partials.workflow-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="modal fade" id="draw-setup-guide-modal" tabindex="-1" aria-labelledby="draw-setup-guide-title" aria-hidden="true" data-draw-guide-modal>
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="draw-setup-guide-title">Continue draw setup</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close draw setup"></button></div>
    <div class="modal-body" data-draw-guide-body></div>
  </div></div>
</div>
<script src="<?php echo e(asset('js/draw-setup-guide.js')); ?>?v=<?php echo e(filemtime(public_path('js/draw-setup-guide.js'))); ?>" defer></script>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\partials\guide-modal.blade.php ENDPATH**/ ?>