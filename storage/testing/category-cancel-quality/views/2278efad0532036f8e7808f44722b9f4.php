
<?php $__env->startSection('title', 'Standings – '.$event->name); ?>
<?php $__env->startSection('page-style'); ?>
<?php echo $__env->make('backend.event.partials.standings-style', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="event-standings" data-backend-wide>
  <?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Event standings and competition statistics', 'eventWorkspaceRegionalOnly' => $regionalOnly ?? false, 'eventWorkspaceHomeUrl' => ($regionalOnly ?? false) ? route('backend.team-selection.index', $event) : route('admin.events.overview', $event)], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="standings-heading d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h4 mb-0"><i class="ti ti-trophy" aria-hidden="true"></i> Standings &amp; statistics</h1><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($regionalOnly ?? false)): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.scoreboard.team.show', $event)); ?>">View match results</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionalOnly ?? false): ?>
    <p class="alert alert-info">These running standings show published draws and matches across the event. Draft results are excluded.</p>
  <?php else: ?>
  <section class="card card-body mb-4 standings-panel standings-publication">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div><h2 class="h5 mb-1">Public team standings</h2><span class="standings-status <?php echo e($event->standings_published ? 'is-published' : 'is-unpublished'); ?>"><?php echo e($event->standings_published ? 'Published' : 'Unpublished'); ?></span></div>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-draw.createFormat', $event)): ?>
      <div class="d-flex flex-wrap align-items-center gap-2">
      <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('admin.events.standings.preview', $event)); ?>" target="_blank" rel="noopener">Preview public standings</a>
      <form method="POST" action="<?php echo e(route('admin.events.standings.publication', $event)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <input type="hidden" name="standings_published" value="<?php echo e($event->standings_published ? '0' : '1'); ?>">
        <button class="btn <?php echo e($event->standings_published ? 'btn-outline-secondary' : 'btn-primary'); ?>" style="min-height:44px" type="submit"><?php echo e($event->standings_published ? 'Unpublish standings' : 'Publish standings'); ?></button>
      </form>
      </div>
      <?php endif; ?>
    </div>
    <p class="small text-muted mb-0 mt-3">Publishing shows Team standings and match totals on the public event page and enables public draw standings. Only published draws and ties contribute. These are current running standings, not final tournament placings. Event, draw, schedule and final results publication remain separate.</p>
  </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <section class="card card-body mb-4 standings-panel standings-ages" aria-label="Combined age group results">
    <h2 class="h5"><i class="ti ti-users" aria-hidden="true"></i> Combined results by age group</h2>
    <p>Choose an age group to combine boys, girls, mixed and every match type. This clears the gender and category filters.</p>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn standings-age-link" <?php if(!array_filter($filters)): ?> aria-current="page" <?php endif; ?> href="<?php echo e(route('admin.events.standings', $event)); ?>">All age groups</a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($options['age'])->sort(fn ($a, $b) => strnatcasecmp($a, $b)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ageOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a class="btn standings-age-link" <?php if(($filters['age'] ?? '') === $ageOption && empty($filters['gender']) && empty($filters['category'])): ?> aria-current="page" <?php endif; ?> href="<?php echo e(route('admin.events.standings', [$event, 'age' => $ageOption])); ?>"><?php echo e($ageOption === 'Unspecified' ? 'Age unspecified' : 'Under '.substr($ageOption, 1)); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
  <form method="GET" class="card card-body mb-4 standings-panel standings-filters">
    <h2 class="h5"><i class="ti ti-adjustments" aria-hidden="true"></i> Detailed filters</h2>
    <div class="row g-3 align-items-end">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="col-12 col-md-3"><label for="standings-<?php echo e($key); ?>" class="form-label"><?php echo e($label); ?></label>
        <select id="standings-<?php echo e($key); ?>" name="<?php echo e($key); ?>" class="form-select"><option value="">All <?php echo e(strtolower($label)); ?></option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $options[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($option); ?>" <?php if(($filters[$key] ?? '') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="col-12 col-md-3 d-flex flex-wrap gap-2"><button class="btn btn-primary">Apply filters</button><a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.standings', $event)); ?>">Reset</a></div>
    </div>
  </form>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div data-live-results="admin-event-standings">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $matrixSection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Services\TeamRoundRobinMatrixService::class)->supports($matrixSection['draw'])): ?>
      <?php echo $__env->make('backend.team-draw.partials.round-robin-matrix', ['matrix' => app(\App\Services\TeamRoundRobinMatrixService::class)->forDraw($matrixSection['draw'], $matrixSection['rows'], $regionalOnly ?? false), 'matrixScoring' => !($regionalOnly ?? false)], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('frontend.fixtures.partials.team-placement-results', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <section class="card mb-4 standings-results standings-overall"><div class="card-header"><h2 class="h5 mb-2"><?php echo e(array_filter($filters) ? 'Filtered' : 'Full event'); ?> standings by region / school</h2>
    <p class="mb-0">Totals combine team ties across the selected draws. Completed rubbers earn points; ties count as played once all required rubbers are complete. These are running standings, not final tournament placings.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mixedRules): ?><p class="text-muted mb-0 mt-2">Draws use different scoring rules. Overall positions use total points, and tied totals share a position.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php echo $__env->make('backend.event.partials.standings-table', ['rows' => $overall, 'agePoints' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </section>
  <h2 class="h4">Region / school standings in each age group</h2>
  <p>Totals follow the selected filters. With only an age selected, boys, girls, mixed and all match types are combined. These are current running ranks, not final tournament placings; tied regions share a rank.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ageStandings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $age => $ageRows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <section class="card mb-4 standings-results standings-age-results" data-age-standings="<?php echo e($age); ?>">
    <div class="card-header"><h3 class="h5 mb-0"><?php echo e($age === 'Unspecified' ? 'Age group unspecified' : 'Under '.substr($age, 1).' combined results'); ?></h3>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ageMixedRules[$age]): ?><p class="text-muted mt-2 mb-0">Draws in this age group use different scoring rules. Positions use total points, and tied totals share a position.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php echo $__env->make('backend.event.partials.standings-table', ['rows' => $ageRows, 'agePoints' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No age groups match these filters.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <details class="card card-body mb-4 standings-panel standings-filters" data-standings-details>
    <summary style="min-height:44px">Competition statistics and breakdowns</summary>
  <div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['draws' => 'Draws', 'teams' => 'Teams in ties', 'ties' => 'Completed ties', 'rubbers' => 'Completed rubbers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="col-6 col-lg-3"><div class="card card-body h-100 standings-stat" data-stat="<?php echo e($key); ?>"><span class="text-muted"><?php echo e($label); ?></span><strong class="h3 mb-0"><?php echo e($stats[$key]); ?></strong></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <section class="col-12 col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0"><?php echo e($label); ?> breakdown</h2></div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th><?php echo e($label); ?></th><th>Draws</th><th>Teams</th><th>Ties</th><th>Rubbers</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $breakdowns[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $counts): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><th scope="row"><?php echo e($name); ?></th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $counts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><?php echo e($count); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No matching draws.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody></table></div>
    </div></section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  </details>
  <h2 class="h4">Standings by draw</h2>
  <p>Ranks follow each draw's saved scoring rules. Teams tied on all configured criteria share a rank. Gender and age groups come from recorded categories, draw names and gender labels; unlabelled groups appear as Unspecified.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <details class="card mb-4 standings-results standings-draw" data-standings-details><summary class="card-header" style="min-height:44px"><h3 class="h5 mb-1"><?php echo e($section['draw']->drawName); ?></h3><span class="text-muted"><?php echo e($section['category']); ?> · <?php echo e($section['gender']); ?> · <?php echo e($section['age']); ?></span></summary>
      <?php echo $__env->make('backend.event.partials.standings-table', ['rows' => $section['rows'], 'agePoints' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </details>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="alert alert-info">No team draws match these filters.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="text-muted">Legacy fixtures contribute completed rubbers, sets, games and points by region. They do not count as completed team ties.</p>
  </div>
</div>
<script>
let standingsPrintState = null;
window.addEventListener('beforeprint', () => {
  if (standingsPrintState) return;
  standingsPrintState = Array.from(document.querySelectorAll('[data-standings-details]')).map(detail => ({ detail, open: detail.open }));
  standingsPrintState.forEach(item => { item.detail.open = true; });
});
window.addEventListener('afterprint', () => {
  (standingsPrintState || []).forEach(item => { if (item.detail.isConnected) item.detail.open = item.open; });
  standingsPrintState = null;
});
</script>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\standings.blade.php ENDPATH**/ ?>