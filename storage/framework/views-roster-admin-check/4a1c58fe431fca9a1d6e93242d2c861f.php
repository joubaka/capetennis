<!-- Add Player to Category Modal -->
<div class="modal fade" id="addPlayerToCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <div class="col-12">
          <h5 class="card-header mb-3">Add Player to Category</h5>

          <form id="addPlayerToCategoryForm">
            <input type="hidden" id="event_id" name="event_id" value="<?php echo e($event->id); ?>">
           <input type="hidden" name="category_event_id" id="categoryEvent">


            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Player</label>
                <select id="select2AddPlayer" name="player_id" class="form-select form-select-lg" data-allow-clear="true">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($player->id); ?>"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
              <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" id="addPlayerToCategoryButton">Add Player</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-add-registration.blade.php ENDPATH**/ ?>