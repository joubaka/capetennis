<?php $__env->startSection('title', 'Order of Play – '.$venue->name); ?>

<?php $__env->startSection('content'); ?>
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div><h3>Order of Play</h3><div><?php echo e($event->name); ?></div><strong><?php echo e($venue->name); ?></strong></div>
    <a class="btn btn-outline-primary align-self-start" href="<?php echo e(route('events.show', $event)); ?>#event-draws-match-times">Back to tournament</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableDate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('fixtures.order', [$event->id, $venue->id, $availableDate, 'draw_id' => $selectedDrawId])); ?>"><?php echo e(\Carbon\Carbon::parse($availableDate)->format('D j M')); ?></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('fixtures.order', [$event->id, $venue->id, 'all', 'draw_id' => $selectedDrawId])); ?>">All days</a>
  </div>
  <form method="GET" action="<?php echo e(route('fixtures.order', [$event->id, $venue->id, $date])); ?>" class="mb-4">
    <label for="order-draw" class="form-label">Age group / draw</label>
    <div class="d-flex gap-2">
      <select id="order-draw" name="draw_id" class="form-select">
        <option value="">All draws at this venue</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableDraw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($availableDraw->id); ?>" <?php if((int) $selectedDrawId === (int) $availableDraw->id): echo 'selected'; endif; ?>><?php echo e($availableDraw->drawName); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <button class="btn btn-outline-primary" type="submit">Apply</button>
    </div>
  </form>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture->scheduled_at)->toDateString()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixtureDate => $dayFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <h4 class="mt-4"><?php echo e(\Carbon\Carbon::parse($fixtureDate)->format('l, j F Y')); ?></h4>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dayFixtures->groupBy('draw_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="mb-4">
        <?php echo $__env->make('frontend.fixture.fixture-table', ['draw' => $drawFixtures->first()->draw, 'fixtures' => $drawFixtures, 'fixtureTableId' => 'venue-draw-'.$drawFixtures->first()->draw_id.'-'.$fixtureDate], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="alert alert-info">No published matches at this venue for this selection.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixture\individual-venue.blade.php ENDPATH**/ ?>