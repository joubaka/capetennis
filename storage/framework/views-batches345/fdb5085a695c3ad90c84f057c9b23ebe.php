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
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">

 
  <script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>">
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
  <script src="<?php echo e(asset('assets/js/draw-show.js')); ?>"></script>

  <script src="<?php echo e(asset('assets/js/my-functions.js')); ?>"></script>
<?php $__env->stopSection(); ?>




<?php $__env->startSection('content'); ?>
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceActive' => 'draws'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="row">
  <div class="col-12">

    <ul class="nav nav-pills mb-2">
      <li class="nav-item me-2">
        <button id="create-draw-button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#drawModal">
          Create Draw
        </button>
      </li>
    </ul>

    <div class="card">
      <div class="row">
        <div class="col-12">
          <div class="list-group m-2">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php echo $__env->make('backend.draw._includes.draw_detail_index', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </div>
        </div>
      </div>
    </div>

  </div>

</div>




<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Create Draw</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="create-draw-form">

        <div class="modal-body">

          <div class="mb-3">
            <ul class="list-group" id="pending-tasks">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li data-id="<?php echo e($region->pivot->id); ?>" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                  <span><?php echo e($region->region_name); ?></span>
                </li>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
          </div>

          <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">

        <div class="mt-4">
    <h5>Draw Format Type</h5>

    <select name="drawType" id="drawTypeSelect" class="form-select form-select-sm">

        <optgroup label="Team Formats">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($drawType->id); ?>">
                    <?php echo e($drawType->drawTypeName); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </optgroup>

        <optgroup label="Individual Formats">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $individualDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($drawType->id); ?>">
                    <?php echo e($drawType->drawTypeName); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </optgroup>

    </select>
</div>


          <div class="row mt-3">
            <div class="col-md">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="form-check form-check-primary mt-2">
                  <input class="form-check-input" name="category[]" type="checkbox" value="<?php echo e($eventCategory->id); ?>">
                  <label class="form-check-label"><?php echo e($eventCategory->category->name); ?></label>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>

          <div class="pt-4">
            <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3">Create Fixtures</button>
            <button type="reset" class="btn btn-label-secondary">Cancel</button>
          </div>

        </div>

      </form>

    </div>
  </div>
</div>



<script>
  var venues = <?php echo $venues->toJson(); ?>;
</script>
<script>
    window.APP_URL = "<?php echo e(url('/')); ?>";

 
    window.storeVenueRoute = "<?php echo e(route('backend.draw.venues.store', ['draw' => $draw->id])); ?>";

</script>



<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\cavaliers-trials-show.blade.php ENDPATH**/ ?>