<?php
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Manage Players'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<script src="<?php echo e(asset('assets/js/draw-players.js')); ?>"></script>

<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="card-header event-header">
    <h3 class="text-center">Players <?php echo e($draw->drawName); ?></h3>
  </div>

  <div class="card-body px-4">
<div class="draw-player-toolbar d-flex justify-content-between mb-3">
  <div>
    <button id="import-category" class="btn btn-secondary">Import From Category</button>
  </div>
  <div class="draw-player-add">
    <select id="player-select" class="form-select d-inline w-auto">
      <option value="">Select Player to Add</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allPlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($player->id); ?>"><?php echo e($player->name); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <button id="add-player" class="btn btn-primary">Add Player</button>
  </div>
</div>

<div class="table-responsive" role="region" aria-label="Draw players" tabindex="0">
<table class="table table-bordered" id="draw-players-table">
  <thead>
    <tr>
      <th>#</th>
      <th>Name</th>
      <th>Team</th>
      <th>Category</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <!-- Populated via AJAX -->
  </tbody>
</table>
</div>




  </div>
</div>







<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\manage-players.blade.php ENDPATH**/ ?>