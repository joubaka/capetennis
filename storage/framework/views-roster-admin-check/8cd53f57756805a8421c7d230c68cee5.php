<!-- Edit Event Modal -->
<div class="modal fade" id="editEvent" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">

        <!-- Edit Event Form -->
        <form action="<?php echo e(route('events.update', $event->id)); ?>" method="POST" id="eventEditForm">
          <?php echo csrf_field(); ?>
          <?php echo method_field('PUT'); ?>

          <div class="card">
            <h5 class="card-header">Edit Events</h5>
            <div class="card-body">

              
              <div class="mb-3 row">
                <label class="col-md-4 col-form-label">Event Name</label>
                <div class="col-md-8">
                  <input class="form-control" type="text" name="name" id="name"
                         value="<?php echo e(old('name', $event->name)); ?>" required>
                </div>
              </div>

              
              <div class="mb-3">
                <label class="form-label">Information</label>
                <div id="full-editor-edit" class="border rounded p-2"><?php echo old('information', $event->information); ?></div>
                <input type="hidden" name="information" id="information" value="<?php echo e(old('information', $event->information)); ?>">
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-2 col-form-label">Start Date</label>
                <div class="col-md-10">
                  <input class="form-control" type="date" name="start_date" id="start_date"
                         value="<?php echo e(old('start_date', $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('Y-m-d') : '')); ?>">
                </div>
              </div>

              <div class="mb-3 row">
                <label class="col-md-2 col-form-label">End Date</label>
                <div class="col-md-10">
                  <input class="form-control" type="date" name="endDate" id="endDate"
                         value="<?php echo e(old('endDate', $event->endDate ? \Carbon\Carbon::parse($event->endDate)->format('Y-m-d') : '')); ?>">
                </div>
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-2 col-form-label">Deadline</label>
                <div class="col-md-10">
                  <input class="form-control" type="text" name="deadline" id="deadline"
                         value="<?php echo e(old('deadline', $event->deadline)); ?>">
                </div>
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-2 col-form-label">Organizer</label>
                <div class="col-md-10">
                  <input class="form-control" name="organizer" id="organizer"
                         value="<?php echo e(old('organizer', $event->organizer)); ?>">
                </div>
              </div>

              <div class="mb-3 row">
                <label class="col-md-2 col-form-label">Email</label>
                <div class="col-md-10">
                  <input class="form-control" name="email" type="email" id="email"
                         value="<?php echo e(old('email', $event->email)); ?>">
                </div>
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-4 col-form-label">Entry Fee</label>
                <div class="col-md-8">
                  <input class="form-control" type="number" step="0.01" name="entry_fee" id="entry_fee"
                         value="<?php echo e(old('entry_fee', $event->entryFee)); ?>">
                </div>
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-4 col-form-label">Logo (URL)</label>
                <div class="col-md-8">
                  <input class="form-control" type="text" name="logo" id="logo"
                         value="<?php echo e(old('logo', $event->logo)); ?>">
                </div>
              </div>

              
              <div class="mb-3 row">
                <label class="col-md-4 col-form-label">Venues</label>
                <div class="col-md-8">
                  <input class="form-control" type="text" name="venues" id="venues"
                         value="<?php echo e(old('venues', $event->venues)); ?>">
                </div>
              </div>

              
              <div class="mb-3">
                <label for="event_type" class="form-label">Event Type</label>
                <select id="event_type" class="form-select" name="event_type">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value->id); ?>"
                      <?php echo e((int) old('event_type', $event->eventType) === (int) $value->id ? 'selected' : ''); ?>>
                      <?php echo e($value->name); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
              </div>

              
              <div class="mb-3 d-flex gap-4">
                <label class="switch switch-success">
                  <input type="hidden" name="published" value="0">
                  <input type="checkbox" class="switch-input" id="published" name="published" value="1"
                         <?php echo e(old('published', $event->published === 'published') ? 'checked' : ''); ?>>
                  <span class="switch-toggle-slider">
                    <span class="switch-on"><i class="ti ti-check"></i></span>
                    <span class="switch-off"><i class="ti ti-x"></i></span>
                  </span>
                  <span class="switch-label">Published</span>
                </label>

                <label class="switch switch-success">
                  <input type="hidden" name="signUP" value="0">
                  <input type="checkbox" class="switch-input" id="signUP" name="signUP" value="1"
                         <?php echo e(old('signUP', (int) $event->signUp) ? 'checked' : ''); ?>>
                  <span class="switch-toggle-slider">
                    <span class="switch-on"><i class="ti ti-check"></i></span>
                    <span class="switch-off"><i class="ti ti-x"></i></span>
                  </span>
                  <span class="switch-label">Registration open</span>
                </label>
              </div>
<?php
  // Safely collect selected category IDs from old input or the event relation
  $selectedCategories = collect(
    old('categories', optional($event->categories)->pluck('id')->all() ?? [])
  )->map(fn($v) => (int) $v); // normalize to ints for strict compare
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array((int)$event->eventType, [5,6,9], true)): ?>
  <div class="mb-3">
    <label for="select2Categories" class="form-label">Please select Categories for event</label>
    <div class="position-relative">
      <select id="select2Categories" name="categories[]" class="form-select" multiple
              style="width:100%;" data-placeholder="Select categories…">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($category->id); ?>"
            <?php echo e($selectedCategories->contains((int)$category->id) ? 'selected' : ''); ?>>
            <?php echo e($category->name); ?>

          </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              
        <?php
  // keep selections on validation error; otherwise use the event's admins
  $selectedAdmins = collect(old('admins', $event->admins?->pluck('id')->all() ?? []))
      ->map(fn($id) => (int) $id)
      ->all();
?>

<div class="mb-3 row">
  <label for="select2user" class="col-md-4 col-form-label">Please select Admin for event</label>
  <div class="col-md-8 position-relative">
    <select name="admins[]" id="select2user" class="form-select" multiple style="width:100%;"
            data-placeholder="Select admin(s)…">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> 
        <option value="<?php echo e($u->id); ?>" <?php if(in_array((int)$u->id, $selectedAdmins, true)): echo 'selected'; endif; ?>>
          <?php echo e($u->name); ?> <?php echo e($u->surname); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
  </div>
</div>


           

            </div><!-- /card-body -->
          </div><!-- /card -->

          <div class="mt-4 d-flex justify-content-end">
            <button type="button" class="btn btn-primary btn-sm" id="editEventButton">Update Event</button>
          </div>
        </form>
        <!-- /Edit Event Form -->

      </div>
    </div>
  </div>
</div>


<style>
  .select2-container { z-index: 2000 !important; width: 100% !important; }

  /* Selection */
  .select2-container .select2-selection {
    border: 1px solid #d4d8dd;
    border-radius: .5rem;
    min-height: 40px;
    padding: 4px 8px;
  }
  .select2-container--default .select2-selection--multiple .select2-selection__rendered {
    display: flex; gap: .25rem; flex-wrap: wrap; padding: 2px 0;
  }
  .select2-container--default .select2-selection--multiple .select2-selection__choice {
    background:#f1f2f4; border:0; color:#4b5563;
    border-radius: .375rem; padding: 2px 6px; margin: 2px;
    font-size: .85rem;
  }
  .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color:#6b7280; margin-right: 4px; font-weight: 600;
  }

  /* Focus */
  .select2-container--default.select2-container--focus .select2-selection,
  .select2-container--open .select2-selection {
    border-color: #7367f0;
    box-shadow: 0 0 0 .25rem rgba(115,103,240,.15);
  }

  /* Dropdown */
  .select2-container .select2-dropdown {
    z-index: 2100 !important;
    border: 1px solid #d4d8dd;
    border-radius: .5rem;
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.1);
    margin-top: .25rem;
    overflow: hidden;
    min-width: 100%;
  }

  .select2-container .select2-search--dropdown .select2-search__field {
    border: 1px solid #e2e8f0;
    border-radius: .375rem;
    padding: .4rem .6rem;
  }

  .select2-results__options {
    max-height: 260px !important;
    overflow-y: auto !important;
  }
  .select2-results__option--highlighted {
    background:#7367f0 !important;
    color:#fff !important;
  }
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.jQuery) return;
  const $modal = jQuery('#editEvent');

  function initS2(selector) {
    const $el = jQuery(selector);
    if (!$el.length) return;

    const $parent = $el.closest('.position-relative');
    if ($parent.length && $parent.css('position') === 'static') {
      $parent.css('position', 'relative');
    }

    if ($el.data('select2')) $el.select2('destroy');

    const isMultiple = $el.prop('multiple');
    $el.select2({
      dropdownParent: $parent.length ? $parent : $modal,
      width: '100%',
      dropdownAutoWidth: true,
      minimumResultsForSearch: 0,
      closeOnSelect: !isMultiple,
      placeholder: $el.data('placeholder') || (isMultiple ? 'Select…' : '')
    }).on('select2:open', () => {
      document.querySelector('.select2-container--open .select2-search__field')?.focus();
    });
  }

  $modal.on('shown.bs.modal', function () {
    initS2('#select2user');
    initS2('#select2Categories');
  });

  if ($modal.is(':visible')) {
    initS2('#select2user');
    initS2('#select2Categories');
  }

  // Quill init
  if (window.Quill) {
    window.__eventQuill = new Quill('#full-editor-edit', {
      theme: 'snow',
      placeholder: 'Event information...',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold','italic','underline','strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link','blockquote','code-block','image'],
          [{ color: [] }, { background: [] }],
          [{ align: [] }],
          ['clean']
        ]
      }
    });
  }

  // AJAX submit
  jQuery(document).on('click', '#editEventButton', function () {
    const $form = jQuery(this).closest('form');
    if (window.__eventQuill) {
      jQuery('#information').val(window.__eventQuill.root.innerHTML || '');
    }
    const data = $form.serialize();
    console.log('🔎 Payload:', data);

    jQuery.ajax({
      url:  $form.attr('action'),
      type: 'POST',
      data,
      headers: { 'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content') },
      success: function (res) {
        console.log('✅ Server responded:', res);
        location.reload();
      },
      error: function (xhr) {
        console.error('❌', xhr.status, xhr.statusText);
        console.log('📩', xhr.responseText);
        alert('Update failed.');
      }
    });
  });
});
</script>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-edit-event.blade.php ENDPATH**/ ?>