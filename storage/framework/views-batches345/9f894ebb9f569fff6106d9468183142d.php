<style>
  .winner-home { background-color: rgba(40,167,69,0.25) !important; color:#155724 !important; }
  .loser-home  { background-color: rgba(220,53,69,0.25) !important; color:#721c24 !important; }
  .draw-cell   { background-color: rgba(255,193,7,0.25) !important;  color:#856404 !important; }

  @media (max-width: 768px) {
      .fixtures-table td, .fixtures-table th {
          font-size: 0.85rem;
          padding: 0.4rem;
      }
  }
  .public-match-cards { display: none; }
  .fixtures-table .fixture-player-label { color: #26394d; font-size: .95rem; font-weight: 600; line-height: 1.6; white-space: normal; }
  .fixtures-table .fixture-roster-rank { display: inline-block; color: #536479; font-size: .8rem; font-weight: 500; margin-right: .25rem; }
  .fixtures-table.fixture-table-tie thead { border-color: #d9e2ed; }
  .fixtures-table.fixture-table-tie thead th { background: #eef3fa; color: #26394d; border-color: #d9e2ed; text-transform: none; font-size: .9rem; letter-spacing: normal; padding: .85rem 1rem; }
  .fixture-table-tie .fixture-team-heading { display: inline-block; border-bottom: 2px solid var(--region-color, #475569); padding-bottom: .25rem; }
  .fixture-table-tie td { padding: .7rem 1rem; }
  .fixture-table-tie .fixture-region-badge, .fixture-table-tie .fixture-region { display: none; }
  .fixture-table-tie tbody tr:hover > td:not(.winner-home):not(.loser-home):not(.draw-cell) { background: #f8fafc; }
  .public-fixture-back { background: #fff !important; color: #26394d !important; border-color: #66788d !important; }
  .public-fixture-back:hover, .public-fixture-back:focus { background: #edf0f4 !important; color: #172e45 !important; }
  .public-fixture-status { background: #e4f1e7 !important; color: #235c31 !important; }
  @media (max-width: 767.98px) {
    .public-match-desktop { display: none; }
    .public-match-cards { display: grid; gap: 1rem; }
    .public-match-card { border: 1px solid #d9dee3; border-radius: .6rem; padding: 1rem; overflow-wrap: anywhere; min-width: 0; }
    .public-match-venue { font-weight: 700; color: #26394d; margin-bottom: .5rem; }
    .public-match-time { font-size: 1.9rem; font-weight: 700; line-height: 1.2; color: #12358f; }
    .public-match-date { margin-top: .25rem; color: #49576a; }
    .public-match-players { border-top: 1px solid #d9dee3; margin-top: .75rem; padding-top: .75rem; font-size: 1rem; line-height: 1.6; }
    .public-match-versus { font-size: .8rem; color: #697a8d; margin: .3rem 0; }
    .public-match-score { margin-top: .75rem; font-size: .85rem; color: #697a8d; }
    .public-match-card .fixture-player-label { white-space: normal; color: #172e45; font-weight: 600; }
    .public-match-card .fixture-region, .public-match-card .fixture-roster-rank { color: #49576a; font-weight: 400; }
  }
</style>

<?php
$notBeforeTimes = (int) $event->id === 241;
$fxPlayer1 = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team1) {
        return $fx->team1->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration1) {
        return $fx->registration1->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
};

$fxPlayer2 = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team2) {
        return $fx->team2->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration2) {
        return $fx->registration2->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
};

/* ============================================================
   SCORE HELPERS — SUPPORT BOTH TEAM & INDIVIDUAL
   ============================================================ */
$fxScoreDisplay = function ($r) {
    if (isset($r->team1_score)) {
        return $r->team1_score . ' - ' . $r->team2_score;
    }
    return $r->registration1_score . ' - ' . $r->registration2_score;
};

/* Determine winner for highlight */
$fxWinnerClasses = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture) {
        return match ($fx->winnerSide()) { 'home' => ['winner-home','loser-home'], 'away' => ['loser-home','winner-home'], default => ['',''] };
    }
    if ($fx->fixtureResults->isEmpty()) {
        return ['',''];
    }

    $winner = $fx->winner_id;
    if (!$winner) return ['',''];
    return (int) $winner === (int) $fx->registration1_id
        ? ['winner-home','loser-home'] : ['loser-home','winner-home'];
};
?>


<div class="card">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hideFixtureHeader ?? false)): ?>
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h3 class="mb-1"><?php echo e($draw->drawName); ?> <?php echo e($draw->age); ?></h3>
      <span class="badge <?php echo e($draw->published ? 'public-fixture-status' : 'bg-label-warning'); ?>"><?php echo e($draw->published ? 'Draw published' : 'Draft preview · Draw not published'); ?></span>
      <span class="badge <?php echo e($draw->scheduleIsPublished() ? 'public-fixture-status' : 'bg-label-secondary'); ?>">
        <?php echo e($draw->scheduleIsPublished() ? 'Match times published' : 'Match times to follow'); ?>

      </span>
    </div>
    <a href="<?php echo e(route('events.show', $event)); ?>" class="btn btn-sm btn-outline-secondary public-fixture-back">
      <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to tournament
    </a>
  </div>

  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card-body">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($draw->scheduleIsPublished() || ($hideFixtureHeader ?? false))): ?>
      <div class="alert alert-info" role="status">
        The draw is available, but match times and venues have not been published yet.
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="public-match-cards">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <article class="public-match-card" aria-label="Match <?php echo e($fx->match_nr); ?>">
          <div class="public-match-venue"><?php echo e($fx->venue?->name ?? 'Venue to follow'); ?></div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->scheduled_at): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notBeforeTimes): ?><div class="fw-bold" style="color:#664d03;">NB: NOT BEFORE</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="public-match-time"><?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('H:i')); ?></div>
            <div class="public-match-date"><?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('l, j F Y')); ?></div>
          <?php else: ?>
            <div class="public-match-date">Match time to follow</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div class="public-match-players">
            <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?><?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php else: ?><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration1?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration1?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <div class="public-match-versus">vs</div>
            <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?><?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php else: ?><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration2?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration2?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
          </div>
          <div class="public-match-score">Match <?php echo e($fx->match_nr); ?> · <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><?php echo e($fxScoreDisplay($r)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?> No score yet <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        </article>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="text-muted mb-0">No matches found.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="table-responsive public-match-desktop">
      <table class="table table-bordered align-middle fixtures-table <?php echo e(($hideFixtureHeader ?? false) ? 'fixture-table-tie' : ''); ?>" id="<?php echo e($fixtureTableId ?? 'fixturesTable'); ?>">
        <thead class="table-dark">
          <tr>
            <th class="d-table-cell d-md-none text-center" style="width:5%">+</th>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['home', 'away']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <th style="width:30%">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($hideFixtureHeader ?? false) && $fixtures->first() instanceof \App\Models\TeamFixture): ?>
                  <span class="fixture-team-heading" style="--region-color: <?php echo e($fixtures->first()->lineup_display[$side]['region_color'] ?? '#475569'); ?>" title="<?php echo e($fixtures->first()->tie_display[$side]); ?>"><?php echo e($fixtures->first()->tie_mobile_display[$side]); ?></span>
                <?php else: ?>
                  Player/Team <?php echo e($loop->iteration); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </th>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <th style="width:12%">Score</th>
            <th style="width:13%"><?php echo e($notBeforeTimes ? 'NB: NOT BEFORE time' : 'Time'); ?></th>
            <th style="width:15%">Venue</th>
          </tr>
        </thead>

        <tbody>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

          <?php [$homeClass, $awayClass] = $fxWinnerClasses($fx); ?>

          <tr id="row-<?php echo e($fx->id); ?>">

            
            <td class="d-table-cell d-md-none text-center">
              <button class="btn btn-xs btn-outline-primary rounded-circle toggle-details"
                type="button" data-target="#details-<?php echo e($fx->id); ?>"
                aria-expanded="false" aria-controls="details-<?php echo e($fx->id); ?>"
                aria-label="Show match details"
                style="width:1.5rem;height:1.5rem;line-height:1;font-size:0.75rem;">
                <i class="ti ti-plus"></i>
              </button>
            </td>

            
            <td class="<?php echo e($homeClass); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?>
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration1?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration1?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td class="<?php echo e($awayClass); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?>
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration2?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration2?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td class="text-center" id="result-col-<?php echo e($fx->id); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <span class="badge bg-info text-dark me-1">
                        <?php echo e($fxScoreDisplay($r)); ?>

                    </span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    <span class="text-muted">No score</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->scheduled_at): ?>
                <?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i')); ?>

              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td><?php echo e(optional($fx->venue)->name ?? '—'); ?></td>

          </tr>

          
          <tr id="details-<?php echo e($fx->id); ?>" class="d-none d-md-none bg-light">
            <td colspan="6">
              <div class="p-2">
                <strong>Player/Team 1:</strong> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?><?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php else: ?><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration1?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration1?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><br>
                <strong>Player/Team 2:</strong> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx instanceof \App\Models\TeamFixture): ?><?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php else: ?><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fx->registration2?->players ?? [],'context' => $draw,'separator' => ' + ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fx->registration2?->players ?? []),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'separator' => ' + ']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><br>
                <strong>Score:</strong>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <?php echo e($fxScoreDisplay($r)); ?>

                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    No score
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><br>

                <strong>Venue:</strong> <?php echo e(optional($fx->venue)->name ?? '—'); ?><br>
                <strong><?php echo e($notBeforeTimes ? 'NB: NOT BEFORE time:' : 'Time:'); ?></strong>
                <?php echo e($fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('D H:i') : '—'); ?>

              </div>
            </td>
          </tr>

          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No matches found.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </tbody>
      </table>
    </div>

  </div>
</div>

<?php if (! $__env->hasRenderedOnce('e88297d5-467e-42ca-8e01-04fec084df04')): $__env->markAsRenderedOnce('e88297d5-467e-42ca-8e01-04fec084df04'); ?>
<script>
// Expand/Collapse details on mobile
document.addEventListener('DOMContentLoaded', function () {
$(document).on('click', '.toggle-details', function () {
  const target = $(this).data('target');
  const $row = $(target);
  $row.toggleClass('d-none');
  $(this).find('i').toggleClass('ti-plus ti-minus');
  const expanded = !$row.hasClass('d-none');
  $(this).attr('aria-expanded', expanded ? 'true' : 'false')
    .attr('aria-label', expanded ? 'Hide match details' : 'Show match details');
});
});
</script>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\fixture-table.blade.php ENDPATH**/ ?>