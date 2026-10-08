

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


  <div class="card">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Event</th>
            <th>Total Uploaded</th>
            <th>View</th>

          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><?php echo e($event->id); ?></td>
            <td><?php echo e($event->name); ?></td>
            <td>
              # fotos here
            </td>
            <td><a href="<?php echo e(route('eventPhoto.show',$event->id)); ?>" class="badge bg-label-primary me-1">View</a></td>

          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>



  </div>
</div>


<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\photo\index.blade.php ENDPATH**/ ?>