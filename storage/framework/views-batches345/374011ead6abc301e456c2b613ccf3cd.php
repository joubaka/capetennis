<!-- Modal: Add Region to Event -->
<div class="modal fade" id="modalToggle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 p-md-5">

      <h4>Add Region to <?php echo e($event->name); ?></h4>

      <div class="modal-body">
        <form id="regionEventForm">
          <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>"> <!-- ✅ FIXED key -->

          <div class="mb-3">
            <label for="select2Region" class="form-label">Select Region</label>
            <select id="select2Region" name="region_id"
                    class="select2Region form-select form-select-lg"
                    data-placeholder="Select a region" data-allow-clear="true" style="width: 100%;">
              <option></option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($region->id); ?>"><?php echo e($region->region_name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
          </div>
        <div class="mb-3"><label for="regionShortName" class="form-label">Short name (optional)</label><input id="regionShortName" name="short_name" class="form-control" maxlength="20"><div class="form-text">For a new region. Leave blank to use an automatic abbreviation. Existing regions keep their saved short name.</div></div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ti ti-x me-1"></i> Close
        </button>
        <button type="button" id="addRegionToEventButton" class="btn btn-primary">
          <i class="ti ti-plus me-1"></i> Add Region to Event
        </button>
      </div>
    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-add-region.blade.php ENDPATH**/ ?>