<?php
  /** Normalize relations */
  $regionsInEvent = $event->regions ?? collect();
?>

<div class="tab-pane fade show active" id="tab-players">

  
  <div class="subtabs-sticky">
    <ul class="nav nav-tabs px-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionsInEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li class="nav-item">
          <button class="nav-link <?php echo e($k === 0 ? 'active' : ''); ?>"
                  data-bs-toggle="tab"
                  data-bs-target="#players-region-<?php echo e($region->id); ?>">
            <?php echo e($region->region_name); ?>

          </button>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </div>

  
  <div class="player-global-actions d-flex align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-semibold">Active-roster exports</div>
      <div class="small text-muted">Downloads contain occupied team places only; reserves remain in the selection queue.</div>
    </div>
    <div class="player-global-actions__buttons d-flex align-items-center gap-2">
      <a href="<?php echo e(route('backend.team-selection.index', $event)); ?>" class="btn btn-sm btn-primary">
        <i class="ti ti-list-check"></i> Team Selection & Reserves
      </a>
      <a href="<?php echo e(route('event.players.exportPdf', $event->id)); ?>"
         class="btn btn-sm btn-outline-danger" target="_blank">
        <i class="ti ti-file-text"></i> Export PDF
      </a>

      <a href="<?php echo e(route('event.players.exportExcel', $event->id)); ?>"
         class="btn btn-sm btn-outline-success" target="_blank">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
      </a>
    </div>
  </div>

  
  <div class="tab-content region-tab-content">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionsInEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="tab-pane fade <?php echo e($k === 0 ? 'show active' : ''); ?>"
           id="players-region-<?php echo e($region->id); ?>">

        <div class="card mt-3">

          
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="m-0">Players — <?php echo e($region->region_name); ?></h5>

            <div class="region-email-actions d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary emailRegionBtn"
                      data-regionid="<?php echo e($region->id); ?>"
                      data-regionname="<?php echo e($region->region_name); ?>">
                <i class="ti ti-mail"></i> Email Active Roster
              </button>

              
              <button class="btn btn-sm btn-outline-warning emailUnpaidRegionBtn"
                      data-regionid="<?php echo e($region->id); ?>"
                      data-regionname="<?php echo e($region->region_name); ?>">
                <i class="ti ti-alert-circle"></i> Email Unpaid Active Roster
              </button>
            </div>
          </div>

          <div class="card-body">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams ?? collect(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

              <?php
                $selectionInvitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
                $rankingManaged = $selectionInvitations->isNotEmpty();
                $teamReserves = $selectionInvitations
                  ->where('status', \App\Models\TeamSelectionInvitation::RESERVE)
                  ->sortBy('queue_position');
              ?>

              
              <div class="mb-4">

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <h5 class="mb-0"><?php echo e($team->name); ?></h5>
                    <small class="text-muted">Team ID: <?php echo e($team->id); ?></small>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?>
                      <span class="badge bg-label-info ms-2">Ranking-managed</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-danger'); ?> me-2">
                      <?php echo e($team->published ? 'Published' : 'Not Published'); ?>

                    </span>

                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-dots"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                          <a class="dropdown-item emailTeamBtn" href="#" data-teamid="<?php echo e($team->id); ?>" data-teamname="<?php echo e($team->name); ?>">
                            <i class="ti ti-mail me-1"></i> Email Active Roster
                          </a>
                        </li>
                        <!-- team-level 'Email Unpaid Players' removed from dropdown -->
                        <li><hr class="dropdown-divider"></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?>
                          <li><a class="dropdown-item" href="<?php echo e(route('backend.team-selection.index', $event)); ?>"><i class="ti ti-list-check me-1"></i> Manage Selection & Reserves</a></li>
                        <?php else: ?>
                          <li><a class="dropdown-item editRosterBtn" href="#" data-teamid="<?php echo e($team->id); ?>"><i class="ti ti-users me-1"></i> Edit Roster</a></li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <li>
                          <a class="dropdown-item" href="<?php echo e(route('backend.region.clothing.edit', $region->id)); ?>">
                            <i class="ti ti-settings me-1"></i> Clothing Setup
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id])); ?>" target="_blank">
                            <i class="ti ti-shirt me-1"></i> Clothing Orders
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                
                <div class="table-responsive">
                  <table class="table table-sm table-bordered text-nowrap team-player-table">
                    <colgroup>
                      <col style="width: 7%">
                      <col style="width: 22%">
                      <col style="width: 31%">
                      <col style="width: 16%">
                      <col style="width: 14%">
                      <col style="width: 10%">
                    </colgroup>
                    <thead class="table-light">
                      <tr>
                        <th>#</th>
                        <th>Player</th>
                        <th>Email</th>
                        <th>Cell</th>
                        <th>Pay Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->teamPlayers ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                          $player = ((int)$slot->player_id > 0) ? $slot->player : null;
                          $np     = (!$player) ? $slot->noProfile : null;
                          $name   = $player
                            ? trim($player->name.' '.$player->surname)
                            : ($np ? trim($np->name.' '.$np->surname) : '—');
                          $paid   = (int)($slot->pay_status ?? 0);
                        ?>

                        <tr data-playerteamid="<?php echo e($slot->id); ?>">
                          <td>
                            <span class="badge bg-label-primary"><?php echo e($slot->rank); ?></span>
                          </td>

                          <td>
                            <?php echo e($name); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$player): ?>
                              <span class="badge bg-label-warning ms-1">No Profile</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </td>

                          <td><?php echo e($player->email ?? '—'); ?></td>
                          <td><?php echo e($player->cellNr ?? '—'); ?></td>

                          <td class="payStatus">
                            <span class="badge <?php echo e($paid ? 'bg-label-success' : 'bg-label-danger'); ?>">
                              <?php echo e($paid ? 'Paid' : 'Unpaid'); ?>

                            </span>
                          </td>

                          <td>
                            <div class="dropdown">
                              <button type="button" class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                              </button>

                              <div class="dropdown-menu dropdown-menu-end">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rankingManaged)): ?>
                                <a class="dropdown-item replacePlayerBtn"
                                   data-slotid="<?php echo e($slot->id); ?>"
                                   data-teamid="<?php echo e($team->id); ?>"
                                   data-playername="<?php echo e($name); ?>">
                                  <i class="ti ti-refresh me-1"></i> Replace Player
                                </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
                                  <a class="dropdown-item emailPlayer"
                                     data-playerid="<?php echo e($player->id); ?>"
                                     data-name="<?php echo e($name); ?>">
                                    <i class="ti ti-mail me-1"></i> Email Player
                                  </a>
                                <?php else: ?>
                                  <span class="dropdown-item text-muted">
                                    <i class="ti ti-mail me-1"></i> No email available
                                  </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rankingManaged)): ?>
                                <a class="dropdown-item changePayStatus"
                                   data-pivot="<?php echo e($slot->id); ?>">
                                  <i class="ti ti-credit-card me-1"></i> Change Pay Status
                                </a>

                                <a class="dropdown-item refundToWallet"
                                   data-pivot="<?php echo e($slot->id); ?>">
                                  <i class="ti ti-cash me-1"></i> Refund to Wallet
                                </a>
                                <?php else: ?>
                                  <a class="dropdown-item" href="<?php echo e(route('backend.team-selection.index', $event)); ?>"><i class="ti ti-list-check me-1"></i> Manage in Team Selection</a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </div>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?>
                  <div class="border rounded bg-light p-3 mt-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                      <div><strong>Reserve queue</strong><div class="small text-muted">Held back from the active roster, draws, exports and active-roster email until promoted.</div></div>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.team-selection.index', $event)); ?>">Manage selection</a>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $teamReserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reserve): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                      <div class="d-flex flex-wrap gap-2 justify-content-between border-top py-2 small">
                        <span><strong>#<?php echo e($loop->iteration); ?></strong> <?php echo e($reserve->player?->full_name ?? 'Missing player'); ?></span>
                        <span class="text-muted">Ranking #<?php echo e($reserve->ranking_position ?? '—'); ?></span>
                      </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                      <div class="small text-muted">No reserves remain for this team.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <div class="alert alert-light text-center">
                No teams in this region
              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </div>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>
<div class="modal fade" id="replaceRosterModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="replaceRosterModalLabel">
          Edit Roster
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="replaceRosterModalBody">
        
      </div>
    </div>
  </div>
</div>





<div class="modal fade" id="replacePlayerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Replace Player</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="replacePlayerModalBody">
        
      </div>
    </div>
  </div>
</div>


<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\players-legacy.blade.php ENDPATH**/ ?>