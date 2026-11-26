
<?php $__env->startSection('title', 'Standings – '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div id="public-age-points" class="container-xxl py-4">
  <?php
    $standingsRoute = match (true) {
        $publicPreview ?? false => 'admin.events.standings.preview',
        $privateStandings ?? false => 'frontend.events.private-standings',
        default => 'frontend.events.standings',
    };
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicPreview ?? false): ?>
    <div class="alert alert-info" role="status"><strong>Private preview</strong> — this shows the public standings layout using only published draws and ties. Previewing does not publish anything.</div>
  <?php elseif($privateStandings ?? false): ?>
    <div class="alert alert-info text-dark" role="status"><strong>Private team standings</strong> for super admins and assigned venue scorers.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <style>
    #public-age-points { max-width: 1120px; min-width: 0; }
    #public-age-points .points-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; }
    #public-age-points h1 { color:#203b50; font-size:clamp(1.5rem, 4vw, 2rem); font-weight:700; letter-spacing:-.03em; overflow-wrap:anywhere; margin:0; }
    #public-age-points .points-intro { color:#61756c; margin-bottom:1.5rem; }
    #public-age-points .points-filter { display:flex; flex-wrap:wrap; align-items:end; gap:.75rem; padding:1rem; background:#edf3ed; border:1px solid #dce6dc; border-radius:12px; margin-bottom:1.25rem; }
    #public-age-points .points-filter-field { min-width:0; flex:1 1 12rem; max-width:24rem; }
    #public-age-points .points-filter label { color:#425c50; font-size:.85rem; margin-bottom:.35rem; display:block; }
    #public-age-points .form-select, #public-age-points .btn { min-height:44px; }
    #public-age-points a.btn { color:#365940; border-color:#b9cdbf; background:#fff; }
    #public-age-points a.btn:hover, #public-age-points a.btn:focus-visible { color:#284631; background:#e7f0e5; border-color:#88a993; }
    #public-age-points .points-groups { display:grid; grid-template-columns:1fr; gap:1.25rem; }
    #public-age-points .points-card { min-width:0; background:#fff; border:1px solid #dfe7e2; border-radius:14px; overflow:hidden; }
    #public-age-points .points-card-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1.1rem 1.25rem; background:#233f53; border-bottom:4px solid #88a993; }
    #public-age-points .points-card:nth-child(even) .points-card-header { background:#3f6656; border-bottom-color:#a9c0a5; }
    #public-age-points .points-card-header h2 { color:#fff; font-size:1.15rem; font-weight:600; margin:0; }
    #public-age-points .points-card-header span { color:#deebe4; font-size:.75rem; }
    #public-age-points .points-card-heading { display:flex; flex-wrap:wrap; align-items:baseline; gap:.3rem .75rem; }
    #public-age-points .points-card-heading span { font-size:.85rem; font-weight:600; }
    #public-age-points .points-table-scroll { overflow-x:auto; }
    #public-age-points .points-table { width:100%; min-width:520px; border-collapse:collapse; }
    #public-age-points .points-position { width:60px; text-align:center; font-size:1.1rem; font-weight:700; color:#365940; }
    #public-age-points .points-record { display:block; color:#617168; font-size:.8rem; font-weight:400; margin-top:.3rem; }
    #public-age-points .points-details { border-top:1px solid #edf1ec; padding:1rem 1.25rem; }
    #public-age-points .points-details summary { cursor:pointer; color:#365940; font-weight:600; min-height:44px; display:flex; align-items:center; }
    #public-age-points .points-details summary::after { content:'⌄'; margin-left:auto; padding-left:.75rem; font-size:1.2rem; }
    #public-age-points .points-details[open] summary::after { content:'⌃'; }
    #public-age-points .points-details > .card { margin-top:.75rem; }
    #public-age-points .team-rr-scroll { overscroll-behavior-x:contain; }
    #public-age-points .points-table tbody tr[data-position="1"] { background:#f1f6ed; }
    #public-age-points .points-table th, #public-age-points .points-table td { padding:.9rem 1.25rem; }
    #public-age-points .points-table thead { color:#617168; background:#f6f8f5; font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
    #public-age-points .points-table td, #public-age-points .points-table thead th:last-child { width:90px; text-align:right; }
    #public-age-points .points-table thead th { text-align:left; }
    #public-age-points .points-table td.points-position { width:48px; text-align:center; }
    #public-age-points .points-details .points-table thead th:not(:first-child) { width:90px; text-align:right; }
    #public-age-points .points-format-breakdown th { min-width:8rem; }
    #public-age-points .points-table tbody tr + tr { border-top:1px solid #edf1ec; }
    #public-age-points .points-table tbody th { color:#2d4350; font-weight:600; font-size:.9rem; overflow-wrap:anywhere; }
    #public-age-points .points-pill { display:inline-block; min-width:40px; padding:.35rem .6rem; border-radius:8px; background:#e7f0e5; color:#365940; font-size:1rem; font-weight:700; }
    #public-age-points .points-region { display:inline-block; width:4px; height:1rem; border-radius:3px; margin-right:.5rem; vertical-align:middle; background:var(--region-color); }
    #public-age-points .points-empty { padding:1rem 1.25rem; color:#617168; font-size:.85rem; margin:0; }
    #public-age-points .points-note { font-size:.85rem; color:#617168; margin:1rem 0; }
    @media(max-width:575.98px) {
      #public-age-points .points-filter-field { max-width:none; flex-basis:100%; }
      #public-age-points .points-table th, #public-age-points .points-table td { padding:.85rem .9rem; }
      #public-age-points .points-card-header { padding:1rem; }
    }
  </style>
  <div class="points-heading">
    <h1><?php echo e($event->name); ?></h1>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicPreview ?? false): ?>
    <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.standings', $event)); ?>">Back to admin standings</a>
    <?php else: ?>
    <a class="btn btn-outline-secondary" href="<?php echo e(route('events.show', $event)); ?>">Back to tournament</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <p class="points-intro"><strong>Team standings</strong> by age group. See each region / school’s position, total points and match record, with boys’, girls’ and mixed points combined. Only published draws and ties contribute.</p>
  <form method="GET" action="<?php echo e(route($standingsRoute, $event)); ?>" class="points-filter">
    <div class="points-filter-field">
      <label for="standings-age">Age group</label>
      <select class="form-select" id="standings-age" name="age">
        <option value="">All age groups</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($options['age'])->sort(fn ($a, $b) => strnatcasecmp($a, $b)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($option); ?>" <?php if(($filters['age'] ?? '') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Apply filter</button>
    <a class="btn btn-outline-secondary" href="<?php echo e(route($standingsRoute, $event)); ?>">Reset</a>
  </form>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div data-live-results="event-standings">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pointSharingEnabled ?? false): ?>
    <section class="points-card mb-4" data-shared-overall-standings>
      <div class="points-card-header"><h2>Overall regional standings</h2><span>Includes shared points</span></div>
      <p class="points-empty">Shared points apply to the selected results below. Source teams retain their points; age-group competition standings remain unchanged.</p>
      <div class="points-table-scroll" tabindex="0" role="region" aria-label="Overall regional standings including shared points">
        <table class="points-table"><thead><tr><th scope="col">Pos.</th><th scope="col">Region / school</th><th scope="col">Match points</th><th scope="col">Tie points</th><th scope="col">Total points</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $overall; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr><td class="points-position"><?php echo e($row['rank'] ?? '—'); ?></td><th scope="row"><?php echo e($row['name']); ?><?php echo $__env->make('frontend.fixtures.partials.shared-point-contributions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></th><td><?php echo e(\App\Support\StandingsPoints::format($row['match_points'])); ?></td><td><?php echo e(\App\Support\StandingsPoints::format($row['tie_points'])); ?></td><td><?php echo e(\App\Support\StandingsPoints::format($row['points'])); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No published regional points yet.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody></table>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mixedRules): ?><p class="points-empty">Draws use different scoring rules. Overall regions are listed alphabetically without a combined rank.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="points-empty">Equal splits display up to four decimal places; calculations retain full precision.</p>
    </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->make('frontend.fixtures.partials.team-placement-results', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="points-groups">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ageStandings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $age => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $colors = collect($ageGroups[$age] ?? [])->pluck('color', 'name');
        $hasResults = collect($rows)->contains(fn ($row) => $row['rubber_wins'] + $row['rubber_losses'] > 0);
        $formats = ($privateStandings ?? false) ? ($agePointFormats[$age] ?? []) : [];
        $progress = ($privateStandings ?? false) ? ($ageMatchProgress[$age] ?? null) : null;
      ?>
      <section class="points-card" data-age-points="<?php echo e($age); ?>">
        <div class="points-card-header">
          <div class="points-card-heading">
            <h2><?php echo e($age === 'Unspecified' ? 'Age group unspecified' : $age.' standings'); ?></h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress): ?>
              <span data-match-progress><?php echo e($progress['completed']); ?> / <?php echo e($progress['total']); ?> matches completed · <?php echo e($progress['remaining']); ?> remaining</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <span><?php echo e(count($rows)); ?> regions / schools</span>
        </div>
        <div class="points-table-scroll" tabindex="0" role="region" aria-label="<?php echo e($age); ?> points table; scroll horizontally if needed">
        <table class="points-table" aria-label="<?php echo e($age); ?> points by region or school">
          <thead><tr><th scope="col">Pos.</th><th scope="col">Region / school</th><th scope="col">Match points</th><th scope="col">Tie points</th><th scope="col">Total points</th></tr></thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr data-position="<?php echo e($hasResults ? $row['rank'] : ''); ?>"><td class="points-position"><?php echo e($hasResults ? ($row['rank'] ?? '—') : '—'); ?></td><th scope="row"><span class="points-region" style="--region-color:<?php echo e($colors->get($row['name'], '#617168')); ?>" aria-hidden="true"></span><?php echo e($row['name']); ?><span class="points-record"><?php echo e($row['rubber_wins']); ?> won · <?php echo e($row['rubber_losses']); ?> lost · <?php echo e($row['rubber_wins'] + $row['rubber_losses']); ?> matches</span></th><td data-match-points><?php echo e($row['match_points']); ?></td><td data-tie-points><?php echo e($row['tie_points']); ?></td><td><span class="points-pill" data-points="<?php echo e($row['points']); ?>"><?php echo e($row['points']); ?></span></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
        </div>
        <details class="points-details">
          <summary>Points breakdown and match statistics</summary>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formats): ?>
            <div class="points-table-scroll table-responsive" tabindex="0" role="region" aria-label="<?php echo e($age); ?> points by format; scroll horizontally if needed">
              <table class="points-table points-format-breakdown">
                <caption class="caption-top">Points by format</caption>
                <thead><tr>
                  <th scope="col">Region / school</th>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $formats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th scope="col"><?php echo e($format); ?></th>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <th scope="col">Total</th>
                </tr></thead>
                <tbody>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                      <th scope="row"><?php echo e($row['name']); ?></th>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = array_keys($formats); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td data-format-points="<?php echo e($drawId); ?>"><?php echo e(isset($row['format_points'][$drawId]) ? \App\Support\StandingsPoints::format($row['format_points'][$drawId]) : '—'); ?></td>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <td><?php echo e(\App\Support\StandingsPoints::format($row['points'])); ?></td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $publicMatrices[$age] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $matrix): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            <?php echo $__env->make('backend.team-draw.partials.round-robin-matrix', ['matrix' => $matrix, 'matrixScoring' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($formats)): ?>
              <p class="points-empty">No published round-robin encounters are available for this age group yet.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </details>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ageMixedRules[$age]): ?>
          <p class="points-empty">Points use each draw’s scoring rules. <?php echo e(($privateStandings ?? false) ? 'Positions use total points.' : 'Regions / schools are listed alphabetically.'); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$hasResults): ?><p class="points-empty">No completed match points yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </section>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="points-empty">No published points or participants for this age group yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($privateStandings ?? false): ?>
      <p class="points-note">Match points plus tie bonus points equal total points. Expand the breakdown to see each format’s contribution. When formats use different scoring rules, age-group positions use total points and tied totals share a position. Standings reflect completed published results and may change while play continues.</p>
    <?php else: ?>
      <p class="points-note">Match points plus tie bonus points equal total points. Expand the breakdown to see opponents, encounter scores and standings for each round-robin draw. Positions follow each draw’s saved scoring and tie-break rules; equal records can share a position. Standings reflect completed published results and may change while play continues. Different scoring rules within an age group mean no combined positions are shown.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixtures\event-standings.blade.php ENDPATH**/ ?>