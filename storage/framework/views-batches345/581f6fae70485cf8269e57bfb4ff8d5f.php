<h2 class="h5">Player order · <?php echo e($region->region_name); ?></h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $rankingManaged = ($teamSelectionInvitations ?? collect())->has($team->id);
    $editable = !$rankingManaged && !$orderLocked && auth()->user()->can('team.players.manage', $team);
    $slots = $team->teamPlayers->keyBy('rank');
    $noProfiles = $team->team_players_no_profile->keyBy('rank');
    $ranks = $slots->keys()->merge($noProfiles->keys())->unique()->sort();
  ?>
  <details class="roster-team" data-order-team="<?php echo e($team->id); ?>" <?php if($loop->first): ?> open <?php endif; ?>>
    <summary class="roster-team-summary"><span class="roster-team-title"><strong><?php echo e($team->name); ?></strong><span class="small text-muted"><?php echo e($rankingManaged ? 'Ranking-managed order' : ($orderLocked ? 'Order locked: fixtures generated' : 'Drag a row or use Move up / Move down')); ?></span></span><span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-team-publication="<?php echo e($team->id); ?>"><?php echo e($team->published ? 'Published' : 'Unpublished'); ?></span></summary>
    <div class="roster-team-body">
      <div class="table-responsive"><table class="table align-middle order-player-table"><thead><tr><th scope="col">Move</th><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Payment</th></tr></thead>
        <tbody class="<?php echo e($editable ? 'sortablePlayers' : ''); ?>" data-team-id="<?php echo e($team->id); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ranks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $slot = $slots->get($rank);
              $player = $slot?->player;
              $np = $noProfiles->get($rank);
              $type = $np ? 'noprofile' : 'profile';
              $id = $np ? $np->id : $slot?->id;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
            ?>
            <tr class="drag-item" data-playerteamid="<?php echo e($id); ?>" data-teamplayerid="<?php echo e($slot?->id); ?>" data-noprofileid="<?php echo e($np?->id); ?>" data-type="<?php echo e($type); ?>">
              <td data-label="Move"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editable && $id): ?><span class="drag-handle" title="Drag to reorder" aria-hidden="true"><i class="ti ti-grip-vertical"></i></span><button type="button" class="btn btn-outline-secondary" data-order-move="up" aria-label="Move <?php echo e($name); ?> up">↑</button><button type="button" class="btn btn-outline-secondary" data-order-move="down" aria-label="Move <?php echo e($name); ?> down">↓</button><?php else: ?><span class="text-muted">Locked</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td data-label="Rank"><span class="badge bg-label-primary"><?php echo e($rank); ?></span></td>
              <td data-label="Player"><?php echo e($name); ?></td>
              <td data-label="Payment"><span class="badge <?php echo e(!$player && !$np ? 'bg-label-secondary' : ((int) $slot?->pay_status === 1 ? 'bg-label-success' : 'bg-label-warning')); ?>"><?php echo e(!$player && !$np ? 'Vacant' : ((int) $slot?->pay_status === 1 ? 'Paid' : 'Unpaid')); ?></span></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.team-selection.index', $event)); ?>">Manage selection order</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="small text-muted mb-0" role="status" data-order-status aria-live="polite"></p>
    </div>
  </details>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No teams in this region.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\order-region.blade.php ENDPATH**/ ?>