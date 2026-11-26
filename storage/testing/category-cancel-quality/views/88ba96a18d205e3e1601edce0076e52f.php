<?php ($compactRoster = $compactRoster ?? false); ?>
<?php ($identity = $team->noProfile ? $slot->noProfile : $slot); ?>
<?php ($replacementPaid = (int) $slot->pay_status === 1 || (int) ($identity?->pay_status ?? 0) === 1); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identity && ($team->noProfile || (int) $identity->player_id > 0)): ?>
<div class="team-roster-actions">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster): ?>
    <?php echo $__env->make('backend.adminPage.admin_show._roster-order-buttons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php $__env->startComponent('backend.adminPage.admin_show._roster-player-modal', ['team' => $team, 'rosterSlot' => $slot, 'identity' => $identity, 'name' => $name, 'event' => $event]); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$replacementPaid || auth()->user()->hasRole('super-user')): ?>
    <details class="roster-replacement" <?php if($replacementPaid): ?> data-super-admin-only <?php endif; ?>>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster): ?>
        <summary class="roster-action-choice">
          <span>
            <strong class="d-block">Replace this player</strong>
            <span class="small text-body">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacementPaid): ?>
                Pick a player for the same spot and choose what happens to the original payment before saving.
              <?php else: ?>
                Pick a different player to take the same spot in the team.
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </span>
          </span>
          <i class="ti ti-chevron-down" aria-hidden="true"></i>
        </summary>
      <?php else: ?>
        <summary class="btn btn-sm btn-outline-primary">Change player</summary>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('backend.team.changePlayer')); ?>" class="mt-2 roster-replacement-form" data-team-roster-change>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
        <input type="hidden" name="slot_id" value="<?php echo e($identity->id); ?>"><input type="hidden" name="expected_rank" value="<?php echo e($identity->rank); ?>">
        <input type="hidden" name="expected_player_id" value="<?php echo e($team->noProfile ? (int) $identity->player_profile : $identity->player_id); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile && !$replacementPaid): ?>
          <fieldset class="roster-replacement-choice mb-3">
            <legend class="form-label">Replacement type</legend>
            <label><input type="radio" name="replacement_type" value="profile" checked data-replacement-type> Existing player profile</label>
            <label><input type="radio" name="replacement_type" value="name" data-replacement-type> Player without a profile</label>
          </fieldset>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <fieldset data-replacement-fields="profile">
          <legend class="visually-hidden">Existing player profile</legend>
          <label class="form-label w-100">Replacement player<select class="form-select" name="player_id" required data-roster-replacement-select="<?php echo e(route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id])); ?>"><option value=""></option></select></label>
        </fieldset>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile && !$replacementPaid): ?>
          <fieldset data-replacement-fields="name" hidden disabled>
            <legend class="visually-hidden">Player without a profile</legend>
            <p class="small text-muted">Enter the replacement player's name.</p>
            <label class="form-label w-100">First name<input class="form-control" name="name" required maxlength="100"></label>
            <label class="form-label w-100">Surname<input class="form-control" name="surname" required maxlength="100"></label>
          </fieldset>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacementPaid): ?>
          <label class="form-label w-100">Original player's payment<select class="form-select" name="refund_action" required>
            <option value="">Choose payment option</option>
            <option value="refund">Refund original payer's wallet</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Models\TeamSelectionInvitation::where('team_id', $team->id)->whereHas('selectionImport', fn ($query) => $query->where('event_id', $event->id)->whereIn('status', ['draft', 'sent']))->exists()): ?>
              <option value="transfer">Move full payment to replacement</option>
              <option value="replacement">Use refund toward replacement player</option>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <option value="waive">Keep the original payment (no refund)</option>
          </select></label>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Models\SiteSetting::get('withdrawal_allowed', '1') !== '1'): ?>
            <label class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="withdrawal_override" value="1">
              <span class="form-check-label">Override withdrawal closure for this replacement</span>
            </label>
            <p class="small text-muted">Withdrawals are currently disabled. Confirm this exception and explain why in Reason. It applies only to this replacement and does not reopen withdrawals. Any refund deadline override must be confirmed separately.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(now()->gt($event->withdrawalCloseAt())): ?>
            <label class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="deadline_override" value="1">
              <span class="form-check-label">Override refund deadline for this replacement</span>
            </label>
            <p class="small text-muted">To return or move payment after the deadline, explicitly confirm this override and explain why in Reason. Leave it unchecked when waiving the refund. The normal refund fee still applies to wallet refunds and refund-funded replacements; moving the full payment waives that fee.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <label class="form-label w-100">Reason<textarea class="form-control" name="reason" required maxlength="1000"></textarea></label>
          <details class="small mb-3">
            <summary>How payment options work</summary>
            <ul class="mb-0 mt-2">
              <li>Moving the full payment to the replacement waives the withdrawal fee.</li>
              <li>Wallet refunds and refunds used toward a replacement deduct the withdrawal fee.</li>
              <li>The replacement stays unpaid until their entry and regional fees are covered. The original payer has <?php echo e(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES); ?> minutes to pay any shortfall before they need to restart checkout.</li>
              <li>Unused or released funds stay in the original payer’s wallet. The original payment record stays with the payer and the original player.</li>
            </ul>
          </details>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <button class="btn btn-primary">Save replacement</button>
        <p class="small text-muted mt-1">The current player stays in place until you save.</p>
      </form>
    </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactRoster)): ?>
    <?php echo $__env->make('backend.adminPage.admin_show._roster-order-buttons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$compactRoster || !$replacementPaid): ?>
    <form method="POST" action="<?php echo e(route('backend.team.removePlayer')); ?>" data-team-roster-change>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
      <input type="hidden" name="remove" value="1">
      <input type="hidden" name="slot_id" value="<?php echo e($identity->id); ?>"><input type="hidden" name="expected_rank" value="<?php echo e($identity->rank); ?>">
      <input type="hidden" name="expected_player_id" value="<?php echo e($team->noProfile ? (int) $identity->player_profile : $identity->player_id); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster): ?>
          <button type="button" class="roster-action-choice" data-team-roster-remove-toggle aria-expanded="false">
            <span>
              <strong class="d-block">Remove this unpaid player</strong>
              <span class="small text-body">Leave the spot empty. You’ll be asked to confirm.</span>
            </span>
            <i class="ti ti-chevron-down" aria-hidden="true"></i>
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-outline-danger" data-team-roster-remove-toggle aria-expanded="false">Remove from roster</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span data-team-roster-remove-confirm hidden role="group" aria-label="Confirm player removal">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster): ?>
            <span class="small">Remove this player and leave the spot empty?</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
            <button class="btn btn-sm btn-danger">Remove player</button>
          <?php else: ?>
            <span class="small">Remove this player?</span>
            <button class="btn btn-sm btn-danger">Confirm remove</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </span>
    </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster && !($rosterOnly ?? false) && $slot->exists && (int) $slot->player_id > 0 && (int) $slot->pay_status === 1): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
      <?php echo $__env->make('backend.adminPage.admin_show._roster-withdrawal-action', ['slot' => $slot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster && $replacementPaid && !auth()->user()->hasRole('super-user')): ?>
    <p class="alert alert-secondary">This player has paid. Contact a Super Admin to replace or withdraw them.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$compactRoster || !$replacementPaid): ?>
    <?php echo $__env->make('backend.adminPage.admin_show._roster-name-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster): ?>
    <?php echo $__env->renderComponent(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-actions.blade.php ENDPATH**/ ?>