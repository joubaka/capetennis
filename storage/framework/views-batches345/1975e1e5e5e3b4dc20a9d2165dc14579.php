<?php $__env->startSection('title', 'Standings – '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div data-backend-wide>
  <?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Event standings and competition statistics'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h1 class="h4 mb-0">Standings &amp; statistics</h1><a class="btn btn-outline-primary" href="<?php echo e(route('backend.scoreboard.team.show', $event)); ?>">View match results</a></div>
  <section class="card card-body mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div><h2 class="h5 mb-1">Public team standings</h2><span class="badge bg-label-<?php echo e($event->standings_published ? 'success' : 'secondary'); ?>"><?php echo e($event->standings_published ? 'Published' : 'Unpublished'); ?></span></div>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-draw.createFormat', $event)): ?>
      <form method="POST" action="<?php echo e(route('admin.events.standings.publication', $event)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <input type="hidden" name="standings_published" value="<?php echo e($event->standings_published ? '0' : '1'); ?>">
        <button class="btn <?php echo e($event->standings_published ? 'btn-outline-secondary' : 'btn-primary'); ?>" style="min-height:44px" type="submit"><?php echo e($event->standings_published ? 'Unpublish standings' : 'Publish standings'); ?></button>
      </form>
      <?php endif; ?>
    </div>
    <p class="small text-muted mb-0 mt-3">Publishing shows Team standings and match totals on the public event page and enables public draw standings. Only published draws and ties contribute. These are current running standings, not final tournament placings. Event, draw, schedule and final results publication remain separate.</p>
  </section>
  <form method="GET" class="card card-body mb-4">
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
  <div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['draws' => 'Draws', 'teams' => 'Teams in ties', 'ties' => 'Completed ties', 'rubbers' => 'Completed rubbers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="col-6 col-lg-3"><div class="card card-body h-100"><span class="text-muted"><?php echo e($label); ?></span><strong class="h3 mb-0"><?php echo e($stats[$key]); ?></strong></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <section class="card mb-4"><div class="card-header"><h2 class="h5 mb-2"><?php echo e(array_filter($filters) ? 'Filtered' : 'Full event'); ?> standings by region / school</h2>
    <p class="mb-0">Totals combine team ties across the selected draws. Completed rubbers earn points; ties count as played once all required rubbers are complete. These are running standings, not final tournament placings.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mixedRules): ?><p class="text-warning mb-0 mt-2">Draws use different scoring rules. Totals are shown alphabetically without an overall rank; use each draw's standings for its ranking.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php echo $__env->make('backend.event.partials.standings-table', ['rows' => $overall], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </section>
  <div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <section class="col-12 col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0"><?php echo e($label); ?> breakdown</h2></div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th><?php echo e($label); ?></th><th>Draws</th><th>Teams</th><th>Ties</th><th>Rubbers</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $breakdowns[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $counts): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><th scope="row"><?php echo e($name); ?></th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $counts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><?php echo e($count); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No matching draws.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody></table></div>
    </div></section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <h2 class="h4">Standings by draw</h2>
  <p>Ranks follow each draw's saved scoring rules. Teams tied on all configured criteria share a rank. Gender and age groups come from recorded categories, draw names and gender labels; unlabelled groups appear as Unspecified.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <section class="card mb-4"><div class="card-header"><h3 class="h5 mb-1"><?php echo e($section['draw']->drawName); ?></h3><span class="text-muted"><?php echo e($section['category']); ?> · <?php echo e($section['gender']); ?> · <?php echo e($section['age']); ?></span></div>
      <?php echo $__env->make('backend.event.partials.standings-table', ['rows' => $section['rows']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="alert alert-info">No team draws match these filters.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="text-muted">Legacy fixtures contribute completed rubbers, sets, games and points by region. They do not count as completed team ties.</p>
  </div>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\standings.blade.php ENDPATH**/ ?>