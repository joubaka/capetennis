<?php $__env->startSection('title', 'Player Plus management'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y" style="max-width:1150px;overflow-wrap:anywhere" data-plus-management>
  <?php echo $__env->make('backend.player-plus.manage-style', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <a href="<?php echo e(route('backend.superadmin.index')); ?>">← Super Admin</a>
  <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 my-3"><div><h1 class="h3">Player Plus management</h1><p class="text-muted mb-0">Manage player access, memberships and payment problems.</p></div><a class="btn btn-outline-primary" href="<?php echo e(route('superadmin.player-plus.index')); ?>">Payment reviews</a></header>
  <div class="card card-body mb-4"><strong class="h4 mb-1"><?php echo e(number_format($activeCount)); ?> active players</strong><span class="text-muted">Verified paid access and current admin grants, excluding suspended access.</span></div>
  <p><a href="<?php echo e(route('settings.index')); ?>#settings-plus">Manage Plus plans, prices and new purchases</a></p>
  <form method="get" class="card card-body mb-4">
    <div class="row g-3"><div class="col-md-6"><label for="plus-search" class="form-label">Player name or profile number</label><input id="plus-search" class="form-control" name="q" value="<?php echo e($search); ?>" maxlength="100" placeholder="Find a player to manage or activate"></div>
      <div class="col-md-4"><label for="plus-status" class="form-label">Show</label><select id="plus-status" class="form-select" name="status"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['members'=>'All registrations and grants','active'=>'Active access','suspended'=>'Deactivated access','scheduled'=>'Scheduled admin access','expired'=>'Expired terms','pending'=>'Pending checkouts','problems'=>'Payment review history · includes resolved','all'=>'All players · add access']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if($status === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Search</button></div></div>
  </form>
  <p class="small text-muted"><?php echo e($players->total()); ?> players found. Select “All players · add access” to activate a player without an existing registration.</p>
  <div class="list-group mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2 py-3" href="<?php echo e(route('superadmin.player-plus.manage.show', $player)); ?>">
      <div><strong><?php echo e($player->full_name); ?></strong><span class="d-block small text-muted">Profile #<?php echo e($player->id); ?> · <?php echo e($player->plus_orders_count); ?> orders</span></div>
      <div class="text-md-end"><span class="badge <?php echo e(in_array($player->id, $activeIds) ? 'bg-label-success' : 'bg-label-secondary'); ?>"><?php echo e($player->plusOverride?->mode === 'suspended' ? 'Deactivated' : (in_array($player->id, $activeIds) ? 'Active' : 'No current access')); ?></span><span class="d-block small mt-1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->plusOverride?->mode === 'grant'): ?>Admin grant: <?php echo e($player->plusOverride->starts_at?->format('d M Y')); ?> – <?php echo e($player->plusOverride->ends_at?->format('d M Y')); ?><?php elseif($player->plusMembership): ?>Paid term ends <?php echo e($player->plusMembership->ends_at->format('d M Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span></div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="card card-body">No players match these filters. Search all players to add access.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php echo e($players->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-plus\manage-index.blade.php ENDPATH**/ ?>