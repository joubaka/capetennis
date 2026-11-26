
<?php $__env->startSection('content'); ?>
<?php
  $lineup = $team_fixture->lineup_display;
  $home = $lineup['home'];
  $away = $lineup['away'];
?>
<div class="container-xxl">
  <a href="<?php echo e(url()->previous()); ?>" class="btn btn-link mb-3">&larr; Back</a>
  <div class="card mb-3"><div class="card-body">
    <h1 class="h4"><?php echo e($home['region']); ?> vs <?php echo e($away['region']); ?></h1>
    <div class="row g-3 mt-1">
      <div class="col-md-6"><strong>Scheduled</strong><div><?php echo e($team_fixture->scheduled_at?->format('D j M Y H:i') ?? 'Unscheduled'); ?></div></div>
      <div class="col-md-6"><strong>Venue / court</strong><div><?php echo e($team_fixture->venue?->name ?? 'Venue not assigned'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team_fixture->court_label): ?> · <?php echo e($team_fixture->court_label); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
    </div>
  </div></div>
  <div class="card mb-3"><div class="card-header"><h2 class="h5 mb-0">Match Players</h2></div><div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($home['players']) && empty($away['players'])): ?>
      <div class="alert alert-info mb-0">No player rows linked to this fixture yet.</div>
    <?php else: ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = range(0, max(count($home['players']), count($away['players'])) - 1); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="row g-2 border-bottom py-3">
          <div class="col-12 col-sm-6"><span class="small text-muted">Home · <?php echo e($home['region']); ?></span><div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player = $home['players'][$index] ?? null): ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['rank']): ?><strong>(<?php echo e($player['rank']); ?>)</strong><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo e($player['name']); ?> <?php else: ?> Players to be confirmed <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
          <div class="col-12 col-sm-6"><span class="small text-muted">Away · <?php echo e($away['region']); ?></span><div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player = $away['players'][$index] ?? null): ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['rank']): ?><strong>(<?php echo e($player['rank']); ?>)</strong><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo e($player['name']); ?> <?php else: ?> Players to be confirmed <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
  <details class="card mb-3"><summary class="card-header" style="min-height:44px">Fixture details</summary><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Fixture</dt><dd class="col-sm-9">#<?php echo e($team_fixture->id); ?></dd>
      <dt class="col-sm-3">Event</dt><dd class="col-sm-9"><?php echo e($team_fixture->draw?->event?->name ?? '—'); ?></dd>
      <dt class="col-sm-3">Draw</dt><dd class="col-sm-9"><?php echo e($team_fixture->draw?->drawName ?? '—'); ?></dd>
      <dt class="col-sm-3">Round / Tie</dt><dd class="col-sm-9"><?php echo e($team_fixture->round_name ?? $team_fixture->round); ?> / <?php echo e($team_fixture->tie_name ?? $team_fixture->tie); ?></dd>
    </dl>
  </div></details>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-fixtures\show.blade.php ENDPATH**/ ?>