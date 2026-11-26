<section class="rr-bracket-preview" aria-labelledby="playoff-preview-heading">
  <h6 id="playoff-preview-heading">Provisional playoff preview</h6>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svgData): ?>
    <p class="small text-muted">This shows qualifying positions from your saved groups and playoff settings. Players are confirmed after group results. No playoff matches have been created.</p>
    <?php echo $__env->make('backend.draw.roundrobin.dynamic-bracket-svg', ['emptyBracket' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php else: ?>
    <p><?php echo e($previewMessage); ?></p>
    <a class="btn btn-outline-primary" href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#<?php echo e($previewTarget ?? ($draw->groups()->exists() ? 'settings' : 'groups')); ?>"><?php echo e($previewAction ?? ($draw->groups()->exists() ? 'Open playoff settings' : 'Open Players & Groups')); ?></a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\roundrobin\bracket-planning.blade.php ENDPATH**/ ?>