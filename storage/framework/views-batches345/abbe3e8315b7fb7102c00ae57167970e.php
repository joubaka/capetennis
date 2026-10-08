<?php
  $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Event Page'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>">
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>" />
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType === 3): ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/team-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('css/team-workspace.css'))); ?>">
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
    <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <script src="<?php echo e(asset('assets/vendor/libs/quill/katex.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
<div class="mb-3"><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-mail-log.index',$event)); ?>">Email log</a></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('backend.event.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>



  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($event->eventType):
    case (3): ?>  
      <?php echo $__env->make('backend.adminPage.admin_show.team-workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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
  <?php case (12): ?> 
      <?php echo $__env->make('backend.adminPage.admin_show.schools', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>
 <?php case (13): ?> 

      <?php echo $__env->make('backend.adminPage.admin_show.interpro-dash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php break; ?>
    <?php default: ?>
      <div class="alert alert-warning">Unknown event type: <?php echo e($event->eventType); ?></div>
  <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php echo $__env->make('_partials._modals.modal-add-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-add-region', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-add-send-email', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
    <?php echo $__env->make('_partials._modals.modal-add-registration', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('_partials._modals.modal-player-in-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('_partials._modals.modal-edit-team-category', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
    <?php echo $__env->make('_partials._modals.add-category-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>


<script>window.APP_URL = <?php echo json_encode(url('/'), 15, 512) ?>;</script>
<script src="<?php echo e(asset(mix('js/regions.js'))); ?>"></script>
<script src="<?php echo e(asset(mix('js/categories.js'))); ?>"></script>
<script src="<?php echo e(asset('js/players.js')); ?>?v=<?php echo e(filemtime(public_path('js/players.js'))); ?>"></script>
<script src="<?php echo e(asset(mix('js/playerOrder.js'))); ?>"></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType === 3): ?>
<script src="<?php echo e(asset('js/team-workspace.js')); ?>?v=<?php echo e(filemtime(public_path('js/team-workspace.js'))); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<script src="<?php echo e(asset('assets/js/draw.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/draw.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/app-email.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/app-email.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/ui-toasts.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/ui-toasts.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/extended-ui-drag-and-drop.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/extended-ui-drag-and-drop.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/js/menu.js')); ?>?v=<?php echo e(filemtime(public_path('assets/vendor/js/menu.js'))); ?>"></script>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin')): ?>
  <script>window.isAdmin = true;</script>
<?php else: ?>
  <script>window.isAdmin = false;</script>
<?php endif; ?>
<script>
window.routes = {
    changePayStatus: "<?php echo e(route('team.change.pay.status')); ?>",
    replacePlayer: "<?php echo e(route('backend.team.replace.player')); ?>",
  replaceForm: "<?php echo e(route('backend.team.player.replace.form')); ?>",
 addRegionToEvent: "<?php echo e(route('eventRegion.store')); ?>"
};

  window.routes = window.routes || {};
  window.routes.addRegionToEvent = "<?php echo e(route('eventRegion.store')); ?>";
</script>



<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\show.blade.php ENDPATH**/ ?>