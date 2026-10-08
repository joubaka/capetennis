
<?php $__env->startSection('title', 'Event Scoreboard'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', [
  'eventWorkspaceActive' => 'more',
  'eventWorkspaceIcon' => 'ti-scoreboard',
  'eventWorkspaceSubtitle' => 'Team scoreboard',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="ti ti-trophy me-1"></i>Event scoreboard</h5>
      </div>
      <div class="card-body">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $scoreboard; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $age => $regions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php echo $__env->make('backend.scoreboard.partials.age-table', [
            'age' => $age,
            'regions' => $regions
          ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <p class="text-muted text-center">No scoreboard data found.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\teamScoreboard.blade.php ENDPATH**/ ?>