

<?php $__env->startSection('title', 'Series – Manage Events'); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<?php $__env->stopSection(); ?>




<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
      <?php echo e($series->name); ?> – Events
    </h4>
    <a href="<?php echo e(route('series.index')); ?>" class="btn btn-outline-secondary">
      Back to Series
    </a>
  </div>

  <div class="row g-4">

    
    <div class="col-xl-7">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Events in this Series</h5>
        </div>

        <div class="card-body p-0">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Event</th>
                <th>Dates</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $seriesEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                  <td><strong><?php echo e($event->name); ?></strong></td>
              
                  <td class="text-end d-flex gap-1 justify-content-end">

  <a href="<?php echo e(route('backend.events.edit', $event)); ?>"
     class="btn btn-sm btn-outline-primary">
    Edit
  </a>

  <form method="POST"
        action="<?php echo e(route('series.events.copy', [$series, $event])); ?>">
    <?php echo csrf_field(); ?>
    <button class="btn btn-sm btn-outline-warning">
      Copy
    </button>
  </form>

  <form method="POST"
        action="<?php echo e(route('series.events.remove', [$series, $event])); ?>"
        onsubmit="return confirm('Remove this event from the series?')">
    <?php echo csrf_field(); ?>
    <?php echo method_field('DELETE'); ?>
    <button class="btn btn-sm btn-outline-danger">
      Remove
    </button>
  </form>

</td>

                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-3">
                    No events in this series yet
                  </td>
                </tr>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    
    <div class="col-xl-5">

      
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Add Existing Event</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="<?php echo e(route('series.events.add', $series)); ?>">
            <?php echo csrf_field(); ?>

            <div class="mb-3">
              <label class="form-label">Event</label>
              <select name="event_id"
                      class="form-select select2"
                      data-placeholder="Select event…"
                      required>
                <option></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($event->id); ?>">
                    <?php echo e($event->name); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>

            <button class="btn btn-primary w-100">
              Add to Series
            </button>
          </form>
        </div>
      </div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Create New Event in Series</h5>
  </div>

  <div class="card-body">
    <form method="POST"
      action="<?php echo e(route('series.events.create', $series)); ?>"
      enctype="multipart/form-data">

      <?php echo csrf_field(); ?>

      
      <div class="mb-3">
        <label class="form-label">Event Name</label>
        <input type="text"
               name="name"
               class="form-control"
               required>
      </div>

      
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label">Start Date</label>
          <input type="date"
                 name="start_date"
                 class="form-control">
        </div>
        <div class="col">
          <label class="form-label">End Date</label>
          <input type="date"
                 name="end_date"
                 class="form-control">
        </div>
      </div>

      
      <div class="mb-3">
        <label class="form-label">Event Type</label>
        <select name="eventType"
                class="form-select"
                required>
          <option value="">Select type…</option>
          <option value="1">Individual</option>
          <option value="2">Team</option>
          <option value="3">Camp</option>
        </select>
      </div>

      
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label">Entry Fee</label>
          <input type="number"
                 name="entryFee"
                 class="form-control"
                 min="0">
        </div>
        <div class="col">
          <label class="form-label">
            Registration Closes (days before start)
          </label>
          <input type="number"
                 name="deadline"
                 class="form-control"
                 min="0">
        </div>
      </div>

      
      <div class="mb-3">
        <label class="form-label">Contact Email</label>
        <input type="email"
               name="email"
               class="form-control">
      </div>

      
      <div class="mb-3">
        <label class="form-label">Event Information</label>
        <textarea name="information"
                  class="form-control"
                  rows="4"></textarea>
      </div>

      
      <div class="mb-3">
        <label class="form-label">Venue Notes</label>
        <textarea name="venue_notes"
                  class="form-control"
                  rows="3"></textarea>
      </div>

      
      <div class="form-check mb-2">
        <input class="form-check-input"
               type="checkbox"
               name="published"
               value="1">
        <label class="form-check-label">
          Published
        </label>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input"
               type="checkbox"
               name="signUp"
               value="1">
        <label class="form-check-label">
          Registration open
        </label>
      </div>
      
<div class="mb-3">
  <label class="form-label">Event Logo</label>

  
  <img id="logo-preview"
       class="img-thumbnail d-none mb-2"
       style="max-height:120px">

  
  <select name="logo_existing"
          class="form-select mb-2">
    <option value="">— Select existing logo —</option>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = File::files(public_path('assets/img/logos')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $logo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($logo->getFilename()); ?>">
        <?php echo e($logo->getFilename()); ?>

      </option>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </select>

  
  <input type="file"
         name="logo_upload"
         class="form-control"
         accept="image/*">

  <small class="text-muted">
    Upload overrides selected logo
  </small>
</div>

      <button class="btn btn-success w-100">
        Create Event
      </button>
    </form>
  </div>
</div>


    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>





<?php $__env->startSection('page-script'); ?>
<script>
  // Debug: expose server-side event lists to browser console
  console.log('seriesEvents', <?php echo json_encode($seriesEvents, 15, 512) ?>);
  console.log('availableEvents', <?php echo json_encode($availableEvents, 15, 512) ?>);
</script>
<script src="<?php echo e(asset(mix('js/seriesEvents.js'))); ?>"></script>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\events.blade.php ENDPATH**/ ?>