<?php
  $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Event Page'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>" />
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>" />
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>" />
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/quill/katex.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
  <script src="<?php echo e(asset('assets/js/admin-showver3.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/js/draw.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/js/app-email.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/js/ui-toasts.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/js/extended-ui-drag-and-drop.js')); ?>"></script>
  
  
<?php $__env->stopSection(); ?>

<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

<?php $__env->startSection('content'); ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($event->eventType):
    case (3): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.team_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>

    <?php case (5): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.cavaliers_trials_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>

    <?php case (6): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.individual_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>

    <?php case (10): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.admin_tournament_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>

    <?php case (11): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.overbergTrialsAdmin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>

    <?php default: ?>
      
      <?php echo $__env->make('backend.adminPage.admin_show.team_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php echo $__env->make('_partials._modals.modal-add-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-add-region', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-add-send-email', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-add-registration', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-player-in-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-edit-team-category', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.add-category-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<script>
  // Prefer roles/permissions over hard-coded user IDs
  <?php
    $isAdmin = method_exists(auth()->user(), 'hasRole')
      ? auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin')
      : (bool) (auth()->user()->is_admin ?? false);
  ?>
  const isAdmin = <?php echo json_encode($isAdmin, 15, 512) ?>;
  console.log('isAdmin:', isAdmin);
</script>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\show2.blade.php ENDPATH**/ ?>