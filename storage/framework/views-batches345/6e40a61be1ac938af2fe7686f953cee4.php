<?php $__env->startSection('title', $isCopy ? 'Copy Event' : 'Create Event'); ?>

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
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?php echo e($isCopy ? 'Copy Event' : 'Create New Event'); ?></h4>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><?php echo e($error); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isCopy)): ?>
    <div class="card border-primary mb-4" id="event-brief-card" data-preview-url="<?php echo e(route('backend.events.preview-brief')); ?>">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
          <div>
            <h5 class="mb-1"><i class="ti ti-sparkles me-1" aria-hidden="true"></i>Paste event information</h5>
            <p class="text-muted mb-0">Paste the notice, email or WhatsApp message. AI will fill the form for you, then show a preview before anything is created.</p>
          </div>
          <button type="button" class="btn btn-primary align-self-md-start" id="fill-event-brief">
            Fill event details
          </button>
        </div>
        <label class="visually-hidden" for="event-brief">Event information to extract</label>
        <textarea id="event-brief" class="form-control mt-3" rows="7" placeholder="Example:&#10;Event: Cape Town Junior Open&#10;Dates: 18–20 October 2026&#10;Type: Individual&#10;Entry fee: R350&#10;Entries close: 7 days before the event&#10;Venue: Bellville Tennis Club&#10;Contact: tournaments@example.org"></textarea>
        <div class="form-text" id="event-brief-status" aria-live="polite">You can edit every extracted field before previewing.</div>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <form method="POST" id="event-create-form"
        action="<?php echo e(route('backend.events.store')); ?>"
        enctype="multipart/form-data">

    <?php echo csrf_field(); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCopy): ?>
      <input type="hidden" name="source_event_id" value="<?php echo e($sourceEvent?->id); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-4">

      
      <div class="col-xl-8">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Details</h5>
          </div>

          <div class="card-body">

            
            <div class="mb-3">
              <label class="form-label">Event Name <span class="text-danger">*</span></label>
              <input name="name"
                     class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                     value="<?php echo e(old('name', $isCopy ? (($sourceEvent?->name ?? '') . ' (Copy)') : '')); ?>"
                     required>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date"
                       name="start_date"
                       class="form-control <?php $__errorArgs = ['start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       value="<?php echo e(old('start_date', optional($sourceEvent?->start_date)->format('Y-m-d'))); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="col">
                <label class="form-label">End Date</label>
                <input type="date"
                       name="end_date"
                       class="form-control <?php $__errorArgs = ['end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       value="<?php echo e(old('end_date', optional($sourceEvent?->end_date)->format('Y-m-d'))); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Event Type <span class="text-danger">*</span></label>
              <select name="eventType"
                      class="form-select <?php $__errorArgs = ['eventType'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                      required>
                <option value="">— Select type —</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($type->id); ?>" <?php if(old('eventType', $sourceEvent?->eventType) == $type->id): echo 'selected'; endif; ?>>
                    <?php echo e($type->name); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['eventType'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Information</label>
              <div id="information-editor" class="border rounded">
                <?php echo old('information', $sourceEvent?->information ?? ''); ?>

              </div>
              <input type="hidden"
                     name="information"
                     id="information-input"
                     value="<?php echo e(old('information', $sourceEvent?->information ?? '')); ?>">
              <div class="form-text">AI formats this into readable paragraphs and lists. You can edit the result before previewing.</div>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Venue Notes</label>
              <textarea name="venue_notes"
                        class="form-control"
                        rows="3"><?php echo e(old('venue_notes', $sourceEvent?->venue_notes ?? '')); ?></textarea>
            </div>

            
            <div class="mb-3">
              <label class="form-label" for="logo-existing">Use Existing Logo</label>
              <?php ($selectedLogo = old('logo_existing', $isCopy ? $sourceEvent?->logo : '')); ?>
              <select id="logo-existing"
                      name="logo_existing"
                      class="form-select <?php $__errorArgs = ['logo_existing'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                      data-logo-base="<?php echo e(asset('assets/img/logos')); ?>">
                <option value="">— No existing logo selected —</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $logoFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $logoFile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($logoFile); ?>" <?php if($selectedLogo === $logoFile): echo 'selected'; endif; ?>><?php echo e($logoFile); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo_existing'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <div class="form-text">Choose a logo already used on the website, or upload a new one below.</div>

              <img id="logo-preview"
                   src="<?php echo e($selectedLogo ? asset('assets/img/logos/'.$selectedLogo) : ''); ?>"
                   alt="Selected event logo preview"
                   class="img-thumbnail mt-2 <?php echo e($selectedLogo ? '' : 'd-none'); ?>"
                   style="width: 160px; height: 100px; object-fit: contain;">

              <label class="form-label mt-3" for="logo-upload">Upload New Logo</label>
              <input type="file"
                     id="logo-upload"
                     name="logo_upload"
                     class="form-control <?php $__errorArgs = ['logo_upload'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                     accept="image/*">
              <div class="form-text">A newly uploaded logo takes priority over the selected existing logo.</div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo_upload'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

          </div>
        </div>
      </div>

      
      <div class="col-xl-4">
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
                     value="<?php echo e(old('entryFee', $sourceEvent?->entryFee ?? '')); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Deadline (days before start)</label>
              <input type="number"
                     name="deadline"
                     class="form-control"
                     value="<?php echo e(old('deadline', $sourceEvent?->deadline ?? 7)); ?>">
              <div class="form-text">Defaults to 7 days before the event unless the event material states otherwise.</div>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Withdrawal Deadline</label>
              <input type="datetime-local"
                     name="withdrawal_deadline"
                     class="form-control"
                     value="<?php echo e(old('withdrawal_deadline', optional($sourceEvent?->withdrawal_deadline)->format('Y-m-d\TH:i'))); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Organizer</label>
              <input type="text"
                     name="organizer"
                     class="form-control"
                     value="<?php echo e(old('organizer', $sourceEvent?->organizer ?? '')); ?>">
            </div>

            
            <div class="mb-3">
              <label class="form-label">Contact Email</label>
              <input type="email"
                     name="email"
                     class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                     value="<?php echo e(old('email', $sourceEvent?->email ?? '')); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Event Admins</label>
              <select name="admins[]"
                      class="form-select select2"
                      multiple
                      data-placeholder="Select admins">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($user->id); ?>"
                    <?php if(is_array(old('admins', $adminIds ?? [])) ? in_array($user->id, old('admins', $adminIds ?? [])) : in_array($user->id, $adminIds ?? [])): echo 'selected'; endif; ?>>
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
                     <?php if(old('published', false)): echo 'checked'; endif; ?>>
              <label class="form-check-label">Published</label>
            </div>

            
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="signUp"
                     value="1"
                     <?php if(old('signUp', false)): echo 'checked'; endif; ?>>
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
        <?php echo e($isCopy ? 'Save Copied Event' : 'Preview Event'); ?>

      </button>
    </div>

  </form>
</div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isCopy)): ?>
<div class="modal fade" id="eventPreviewModal" tabindex="-1" aria-labelledby="eventPreviewTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="eventPreviewTitle">Review event before creating</h5>
          <div class="small text-muted">Nothing has been saved yet.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="event-preview-content"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep editing</button>
        <button type="button" class="btn btn-primary" id="confirm-create-event">
          <i class="ti ti-check me-1" aria-hidden="true"></i>Create Event
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
  $(document).ready(function () {
    $('.select2').select2();
  });

  (() => {
    const form = document.getElementById('event-create-form');
    if (!form) return;
    const informationInput = document.getElementById('information-input');
    const existingLogo = document.getElementById('logo-existing');
    const logoUpload = document.getElementById('logo-upload');
    const logoPreview = document.getElementById('logo-preview');
    let uploadedLogoPreviewUrl = null;
    const showLogoPreview = source => {
      logoPreview.src = source || '';
      logoPreview.classList.toggle('d-none', !source);
    };
    existingLogo?.addEventListener('change', () => {
      if (uploadedLogoPreviewUrl) {
        URL.revokeObjectURL(uploadedLogoPreviewUrl);
        uploadedLogoPreviewUrl = null;
      }
      logoUpload.value = '';
      showLogoPreview(existingLogo.value ? `${existingLogo.dataset.logoBase}/${encodeURIComponent(existingLogo.value)}` : '');
    });
    logoUpload?.addEventListener('change', () => {
      if (uploadedLogoPreviewUrl) URL.revokeObjectURL(uploadedLogoPreviewUrl);
      uploadedLogoPreviewUrl = logoUpload.files[0] ? URL.createObjectURL(logoUpload.files[0]) : null;
      showLogoPreview(uploadedLogoPreviewUrl || (existingLogo.value ? `${existingLogo.dataset.logoBase}/${encodeURIComponent(existingLogo.value)}` : ''));
    });
    const informationEditor = new Quill('#information-editor', {
      theme: 'snow',
      modules: {
        toolbar: [[{ header: [3, 4, false] }], ['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']]
      }
    });
    const brief = document.getElementById('event-brief');
    const syncInformation = () => {
      informationInput.value = informationEditor.getText().trim() ? informationEditor.root.innerHTML : '';
    };
    informationEditor.on('text-change', syncInformation);
    form.addEventListener('submit', syncInformation);

    const value = name => {
      if (name === 'information') syncInformation();
      return form.elements[name]?.value?.trim() || '';
    };
    const setValue = (name, next) => {
      if (next === null || next === undefined || next === '') return false;
      if (name === 'information') {
        informationEditor.clipboard.dangerouslyPasteHTML(String(next));
        syncInformation();
        return true;
      }
      const control = form.elements[name];
      if (!control) return false;
      control.value = next;
      control.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    };
    const escapeHtml = text => String(text ?? '').replace(/[&<>'"]/g, char => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);
    if (!brief) return;
    document.getElementById('fill-event-brief').addEventListener('click', async event => {
      const text = brief.value.trim();
      const status = document.getElementById('event-brief-status');
      if (text.length < 20) {
        status.textContent = 'Paste at least a short event notice first.';
        brief.focus();
        return;
      }

      const button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Reading with AI…';
      status.textContent = 'AI is preparing an editable draft…';

      try {
        const response = await fetch(document.getElementById('event-brief-card').dataset.previewUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
          },
          body: JSON.stringify({ brief: text }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'The AI event assistant could not prepare a draft.');

        const draft = payload.draft || {};
        let filled = 0;
        for (const field of ['name', 'start_date', 'end_date', 'information', 'venue_notes', 'entryFee', 'deadline', 'withdrawal_deadline', 'organizer', 'email']) {
          filled += setValue(field, draft[field]) ? 1 : 0;
        }
        filled += setValue('eventType', draft.event_type_id) ? 1 : 0;
        form.elements.published.checked = draft.published === true;
        form.elements.signUp.checked = draft.signUp === true;

        const notes = Array.isArray(draft.review_notes) && draft.review_notes.length
          ? ` Please review: ${draft.review_notes.join(' ')}`
          : '';
        status.textContent = `${filled} field${filled === 1 ? '' : 's'} filled by AI. Check the draft, then preview the event.${notes}`;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        status.textContent = error.message || 'The AI event assistant could not prepare a draft.';
      } finally {
        button.disabled = false;
        button.textContent = 'Fill event details';
      }
    });

    form.addEventListener('submit', event => {
      if (form.dataset.confirmed === 'true') return;
      event.preventDefault();
      if (!form.reportValidity()) return;

      const type = form.elements.eventType.options[form.elements.eventType.selectedIndex]?.text || 'Not selected';
      const admins = [...form.querySelectorAll('[name="admins[]"] option:checked')].map(option => option.text);
      const rows = [
        ['Event name', value('name')], ['Dates', [value('start_date'), value('end_date')].filter(Boolean).join(' to ')],
        ['Event type', type], ['Entry fee', value('entryFee') ? `R${value('entryFee')}` : 'Not set'],
        ['Registration deadline', value('deadline') ? `${value('deadline')} days before start` : 'Not set'],
        ['Withdrawal deadline', value('withdrawal_deadline') || 'Not set'], ['Organizer', value('organizer') || 'Not set'],
        ['Contact email', value('email') || 'Not set'], ['Event admins', admins.join(', ') || 'None selected'],
        ['Published', form.elements.published.checked ? 'Yes' : 'No'], ['Registration open', form.elements.signUp.checked ? 'Yes' : 'No']
      ];
      document.getElementById('event-preview-content').innerHTML = `
        <dl class="row mb-0">${rows.map(([label, content]) => `<dt class="col-sm-4">${escapeHtml(label)}</dt><dd class="col-sm-8">${escapeHtml(content || 'Not set')}</dd>`).join('')}</dl>
        ${value('information') ? `<hr><h6>Information</h6><div class="event-information-content mb-3">${value('information')}</div>` : ''}
        ${value('venue_notes') ? `<h6>Venue notes</h6><p class="mb-0" style="white-space:pre-wrap">${escapeHtml(value('venue_notes'))}</p>` : ''}`;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('eventPreviewModal')).show();
    });

    document.getElementById('confirm-create-event').addEventListener('click', event => {
      event.currentTarget.disabled = true;
      event.currentTarget.textContent = 'Creating…';
      form.dataset.confirmed = 'true';
      syncInformation();
      form.requestSubmit();
    });
  })();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\create.blade.php ENDPATH**/ ?>