

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
                   Import file
                </div>
                <div class="card-body">
                   <div class="alert alert-info">Open the event’s Regions &amp; Teams page and choose <strong>Import Roster</strong> on the exact team. Imports are now scoped to that event and team.</div>
                   <form action="#"
      method="post"
      enctype="multipart/form-data">
  <?php echo csrf_field(); ?>

  <div class="row mb-3">
    <label class="col-sm-3 col-form-label">File</label>
    <div class="col-sm-9">
      <input type="file" class="form-control" name="file" required>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <div class="text-danger small"><?php echo e($message); ?></div>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
      <button type="submit" class="btn btn-success" disabled>Choose a team from the event page</button>
    </div>
  </div>
</form>

                    <div class="card">
<div class="card-body">
    team_id,rank,name,surname,paystatus,
</div>



                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\import\noProfileImport.blade.php ENDPATH**/ ?>