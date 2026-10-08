<a class="btn btn-sm btn-outline-primary mb-2" href="<?php echo e(route('backend.team-substitutions.show', $team)); ?>">Competition player replacements</a>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->competitionSubstitutions()->exists()): ?>
<?php($competitionRoster = app(\App\Services\TeamDrawSideResolver::class)->activeRoster($team))
<div class="alert alert-info">
<strong>Effective competition roster</strong>
@php
$competitionEntries = $competitionRoster->team_players->map(fn ($member) => ['rank' => $member->rank, 'name' => $member->player?->full_name, 'player_id' => $member->player_id])
    ->concat($competitionRoster->team_players_no_profile->map(fn ($member) => ['rank' => $member->rank, 'name' => trim($member->name.' '.$member->surname).' · imported']))->sortBy('rank');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $competitionEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div>(<?php echo e($entry['rank']); ?>) <?php echo e($entry['name']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($entry['player_id'])): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $entry['player_id'],'context' => $team->category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($entry['player_id']),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($team->category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="small mt-2">Round cutoffs and selected-match stand-ins follow the recorded replacement scope. The table below is the original registration and payment roster.</div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<table class="table table-sm align-middle table-bordered text-nowrap" data-team-id="<?php echo e($team->id); ?>">
  <thead class="table-light">
    <tr>
      <th>#</th>
      <th>Profile Player</th>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile): ?>
        <th>No-Profile Player</th>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <th>Email</th>
      <th>Cell</th>
      <th>Pay Status</th>
      <th>Actions</th>
    </tr>
  </thead>

  <?php
    $profiles = $team->players()->withPivot('rank','pay_status')->orderBy('team_players.rank')->get();
    $noProfiles = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get() : collect();
    $max = max($profiles->count(), $noProfiles->count());
  ?>

  <tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $max; $i++): ?>
      <?php
        $profile = $profiles[$i] ?? null;
        $noProfile = $team->noProfile ? ($noProfiles[$i] ?? null) : null;
        $pivotId = $profile?->pivot?->id ?? $noProfile?->id;
        $payStatus = $profile?->pivot?->pay_status ?? 0;
      ?>
      <tr data-playerteamid="<?php echo e($pivotId); ?>" data-team-id="<?php echo e($team->id); ?>">
        <td><span class="badge bg-label-primary"><?php echo e($i + 1); ?></span></td>
        <td class="name <?php echo e($profile ? 'table-success' : 'table-light'); ?>"><?php echo e($profile?->name); ?> <?php echo e($profile?->surname); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $profile->id,'context' => $team->category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($profile->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($team->category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile): ?>
          <td class="noprofile-name <?php echo e($noProfile ? 'table-warning' : 'table-light'); ?>">
            <?php echo e($noProfile?->name); ?> <?php echo e($noProfile?->surname); ?>

          </td>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <td class="email"><?php echo e($profile?->email ?? $noProfile?->email ?? '—'); ?></td>
        <td class="cellNr"><?php echo e($profile?->cellNr ?? $noProfile?->cell_nr ?? '—'); ?></td>
        <td class="payStatus">
          <span class="badge <?php echo e($payStatus ? 'bg-label-success' : 'bg-label-danger'); ?>">
            <?php echo e($payStatus ? 'Paid' : 'Not Paid'); ?>

          </span>
        </td>
        <td>
          <div class="dropdown">
            <button class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
              <i class="ti ti-dots-vertical"></i>
            </button>
            <div class="dropdown-menu">
              <a class="dropdown-item insertPlayer" href="javascript:void(0);" data-pivot="<?php echo e($pivotId); ?>" data-position="<?php echo e($i + 1); ?>" data-teamid="<?php echo e($team->id); ?>">
                <i class="ti ti-insert me-1"></i> Replace Player
              </a>
              <a class="dropdown-item changePayStatus" href="javascript:void(0);" data-pivot="<?php echo e($pivotId); ?>">
                <i class="ti ti-credit-card me-1"></i> Change Pay Status
              </a>
              <a class="dropdown-item refundToWallet" href="javascript:void(0);" data-pivot="<?php echo e($pivotId); ?>">
                <i class="ti ti-cash me-1"></i> Refund to Wallet
              </a>
            </div>
          </div>
        </td>
      </tr>
    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </tbody>
</table>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\partials\team_players_table.blade.php ENDPATH**/ ?>