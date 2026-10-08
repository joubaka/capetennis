<!-- Add Player to Profile Modal -->
<div class="modal fade" id="addProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <form id="playerProfileForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Player to Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          
          <div class="mb-3">
            <label for="add-player-select" class="form-label fw-bold">
              Select Player to Add
            </label>

            <select
              name="player_id"
              id="add-player-select"
              class="form-select select2"
              data-placeholder="Select a player">
              <option></option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($player->id); ?>">
                  <?php echo e($player->full_name); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
          </div>

          
          <input type="hidden" name="user_id" value="<?php echo e($user->id); ?>">
          
        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-label-secondary"
                  data-bs-dismiss="modal">
            Close
          </button>

          <button type="button"
                  class="btn btn-primary"
                  id="addPlayerToProfileButton">
            Add Player
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-add-player-to-profile.blade.php ENDPATH**/ ?>