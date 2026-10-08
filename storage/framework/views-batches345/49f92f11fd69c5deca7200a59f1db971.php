

<?php $__env->startSection('title', 'Event Details'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>

<!-- Page -->
<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/photos.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<div class="container">


    <div class="card">
        <div class="card m-2">

            <div>
                <button class="btn btn-sm btn-primary" data-bs-target="#uploadModal" data-bs-toggle="modal">Upload Photos</button>
                <h3>Upload photos to <?php echo e($event->name); ?><a class="ms-3 btn btn-sm btn-secondary" href="<?php echo e(route('eventPhoto.show',$event->id)); ?>">Back to all Folders</a></h3>
                <p class="badge bg-secondary">Folder: <?php echo e($folder->name); ?></p>
</div>




            <div class="table-responsive">
                <table class="table" id="photo-list">
                    <thead>
                        <tr>
                            <td></td>
                            <th>Photo</th>
                            <th>Name</th>

                            <th>Folder</th>
                            <th>Action</th>

                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr data-id="<?php echo e($image); ?>">
                            <td class="  dt-checkboxes-cell"><input type="checkbox" class="dt-checkboxes form-check-input" value="<?php echo e($image->id); ?>"></td>

                            <td>
                                <a href="javascript(void[0])" class="preview" data-image="<?php echo e($image); ?>" data-bs-target="#photo-modal-preview" data-bs-toggle="modal">

                                    <img class=" img-thumbnail" style="max-width: 100px;" src="<?php echo e(asset('storage/photoFolder/'.$image->path)); ?>" alt="cbImg">
                                </a>


                            </td>
                            <td><?php echo e($image->name); ?></td>
                            <td>
                                <?php echo e($image->folder->name); ?>

                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                    <div class="dropdown-menu">
                                        <!-- <a class="dropdown-item editPicture" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Edit</a> -->
                                        <form action="<?php echo e(route('photo.destroy',$image->id)); ?>" method="post">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="dropdown-item" data-id="<?php echo e($image->id); ?>"><i class="delete ti ti-trash me-1"></i>Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dropdown m-4">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i>Options with selected</button>

                <div class="dropdown-menu">
                    <a class="btn btn-danger btn-sm m-2" id="deleteSelected" href="javascript:void(0)">Delete Selected</a>
                    <a class="btn btn-success btn-sm m-2" id="moveSelected" href="javascript:void(0)" data-bs-target="#move-selected-modal" data-bs-toggle="modal">Move to another folder</a>
                </div>
            </div>
        </div>


    </div>
</div>







<?php echo $__env->make('backend.photo._includes.upload-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.photo._includes.photo-preview-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.photo._includes.photo-move-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\photo\show.blade.php ENDPATH**/ ?>