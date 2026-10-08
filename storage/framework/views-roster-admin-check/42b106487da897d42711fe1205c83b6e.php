<?php
  $pathwayStages = collect($draw['oops'])
    ->reject(fn ($fixture) => ($fixture['stage'] ?? 'RR') === 'RR')
    ->groupBy(fn ($fixture) => $fixture['stage'] ?: 'DRAW');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathwayStages->isNotEmpty()): ?>
  <h3>Bracket and placement pathways</h3>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pathwayStages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $stageFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $rounds = $stageFixtures
        ->groupBy(fn ($fixture) => (int) ($fixture['round'] ?: 1))
        ->sortKeys();
    ?>
    <h4><?php echo e($stageLabels[$stage] ?? str($stage)->headline()); ?></h4>
    <table class="pathway">
      <caption><?php echo e($draw['name']); ?> <?php echo e($stageLabels[$stage] ?? str($stage)->headline()); ?> pathway</caption>
      <tbody><tr><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rounds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $roundFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><div class="round-heading">Round <?php echo e($round); ?></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roundFixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="match-card">
          <strong>M<?php echo e($fixture['match_nr'] ?? $fixture['id']); ?></strong><br>
          <?php echo e($fixture['home']); ?><br><?php echo e($fixture['away']); ?>

          <div class="advance">Result: <?php echo e($fixture['score'] ?: '________________'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture['winner_to']): ?><br>Winner to M<?php echo e($fixture['winner_to']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture['loser_to']): ?><br>Loser to M<?php echo e($fixture['loser_to']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></tbody>
    </table>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php elseif($showEmptyPathway ?? false): ?>
  <div class="empty-note">This draw does not have a generated bracket yet.</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\pdf\partials\pathway-board.blade.php ENDPATH**/ ?>