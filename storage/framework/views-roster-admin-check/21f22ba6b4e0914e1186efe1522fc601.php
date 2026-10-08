<div class="card m-2">
  <div class="card-body">

    <div class="row">

      
      <div class="col-12 col-md-3">

        <a href="<?php echo e(route('draw.show', $draw->id)); ?>"
           class="list-group-item list-group-item-action d-flex align-items-center">

          <div class="w-100">

            <div class="d-flex justify-content-between">

              <div class="user-info">

                <h6 class="mb-1"><?php echo e($draw->drawName); ?> (ID: <?php echo e($draw->id); ?>)</h6>

                <small>
                  <?php echo e($draw->draw_types->drawTypeName ?? 'No Type'); ?>

                </small>

                <div class="user-status mt-1">
                  <span class="badge badge-dot <?php echo e($draw->locked ? 'bg-danger' : 'bg-success'); ?>"></span>
                  <small>Draw <?php echo e($draw->locked ? 'Locked' : 'Unlocked'); ?></small>
                </div>
              </div>

              
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::currentRouteName() === 'event.draw.index'): ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$draw->locked): ?>
                  <button class="btn btn-secondary btn-sm remove-draw-button"
                          data-id="<?php echo e($draw->id); ?>">
                    Delete Draw
                  </button>

                <?php else: ?>
                  <div class="text-end">
                    <small class="badge bg-label-warning mb-2 d-block">
                      This will delete ALL fixtures & results!
                    </small>

                    <button class="btn btn-danger btn-sm unlock-draw-button"
                            data-id="<?php echo e($draw->id); ?>">
                      Unlock Draw
                    </button>
                  </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>
          </div>
        </a>

        
        <button
          id="toggleDraw<?php echo e($draw->id); ?>"
          data-id="<?php echo e($draw->id); ?>"
          class="toggleDraw m-2 btn btn-sm btn-<?php echo e($draw->published ? 'success' : 'danger'); ?>">
          <?php echo e($draw->published ? 'Draw is Published' : 'Draw is Not Published'); ?>

        </button>

        <button
          id="toggleOrderOfPlay<?php echo e($draw->id); ?>"
          data-id="<?php echo e($draw->id); ?>"
          class="toggleOrderOfPlay m-2 btn btn-sm btn-<?php echo e($draw->oop_published ? 'success' : 'danger'); ?>">
          <?php echo e($draw->oop_published ? 'OOP is Published' : 'OOP is Not Published'); ?>

        </button>

      </div>


      
      <div class="col-12 col-md-5">

        
        <div class="mb-4">
          <label class="form-label">Day to Schedule</label>

          <select
            class="form-select"
            id="dayScheduleSelect<?php echo e($draw->id); ?>"
            name="dayScheduleSelect">

            <option value="" selected>-</option>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playingDays ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($index+1); ?>">
                <?php echo e($day); ?> — Day <?php echo e($index+1); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </select>
        </div>

        
        <div class="mb-4">
          <div class="row">

            <div class="col-6">
              <label class="form-label">Start Time</label>
              <input type="text"
                     class="form-control flatPickerTime"
                     placeholder="HH:MM"
                     id="flatpickr-time-start<?php echo e($draw->id); ?>">
            </div>

            <div class="col-6">
              <label class="form-label">Last Match Time</label>
              <input type="text"
                     class="form-control flatPickerTime"
                     placeholder="HH:MM"
                     id="flatpickr-time-end<?php echo e($draw->id); ?>">
            </div>

          </div>
        </div>

        <button class="btn btn-info btn-sm scheduleDraw"
                data-id="<?php echo e($draw->id); ?>">
          Schedule Matches
        </button>

      </div>


      
      <div class="col-12 col-md-4">

        <button type="button"
                class="btn btn-info btn-sm btn-add-venues mb-3"
                data-draw-id="<?php echo e($draw->id); ?>"
                data-draw-name="<?php echo e($draw->drawName); ?>">
          Add Venues
        </button>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
        <a href="<?php echo e(route('engine.draw.show', $draw->id)); ?>"
           class="btn btn-outline-secondary btn-sm mb-3 ms-1"
           title="Engine mode &amp; mismatch log for this draw">
          <i class="ti ti-engine ti-xs me-1"></i>Engine
        </a>
        <?php endif; ?>

        <div class="draw-venues" data-draw-id="<?php echo e($draw->id); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->venues && $draw->venues->count() > 0): ?>
            <small class="text-muted me-1"><i class="ti ti-map-pin ti-xs"></i> Venues:</small>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <span class="badge bg-label-primary me-1">
                <?php echo e($venue->name); ?> <span class="text-muted">(<?php echo e($venue->pivot->num_courts); ?> <?php echo e(Str::plural('court', $venue->pivot->num_courts)); ?>)</span>
              </span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php else: ?>
            <small class="text-muted"><i class="ti ti-map-pin-off ti-xs me-1"></i>No venues assigned</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

      </div>

    </div>
  </div>
</div>


<?php echo $__env->make('backend.draw._modals.addVenueModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if (! $__env->hasRenderedOnce('29643270-f091-4550-9542-4d9367c7b5c4')): $__env->markAsRenderedOnce('29643270-f091-4550-9542-4d9367c7b5c4'); ?>
<script>
    window.venueStoreBase = "<?php echo e(route('backend.draw.venues.store', ['draw' => 'DRAW_ID'])); ?>";
    window.venueJsonBase = "<?php echo e(route('backend.draw.venues.json', ['draw' => 'DRAW_ID'])); ?>";
    window.allVenuesUrl = "<?php echo e(route('venue.list')); ?>";
</script>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\_includes\draw_tab.blade.php ENDPATH**/ ?>