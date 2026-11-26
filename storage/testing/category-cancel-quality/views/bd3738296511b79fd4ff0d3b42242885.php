

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
<div class="container-xl series-events-admin">

  
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
      <?php echo e($series->name); ?> – Events
    </h4>
    <a href="<?php echo e(route('series.index')); ?>" class="btn btn-outline-secondary">
      Back to Series
    </a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="text-muted"><?php echo e($seriesEvents->count()); ?> events in this series. Edit an event for its full setup; removing it here keeps the event and its records.</p>
  <div class="row g-4">

    
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Events in this Series</h5>
        </div>

        <div class="card-body p-0 table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Event</th>
                <th>Dates</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $seriesEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                  <td><strong><?php echo e($event->name); ?></strong><div class="small text-muted"><?php echo e($event->published ? 'Published' : 'Unpublished'); ?> · <?php echo e($event->signUp ? 'Registration open' : 'Registration closed'); ?></div></td>
                  <td><?php echo e($event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('d M Y') : 'Date not set'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->end_date && $event->end_date !== $event->start_date): ?><br><?php echo e(\Illuminate\Support\Carbon::parse($event->end_date)->format('d M Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              
                  <td class="text-end series-event-actions">

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

    
    <div class="col-12">

      
      <details class="card mb-4" <?php echo e($errors->has('event_id') ? 'open' : ''); ?>>
        <summary class="card-header">Add Existing Event</summary>
        <div class="card-body">
          <form method="POST" action="<?php echo e(route('series.events.add', $series)); ?>">
            <?php echo csrf_field(); ?>

            <div class="mb-3">
              <p class="small text-muted">Attach an existing event to this series. Selecting an event from another series moves its series association.</p>
              <label class="form-label" for="series-existing-event">Event</label>
              <select id="series-existing-event" name="event_id"
                      class="form-select select2"
                      data-placeholder="Select event…"
                      required>
                <option></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($event->id); ?>" <?php if((string) old('event_id') === (string) $event->id): echo 'selected'; endif; ?>>
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
      </details>

<details class="card" <?php echo e($errors->any() && !$errors->has('event_id') ? 'open' : ''); ?>>
  <summary class="card-header">Create New Event in Series</summary>

  <div class="card-body">
    <form method="POST"
      action="<?php echo e(route('series.events.create', $series)); ?>"
      enctype="multipart/form-data">

      <?php echo csrf_field(); ?>

      <p class="small text-muted">Create a new event attached to this series. Publication and registration stay closed unless selected below.</p>
      
      <div class="mb-3">
        <label class="form-label" for="series-name">Event Name</label>
        <input type="text"
               id="series-name" name="name" value="<?php echo e(old('name')); ?>"
               class="form-control"
               required>
      </div>

      
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label" for="series-start_date">Start Date</label>
          <input type="date"
                 id="series-start_date" name="start_date" value="<?php echo e(old('start_date')); ?>"
                 class="form-control">
        </div>
        <div class="col">
          <label class="form-label" for="series-end_date">End Date</label>
          <input type="date"
                 id="series-end_date" name="end_date" value="<?php echo e(old('end_date')); ?>"
                 class="form-control">
        </div>
      </div>

      
      <div class="mb-3">
        <label class="form-label" for="series-eventType">Event Type</label>
        <select id="series-eventType" name="eventType"
                class="form-select"
                required>
          <option value="">Select type…</option>
          <option value="1" <?php if((string) old('eventType') === '1'): echo 'selected'; endif; ?>>Individual</option>
          <option value="2" <?php if((string) old('eventType') === '2'): echo 'selected'; endif; ?>>Team</option>
          <option value="3" <?php if((string) old('eventType') === '3'): echo 'selected'; endif; ?>>Camp</option>
        </select>
      </div>

      
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label" for="series-entryFee">Entry Fee</label>
          <input type="number"
                 id="series-entryFee" name="entryFee" value="<?php echo e(old('entryFee')); ?>"
                 class="form-control"
                 min="0">
        </div>
        <div class="col">
          <label class="form-label" for="series-deadline">
            Registration Closes (days before start)
          </label>
          <input type="number"
                 id="series-deadline" name="deadline" value="<?php echo e(old('deadline')); ?>"
                 class="form-control"
                 min="0">
        </div>
      </div>

      <details class="border rounded p-3 mb-3" <?php echo e($errors->hasAny(['email','information','venue_notes','logo_existing','logo_upload']) ? 'open' : ''); ?>><summary>Contact, information and logo</summary>
      <div class="mt-3">
      
      <div class="mb-3">
        <label class="form-label" for="series-email">Contact Email</label>
        <input type="email"
               id="series-email" name="email" value="<?php echo e(old('email')); ?>"
               class="form-control">
      </div>

      
      <div class="mb-3">
        <label class="form-label" for="series-information">Event Information</label>
        <textarea id="series-information" name="information"
                  class="form-control"
                  rows="4"><?php echo e(old('information')); ?></textarea>
      </div>

      
      <div class="mb-3">
        <label class="form-label" for="series-venue_notes">Venue Notes</label>
        <textarea id="series-venue_notes" name="venue_notes"
                  class="form-control"
                  rows="3"><?php echo e(old('venue_notes')); ?></textarea>
      </div>

      
<div class="mb-3">
  <label class="form-label" for="series-logo_existing">Event Logo</label>

  
  <img id="logo-preview"
       class="img-thumbnail d-none mb-2"
       style="max-height:120px">

  
  <select id="series-logo_existing" name="logo_existing"
          class="form-select mb-2">
    <option value="">— Select existing logo —</option>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = File::files(public_path('assets/img/logos')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $logo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($logo->getFilename()); ?>" <?php if(old('logo_existing') === $logo->getFilename()): echo 'selected'; endif; ?>>
        <?php echo e($logo->getFilename()); ?>

      </option>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </select>

  
  <input type="file"
         id="series-logo-upload" name="logo_upload" aria-label="Upload event logo"
         class="form-control"
         accept="image/*">

  <small class="text-muted">
    Upload overrides selected logo
  </small>
</div>

      </div></details>
      
      <div class="form-check mb-2">
        <input class="form-check-input"
               type="checkbox"
               id="series-event-published" name="published"
               value="1" <?php if(old('published')): echo 'checked'; endif; ?>>
        <label class="form-check-label" for="series-event-published">
          Publish event
        </label>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input"
               type="checkbox"
               id="series-event-registration" name="signUp"
               value="1" <?php if(old('signUp')): echo 'checked'; endif; ?>>
        <label class="form-check-label" for="series-event-registration">
          Registration open
        </label>
      </div>
      <button class="btn btn-success w-100">
        Create Event
      </button>
    </form>
  </div>
</details>


    </div>
  </div>
</div>
<style>
.series-events-admin :is(.btn,input:not([type=checkbox]),select,summary) { min-height:44px !important; }
.series-events-admin summary { cursor:pointer; align-content:center; }
.series-event-actions { min-width:210px; }
.series-event-actions form { display:inline-block; margin:.2rem 0; }
.series-events-admin td { white-space:normal; overflow-wrap:anywhere; }
.series-events-admin .select2-container { max-width:100%; }
.series-events-admin .select2-selection { min-height:44px !important; }
.series-events-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }
@media(max-width:575px) { .series-events-admin table { min-width:560px; } .series-events-admin .row > .col { flex:0 0 100%; } }
</style>
<?php $__env->stopSection(); ?>





<?php $__env->startSection('page-script'); ?>

<script src="<?php echo e(asset(mix('js/seriesEvents.js'))); ?>"></script>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\series\events.blade.php ENDPATH**/ ?>