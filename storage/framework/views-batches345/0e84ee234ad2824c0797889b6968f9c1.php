<?php $__env->startSection('title', ($draw->drawName ?? 'Tournament') . ' matches'); ?>

<?php $__env->startSection('content'); ?>
<style>.fixture-round > summary .when-open,.fixture-tie > summary .when-open{display:none}.fixture-round[open] > summary .when-open,.fixture-tie[open] > summary .when-open{display:inline}.fixture-round[open] > summary .when-closed,.fixture-tie[open] > summary .when-closed{display:none}.fixture-tie > summary{padding:.75rem;border:1px solid #d9dee3;border-radius:.4rem;background:#f5f7fa}.fixture-toggle{font-size:.8rem;color:#315785;font-weight:600}</style>
<style>
  .public-fixture-page .badge.bg-label-success { background: #e4f1e7 !important; color: #235c31 !important; }
  .public-fixture-page .badge.bg-label-secondary { background: #edf0f4 !important; color: #35465b !important; }
  @media (max-width: 767.98px) {
    .public-fixture-page .fixture-round > summary,
    .public-fixture-page .fixture-tie > summary { min-height: 44px; }
    .public-fixture-page .fixture-round > .card-body { padding: .75rem; }
    .public-fixture-page .fixture-tie > .card > .card-body { padding: .5rem; }
    .public-fixture-page .fixture-tie h5 { overflow-wrap: anywhere; font-size: 1rem; }
    .public-fixture-page .btn { min-height: 44px; display: inline-flex; align-items: center; }
  }
</style>
<style>
  .public-fixture-page summary { cursor: pointer; }
  .public-fixture-page summary:hover { background: #e8eff8; }
  .public-fixture-page summary:focus-visible { outline: 3px solid #173f7a; outline-offset: 3px; }
  .public-fixture-page .fixture-round > summary { border-left: 5px solid #173f7a; background: #eef3fa; }
  .fixture-team-chip { display: inline-block; background: #fff; color: #26394d; border: 1px solid var(--region-color, #475569); padding: .3rem .55rem; border-radius: .35rem; line-height: 1.5; overflow-wrap: anywhere; }
</style>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->standings_published): ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.team-draw.standings', $draw)); ?>">Draw standings</a>
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.events.standings', $event)); ?>">Full event standings</a>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="public-fixture-page" <?php if($draw->published): ?> data-live-results="team-fixtures" <?php endif; ?>>
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h3 class="mb-2"><?php echo e($draw->drawName); ?></h3>
        <span class="badge <?php echo e($draw->published ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($draw->published ? 'Draw published' : 'Draft preview · Draw not published'); ?></span>
        <span class="badge <?php echo e($draw->scheduleIsPublished() ? 'bg-label-success' : 'bg-label-secondary'); ?>"><?php echo e($draw->scheduleIsPublished() ? 'Match times published' : 'Match times to follow'); ?></span>
      </div>
      <a href="<?php echo e(route('events.show', $event)); ?>" class="btn btn-sm btn-outline-secondary public-fixture-back">Back to tournament</a>
    </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->id === 241 && $draw->scheduleIsPublished()): ?>
    <div class="alert mb-3" style="background:#fff3cd;color:#664d03;border:1px solid #e6c76a;" role="note">
      <strong class="d-block mb-1">NB: NOT BEFORE times</strong>
      All scheduled times are NOT BEFORE times. A match will not start before its listed time, but it may start later if earlier matches are still being played. Please be at your assigned venue and ready to play by the listed time, and check Cape Tennis for schedule updates.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($draw->scheduleIsPublished())): ?>
    <div class="alert alert-info" role="status">The draw is available, but match times and venues have not been published yet.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures->groupBy(fn ($fixture) => (int) $fixture->round_nr); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $roundFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $ties = $roundFixtures->groupBy(fn ($fixture) => implode('-', [$fixture->draw_id, $fixture->team_tie_id ?: implode('-', [$fixture->tie_nr, $fixture->region1, $fixture->region2])]));
    ?>
    <details class="fixture-round card mb-3" data-live-key="round-<?php echo e($round); ?>">
      <summary class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap" style="cursor: pointer;">
        <h4 class="mb-0">Round <?php echo e($round ?: '—'); ?></h4>
        <span class="fixture-toggle"><span class="when-closed">▸ Click to show ties</span><span class="when-open">▾ Click to hide ties</span></span>
        <span class="text-muted"><?php echo e($ties->count()); ?> <?php echo e($ties->count() === 1 ? 'tie' : 'ties'); ?> · <?php echo e($roundFixtures->count()); ?> matches</span>
      </summary>
      <div class="card-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tieKey => $tieFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $firstFixture = $tieFixtures->first(); ?>
          <details class="fixture-tie mb-3" data-live-key="tie-<?php echo e($round); ?>-<?php echo e($tieKey); ?>">
            <summary class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2" style="cursor: pointer;">
            <h5 class="mb-0" id="fixture-tie-<?php echo e($round); ?>-<?php echo e($tieKey); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixtures->pluck('draw_id')->unique()->count() > 1): ?><span class="badge bg-label-secondary"><?php echo e($firstFixture->draw->drawName); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <span class="text-muted">Tie <?php echo e($firstFixture->tie_nr ?: $loop->iteration); ?> ·</span>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['home', 'away']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->first): ?><span class="text-muted">vs</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="fixture-team-chip" style="--region-color: <?php echo e($firstFixture->lineup_display[$side]['region_color'] ?? '#475569'); ?>">
                  <span class="d-none d-md-inline"><?php echo e($firstFixture->tie_display[$side]); ?></span>
                  <span class="d-md-none"><?php echo e($firstFixture->tie_mobile_display[$side]); ?></span>
                </span>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </h5>
              <span class="text-muted small"><?php echo e($tieFixtures->count()); ?> matches <span class="fixture-toggle"><span class="when-closed">▸ Click to show matches</span><span class="when-open">▾ Click to hide matches</span></span></span>
            </summary>
            <?php echo $__env->make('frontend.fixture.fixture-table', ['fixtures' => $tieFixtures,
              'hideFixtureHeader' => true, 'fixtureTableId' => 'fixturesTable-'.$round.'-'.$tieKey], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          </details>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </details>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\draw-fixtures-show-team.blade.php ENDPATH**/ ?>