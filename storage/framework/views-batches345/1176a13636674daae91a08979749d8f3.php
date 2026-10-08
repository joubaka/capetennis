

<?php $__env->startSection('title', 'Checkout'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/bs-stepper/bs-stepper.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/rateyo/rateyo.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>" />

<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?> " />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/bs-stepper/bs-stepper.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/rateyo/rateyo.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/wizard-ex-checkout.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<script src="<?php echo e(asset('assets/js/test-wizard-ex-checkout.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/forms-selects.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/select2-search-addon.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<form class="browser-default-validation col-md-6">
  <div class="mb-3">
    <label class="form-label" for="basic-default-name">Name</label>
    <input type="text" class="form-control" id="basic-default-name" placeholder="John Doe" required />
  </div>
  <div class="mb-3">
    <label class="form-label" for="basic-default-email">Email</label>
    <input type="email" id="basic-default-email" class="form-control" placeholder="john.doe" required />
  </div>
  <div class="mb-3 form-password-toggle">
    <label class="form-label" for="basic-default-password">Password</label>
    <div class="input-group input-group-merge">
      <input type="password" id="basic-default-password" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="basic-default-password2" required />
      <span class="input-group-text cursor-pointer" id="basic-default-password2"><i class="ti ti-eye-off"></i></span>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="basic-default-country">Country</label>
    <select class="form-select" id="basic-default-country" required>
      <option value="">Select Country</option>
      <option value="usa">USA</option>
      <option value="uk">UK</option>
      <option value="france">France</option>
      <option value="australia">Australia</option>
      <option value="spain">Spain</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="basic-default-dob">DOB</label>
    <input type="text" class="form-control flatpickr-validation" id="basic-default-dob" required />
  </div>
  <div class="mb-3">
    <label class="form-label" for="basic-default-upload-file">Profile pic</label>
    <input type="file" class="form-control" id="basic-default-upload-file" required />
  </div>
  <div class="mb-3">
    <label class="d-block form-label">Gender</label>
    <div class="form-check mb-2">
      <input type="radio" id="basic-default-radio-male" name="basic-default-radio" class="form-check-input" required />
      <label class="form-check-label" for="basic-default-radio-male">Male</label>
    </div>
    <div class="form-check">
      <input type="radio" id="basic-default-radio-female" name="basic-default-radio" class="form-check-input" required />
      <label class="form-check-label" for="basic-default-radio-female">Female</label>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="basic-default-bio">Bio</label>
    <textarea class="form-control" id="basic-default-bio" name="basic-default-bio" rows="3" required></textarea>
  </div>
  <div class="mb-3">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="basic-default-checkbox" required />
      <label class="form-check-label" for="basic-default-checkbox">Agree to our terms and conditions</label>
    </div>
  </div>
  <div class="mb-3">
    <label class="switch switch-primary">
      <input type="checkbox" class="switch-input" required />
      <span class="switch-toggle-slider">
        <span class="switch-on"></span>
        <span class="switch-off"></span>
      </span>
      <span class="switch-label">Send me related emails</span>
    </label>
  </div>
  <div class="row">
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Submit</button>
    </div>
  </div>
</form>


<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\testcheckout.blade.php ENDPATH**/ ?>