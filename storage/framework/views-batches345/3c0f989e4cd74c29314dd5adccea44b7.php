

<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(document).ready(function() {
  let currentSearchTerm = '';

  // Ajax search with Select2
  $('#player-search').select2({
    placeholder: 'Search all Cape Tennis player profiles',
    ajax: {
      url: '<?php echo e(route("player.search")); ?>',
      dataType: 'json',
      delay: 250,
      data: function (params) {
        currentSearchTerm = params.term || '';
        return { q: currentSearchTerm, page: params.page || 1, format: 'select2' };
      },
      processResults: function (data, params) {
        let results = data.results || [];

        // Offer creation after every existing matching profile has been reviewed.
        if (!data.pagination?.more) {
          results.push({
            id: 'create_new',
            text: '➕ Create new player "' + currentSearchTerm + '"'
          });
        }

        return { results: results, pagination: data.pagination || { more: false } };
      }
    },
    minimumInputLength: 2
  });

  // Handle selection
  $('#player-search').on('select2:select', function(e) {
    let data = e.params.data;

    if (data.id === 'create_new') {
      // Show Create Form
      $('#create-player-form').removeClass('d-none');
      $('#attach-player-form').addClass('d-none');

      // Pre-fill name/surname from search term
      let term = currentSearchTerm;
      if (term) {
        let parts = term.split(' ');
        $('input[name="player_name"]').val(parts.shift() || '');
        $('input[name="player_surname"]').val(parts.join(' '));
      }
    } else {
      // Confirm the selected profile before reviewing its current details.
      $('#attach-player-id').val(data.id);
      $('#selected-player-summary').text(data.text);
      $('#attach-player-form').removeClass('d-none');
      $('#create-player-form').addClass('d-none');
    }
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
  <div class="alert alert-danger" role="alert">
    <strong>We could not link this roster position.</strong>
    <ul class="mb-0 mt-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li><?php echo e($error); ?></li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="card mb-4">
  <div class="card-header">Link <?php echo e($name ?? 'this player'); ?> <?php echo e($surname ?? ''); ?></div>
  <div class="card-body">
    <p class="text-muted small">Search every Cape Tennis profile before creating a new one. Results show the player name, profile number, and a masked email address to help identify the correct profile.</p>
    <select id="player-search" style="width:100%"></select>
  </div>
</div>


<form id="attach-player-form" class="d-none" method="POST" action="<?php echo e(route('player.attach')); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="player_id" id="attach-player-id">
  <input type="hidden" name="team" value="<?php echo e($team ?? ''); ?>">
  <input type="hidden" name="event" value="<?php echo e($event ?? ''); ?>">
  <input type="hidden" name="noProfile" value="<?php echo e($noProfileId ?? ''); ?>">
  <div class="card mb-3">
    <div class="card-header">Confirm selected profile</div>
    <div class="card-body">
      <p class="fw-semibold mb-2" id="selected-player-summary">Selected Cape Tennis player profile</p>
      <p class="text-muted small">Confirm that this is the correct player. On the next screen you must review and update the player’s full profile before continuing to payment.</p>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="confirmed-profile" name="confirmed_profile" required>
        <label class="form-check-label" for="confirmed-profile">Yes, this is the correct player profile.</label>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-success">Confirm Profile and Review Details</button>
</form>


<form id="create-player-form" class="<?php echo e(old('player_name') ? '' : 'd-none'); ?>" method="POST" action="<?php echo e(route('player.store')); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="type" value="noProfile">
  <input type="hidden" name="team" value="<?php echo e($team ?? ''); ?>">
  <input type="hidden" name="event" value="<?php echo e($event ?? ''); ?>">
  <input type="hidden" name="noProfile" value="<?php echo e($noProfileId ?? ''); ?>">

  <div class="card">
    <h5 class="card-header">Create Player</h5>
    <div class="card-body">

      <div class="mb-3">
        <label>Player Name</label>
        <input type="text" name="player_name" class="form-control" value="<?php echo e(old('player_name', $name ?? '')); ?>" required>
      </div>

      <div class="mb-3">
        <label>Player Surname</label>
        <input type="text" name="player_surname" class="form-control" value="<?php echo e(old('player_surname', $surname ?? '')); ?>" required>
      </div>

      <div class="mb-3">
        <label>Date of Birth</label>
        <input type="date" name="dob" class="form-control" value="<?php echo e(old('dob', $dob ?? '')); ?>" required>
      </div>

      <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?php echo e(old('email', $email ?? '')); ?>">
      </div>

      <div class="mb-3">
        <label>Cell No.</label>
        <input type="text" name="cell_nr" class="form-control" value="<?php echo e(old('cell_nr', $cell_nr ?? '')); ?>">
      </div>

      <div class="mb-3">
        <label>Gender</label>
        <select name="gender" class="form-select" required>
          <option value="">Select Gender</option>
          <option value="1">Male</option>
          <option value="2">Female</option>
        </select>
      </div>

      <div class="mb-3">
        <label>Coach</label>
        <input type="text" name="coach" class="form-control">
      </div>

    </div>
  </div>

  <button type="submit" class="btn btn-primary mt-3">Create Profile and Continue to Payment</button>
</form>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\player\create-player.blade.php ENDPATH**/ ?>