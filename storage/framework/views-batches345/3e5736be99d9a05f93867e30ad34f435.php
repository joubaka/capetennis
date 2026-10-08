<?php
  $player = optional($reg->registration?->players)->first();
?>

<tr data-row data-entry-id="<?php echo e($reg->id); ?>">

  
  <td>—</td>

  
  <td><?php echo e($player?->name); ?> <?php echo e($player?->surname); ?></td>

  
  <td class="col-email">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player?->email): ?>
      <a href="mailto:<?php echo e($player->email); ?>" class="text-decoration-none"><?php echo e($player->email); ?></a>
    <?php else: ?>
      —
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </td>

  
  <td class="col-cell"><?php echo e($player?->cellNr ?? '—'); ?></td>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasAnyRole(['super-user', 'admin'])): ?>
    <td class="col-poc">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player?->is_player_of_colour === true): ?>
        <span class="badge bg-info">Yes</span>
      <?php elseif($player?->is_player_of_colour === false): ?>
        <span class="badge bg-light text-dark">No</span>
      <?php else: ?>
        <span class="text-muted">—</span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </td>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <td class="col-status">
    <span class="badge <?php echo e($reg->status === 'withdrawn' ? 'bg-danger' : 'bg-success'); ?>">
      <?php echo e(ucfirst($reg->status ?? 'active')); ?>

    </span>
  </td>

  
  <td>
    <?php echo $__env->make('backend.event.partials.admin-payment-note', ['reg' => $reg], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </td>

  
  <td class="col-actions text-end">
    <div class="dropdown">
      <button type="button"
              class="btn btn-outline-secondary btn-sm dropdown-toggle"
              data-bs-toggle="dropdown"
              aria-expanded="false">
        Actions
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <button type="button"
                  class="dropdown-item email-btn"
                  data-scope="player"
                  data-registration="<?php echo e($reg->registration_id); ?>">
            <i class="ti ti-mail me-1"></i>Email
          </button>
        </li>
        <li>
          <button type="button"
                  class="dropdown-item move-player-btn"
                  data-entry="<?php echo e($reg->id); ?>"
                  data-player="<?php echo e($player?->name); ?> <?php echo e($player?->surname); ?>"
                  data-from-category="">
            <i class="ti ti-arrows-transfer-up me-1"></i>Move
          </button>
        </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->status !== 'withdrawn'): ?>
          <li>
            <button type="button"
                    class="dropdown-item text-warning withdraw-player-btn"
                    data-url="<?php echo e(route('admin.category.registration.withdraw', $reg)); ?>"
                    data-player="<?php echo e(trim(($player?->name ?? '') . ' ' . ($player?->surname ?? ''))); ?>">
              <i class="ti ti-user-minus me-1"></i>Withdraw
            </button>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?>
          <li><hr class="dropdown-divider"></li>
          <li>
            <button type="button"
                    class="dropdown-item text-info view-entry-details-btn"
                    data-url="<?php echo e(route('admin.entry.details', $reg)); ?>">
              <i class="ti ti-info-circle me-1"></i>View Details
            </button>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </div>
  </td>

</tr>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\entry-row.blade.php ENDPATH**/ ?>