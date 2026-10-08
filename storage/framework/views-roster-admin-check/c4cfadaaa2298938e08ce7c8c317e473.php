<?php $__env->startSection('title', 'Review Player Profile'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="alert alert-danger" role="alert">
          <strong>Please correct the player details before continuing.</strong>
          <ul class="mb-0 mt-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h5 class="mb-1">Review Player Profile</h5>
          <p class="text-muted mb-0"><?php echo e($player->full_name); ?> · profile #<?php echo e($player->id); ?></p>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            Confirm that these details are current and correct. The player will only be linked to your account and roster position after this form is saved.
          </div>

          <form method="POST" action="<?php echo e(route('player.claim.complete')); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" value="<?php echo e($player->name); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Surname</label>
                <input type="text" class="form-control" value="<?php echo e($player->surname); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="dateOfBirth">Date of Birth <span class="text-danger">*</span></label>
                <input id="dateOfBirth" type="date" name="dateOfBirth" class="form-control <?php $__errorArgs = ['dateOfBirth'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('dateOfBirth', $player->dateOfBirth ? \Carbon\Carbon::parse($player->dateOfBirth)->format('Y-m-d') : '')); ?>" max="<?php echo e(now()->subDay()->format('Y-m-d')); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                <select id="gender" name="gender" class="form-select <?php $__errorArgs = ['gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                  <option value="">Select Gender</option>
                  <option value="Male" <?php if(old('gender', (int) $player->gender === 1 ? 'Male' : '') === 'Male'): echo 'selected'; endif; ?>>Male</option>
                  <option value="Female" <?php if(old('gender', (int) $player->gender === 2 ? 'Female' : '') === 'Female'): echo 'selected'; endif; ?>>Female</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="cellNr">Cell Number <span class="text-danger">*</span></label>
                <input id="cellNr" type="tel" name="cellNr" class="form-control <?php $__errorArgs = ['cellNr'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('cellNr', $player->cellNr)); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('email', $player->email)); ?>" placeholder="player@example.com">
              </div>
            </div>

            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" value="1" id="confirmed-details" name="confirmed_details" required>
              <label class="form-check-label" for="confirmed-details">I confirm that these player details are current and correct.</label>
            </div>

            <div class="d-flex justify-content-end mt-4">
              <button type="submit" class="btn btn-success">Save Profile and Continue to Payment</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\player\review-team-profile.blade.php ENDPATH**/ ?>