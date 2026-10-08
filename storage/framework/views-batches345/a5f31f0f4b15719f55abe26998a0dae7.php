<!-- Modal -->
<div class="modal fade" id="move-selected-modal" tabindex="-1" aria-labelledby="move-selected-modal" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <div class="modal-header">
                Move Photos to
            </div>
            <div class="modal-body">


                <div>

                    <select id="folder" name="folder"  class="form-select form-select-sm">
                        <option>Please select Folder</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->photoFolders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($folder->id); ?>"><?php echo e($folder->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <input type="hidden" name="photos[]" id="photos">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="submit-move-button" class="btn btn-primary">Move</button>

                </div>



            </div>


        </div>
    </div><?php /**PATH C:\wamp64\www\ct\resources\views\backend\photo\_includes\photo-move-modal.blade.php ENDPATH**/ ?>