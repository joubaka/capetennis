<!-- Add New Address Modal -->
<div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-add-file">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <div class="col-12">


                    <h1>Upload PDF</h1>

                    <form name="upoadFileForm" method="POST" enctype="multipart/form-data" action="<?php echo e(route('file.store')); ?>">
                        <?php echo e(csrf_field()); ?>

                        <input type="file" name="myFile"><br><br>
                        <input type="hidden" value="<?php echo e($event->id); ?>" name="event_id">
                        <input type="submit" name="submit" value="upload">

                    </form>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Session()->has('msg')): ?>
                    <h2><?php echo e(Session()->get('msg')); ?></h2>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <h2 class="mt-3">Files added to event</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=> $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <h3 class="mt-3"><?php echo e(($key+1).'. '.$file->name); ?> </h3>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <!-- /Full Editor -->
                </div>
            </div>
        </div>
    </div>
    <!--/ Add New Address Modal -->
<?php /**PATH C:\wamp64\www\ct\resources\views\_partials\_modals\modal-add-upload-file.blade.php ENDPATH**/ ?>