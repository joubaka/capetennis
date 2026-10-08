
<?php
  $regionsInEvent = $event->regions ?? collect();
?>

<style>
  .drag-handle {
    cursor: grab;
  }
  .drag-handle * {
    pointer-events: none;
  }
</style>

<div class="tab-pane fade" id="tab-order">

  <div class="alert alert-warning" role="status">
    Set player order before generating fixtures. Once fixtures exist, dragging is blocked because changing the order would rebuild draw lineups and may change match times. Delete and recreate unplayed draws first. Preserve started matches and results.
  </div>

  
  <div class="subtabs-sticky">
    <ul class="nav nav-tabs px-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $regionsInEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <li class="nav-item">
          <button
            class="nav-link <?php echo e($k === 0 ? 'active' : ''); ?>"
            data-bs-toggle="tab"
            data-bs-target="#order-region-<?php echo e($region->id); ?>">
            <?php echo e($region->region_name); ?>

          </button>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <li class="nav-item">
          <span class="nav-link disabled">No regions</span>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </div>

  <div class="tab-content">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionsInEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div
        class="tab-pane fade <?php echo e($k === 0 ? 'show active' : ''); ?>"
        id="order-region-<?php echo e($region->id); ?>">

        <div class="card mt-3">
          <div class="card-header">
            <h5 class="mb-0">Player Order — <?php echo e($region->region_name); ?></h5>
          </div>

          <div class="card-body">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams ?? collect(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

              <?php
                $rankingManaged = ($teamSelectionInvitations ?? collect())->has($team->id);
                $slots = ($team->teamPlayers ?? collect())->sortBy('rank')->values();
                $noProfiles = $team->noProfile
                  ? $team->team_players_no_profile()->orderBy('rank')->get()
                  : collect();

                $maxRows = max($slots->count(), $noProfiles->count());
              ?>

              <div class="mb-4">

                
                <div class="d-flex justify-content-between mb-2">
                  <div>
                    <h5 class="mb-0"><?php echo e($team->name); ?></h5>
                    <small class="text-muted">Team ID: <?php echo e($team->id); ?></small>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?><span class="badge bg-label-info ms-2">Ranking-managed</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                  <span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-danger'); ?>">
                    <?php echo e($team->published ? 'Published' : 'Not Published'); ?>

                  </span>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle text-nowrap" style="min-width:1150px;">
                    <thead class="table-light">
                      <tr>
                        <th style="width:40px"></th>
                        <th>#</th>
                        <th>Profile Player</th>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile): ?>
                          <th>No-Profile Player</th>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <th>Email</th>
                        <th>Cell</th>
                        <th>Pay Status</th>
                      </tr>
                    </thead>

                    <tbody class="<?php echo e($rankingManaged ? '' : 'sortablePlayers'); ?>" data-team-id="<?php echo e($team->id); ?>">

                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($rank = 1; $rank <= $maxRows; $rank++): ?>
                        <?php
                          $profileSlot = $slots->firstWhere('rank', $rank);
                          $profile = $profileSlot?->player;

                          $noProfile = $team->noProfile
                            ? $noProfiles->firstWhere('rank', $rank)
                            : null;

                          $pivotId = $profileSlot?->id ?? $noProfile?->id;
                          $rowType = $profile ? 'profile' : 'noprofile';
                          $payStatus = $profileSlot?->pay_status ?? 0;
                        ?>

                        <tr
                          class="drag-item"
                          data-playerteamid="<?php echo e($pivotId); ?>"
                          data-teamplayerid="<?php echo e($profileSlot?->id); ?>"
                          data-noprofileid="<?php echo e($noProfile?->id); ?>"
                          data-type="<?php echo e($rowType); ?>">

                          <td class="text-center <?php echo e($rankingManaged ? '' : 'drag-handle'); ?>">
                            <i class="ti <?php echo e($rankingManaged ? 'ti-lock text-muted' : 'ti-grip-vertical text-muted'); ?>"></i>
                          </td>

                          <td>
                            <span class="badge bg-label-primary"><?php echo e($rank); ?></span>
                          </td>

                          <td class="<?php echo e($profile ? 'table-success' : 'table-light'); ?>">
                            <?php echo e($profile?->name); ?> <?php echo e($profile?->surname); ?>

                          </td>

                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile): ?>
                            <td class="<?php echo e($noProfile ? 'table-warning' : 'table-light'); ?>">
                              <?php echo e($noProfile?->name); ?> <?php echo e($noProfile?->surname); ?>

                            </td>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                          <td><?php echo e($profile?->email ?? $noProfile?->email ?? '—'); ?></td>
                          <td><?php echo e($profile?->cellNr ?? $noProfile?->cell_nr ?? '—'); ?></td>

                          <td class="payStatus">
                            <span class="badge <?php echo e($payStatus ? 'bg-label-success' : 'bg-label-danger'); ?>">
                              <?php echo e($payStatus ? 'Paid' : 'Not Paid'); ?>

                            </span>
                          </td>

                        </tr>
                      <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </tbody>
                  </table>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?>
                  <div class="alert alert-info py-2 small mt-2 mb-0">This order is controlled by the ranking selection. Use <a href="<?php echo e(route('backend.team-selection.index', $event)); ?>" class="alert-link">Team Selection & Reserves</a> to replace an unpaid player with the next reserve.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <div class="alert alert-light text-center">No teams found</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </div>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>


<script>
  window.APP_URL = "<?php echo e(url('/')); ?>";
</script>


<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\player-order-legacy.blade.php ENDPATH**/ ?>