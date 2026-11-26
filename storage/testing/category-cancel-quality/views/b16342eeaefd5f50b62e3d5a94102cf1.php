<?php
  $fixtureScheduleAvailable = ($fixtureScheduleSummary ?? app(\App\Services\PublicDrawPresentation::class)->summary($draw))['available'];
?>
<style>.fixture-round > summary .when-open,.fixture-tie > summary .when-open{display:none}.fixture-round[open] > summary .when-open,.fixture-tie[open] > summary .when-open{display:inline}.fixture-round[open] > summary .when-closed,.fixture-tie[open] > summary .when-closed{display:none}.fixture-tie > summary{padding:.75rem;border:1px solid #d9dee3;border-radius:.4rem;background:#f5f7fa}.fixture-toggle{font-size:.8rem;color:#315785;font-weight:600}</style>
<style>
  .public-fixture-page .badge.bg-label-success { background: #e4f1e7 !important; color: #235c31 !important; }
  .public-fixture-page .badge.bg-label-secondary { background: #edf0f4 !important; color: #35465b !important; }
  @media (max-width: 767.98px) {
    .public-fixture-page .fixture-round > summary,
    .public-fixture-page .fixture-tie > summary { min-height: 44px; }
    .public-fixture-page .fixture-draw-heading,
    .public-fixture-page .fixture-round > summary,
    .public-fixture-page .fixture-round > .card-body { padding: .625rem; }
    .public-fixture-page .fixture-draw-heading h3 { font-size: 1.25rem; }
    .public-fixture-page .fixture-draw-heading .badge { white-space: normal; text-align: left; }
    .public-fixture-page .fixture-round h4 { font-size: 1.1rem; }
    .public-fixture-page .public-tie-matches { border: 0; box-shadow: none; background: transparent; }
    .public-fixture-page .public-tie-matches > .card-body { padding: 0; }
    .public-tie-matches .public-match-cards { gap: .5rem; }
    .public-tie-matches .public-match-card { padding: .625rem; background: #fff; }
    .public-tie-matches .public-match-schedule { display: grid; gap: .125rem .5rem; align-items: baseline; }
    .public-tie-matches .public-match-schedule.has-time { grid-template-columns: minmax(0, 1fr) auto; }
    .public-tie-matches .public-match-not-before,
    .public-tie-matches .public-match-date { grid-column: 1 / -1; margin: 0; font-size: .8rem; }
    .public-tie-matches .public-match-time { grid-column: 2; grid-row: 1; font-size: 1.125rem; }
    .public-tie-matches .public-match-venue { margin: 0; font-size: .875rem; }
    .public-tie-matches .public-match-players { display: grid; gap: .25rem; margin-top: .375rem; padding-top: .375rem; font-size: .9rem; line-height: 1.4; }
    .public-tie-matches .public-match-versus { display: none; }
    .public-tie-matches .fixture-region-side { border-left: 3px solid var(--region-color, #475569); padding-left: .375rem; }
    .public-tie-matches .fixture-region-badge { display: none; }
    .public-tie-matches .public-match-score { margin-top: .375rem; color: #526277; }
    .public-fixture-page .btn { min-height: 44px; display: inline-flex; align-items: center; }
  }
</style>
<style>
  .public-fixture-page summary { cursor: pointer; }
  .public-fixture-page summary:hover { background: #e8eff8; }
  .public-fixture-page summary:focus-visible { outline: 3px solid #173f7a; outline-offset: 3px; }
  .public-fixture-page .fixture-round > summary { border-left: 5px solid #173f7a; background: #eef3fa; }
  .fixture-team-chip { display: inline-block; background: #fff; color: #26394d; border: 1px solid var(--region-color, #475569); padding: .3rem .55rem; border-radius: .35rem; line-height: 1.5; overflow-wrap: anywhere; }
  .public-fixture-page .fixture-tie > summary { display: block; padding: 1rem; list-style: none; }
  .public-fixture-page .fixture-tie > summary::-webkit-details-marker { display: none; }
  .public-fixture-page .fixture-tie-meta { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .65rem; font-size: .85rem; }
  .public-fixture-page .fixture-pairing { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: .75rem; }
  .public-fixture-page .fixture-pairing .fixture-team-chip { display: block; min-width: 0; padding: .65rem .8rem; border: 1px solid #dce4ed; border-left: 4px solid var(--region-color, #475569); font-size: 1rem; font-weight: 600; }
  .public-fixture-page .fixture-versus { font-size: .8rem; color: #64748b; }
  .public-fixture-page .fixture-team-names { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: .25rem .5rem .75rem; color: #526277; overflow-wrap: anywhere; }
  @media (max-width: 575.98px) {
    .public-fixture-page .fixture-pairing, .public-fixture-page .fixture-team-names { gap: .25rem; }
    .public-fixture-page .fixture-pairing .fixture-team-chip { padding: .25rem .375rem; font-size: .875rem; line-height: 1.3; }
    .public-fixture-page .fixture-team-names { padding: 0 .125rem .375rem; }
    .public-fixture-page .fixture-tie > summary { padding: .5rem; }
    .public-fixture-page .fixture-tie-meta { gap: .25rem; margin-bottom: .375rem; }
  }
</style>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->standings_published): ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.team-draw.standings', $draw)); ?>">Draw standings</a>
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.events.standings', $event)); ?>">Full event standings</a>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($enableLiveResults ?? true): ?>
    <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="public-fixture-page" <?php if($draw->published && ($enableLiveResults ?? true)): ?> data-live-results="team-fixtures" <?php endif; ?>>
  <div class="card mb-3">
    <div class="fixture-draw-heading card-body d-flex justify-content-between align-items-start gap-2 gap-md-3 flex-wrap">
      <div>
        <h3 class="mb-2"><?php echo e($draw->drawName); ?></h3>
        <span class="badge <?php echo e($draw->published ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($draw->published ? 'Draw published' : 'Draft preview · Draw not published'); ?></span>
        <span class="badge <?php echo e($fixtureScheduleAvailable ? 'bg-label-success' : 'bg-label-secondary'); ?>"><?php echo e($fixtureScheduleAvailable ? 'Match times published' : 'Match times to follow'); ?></span>
      </div>
      <a href="<?php echo e(route('events.show', $event)); ?>#event-draws-match-times" class="btn btn-sm btn-outline-secondary public-fixture-back">Back to tournament draws</a>
    </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->id === 241 && $fixtureScheduleAvailable): ?>
    <div class="alert mb-3" style="background:#fff3cd;color:#664d03;border:1px solid #e6c76a;" role="note">
      <strong class="d-block mb-1">NB: NOT BEFORE times</strong>
      All scheduled times are NOT BEFORE times. A match will not start before its listed time, but it may start later if earlier matches are still being played. Please be at your assigned venue and ready to play by the listed time, and check Cape Tennis for schedule updates.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($fixtureScheduleAvailable)): ?>
    <div class="alert alert-info" role="status">The draw is available, but match times and venues have not been published yet.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures->groupBy(fn ($fixture) => (int) $fixture->round_nr); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $roundFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $ties = $roundFixtures->groupBy(fn ($fixture) => implode('-', [$fixture->draw_id, $fixture->team_tie_id ?: implode('-', [$fixture->tie_nr, $fixture->region1, $fixture->region2])]));
    ?>
    <details class="fixture-round card mb-3" data-live-key="round-<?php echo e($round); ?>">
      <summary class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap" style="cursor: pointer;">
        <h4 class="mb-0">Round <?php echo e($round ?: '—'); ?></h4>
        <span class="fixture-toggle"><span class="when-closed">▸ Show fixtures</span><span class="when-open">▾ Hide fixtures</span></span>
        <span class="text-muted"><?php echo e($ties->count()); ?> <?php echo e($ties->count() === 1 ? 'tie' : 'ties'); ?> · <?php echo e($roundFixtures->count()); ?> matches</span>
      </summary>
      <div class="card-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tieKey => $tieFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $firstFixture = $tieFixtures->first(); [$tieHomeClass, $tieAwayClass] = \App\Support\ResultPresentation::tieClasses($tieFixtures); ?>
          <details class="fixture-tie mb-3" data-live-key="tie-<?php echo e($round); ?>-<?php echo e($tieKey); ?>">
            <summary class="mb-2" id="fixture-tie-<?php echo e($round); ?>-<?php echo e($tieKey); ?>">
              <div class="fixture-tie-meta">
                <span class="text-muted">Tie <?php echo e($firstFixture->tie_nr ?: $loop->iteration); ?> · <?php echo e($tieFixtures->count()); ?> matches</span>
                <span class="fixture-toggle"><span class="when-closed">▸ Show matches</span><span class="when-open">▾ Hide matches</span></span>
              </div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixtures->pluck('draw_id')->unique()->count() > 1): ?><span class="badge bg-label-secondary"><?php echo e($firstFixture->draw->drawName); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <div class="fixture-pairing">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['home', 'away']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->first): ?><span class="fixture-versus">vs</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="fixture-team-chip <?php echo e($side === 'home' ? $tieHomeClass : $tieAwayClass); ?>" title="<?php echo e($firstFixture->tie_display[$side]); ?>" style="--region-color: <?php echo e($firstFixture->lineup_display[$side]['region_color'] ?? '#475569'); ?>">
                  <?php echo e($firstFixture->tie_mobile_display[$side]); ?><?php if (isset($component)) { $__componentOriginal2c336395b6812522970387a8085dd1d4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2c336395b6812522970387a8085dd1d4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.result-label','data' => ['outcome' => $side === 'home' ? $tieHomeClass : $tieAwayClass]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('result-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['outcome' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($side === 'home' ? $tieHomeClass : $tieAwayClass)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $attributes = $__attributesOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__attributesOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $component = $__componentOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__componentOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?>
                </span>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </summary>
            <div class="fixture-team-names small" aria-label="Full team names">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['home', 'away']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><strong><?php echo e(ucfirst($side)); ?></strong> · <?php echo e($firstFixture->tie_display[$side]); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php echo $__env->make('frontend.fixture.fixture-table', ['fixtures' => $tieFixtures,
              'hideFixtureHeader' => true, 'fixtureTableId' => 'fixturesTable-'.$round.'-'.$tieKey], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          </details>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </details>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($enableLiveResults ?? true): ?>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixture\partials\team-schedule.blade.php ENDPATH**/ ?>