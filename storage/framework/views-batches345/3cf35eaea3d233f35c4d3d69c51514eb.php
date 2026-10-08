<!-- Modal -->
<div class="modal fade" id="generateDrawModal" tabindex="-1" aria-labelledby="generateDrawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="<?php echo e(route('draws.generate.from.modal')); ?>">
      <?php echo csrf_field(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>


        <div class="modal-body">
          <div class="mb-3">
            <label for="category_event_id" class="form-label">Select Category</label>

            <select name="category_event_id" class="form-select select2" required>
              <option value="">-- Choose Category --</option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($eventCategory->id); ?>">
                  <?php echo e(optional($eventCategory->category)->name); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>

          </div>

          <div class="mb-3">
            <label for="draw_name" class="form-label">Draw Name</label>
            <input type="text" name="draw_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="draw_format_id" class="form-label">Draw Format</label>
            <select name="draw_format_id" class="form-select" required>
              <option value="1">Knockout</option>
              <option value="2">Feed-In</option>
              <option value="3">Round Robin</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Create Draw</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\modals\generateDrawOptionsModal.blade.php ENDPATH**/ ?>