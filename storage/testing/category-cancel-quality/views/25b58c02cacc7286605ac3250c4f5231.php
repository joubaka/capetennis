

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

<div class="container photo-admin">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3"><div><h4 class="mb-1"><?php echo e($event->name); ?> · Photo folders</h4><p class="text-muted mb-0">Open a folder to upload photos or manage its contents.</p></div></div>
    <div class="card ">

        <div class="card m-2">
            <div>
                <a class="btn btn-success btn-sm m-2" href="#folder-modal-add" data-bs-toggle="modal" data-bs-target="#folder-modal-add">Add folder</a>
            </div>

            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Folder</th>
                                <th>Photos</th>
                                <th>Name</th>

                                <th>Event</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->photoFolders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>


                                <td>
                                <a href="<?php echo e(route('photoFolder.show',$folder->id)); ?>">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="<?php echo e(asset('assets/img/avatars/folder.png')); ?>" alt="Open <?php echo e($folder->name); ?>">
                                </a>
                            </td>
                            <td><?php echo e(count($folder->photos)); ?></td>
                              <td><a href="<?php echo e(route('photoFolder.show', $folder->id)); ?>"><?php echo e($folder->name); ?></a></td>
                               <td>
                                    <?php echo e($folder->event->name); ?>

                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="Actions for <?php echo e($folder->name); ?>"><i class="ti ti-dots-vertical"></i></button>
                                        <div class="dropdown-menu">
                                            <a class=" edit-folder-button dropdown-item" data-id="<?php echo e($folder); ?>" data-bs-target="#folder-modal-edit" data-bs-toggle="modal" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Rename folder</a>
                                           <form action="<?php echo e(route('photoFolder.destroy',$folder->id)); ?>" method="post" onsubmit="return confirm('Delete this folder record? This action cannot be undone.')">
                                           <?php echo csrf_field(); ?>
                                           <?php echo method_field('DELETE'); ?>
                                             <button type="submit" class="dropdown-item" data-id="<?php echo e($folder->id); ?>" ><i class="delete ti ti-trash me-1"></i>Delete folder</button>
                                           </form>

                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>

<?php echo $__env->make('backend.photo._includes.folder-edit-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.photo._includes.folder-add-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<style>.photo-admin :is(.btn,.dropdown-item,input,select) { min-height:44px !important; } .photo-admin .dropdown-toggle { min-width:44px; } .photo-admin td { white-space:normal; overflow-wrap:anywhere; } .photo-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }</style>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\photo\eventPhotos.blade.php ENDPATH**/ ?>