

  <div class="col-12 col-md-6">
  <div class="card h-100 shadow-sm">

    
    <div class="card-header border">
      <div class="d-flex align-items-center">
        <i class="ti ti-users fs-4 text-primary me-2"></i>
        <h5 class="m-0 fw-semibold"><?php echo e($team->name ?? 'Team'); ?></h5>
      </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) ($team->published ?? 0) !== 1): ?>
      <div class="card-body text-center py-4">
        <i class="ti ti-eye-off fs-1 text-warning d-block mb-2"></i>
        <span class="text-warning fw-medium">Team not yet published</span>
      </div>
    <?php else: ?>
    <div class="card-body p-0">
      <?php
        $registrationOpen = app(
          \App\Domain\Teams\Services\ExternalTeamRosterService::class
        )->registrationIsOpen($event);
      ?>
      <ul class="list-group list-group-flush m-0">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $team->team_players_no_profile; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $play): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $teamModel  = $play->team;
            $teamSlot = $teamModel->team_players->firstWhere('rank', $play->rank);
            $paystatus  = $teamSlot?->pay_status;
            $hasLinkedProfile = $play->player_profile && $play->profile;
            $linkProfileUrl = route('player.create', [
                'type' => 'noProfile',
                'noProfile' => $play->id,
                'team' => $team->id,
                'event' => $event->id
            ]);

            $playerName = $hasLinkedProfile
              ? ucfirst(strtolower($play->profile->name)) . ' ' . ucfirst(strtolower($play->profile->surname))
              : ucfirst(strtolower($play->name)) . ' ' . ucfirst(strtolower($play->surname));
            $myPaidTeamOrder = $hasLinkedProfile
              ? ($myPaidTeamOrdersByPlayer ?? collect())->get($teamModel->id.'-'.$play->player_profile)
              : null;
          ?>

          <li class="list-group-item">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

              
              <div class="d-flex align-items-center">
                <span class="badge bg-light text-muted border rounded-circle me-2" style="width: 24px; height: 24px; line-height: 16px; font-size: 0.75rem;">
                  <?php echo e($play->rank); ?>

                </span>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasLinkedProfile): ?>
                  <span class="fw-medium"><?php echo e($playerName); ?></span>
                <?php elseif($registrationOpen): ?>
                  <a href="<?php echo e($linkProfileUrl); ?>" class="text-primary fw-medium" title="Click to link a player profile">
                    <i class="ti ti-link me-1"></i><?php echo e($playerName); ?>

                  </a>
                <?php else: ?>
                  <span class="fw-medium"><?php echo e($playerName); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              
              <div class="d-flex align-items-center gap-1">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paystatus): ?>
                  <span class="badge bg-success-subtle text-success px-2 py-1">
                    <i class="ti ti-circle-check me-1"></i>Registered
                  </span>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($canWithdraw ?? false) && $myPaidTeamOrder): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger withDrawPlayer"
                            title="Cancel registration and withdraw from event"
                            aria-label="Withdraw <?php echo e($playerName); ?> from this team"
                            data-id="<?php echo e($teamSlot->id); ?>"
                            data-team="<?php echo e($teamModel->id); ?>"
                            data-player="<?php echo e($play->player_profile); ?>"
                            data-event="<?php echo e($event->id); ?>"
                            data-url="<?php echo e(route('team.player.withdraw', [$teamModel->id, $play->player_profile, $event->id])); ?>">
                      <i class="ti ti-x me-1" aria-hidden="true"></i>Withdraw
                    </button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php elseif($hasLinkedProfile && $registrationOpen): ?>
                  <a href="<?php echo e(route('team.payment.payfast', [$teamModel->id, $play->player_profile, $event->id])); ?>"
                     class="btn btn-sm btn-warning">
                    <i class="ti ti-credit-card me-1"></i>Register
                  </a>

                <?php elseif(!$hasLinkedProfile && $registrationOpen): ?>
                  <a href="<?php echo e($linkProfileUrl); ?>" class="btn btn-xs btn-outline-primary" title="Link a profile first">
                    <i class="ti ti-user-plus me-1"></i>Link Profile
                  </a>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary"><i class="ti ti-lock me-1"></i>Closed</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </div>
            </div>
          </li>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <li class="list-group-item text-center py-4">
            <i class="ti ti-users-minus fs-1 text-muted d-block mb-2"></i>
            <span class="text-muted">No team slots defined</span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      </ul>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\no-profile-team.blade.php ENDPATH**/ ?>