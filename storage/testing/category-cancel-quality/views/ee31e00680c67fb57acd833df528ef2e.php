<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
  <?php
    $oddTailReview = null;
    try { $oddTailReview = app(\App\Services\Draw\OddPlayerPlayoffService::class)->review($draw, (int) $draw->event_id); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { /* Other formats keep their normal setup. */ }
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($oddTailReview): ?>
    <section class="border rounded p-3 my-3" aria-labelledby="odd-tail-title">
      <h6 id="odd-tail-title">Everyone plays a playoff · odd player count</h6>
      <p>The last-ranked player (#<?php echo e($oddTailReview['players']); ?>) plays #<?php echo e($oddTailReview['players'] - 1); ?>. The winner plays #<?php echo e($oddTailReview['players'] - 2); ?>; the preliminary loser finishes <?php echo e($oddTailReview['players']); ?>th. Other position pairs stay the same.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($oddTailReview['already_applied']): ?>
        <p class="mb-0 text-success">Enabled. Schedule the preliminary before its final.</p>
      <?php else: ?>
        <form method="POST" action="<?php echo e(route('backend.draw.odd-player-playoff', $draw)); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="event_id" value="<?php echo e($draw->event_id); ?>">
          <input type="hidden" name="snapshot" value="<?php echo e($oddTailReview['snapshot']); ?>">
          <label class="d-block mb-2"><input type="checkbox" name="confirmed" value="1" required> Use this option: add one preliminary and retain the existing final and saved times.</label>
          <button class="btn btn-outline-primary" type="submit">Enable odd-player playoff</button>
        </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\roundrobin\odd-tail-option.blade.php ENDPATH**/ ?>