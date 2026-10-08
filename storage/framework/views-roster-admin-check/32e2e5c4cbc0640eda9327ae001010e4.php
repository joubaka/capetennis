<!-- Modal -->
<div class="modal fade" id="edit-team-category-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-team-category-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="col-sm">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                   
                    <div class="form-check mt-3">
                        <input name="category" class="form-check-input" type="radio" value="<?php echo e($eventCategory->id); ?>" id="category-radio" />
                        <label class="form-check-label" for="defaultRadio1">
                           <?php echo e($eventCategory->category->name); ?>

                        </label>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                   
                    <input type="hidden" name="team" value="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="change-team-category-button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-edit-team-category.blade.php ENDPATH**/ ?>