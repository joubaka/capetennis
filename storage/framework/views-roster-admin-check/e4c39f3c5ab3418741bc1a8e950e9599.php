

<div class="col-12 col-xl-6 team-roster-column">
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

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $team->teamPlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $dummyId    = 0;
              $isDummy    = (int) $slot->player_id === $dummyId;
              $player     = $isDummy ? null : $slot->player;

              $paid       = (int) ($slot->pay_status ?? 0) === 1;
              $canOrder   = $region->usesOnlineClothingOrders() && (int) ($region->clothing_order ?? 0) === 1;
              $signupOpen = (int) ($event->signUp ?? 0) === 1;

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
              <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2 team-player-row">

                
                <div class="d-flex align-items-center">
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

                
                <div class="d-flex align-items-center justify-content-xl-end flex-wrap gap-1 team-player-actions">

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

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canOrder): ?>
                      <a href="javascript:void(0)"
                         class="btn btn-sm btn-outline-secondary clothing-order"
                         title="Order clothing for <?php echo e($playerName); ?>"
                         aria-label="Order clothing for <?php echo e($playerName); ?>"
                         data-playerid="<?php echo e($player->id); ?>"
                         data-name="<?php echo e($playerName); ?>"
                         data-team="<?php echo e($team->id); ?>"
                         data-region="<?php echo e($region->id); ?>"
                         data-eventid="<?php echo e($event->id); ?>"
                         data-bs-toggle="modal"
                         data-bs-target="#clothing-order-modal">
                        <i class="ti ti-shirt me-1" aria-hidden="true"></i>Order clothing
                      </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerClothingOrders->isNotEmpty()): ?>
                      <button type="button"
                              class="btn btn-sm btn-outline-success"
                              data-bs-toggle="collapse"
                              data-bs-target="#<?php echo e($clothingDetailsId); ?>"
                              aria-expanded="false"
                              aria-controls="<?php echo e($clothingDetailsId); ?>">
                        <i class="ti ti-shopping-bag-check me-1" aria-hidden="true"></i>
                        <?php echo e($clothingQuantity); ?> <?php echo e(Str::plural('item', $clothingQuantity)); ?> ordered
                      </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                  <?php else: ?>
                    
                    <span class="badge bg-light text-muted border">
                      <i class="ti ti-user-plus me-1"></i>Available
                    </span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>
              </div>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerClothingOrders->isNotEmpty()): ?>
                <div class="collapse mt-2" id="<?php echo e($clothingDetailsId); ?>">
                  <div class="border rounded bg-light-subtle p-2" aria-label="Clothing ordered for <?php echo e($playerName); ?>">
                    <div class="small fw-semibold mb-1">Your clothing order for <?php echo e($playerName); ?></div>
                    <ul class="list-unstyled small mb-0">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playerClothingOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothingOrder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingOrder->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothingItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php
                            $clothingItemName = $clothingItem->item_name
                              ?: ($clothingItem->itemType?->item_type_name ?? 'Clothing item');
                            $clothingSizeName = $clothingItem->size_name
                              ?: $clothingItem->size?->size;
                            $itemQuantity = max(1, (int) ($clothingItem->qty ?? 1));
                          ?>
                          <li class="d-flex flex-wrap justify-content-between gap-2 py-1">
                            <span>
                              <i class="ti ti-shirt me-1 text-success" aria-hidden="true"></i>
                              <?php echo e($clothingItemName); ?>

                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingSizeName): ?>
                                <span class="text-muted">(<?php echo e($clothingSizeName); ?>)</span>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="fw-semibold">Qty <?php echo e($itemQuantity); ?></span>
                          </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                  </div>
                </div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

<?php if (! $__env->hasRenderedOnce('9b29b511-49a8-49be-81bd-9fe5e7ecb546')): $__env->markAsRenderedOnce('9b29b511-49a8-49be-81bd-9fe5e7ecb546'); ?>
  <style>
    @media (max-width: 1199.98px) {
      .team-player-actions {
        width: 100%;
      }

      .team-player-actions > .btn,
      .team-player-actions > .badge {
        display: inline-flex;
        flex: 1 1 10rem;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        white-space: normal;
      }
    }

    @media (max-width: 575.98px) {
      .team-player-actions > .btn,
      .team-player-actions > .badge {
        flex-basis: 100%;
      }
    }
  </style>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\profile-team.blade.php ENDPATH**/ ?>