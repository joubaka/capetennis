<form id="venuesForm" action="<?php echo e(route('backend.draw.venues.store', $draw->id)); ?>" method="POST">
  <?php echo csrf_field(); ?>

  <div id="venuesRepeater">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="row venue-row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Venue</label>
          <select name="venue_id[]" class="form-select select2" required>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($v->id); ?>" <?php echo e($venue->id == $v->id ? 'selected' : ''); ?>>
                <?php echo e($v->name); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"># of Courts</label>
          <input type="number" name="num_courts[]" class="form-control"
                 value="<?php echo e($venue->pivot->num_courts ?? 1); ?>" min="1">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-outline-danger remove-venue-row">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      
      <div class="row venue-row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Venue</label>
          <select name="venue_id[]" class="form-select select2" required>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($v->id); ?>"><?php echo e($v->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"># of Courts</label>
          <input type="number" name="num_courts[]" class="form-control" value="1" min="1">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-outline-danger remove-venue-row">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <button type="button" id="addVenueRow" class="btn btn-sm btn-outline-primary">
    + Add Another Venue
  </button>

  <div class="mt-3 text-end">
    <button type="submit" class="btn btn-primary">Save Venues</button>
  </div>
</form>

<template id="venueTemplate">
  <div class="row venue-row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label">Venue</label>
      <select name="venue_id[]" class="form-select select2" required>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($v->id); ?>"><?php echo e($v->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label"># of Courts</label>
      <input type="number" name="num_courts[]" class="form-control" value="1" min="1">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="button" class="btn btn-outline-danger remove-venue-row">
        <i class="ti ti-trash"></i>
      </button>
    </div>
  </div>
</template>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\venues_form.blade.php ENDPATH**/ ?>