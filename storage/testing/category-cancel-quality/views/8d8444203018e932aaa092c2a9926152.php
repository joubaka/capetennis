<?php
  $selectedReserves = ($team->noProfile ? $team->team_players_no_profile : $team->teamPlayers)->filter(fn($member) => $member->selected_team_reserve)->sortBy('rank');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedReserves->isNotEmpty()): ?>
<section class="border-top" aria-label="Reserves for <?php echo e($team->name); ?>">
  <div class="p-3"><h6 class="mb-1">Reserves (<?php echo e($selectedReserves->count()); ?>)</h6><p class="small text-muted mb-0">Reserves can register after a tournament administrator approves promotion to the team.</p></div>
  <ul class="list-group list-group-flush">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $selectedReserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reserve): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $reserveProfile = $team->noProfile ? $reserve->profile : $reserve->player;
      $reservePlayerId = $team->noProfile ? $reserve->player_profile : $reserve->player_id;
      $reserveName = $reserveProfile ? trim($reserveProfile->name.' '.$reserveProfile->surname) : trim($reserve->name.' '.$reserve->surname);
      $reserveOrder = ($myPaidTeamOrdersByPlayer ?? collect())->get($team->id.'-'.$reservePlayerId);
      $reserveMirror = $team->noProfile ? $team->team_players->first(fn($mirror) => (int) $mirror->rank === (int) $reserve->rank && (int) $mirror->player_id === (int) $reservePlayerId) : $reserve;
      $reservePaid = (int) $reserve->pay_status === 1 || (int) $reserveMirror?->pay_status === 1;
    ?>
    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
      <span><?php echo e($reserveName); ?></span>
      <div class="d-flex flex-wrap gap-1"><span class="badge bg-label-warning">Reserve</span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reservePaid): ?><span class="badge bg-success-subtle text-success">Registered</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reservePaid && ($canWithdraw ?? false) && $reserveOrder && $reserveMirror): ?>
      <button type="button" class="btn btn-sm btn-outline-danger withDrawPlayer" data-id="<?php echo e($reserveMirror->id); ?>" data-team="<?php echo e($team->id); ?>" data-player="<?php echo e($reservePlayerId); ?>" data-event="<?php echo e($event->id); ?>" data-url="<?php echo e(route('team.player.withdraw', [$team->id, $reservePlayerId, $event->id])); ?>">Withdraw</button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
    </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ul>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\selected-team-reserves.blade.php ENDPATH**/ ?>