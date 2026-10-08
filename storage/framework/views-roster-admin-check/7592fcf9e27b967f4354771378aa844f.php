

<?php $__env->startSection('title', 'Event Details'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<!-- Page -->
<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="container">
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header" style="background: gray; color:#f1f7fa; font-weight:bold;">
                    Laravel 10 Upload Image To Storage Folder Preview - Laravelia
                </div>
                 <div class="card-body">                    
                    <form class="w-px-500 p-3 p-md-3" action="<?php echo e(route('photo.store')); ?>" method="post" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Image</label>
                            <div class="col-sm-9">
                              <input type="file" class="form-control" name="image" <?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> id="selectImage">
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <span class="invalid-feedback" role="alert">
                                    <strong><?php echo e($message); ?></strong>
                                </span>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <img id="preview" src="#" alt="your image" class="mt-3" style="display:none;"/>
                        </div>
                        <input type="hidden" name="event" value="<?php echo e($event->id); ?>">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"></label>
                            <div class="col-sm-9">
                                <button type="submit" class="btn btn-success btn-block">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<?php $__env->stopSection(); ?>
<?php $__env->startPush('script'); ?>
    <script>
        selectImage.onchange = evt => {
            preview = document.getElementById('preview');
            preview.style.display = 'block';
            const [file] = selectImage.files
            if (file) {
                preview.src = URL.createObjectURL(file)
            }
        }
    </script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\photo\upload.blade.php ENDPATH**/ ?>