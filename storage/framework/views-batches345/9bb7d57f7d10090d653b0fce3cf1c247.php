<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?> " />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/apex-charts/apex-charts.css')); ?>" />

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<?php echo $__env->make('multiend.player_profile_styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/apex-charts/apexcharts.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/player-profile.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/charts.js')); ?>"></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row g-4 player-profile-page">
  <!-- User Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <!-- User Card -->
    <div class="card mb-4 player-summary-card">
      <div class="card-body">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column">
            <div class="player-avatar-placeholder mb-3" aria-hidden="true"><?php echo e(\Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($player->full_name, 0, 1))); ?></div>
            <div class="user-info text-center">
              <h4 class="mb-1"><?php echo e($player->full_name); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?></h4>
              <span class="text-muted small">Player profile</span>

            </div>
          </div>
        </div>
        <div class="d-flex justify-content-around flex-wrap mt-3 pt-3 pb-4 border-bottom">
          <div class="player-stat d-flex align-items-center me-2 mt-3 gap-2">
            <span class="badge bg-label-primary  rounded"><i class="ti ti-checkbox ti-sm"></i></span>
            <div>
              <p class="mb-0 fw-semibold"><?php echo e($player->registrations->count()); ?></p>
              <small>Registered events</small>
            </div>
          </div>
          <div class="player-stat d-flex align-items-center mt-3 mb-3 gap-2">
            <span class="badge bg-label-primary p-2 rounded"><i class="ti ti-briefcase ti-sm"></i></span>
            <div>
              <p class="mb-0 fw-semibold"><?php echo e($player->users()->count()); ?></p>
              <small>Linked users</small>

            </div>

          </div>
          <div class="card shadow-none bg-transparent border w-100 mt-2">
            <div class="card-header">
              <h6 class="mb-0">Linked users</h6>
            </div>
            <div class="card-body">
              <ol class="list-group list-group-numbered linked-user-list mb-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="list-group-item"><span class="fw-medium"><?php echo e($user->name); ?></span><br><small class="text-muted"><?php echo e($user->email); ?></small></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </ol>
            </div>
          </div>


        </div>
        <p class="mt-4 mb-2 small text-uppercase text-muted fw-semibold">Player details</p>
        <div class="info-container">
          <ul class="list-unstyled">
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Name:</span>
              <span><?php echo e($player->full_name); ?></span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Email:</span>
              <span><?php echo e($player->email); ?></span>
            </li>



            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Contact:</span>
              <span><?php echo e($player->cellNr); ?></span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Gender:</span>
              <span><?php echo e($player->gender == 1 ? 'Male':'Female'); ?></span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Date of Birth:</span>
              <span><?php echo e($player->dateOfBirth); ?></span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Coach:</span>
              <span><?php echo e($player->coach); ?></span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Memberships:</span>
              <span class="badge bg-label-<?php echo e($player->subscriptions->count() > 0  ? 'success':'info'); ?>"><?php echo e($player->subscriptions->count() > 0  ? $player->subscriptions[0]->type:'Free Membership'); ?> </span>
            </li>
          </ul>
          <div class="d-grid mt-3">
            <a href="<?php echo e(route('player.edit',$player->id)); ?>" class="btn btn-outline-primary waves-effect"><i class="ti ti-pencil me-1"></i>Edit player details</a>

          </div>
        </div>
      </div>
    </div>
    <!-- /User Card -->

  </div>
  <!--/ User Sidebar -->


  <!-- User Content -->
  <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
    <?php echo $__env->make('multiend.player_profile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>
  <!--/ User Content -->

</div>


<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\player\player_profile.blade.php ENDPATH**/ ?>