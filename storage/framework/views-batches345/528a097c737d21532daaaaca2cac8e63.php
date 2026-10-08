<h3 class="h5">Player points ranking</h3>
<p class="small text-muted">Completed singles only. Roster bands come first: 1–2, 3–4, 5–6, then 7–8. Within each band, each match win earns 100, 35, 12, or 2 points respectively. Equal points within the same band share the same position.</p>
<ol class="list-group mb-4" aria-label="Player points ranking">
  <?php $position = 0; $previousPoints = null; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ranking; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $positionKey = [(int) ceil($player['rank'] / 2), $player['points']];
      if ($previousPoints !== $positionKey) $position = $index + 1;
      $previousPoints = $positionKey;
    ?>
    <li class="list-group-item d-flex align-items-center gap-3">
      <span class="badge bg-label-primary"><?php echo e($position); ?></span>
      <span class="flex-grow-1"><?php echo e($player['name']); ?><small class="d-block text-muted"><?php echo e($player['region']); ?> · Roster rank <?php echo e($player['rank']); ?></small></span>
      <strong class="text-nowrap"><?php echo e($player['points']); ?> points</strong>
    </li>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ol>
<h3 class="h5">Supporting match results</h3>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playerFixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $teamName => $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <details class="roster-team">
    <summary class="roster-team-summary"><strong><?php echo e($teamName); ?></strong><span aria-hidden="true">⌄</span></summary>
    <div class="roster-team-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank => $playerDetails): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <h4 class="h6"><?php echo e($rank + 1); ?>. <?php echo e($playerDetails['name']); ?></h4>
        <ul class="list-group mb-3">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playerDetails['results']['opponents']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
              <span class="flex-grow-1"><?php echo e($opponent['player']['name']); ?> <?php echo e($opponent['player']['surname']); ?></span>
              <span class="badge <?php echo e($playerDetails['results']['w/l'][$key] === 1 ? 'bg-label-success' : 'bg-label-danger'); ?>"><?php echo e($playerDetails['results']['w/l'][$key] === 1 ? 'Won' : 'Lost'); ?></span>
              <span class="small" aria-label="Home–away set scores">Home–away:
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $opponent['score']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $match; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $set): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="text-nowrap"><?php echo e($set->team1_score); ?>–<?php echo e($set->team2_score); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->last)): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </span>
            </li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </details>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_table\results.blade.php ENDPATH**/ ?>