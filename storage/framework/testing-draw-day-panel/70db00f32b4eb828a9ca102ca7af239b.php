<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('replaceAlternate', $draw)): ?>
  <a class="btn btn-outline-warning mb-3" href="<?php echo e(route('draw.alternate.show', $draw)); ?>">Replace with alternate</a>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/partials/alternate-button.blade.php ENDPATH**/ ?>