<!-- Modal -->
<div class="modal fade" id="nominatePlayerModal" tabindex="-1" aria-labelledby="nominatePlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Nominate Players</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="nominationForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="select2Nominate" id="nominateLabel" class="form-label">Select Players</label>
                        <select name="players[]" id="nominateSelect2" class=" form-select" multiple>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($player->id); ?>"><?php echo e($player->getFullNameAttribute()); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>

                    </div>
                    <input type="hidden" name="category" id="category">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="submitNomination" class="btn btn-primary">Save changes</button>
                </div>


            </form>

        </div>
    </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\modals\nominationModal.blade.php ENDPATH**/ ?>