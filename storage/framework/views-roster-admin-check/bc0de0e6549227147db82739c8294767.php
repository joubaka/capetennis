

<?php $__env->startSection('title', 'Edit Event'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.min.css')); ?>">
<?php $__env->stopSection(); ?>



<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<div class="container-xl event-edit-page">

  <style>
    .event-edit-page .card { border: 1px solid #ebeaf0; box-shadow: 0 .25rem 1rem rgba(47,43,61,.05); }
    .event-edit-page .card-header { background: #fff; border-bottom: 1px solid #ebeaf0; }
    .event-edit-page .setup-status { display:inline-flex; align-items:center; gap:.35rem; font-size:.75rem; font-weight:600; border-radius:999px; padding:.35rem .65rem; }
    .event-edit-page .setup-status.ready { background:#e8f8ef; color:#198754; }
    .event-edit-page .setup-status.warning { background:#fff4dd; color:#9a6700; }
    .event-edit-page .setup-status.blocked { background:#fde8e7; color:#b42318; }
    .event-edit-page .mapping-row { border-bottom:1px solid #f0eff3; padding:.65rem 0; }
    .event-edit-page .mapping-row:last-child { border-bottom:0; }
  </style>

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Edit Event</h4>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series_id): ?>
      <a href="<?php echo e(route('series.events', $event->series_id)); ?>"
         class="btn btn-outline-secondary">
        Back to Series
      </a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-1">Series and category setup</h5>
            <p class="text-muted small mb-0">Confirm the event is connected to the correct ranking structure.</p>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series): ?>
            <span class="setup-status ready"><i class="ti ti-check"></i> Series linked</span>
          <?php else: ?>
            <span class="setup-status blocked"><i class="ti ti-alert-circle"></i> No series linked</span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-7"><span class="text-muted small d-block">Parent series</span><strong><?php echo e($event->series?->name ?? 'Not linked'); ?></strong></div>
            <div class="col-md-2"><span class="text-muted small d-block">Series year</span><strong><?php echo e($event->series?->year ?? '—'); ?></strong></div>
            <div class="col-md-3"><span class="text-muted small d-block">Ranking type</span><strong><?php echo e($event->series?->rank_type ?? '—'); ?></strong></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Configured categories</h6>
            <span class="badge bg-label-secondary"><?php echo e($event->categoryEvents->count()); ?></span>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="mapping-row d-flex justify-content-between align-items-center">
              <div><strong><?php echo e($categoryEvent->category?->name ?? 'Unnamed category'); ?></strong><span class="text-muted small ms-2">Category event #<?php echo e($categoryEvent->id); ?></span></div>
              <span class="text-muted">R <?php echo e(number_format((float)($categoryEvent->entry_fee ?? $event->entryFee ?? 0), 2)); ?></span>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="alert alert-danger mb-0">Blocked: no age-group categories are attached to this event.</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div><h5 class="mb-1">Masters readiness</h5><p class="text-muted small mb-0">Invitation and replacement status.</p></div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mastersReadiness): ?>
            <span class="setup-status <?php echo e($mastersReadiness['status']); ?>"><?php echo e(ucfirst($mastersReadiness['status'])); ?></span>
          <?php else: ?>
            <span class="setup-status warning">Not generated</span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event->series): ?>
            <p class="text-danger mb-2"><i class="ti ti-alert-circle me-1"></i> Link this event to a series first.</p>
          <?php elseif($event->categoryEvents->isEmpty()): ?>
            <p class="text-danger mb-2"><i class="ti ti-alert-circle me-1"></i> Add at least one Masters age group.</p>
          <?php elseif(!$mastersBatch): ?>
            <p class="text-warning mb-3"><i class="ti ti-alert-triangle me-1"></i> Event exists, but no invitation batch has been generated.</p>
            <a href="<?php echo e(route('series.events', $event->series_id)); ?>" class="btn btn-outline-primary btn-sm">Return to series setup</a>
          <?php else: ?>
            <p class="small mb-2">Batch #<?php echo e($mastersBatch->id); ?> · ranking run <code><?php echo e($mastersBatch->ranking_run_id); ?></code></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $mastersReadiness['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="d-flex justify-content-between border-bottom py-2 small"><span><?php echo e($group['label'] ?? 'Age group'); ?></span><strong class="<?php echo e($group['status'] === 'blocked' ? 'text-danger' : ($group['status'] === 'warning' ? 'text-warning' : 'text-success')); ?>"><?php echo e(ucfirst($group['status'])); ?></strong></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <a href="<?php echo e(route('backend.masters.show', $mastersBatch)); ?>" class="btn btn-primary btn-sm">Open Masters readiness</a>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mastersBatch->status === 'sent'): ?>
                <form method="POST" action="<?php echo e(route('backend.masters.public-list.toggle', $mastersBatch)); ?>" onsubmit="return confirm('<?php echo e($mastersBatch->public_list_published ? 'Hide the Masters player names from the public list?' : 'Publish the Masters player names publicly now?'); ?>');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="published" value="<?php echo e($mastersBatch->public_list_published ? 0 : 1); ?>">
                  <button class="btn btn-sm <?php echo e($mastersBatch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success'); ?>" type="submit">
                    <i class="ti ti-world me-1"></i><?php echo e($mastersBatch->public_list_published ? 'Unpublish public player list' : 'Publish public player list'); ?>

                  </button>
                </form>
                <form method="POST" action="<?php echo e(route('backend.masters.registration.toggle', $mastersBatch)); ?>" onsubmit="return confirm('<?php echo e($mastersBatch->registration_open ? 'Close Masters registration now?' : 'Open Masters registration now?'); ?>');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="open" value="<?php echo e($mastersBatch->registration_open ? 0 : 1); ?>">
                  <button class="btn btn-sm <?php echo e($mastersBatch->registration_open ? 'btn-outline-danger' : 'btn-outline-success'); ?>" type="submit">
                    <i class="ti ti-lock<?php echo e($mastersBatch->registration_open ? '-open' : ''); ?> me-1"></i><?php echo e($mastersBatch->registration_open ? 'Close registration' : 'Open registration'); ?>

                  </button>
                </form>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mastersBatch->status === 'sent'): ?>
              <p class="small text-muted mt-2 mb-0">Public list: <strong><?php echo e($mastersBatch->public_list_published ? 'Published' : 'Unpublished'); ?></strong> · Registration: <strong><?php echo e($mastersBatch->registration_open ? 'Open' : 'Closed'); ?></strong></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <form id="event-edit-form"
        method="POST"
        action="<?php echo e(route('backend.events.update', $event)); ?>"
        enctype="multipart/form-data">

    <?php echo csrf_field(); ?>
    <?php echo method_field('PATCH'); ?>

    <div class="row g-4">

      
      <div class="col-xl-8">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Details</h5>
          </div>

          <div class="card-body">

            
            <div class="mb-3">
              <label class="form-label">Event Name</label>
              <input name="name"
                     class="form-control"
                     value="<?php echo e(old('name', $event->name)); ?>"
                     required>
            </div>

            
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date"
                       name="start_date"
                       class="form-control"
                       value="<?php echo e(optional($event->start_date)->format('Y-m-d')); ?>">
              </div>
              <div class="col">
                <label class="form-label">End Date</label>
                <input type="date"
                       name="end_date"
                       class="form-control"
                       value="<?php echo e(optional($event->end_date)->format('Y-m-d')); ?>">
              </div>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Event Type</label>
              <select name="eventType" class="form-select" required>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($type->id); ?>"
                    <?php if($event->eventType == $type->id): echo 'selected'; endif; ?>>
                    <?php echo e($type->type); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Information</label>
              <div id="information-editor" class="border rounded">
                <?php echo old('information', $event->information); ?>

              </div>

              <input type="hidden"
                     name="information"
                     id="information-input"
                     value="<?php echo e(old('information', $event->information)); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Venue Notes</label>
              <textarea name="venue_notes"
                        rows="3"
                        class="form-control"><?php echo e(old('venue_notes', $event->venue_notes)); ?></textarea>
            </div>

          </div>
        </div>
      </div>

      
      <div class="col-xl-4">

        
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Logo</h5>
          </div>

          <div class="card-body">

            <div class="mb-3">
              <img id="logo-preview"
                   src="<?php echo e($event->logo ? asset('assets/img/logos/'.$event->logo) : ''); ?>"
                   class="img-thumbnail <?php echo e($event->logo ? '' : 'd-none'); ?>"
                   style="max-height:120px">
            </div>

            <div class="mb-3">
              <label class="form-label">Select Existing Logo</label>
              <select name="logo_existing" class="form-select">
                <option value="">— Select existing logo —</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = File::files(public_path('assets/img/logos')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($file->getFilename()); ?>"
                    <?php if($event->logo === $file->getFilename()): echo 'selected'; endif; ?>>
                    <?php echo e($file->getFilename()); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Upload New Logo</label>
              <input type="file"
                     name="logo_upload"
                     class="form-control"
                     accept="image/*">
            </div>

          </div>
        </div>

        
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Settings</h5>
          </div>

          <div class="card-body">

            
            <div class="mb-3">
              <label class="form-label">Entry Fee</label>
              <input type="number"
                     name="entryFee"
                     class="form-control"
                     value="<?php echo e(old('entryFee', $event->entryFee)); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Deadline (days before start)</label>
              <input type="number"
                     name="deadline"
                     class="form-control"
                     value="<?php echo e(old('deadline', $event->deadline)); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Withdrawal Deadline</label>
              <input type="datetime-local"
                     name="withdrawal_deadline"
                     class="form-control"
                     value="<?php echo e(optional($event->withdrawal_deadline)->format('Y-m-d\TH:i')); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Organizer</label>
              <input type="text"
                     name="organizer"
                     class="form-control"
                     value="<?php echo e(old('organizer', $event->organizer)); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Contact Email</label>
              <input type="email"
                     name="email"
                     class="form-control"
                     value="<?php echo e(old('email', $event->email)); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Event Admins</label>
              <select name="admins[]"
                      class="form-select select2"
                      multiple
                      data-placeholder="Select admins">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($user->id); ?>"
                    <?php if(in_array($user->id, $adminIds)): echo 'selected'; endif; ?>>
                    <?php echo e($user->name); ?> (<?php echo e($user->email); ?>)
                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>

            
            <div class="form-check mb-2">
              <input class="form-check-input"
                     type="checkbox"
                     name="published"
                     value="1"
                     <?php if($event->published): echo 'checked'; endif; ?>>
              <label class="form-check-label">Published</label>
            </div>

            
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="signUp"
                     value="1"
                     <?php if($event->signUp): echo 'checked'; endif; ?>>
              <label class="form-check-label">Registration open</label>
            </div>

          </div>
        </div>

      </div>
    </div>

    
    <div class="d-flex justify-content-end mt-4 gap-2">
      <a href="<?php echo e(url()->previous()); ?>" class="btn btn-outline-secondary">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary">
        Save Changes
      </button>
    </div>

  </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
window.eventConfig = {
    logoBaseUrl: "<?php echo e(asset('assets/img/logos')); ?>/"
};

if (window.toastr) {
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 2500
    };
}
</script>

<script src="<?php echo e(asset(mix('js/eventEdit.js'))); ?>"></script>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\edit.blade.php ENDPATH**/ ?>