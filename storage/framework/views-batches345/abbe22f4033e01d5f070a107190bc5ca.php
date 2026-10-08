

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

<div class="container">
    <div class="card ">

        <div class="card m-2">
            <div>
                <a class="btn btn-success btn-sm m-2" href="javascript::void[0]" data-bs-toggle="modal" data-bs-target="#folder-modal-add">Add new Folder</a>
            </div>
           
            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th># of Photos</th>
                                <th>Name</th>
                                
                                <th>Event</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->photoFolders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                               

                                <td>
                                <a href="<?php echo e(route('photoFolder.show',$folder->id)); ?>">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="<?php echo e(asset('assets/img/avatars/folder.png')); ?>" alt="cbImg">
                                </a>
                            </td>
                            <td><?php echo e(count($folder->photos)); ?></td>
                              <td><?php echo e($folder->name); ?></td>  
                               <td>
                                    <?php echo e($folder->event->name); ?>

                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                        <div class="dropdown-menu">
                                            <a class=" edit-folder-button dropdown-item" data-id="<?php echo e($folder); ?>" data-bs-target="#folder-modal-edit" data-bs-toggle="modal" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Edit</a>
                                           <form action="<?php echo e(route('photoFolder.destroy',$folder->id)); ?>" method="post">
                                           <?php echo csrf_field(); ?> 
                                           <?php echo method_field('DELETE'); ?>
                                             <button type="submit" class="dropdown-item" data-id="<?php echo e($folder->id); ?>" ><i class="delete ti ti-trash me-1"></i>Delete</button>
                                           </form>
                                           
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>

<?php echo $__env->make('backend.photo._includes.folder-edit-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.photo._includes.folder-add-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.photo._includes.photo-preview-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\photo\eventPhotos.blade.php ENDPATH**/ ?>