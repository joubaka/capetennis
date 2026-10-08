  <div class="text-center">


      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawFixtures->isNotEmpty()): ?>
          <div class="draw-preview-area">
              
              <?php echo $__env->make('backend.draw.partials.draw-preview', ['draw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          </div>
      <?php else: ?>
          <p class="text-muted">No matches available to preview yet. Configure your settings and generate
              the draw.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\tabs\roundrobin-preview.blade.php ENDPATH**/ ?>