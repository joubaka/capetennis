

<div class="col-12 col-md-6 team-roster-column">
  <div class="card h-100 shadow-sm">

    
    <div class="card-header border rounded-top">
      <div class="d-flex align-items-center">
        <i class="ti ti-users fs-4 text-primary me-2"></i>
        <h5 class="m-0 fw-semibold"><?php echo e($team->name ?? 'Team'); ?></h5>
      </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int)($team->published ?? 0) === 1): ?>

      <div class="card-body p-0">
        <ul class="list-group list-group-flush m-0">

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $team->teamPlayers->filter(fn($member) => !$member->selected_team_reserve); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $dummyId    = 0;
              $isDummy    = (int) $slot->player_id === $dummyId;
              $player     = $isDummy ? null : $slot->player;

              $paid       = (int) ($slot->pay_status ?? 0) === 1;
              $canOrder   = $region->usesOnlineClothingOrders() && (int) ($region->clothing_order ?? 0) === 1;
              $signupOpen = $event->published && $event->registrationIsOpen();

              $playerName = $player
                ? trim(($player->name ?? '').' '.($player->surname ?? ''))
                : '— Empty slot —';
              $isInvitationTarget = $player
                && request()->integer('team') === (int) $team->id
                && request()->integer('player') === (int) $player->id;
              $playerClothingOrders = $player
                ? ($myPaidClothingOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id, collect())
                : collect();
              $clothingQuantity = $playerClothingOrders->sum(
                fn ($order) => $order->items->sum(fn ($item) => max(1, (int) ($item->qty ?? 1)))
              );
              $clothingDetailsId = 'clothing-orders-'.$event->id.'-'.$team->id.'-'.($player?->id ?? 0);
              $myPaidTeamOrder = $player
                ? ($myPaidTeamOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id)
                : null;
            ?>

            <li id="team-registration-<?php echo e($team->id); ?>-<?php echo e($player?->id ?? 0); ?>"
                class="list-group-item <?php echo e($isDummy ? 'bg-light' : ''); ?> <?php echo e($isInvitationTarget ? 'border border-success rounded bg-success-subtle' : ''); ?>"
                <?php if($isInvitationTarget): ?> data-team-registration-target="true" <?php endif; ?>>
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 team-player-row">

                
                <div class="d-flex align-items-center team-player-identity">
                  <span class="badge bg-light text-muted border rounded-circle me-2" style="width: 24px; height: 24px; line-height: 16px; font-size: 0.75rem;">
                    <?php echo e($slot->rank); ?>

                  </span>
                  <span class="<?php echo e($isDummy ? 'text-muted fst-italic' : 'fw-medium'); ?>">
                    <?php echo e($playerName); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id,'context' => $team->category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($team->category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </span>
                </div>

                
                <div class="d-flex align-items-center justify-content-sm-end flex-wrap gap-1 team-player-actions">

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paid): ?>
                      <span class="badge bg-success-subtle text-success px-2 py-1">
                        <i class="ti ti-circle-check me-1"></i>Registered
                      </span>

                      
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($canWithdraw ?? false) && $myPaidTeamOrder): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger withDrawPlayer"
                                title="Cancel registration and withdraw from event"
                                aria-label="Withdraw <?php echo e($playerName); ?> from this team"
                                data-id="<?php echo e($slot->id); ?>"
                                data-team="<?php echo e($team->id); ?>"
                                data-player="<?php echo e($player->id); ?>"
                                data-event="<?php echo e($event->id); ?>"
                                data-url="<?php echo e(route('team.player.withdraw', [$team->id, $player->id, $event->id])); ?>">
                          <i class="ti ti-x me-1" aria-hidden="true"></i>Withdraw
                        </button>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php elseif(($teamPaymentsUnderReview ?? collect())->has($team->id.'-'.$player->id)): ?>
                      <span class="badge bg-warning-subtle text-dark text-wrap" title="A payment has been received and needs review. Please do not pay again.">
                        <i class="ti ti-clock me-1"></i>Payment being checked
                      </span>

                    
                    <?php elseif($signupOpen): ?>
                      <a href="<?php echo e(route('team.payment.payfast', [$team->id, $player->id, $event->id])); ?>"
                         class="btn btn-sm btn-warning team-registration-button">
                        <i class="ti ti-credit-card me-1"></i>Register
                      </a>

                    
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary">
                        <i class="ti ti-lock me-1"></i>Closed
                      </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                  <?php else: ?>
                    
                    <span class="badge bg-light text-muted border">
                      <i class="ti ti-user-plus me-1"></i>Available
                    </span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>
              </div>

              <?php echo $__env->make('frontend.event.partials.player-clothing-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </li>

          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <li class="list-group-item text-center py-4">
              <i class="ti ti-users-minus fs-1 text-muted d-block mb-2"></i>
              <span class="text-muted">No team slots defined</span>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </ul>

      </div>

    <?php else: ?>
      <div class="card-body text-center py-4">
        <i class="ti ti-eye-off fs-1 text-warning d-block mb-2"></i>
        <span class="text-warning fw-medium">Team not yet published</span>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>

<?php echo $__env->make('frontend.event.partials.team-roster-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\profile-team.blade.php ENDPATH**/ ?>