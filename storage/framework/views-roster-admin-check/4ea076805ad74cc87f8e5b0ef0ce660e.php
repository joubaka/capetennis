<!-- Modal -->
<div class="modal fade" id="add-category-modal" tabindex="-1" aria-labelledby="add-category-modal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <form class="formPlayer" method="post">
                    <?php echo csrf_field(); ?>
                    <div class="card">


                        <h5 class="card-header">Add/remove Categories - <?php echo e($event->name); ?> </h5>


                        <div class="card-body">
                            <div class="row row-bordered g-0">
                                <div class="col-md p-6">


                                    <select id="categories" class="categories" multiple="multiple" style="width: 50%;">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($category->id); ?>"><?php echo e($category->name); ?></option>

                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                    
                                </div>

                            </div>




                        </div>

                    </div>
                    <div type="button" class="btn btn-primary btn-sm mt-4" id="save-category-button">Save Categories</div>
                </form>
                <!-- /Full Editor -->



                <input type="hidden" id="event_id" value="<?php echo e($event->id); ?>">

                <!-- /Full Editor -->
            </div>
        </div>
    </div>
</div>
<script>
  var eventCategories = <?php echo json_encode($event->eventCategories, 15, 512) ?>;  
  
</script>
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\add-category-modal.blade.php ENDPATH**/ ?>