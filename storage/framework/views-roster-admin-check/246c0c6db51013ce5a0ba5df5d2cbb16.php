<style>
.file-item:hover {
  background-color: #f8f9fa;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.card .btn-outline-primary:hover {
  background-color: #0d6efd;
  color: #fff;
}
</style>
<?php
  $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584, 585, 763]);
?>

<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

   
      
      <?php echo $__env->make('frontend.event.partials.interpro-draws-mobile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <div class="card p-3 p-sm-4">
        <div class="card-body p-0 pb-2">
          <div class="badge bg-label-primary mb-3" role="alert">
            Click on a Region below to register
          </div>
        </div>

        <?php $regions = $event->region_in_events ?? collect(); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regions->isNotEmpty()): ?>
          <?php echo $__env->make('frontend.event.partials._region_team_picker', ['regions' => $regions], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php else: ?>
          <div class="alert alert-secondary mt-3">
            Regions are not configured for this event.
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      </div>
    </div>


    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

   

      
      <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> Documents
          </h6>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->is_admin($event->id) || auth()->id() == 584): ?>
            <form action="<?php echo e(route('file.store')); ?>" method="POST" enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2 mb-0">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">

              <label class="btn btn-sm btn-outline-primary mb-0">
                <i class="ti ti-upload me-1"></i> Upload
                <input type="file" name="myFile" class="d-none"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>

            </form>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

        <div class="card-body pb-2">

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">

              <div class="d-flex align-items-center">
                <i class="ti ti-file-description text-primary fs-5 me-2"></i>
                <a href="<?php echo e(route('events.documents.show', [$event, $file])); ?>" target="_blank"
                   class="fw-semibold text-dark text-decoration-none">
                  <?php echo e($file->name); ?>

                </a>
              </div>

              <div class="d-flex align-items-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->is_admin($event->id) || auth()->id() == 584): ?>
                  <button type="button" data-id="<?php echo e($file->id); ?>"
                          class="btn btn-sm btn-icon btn-outline-danger deleteFileButton"
                          data-bs-toggle="tooltip" title="Delete">
                    <i class="ti ti-trash"></i>
                  </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-muted">No documents uploaded yet.</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

      </div>


      
      <?php echo $__env->make('frontend.event.partials._venue-scoring', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->make('frontend.event.partials.interpro-draws-desktop', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->renderWhen(isset($myClothingOrders) && $myClothingOrders->isNotEmpty(),
          'frontend.event.partials._my_clothing_orders', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

    </div>
  </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.deleteFileButton').forEach(btn => {
    btn.addEventListener('click', function () {
      const fileId = this.dataset.id;
      const button = this;

      Swal.fire({
        title: 'Delete this file?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-secondary ms-2'
        },
        buttonsStyling: false
      }).then(result => {
        if (result.isConfirmed) {

          fetch(`<?php echo e(url('/file')); ?>/${fileId}`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ '_method': 'DELETE' })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              Swal.fire('Deleted!', 'File removed successfully.', 'success');
              button.closest('.file-item').remove();
            } else {
              Swal.fire('Error', data.msg || 'Delete failed.', 'error');
            }
          })
          .catch(err => {
            console.error('Delete error:', err);
            Swal.fire('Error', 'Something went wrong.', 'error');
          });

        }
      });
    });
  });
});
</script>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\interpro.blade.php ENDPATH**/ ?>