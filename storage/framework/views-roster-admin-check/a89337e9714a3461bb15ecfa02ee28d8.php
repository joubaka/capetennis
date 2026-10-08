

<?php $__env->startSection('title', 'Event Details'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>" />
<?php $__env->stopSection(); ?>

<!-- Page -->
<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/createSchedule.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Create Schedule</h5>
  </div>
  <div class="row">
  <div class="col-md-6 col-12 mb-md-0 mb-4">
    <h5>Pending Tasks</h5>

    <ul class="list-group list-group-flush" id="pending-tasks">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li data-fixtureid="<?php echo e($fixture->id); ?>" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center ">
        <span><?php echo e($fixture->bracket->name); ?>-<?php echo e($fixture->match_nr); ?> <?php echo e($fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''); ?> vs <?php echo e($fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:'Bye/not scheduled'); ?></span>
       
      </li>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </div>

  <div class="col-md-6 col-12 mb-md-0 mb-4">
    <h5><?php echo e($venue->name); ?> - <?php echo e($numcourts); ?> courts</h5>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="card m-2 ">
        <div class="card-header"><h5><?php echo e($slot); ?> </h5></div>
        <div class="card-body shadow-none bg-transparent border border-primary"> <ul data-venue='<?php echo e($venue->id); ?>' data-date='<?php echo e($date); ?>' data-slot='<?php echo e($slot); ?>' class="  list-group list-group-flush slots " id="slots-<?php echo e($key); ?>">

     
 
    </ul></div>

   

    </div>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\schedule\create-schedule.blade.php ENDPATH**/ ?>