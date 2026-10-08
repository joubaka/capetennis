

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
<form action="<?php echo e(route('player.update',$player->id)); ?>" method="POST">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PATCH'); ?>
    <div class="card">


        <h5 class="card-header">Edit Player</h5>

        <div class="col-6">
            <div class="card-body">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Name</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="player_name" value="<?php echo e($player->name); ?>" id="html5-text-input">
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasAnyRole(['super-user', 'admin'])): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="is-player-of-colour">Player of colour (POC)</label>
                    <select class="form-select" name="is_player_of_colour" id="is-player-of-colour">
                        <option value="" <?php if($player->is_player_of_colour === null): echo 'selected'; endif; ?>>Not declared</option>
                        <option value="1" <?php if($player->is_player_of_colour === true): echo 'selected'; endif; ?>>Yes</option>
                        <option value="0" <?php if($player->is_player_of_colour === false): echo 'selected'; endif; ?>>No</option>
                    </select>
                    <small class="text-muted">For transformation and player development planning.</small>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Surname</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="player_surname" value="<?php echo e($player->surname); ?>" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-date-input" class="col-md-2 col-form-label">Date of Birth</label>
                    <div class="col-md-10">
                        <input class="form-control" type="date" name="dob" value="<?php echo e($player->dateOfBirth); ?>" id="html5-date-input">
                    </div>
                </div>



                <div class="mb-3 row">
                    <label for="html5-email-input" class="col-md-2 col-form-label">Email</label>
                    <div class="col-md-10">
                        <input class="form-control" name="email" type="email" value="<?php echo e($player->email); ?>" id="html5-email-input">
                    </div>
                </div>




                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Cell nr.</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="cell_nr" value="<?php echo e($player->cellNr); ?>" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3">

                    <label for="html5-text-input" class="col-md-4 col-form-label">Gender</label>

                    <select name="gender" class="select2gender select2 form-select form-select-lg select2-hidden-accessible" data-allow-clear="true" tabindex="-1" aria-hidden="true">

                        <option value="1" <?php echo e($player->gender == 1 ? 'selected':''); ?>>Male</option>
                        <option value="2" <?php echo e($player->gender == 2 ? 'selected':''); ?>>Female</option>
                    </select>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Coach</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="coach" value="<?php echo e($player->coach ? $player->coach:''); ?>" id="html5-text-input">
                    </div>
                </div>
            </div>


        </div>

    </div>
    <button type="submit" class="btn btn-primary btn-sm mt-4">Confirm changes</button>
</form>


<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\player\edit-player.blade.php ENDPATH**/ ?>