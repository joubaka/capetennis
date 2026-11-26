<!-- Modal -->
<div class="modal fade photo-admin" id="move-selected-modal" tabindex="-1" aria-labelledby="photo-move-title" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <div class="modal-header">
                <h5 id="photo-move-title">Move selected photos</h5>
            </div>
            <div class="modal-body">


                <div>

                    <label for="folder" class="form-label">Destination folder in this event</label><select id="folder" name="folder"  class="form-select form-select-sm">
                        <option value="">Choose a folder</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->photoFolders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($folder->id); ?>"><?php echo e($folder->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <input type="hidden" name="photos[]" id="photos">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <p id="photo-action-status" class="small text-danger" role="status" aria-live="polite"></p>
                    <button type="button" id="submit-move-button" class="btn btn-primary" disabled>Move</button>

                </div>



            </div>


        </div>
    </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\photo\_includes\photo-move-modal.blade.php ENDPATH**/ ?>