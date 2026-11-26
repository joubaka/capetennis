<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Draw\InterproRoundRobinFormat::selected($draw)): ?>
  <a href="<?php echo e(route('public.roundrobin.show', $draw)); ?>#playoffs" class="btn btn-primary" data-playoff-bracket-link>
    <i class="ti ti-tournament me-1" aria-hidden="true"></i>View playoff bracket
  </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixture\partials\interpro-bracket-link.blade.php ENDPATH**/ ?>