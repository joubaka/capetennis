

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
    <div class="card ">

        <div class="card m-2">
           
           
            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th># of Photos</th>
                                <th>Name</th>
                                
                                <th>Event</th>
                                

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $folders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                               

                                <td>
                                <a href="<?php echo e(route('frontend.event.show.folder',$folder->id)); ?>">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="<?php echo e(asset('assets/img/avatars/folder.png')); ?>" alt="cbImg">
                                </a>
                            </td>
                            <td><?php echo e(count($folder->photos)); ?></td>
                              <td><?php echo e($folder->name); ?></td>  
                               <td>
                                    <?php echo e($folder->event->name); ?>

                                </td>
                               
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>


<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\photo\photo-folders.blade.php ENDPATH**/ ?>