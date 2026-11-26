

<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?> " />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/apex-charts/apex-charts.css')); ?>" />

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/edit-player.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="player-edit-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h4 class="mb-1">Edit player</h4><p class="text-muted mb-0"><?php echo e($player->name); ?> <?php echo e($player->surname); ?> · Profile #<?php echo e($player->id); ?></p></div><a href="<?php echo e(route('backend.player.profile', $player->id)); ?>" class="btn btn-outline-secondary">Back to profile</a></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><strong>Review the player details.</strong><ul class="mb-0 mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form action="<?php echo e(route('player.update', $player->id)); ?>" method="POST">
    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Identity</h5></div><div class="card-body"><div class="row g-3">
      <div class="col-md-6"><label for="player-name" class="form-label">Player name</label><input class="form-control" type="text" name="player_name" value="<?php echo e(old('player_name', $player->name)); ?>" id="player-name" required maxlength="255" autocomplete="given-name"></div>
      <div class="col-md-6"><label for="player-surname" class="form-label">Player surname</label><input class="form-control" type="text" name="player_surname" value="<?php echo e(old('player_surname', $player->surname)); ?>" id="player-surname" required maxlength="255" autocomplete="family-name"></div>
      <div class="col-md-6"><label for="player-dob" class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" value="<?php echo e(old('dob', $player->dateOfBirth)); ?>" id="player-dob" autocomplete="bday"></div>
      <div class="col-md-6"><label for="player-gender" class="form-label">Gender</label><?php ($savedGender = (string) old('gender', $player->gender)); ?><select name="gender" id="player-gender" class="form-select"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedGender !== '' && !in_array($savedGender, ['1','2'], true)): ?><option value="<?php echo e($savedGender); ?>" selected><?php echo e(ucfirst($savedGender)); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><option value="">Not set</option><option value="1" <?php if((string) old('gender', $player->gender) === '1'): echo 'selected'; endif; ?>>Male</option><option value="2" <?php if((string) old('gender', $player->gender) === '2'): echo 'selected'; endif; ?>>Female</option></select></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Contact details</h5></div><div class="card-body"><div class="row g-3">
      <div class="col-md-6"><label for="player-email" class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?php echo e(old('email', $player->email)); ?>" id="player-email" maxlength="255" autocomplete="email"></div>
<?php echo $__env->make('frontend.player.partials.additional-emails', ['contactPlayer' => $player], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <div class="col-md-6"><label for="player-cell" class="form-label">Cell number</label><input class="form-control" type="tel" name="cell_nr" value="<?php echo e(old('cell_nr', $player->cellNr)); ?>" id="player-cell" maxlength="50" autocomplete="tel"></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Coach and development</h5></div><div class="card-body">
      <div class="mb-3"><label for="player-coach" class="form-label">Player coach</label><input class="form-control" type="text" name="coach" value="<?php echo e(old('coach', $player->coach)); ?>" id="player-coach" maxlength="255"></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasAnyRole(['super-user', 'admin'])): ?>
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <label class="form-label mb-0" for="is-player-of-colour">Player of colour (POC)</label>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
            data-bs-target="#player-of-colour-help" aria-expanded="false" aria-controls="player-of-colour-help"
            aria-label="Why? Why we ask about player of colour status">Why?</button>
        </div>
        <select class="form-select" name="is_player_of_colour" id="is-player-of-colour"><option value="" <?php if(old('is_player_of_colour', $player->is_player_of_colour) === null || old('is_player_of_colour', $player->is_player_of_colour) === ''): echo 'selected'; endif; ?>>Not declared</option><option value="1" <?php if((string) old('is_player_of_colour', $player->is_player_of_colour) === '1'): echo 'selected'; endif; ?>>Yes</option><option value="0" <?php if(old('is_player_of_colour', $player->is_player_of_colour) === false || (string) old('is_player_of_colour', $player->is_player_of_colour) === '0'): echo 'selected'; endif; ?>>No</option></select>
        <div class="collapse" id="player-of-colour-help">
          <p class="small text-muted mb-0 mt-2">As part of the Interpro development programme, each six-player provincial team must include at least two players of colour. This information helps selectors identify players to consider for Interpro.</p>
        </div>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></div>
    <button type="submit" class="btn btn-primary">Save player details</button>
  </form>
</div>
<style>.player-edit-admin { max-width:960px; } .player-edit-admin :is(.btn,input,select) { min-height:44px !important; } .player-edit-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player\edit-player.blade.php ENDPATH**/ ?>