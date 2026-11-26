<p class="small text-muted" data-order-match-count role="status" aria-live="polite"></p>
<div class="alert alert-light border" data-order-empty hidden>No teams match this search.</div>
<h2 class="h6 mb-2">Player order · <?php echo e($region->region_name); ?></h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $rosterOnly = $rosterOnly ?? false;
    $rankingManaged = ($teamSelectionInvitations ?? collect())->has($team->id);
    $orderLocked = $orderLocked && !app(\App\Services\TeamRosterService::class)->canUpdateOrderAutomatically($event);
    $editable = $rosterOnly ? !$orderLocked : (!$rankingManaged && !$orderLocked && auth()->user()->can('team.players.manage', $team));
    $orderStatus = $orderLocked ? 'Locked' : ($rankingManaged ? 'Ranking-managed' : ($editable ? 'Editable' : 'Read-only'));
    $orderHint = $orderLocked ? 'Locked here: fixtures generated. Review safe moves in selection playing order.' : ($rankingManaged ? 'Order follows team selection.' : ($editable ? 'Drag a row or use Move up / Move down. Moves save immediately.' : 'You do not have permission to change this team order.'));
    if ($rosterOnly) $orderHint = ($isRegionalPreview ?? false) ? 'Read-only preview.' : ($orderLocked ? 'Ask the event administrator to change this player list.' : 'Use Move up and Move down to change player order.');
    $slots = $team->teamPlayers->keyBy('rank');
    $noProfiles = $team->team_players_no_profile->keyBy('rank');
    $ranks = $slots->keys()->merge($noProfiles->keys())->unique()->sort();
  ?>
  <details class="roster-team" data-order-team="<?php echo e($team->id); ?>" data-order-team-name="<?php echo e($team->name); ?>">
    <summary class="roster-team-summary"><span class="roster-team-title"><strong><?php echo e($team->name); ?></strong><span class="small text-muted"><?php echo e($orderHint); ?></span></span>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
<span class="d-flex flex-wrap gap-2"><span class="badge <?php echo e($editable ? 'bg-label-info' : 'bg-label-warning'); ?>"><?php echo e($orderStatus); ?></span><span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-team-publication="<?php echo e($team->id); ?>"><?php echo e($team->published ? 'Published' : 'Unpublished'); ?></span></span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</summary>
    <div class="roster-team-body">
      <div class="table-responsive"><table class="table table-sm align-middle order-player-table mb-1"><thead><tr><th scope="col"><?php echo e($editable ? 'Move' : 'Order status'); ?></th><th scope="col">Rank</th><th scope="col">Player</th>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
<th scope="col">Payment</th>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tr></thead>
        <tbody class="<?php echo e($editable && !$rosterOnly ? 'sortablePlayers' : ''); ?>" data-team-id="<?php echo e($team->id); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ranks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $slot = $rosterOnly ? $team->workspaceSlots->firstWhere('rank', $rank) : $slots->get($rank);
              $player = $slot?->player;
              $np = $noProfiles->get($rank);
              if ($team->noProfile && (int) $np?->player_profile > 0) $player = $np->profile;
              $type = $np ? 'noprofile' : 'profile';
              $id = $np ? $np->id : $slot?->id;
              $orderable = $team->noProfile ? (bool) $np : (bool) $slot;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
            ?>
            <?php if(!$player && !$np && $rank > $team->num_team_members) continue; ?>
            <tr class="<?php echo e($orderable ? 'drag-item' : 'order-vacancy'); ?>" data-playerteamid="<?php echo e($orderable ? $id : ''); ?>" data-teamplayerid="<?php echo e($slot?->id); ?>" data-noprofileid="<?php echo e($np?->id); ?>" data-type="<?php echo e($type); ?>">
              <td data-order-controls data-label="<?php echo e($editable ? 'Move' : 'Order status'); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterOnly && $editable && $slot): ?>
                  <?php echo $__env->make('backend.adminPage.admin_show._roster-actions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php elseif($editable && $id && $orderable): ?><span class="drag-handle" title="Drag to reorder" aria-hidden="true"><i class="ti ti-grip-vertical"></i></span><button type="button" class="btn btn-outline-secondary" data-order-move="up" aria-label="Move <?php echo e($name); ?> up">↑</button><button type="button" class="btn btn-outline-secondary" data-order-move="down" aria-label="Move <?php echo e($name); ?> down">↓</button><?php else: ?><span class="text-muted"><?php echo e($editable ? 'No roster place' : $orderStatus); ?></span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </td>
              <td data-label="Rank"><span class="badge bg-label-primary"><?php echo e($rank); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->workspaceSlots?->firstWhere('rank', $rank)?->selected_team_reserve): ?><span class="badge bg-label-warning ms-1">Reserve</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>
              <td data-label="Player"><?php echo e($name); ?></td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
              <td data-label="Payment"><span class="badge <?php echo e(!$player && !$np ? 'bg-label-secondary' : ((int) $slot?->pay_status === 1 ? 'bg-label-success' : 'bg-label-warning')); ?>"><?php echo e(!$player && !$np ? 'Vacant' : ((int) $slot?->pay_status === 1 ? 'Paid' : 'Unpaid')); ?></span></td>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged && !$rosterOnly): ?><button type="button" class="btn btn-outline-primary" data-open-selection-order="<?php echo e($team->id); ?>">Manage selection order</button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <p class="small text-muted mb-0" role="status" data-order-status aria-live="polite"></p>
    </div>
  </details>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No teams in this region.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\order-region.blade.php ENDPATH**/ ?>