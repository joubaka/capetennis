<?php
  $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Draw – '.$draw->name); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>">
<?php $__env->stopSection(); ?>



<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/draw-show.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/schedule.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<input type="hidden" id="drawId" value="<?php echo e($draw->id); ?>">

<script>
window.scheduleRoutes = {
    data  : "<?php echo e(route('backend.draw.schedule.index', $draw->id)); ?>",
    apply : "<?php echo e(route('backend.draw.schedule.apply', $draw->id)); ?>",
    auto  : "<?php echo e(route('backend.draw.schedule.auto', $draw->id)); ?>",
    clear : "<?php echo e(route('backend.draw.schedule.clear', $draw->id)); ?>"
};
</script>




<div class="card-header mb-3">
    <h3 class="text-center"><?php echo e($draw->name); ?></h3>
    <h6 class="text-center text-muted"><?php echo e($draw->event->name); ?></h6>
</div>



<ul class="nav nav-tabs mb-3" id="drawTabs">
  <li class="nav-item">
    <a class="nav-link active" data-bs-toggle="tab" href="#fixturesTab">Fixtures</a>
  </li>

  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#scheduleTab">Schedule</a>
  </li>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawType->type === 'individual'): ?>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#bracketTab">Bracket</a>
  </li>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawType->is_round_robin): ?>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#roundRobinTab">Round Robin</a>
  </li>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ul>



<div class="tab-content">

  
  <div class="tab-pane fade show active" id="fixturesTab">
      <?php echo $__env->make('backend.fixture.fixture-table-admin', ['draw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>

  
  <div class="tab-pane fade" id="scheduleTab">
      <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            Schedule Matches
          </button>
      </div>

      <?php echo $__env->make('backend.schedule.schedule-table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> 
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawType->type === 'individual'): ?>
  <div class="tab-pane fade" id="bracketTab">
      <div id="bracketContainer"></div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawType->is_round_robin): ?>
  <div class="tab-pane fade" id="roundRobinTab">
      <div id="rrMatrixContainer"></div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>



<?php echo $__env->make('backend.schedule._modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\show.blade.php ENDPATH**/ ?>