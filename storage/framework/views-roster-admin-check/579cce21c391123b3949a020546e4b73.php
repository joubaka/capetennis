<?php
  $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Main Page'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-style'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
    <script src="<?php echo e(asset('assets/js/admin-main.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-header event-header">
    <h3 class="text-center"><?php echo e($event->name); ?></h3>
  </div>

  <div class="card-body px-4">
    
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item">
        <a class="nav-link active ajax-tab" data-url="<?php echo e(route('event.tab.draws', $event->id)); ?>" href="#drawsTab" data-bs-toggle="tab" role="tab">Draws</a>
      </li>
      
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade" id="entriesTab" role="tabpanel"></div>
      <div class="tab-pane fade show active" id="drawsTab" role="tabpanel"></div>
      <div class="tab-pane fade" id="resultsTab" role="tabpanel"></div>
      <div class="tab-pane fade" id="settingsTab" role="tabpanel"></div>
    </div>



  </div>
</div>



<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\eventAdmin\main.blade.php ENDPATH**/ ?>