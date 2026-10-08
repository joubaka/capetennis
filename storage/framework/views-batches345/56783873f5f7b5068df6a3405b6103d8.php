<?php ($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values()); ?>
<?php ($transferOrders = auth()->user()->hasRole('super-user') ? \App\Models\TeamPaymentOrder::query()->where('event_id', $event->id)->where('team_id', $regionTeam->id)->whereNull('withdrawn_at')->where('pay_status', true)->get()->keyBy('effective_player_id') : collect()); ?>

<ul class="nav nav-tabs px-3 pt-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-players-<?php echo e($regionTeam->id); ?>" type="button"><i class="ti ti-users me-1"></i>Players</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-order-<?php echo e($regionTeam->id); ?>" type="button"><i class="ti ti-list-numbers me-1"></i>Player order</button></li>
</ul>
<div class="tab-content p-0">
  <div class="tab-pane fade show active" id="team-players-<?php echo e($regionTeam->id); ?>">
    <div class="p-3 border-bottom small text-muted">Imported roster names remain visible even before a Cape Tennis player profile is linked. Correcting a name does not create, unlink or edit a player profile.</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Rank</th><th>Imported roster name</th><th>Profile status</th><th>Contact</th></tr></thead>
        <tbody class="imported-roster-players" data-team-id="<?php echo e($regionTeam->id); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $importedRoster; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($linkedProfile = $slot->player_profile ? $slot->profile : null); ?>
            <?php ($linkedEmail = $linkedProfile ? $teamSelectionContacts->primaryEmail($linkedProfile) : null); ?>
            <?php ($contactEmail = $linkedEmail ?: $slot->email); ?>
            <?php ($contactCell = $linkedProfile?->cellNr ?: $slot->cell_nr); ?>
            <tr data-slot-id="<?php echo e($slot->id); ?>">
              <td><span class="badge bg-label-primary">Rank <?php echo e($slot->rank); ?></span></td>
              <td style="min-width:320px">
                <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.update', [$event, $eventRegion, $regionTeam, $slot])); ?>" class="row g-1 align-items-center imported-name-form" data-slot-id="<?php echo e($slot->id); ?>">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <div class="col"><label class="visually-hidden" for="imported-name-<?php echo e($slot->id); ?>">First name</label><input id="imported-name-<?php echo e($slot->id); ?>" name="name" value="<?php echo e($slot->name); ?>" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col"><label class="visually-hidden" for="imported-surname-<?php echo e($slot->id); ?>">Surname</label><input id="imported-surname-<?php echo e($slot->id); ?>" name="surname" value="<?php echo e($slot->surname); ?>" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Save name</button></div>
                </form>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$linkedProfile): ?>
                  <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.email.update', [$event, $eventRegion, $regionTeam, $slot])); ?>" class="d-flex align-items-center gap-1 mt-2 imported-email-form" data-slot-id="<?php echo e($slot->id); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <span class="badge bg-label-info text-nowrap">No-profile email</span>
                    <label class="visually-hidden" for="imported-email-<?php echo e($slot->id); ?>">No-profile email</label>
                    <input id="imported-email-<?php echo e($slot->id); ?>" type="email" name="email" value="<?php echo e($slot->email); ?>" class="form-control form-control-sm" maxlength="255" placeholder="Click to add email" required>
                    <button class="btn btn-sm btn-outline-primary text-nowrap">Save email</button>
                  </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedProfile && auth()->user()->hasRole('super-user') && ($coverageOrder = $transferOrders->get($slot->player_profile))): ?>
                  <details class="mt-2">
                    <summary class="small text-primary">Move payment to another player</summary>
                    <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.payment.transfer', [$event, $eventRegion, $regionTeam, $slot])); ?>" class="mt-2" style="min-width:220px">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="order_id" value="<?php echo e($coverageOrder->id); ?>">
                      <input type="hidden" name="expected_player_id" value="<?php echo e($slot->player_profile); ?>">
                      <label class="small" for="transfer-target-<?php echo e($slot->id); ?>">Unpaid player in this team</label>
                      <select id="transfer-target-<?php echo e($slot->id); ?>" name="target_player_id" class="form-select form-select-sm" required>
                        <option value="">Choose player</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $importedRoster; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($candidate->player_profile && $candidate->player_profile != $slot->player_profile && !$transferOrders->has($candidate->player_profile) && !$candidate->pay_status): ?>
                            <option value="<?php echo e($candidate->player_profile); ?>" data-rank-slot-id="<?php echo e($candidate->id); ?>" data-rank-player-name="<?php echo e($candidate->profile?->full_name ?: trim($candidate->name.' '.$candidate->surname)); ?>"><?php echo e($candidate->profile?->full_name ?: trim($candidate->name.' '.$candidate->surname)); ?> · Rank <?php echo e($candidate->rank); ?></option>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </select>
                      <label class="small mt-2" for="transfer-reason-<?php echo e($slot->id); ?>">Reason</label>
                      <input id="transfer-reason-<?php echo e($slot->id); ?>" name="reason" maxlength="1000" class="form-control form-control-sm" required>
                      <p class="small text-muted mt-2">Use this payment for the selected player. Any later refund goes to the original payer.</p>
                      <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_transfer" value="1" required><span>Confirm this player becomes unpaid and the selected player becomes paid.</span></label>
                      <button class="btn btn-sm btn-outline-primary">Move payment</button>
                    </form>
                  </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedProfile): ?>
                  <span class="badge bg-label-success">Imported · Linked</span>
                  <div class="small text-muted mt-1"><?php echo e($linkedProfile->full_name); ?></div>
                <?php else: ?>
                  <span class="badge bg-label-info">Imported · Unlinked</span>
                  <div class="small text-muted mt-1">Profile can be linked from the public team page.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event)): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedProfile && !(int) $slot->pay_status): ?>
                    <details class="mt-2">
                      <summary class="small text-danger">Unlink unpaid profile / reset</summary>
                      <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.profile.destroy', [$event, $eventRegion, $regionTeam, $slot])); ?>" class="mt-2" style="min-width:220px">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <input type="hidden" name="expected_player_id" value="<?php echo e((int) $slot->player_profile); ?>">
                        <input type="hidden" name="expected_rank" value="<?php echo e($slot->rank); ?>">
                        <p class="small text-muted">Keeps the imported name, contact and rank and releases unpaid wallet reservations. Paid, in-flight or previously participating profiles cannot be reset here.</p>
                        <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_unlink" value="1" required><span>Confirm unlinking <?php echo e($linkedProfile->full_name); ?>.</span></label>
                        <button class="btn btn-sm btn-outline-danger">Unlink profile and reset</button>
                      </form>
                    </details>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <details class="mt-2">
                    <summary class="small text-primary"><?php echo e($linkedProfile ? 'Replace linked profile' : 'Link player profile'); ?></summary>
                    <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.profile.update', [$event, $eventRegion, $regionTeam, $slot])); ?>" class="mt-2" style="min-width:220px">
                      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                      <input type="hidden" name="expected_player_id" value="<?php echo e((int) $slot->player_profile); ?>">
                      <input type="hidden" name="expected_rank" value="<?php echo e($slot->rank); ?>">
                      <label class="small" for="relink-player-<?php echo e($slot->id); ?>">Replacement profile</label>
                      <select id="relink-player-<?php echo e($slot->id); ?>" name="player_id" class="form-select team-player-select" data-placeholder="Search player name…" data-search-url="<?php echo e(route('backend.team-selection.imported-players.profiles.search', [$event, $eventRegion, $regionTeam, $slot])); ?>" required><option value=""></option></select>
                      <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_replacement" value="1" required><span>Confirm replacing <?php echo e($linkedProfile?->full_name ?: 'the unlinked position'); ?> with the selected profile.</span></label>
                      <p class="small text-muted mb-2">Imported names stay unchanged. Profiles with registration, payment or fixture history cannot be relinked here.</p>
                      <button class="btn btn-sm btn-outline-primary">Save profile link</button>
                    </form>
                  </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td data-effective-contact>
                <div data-effective-email><?php echo e($contactEmail ?: 'No email'); ?></div>
                <span class="badge <?php echo e($linkedEmail ? 'bg-label-success' : 'bg-label-info'); ?>" data-effective-email-source><?php echo e($linkedEmail ? 'Linked profile email' : ($linkedProfile ? 'No-profile fallback email' : 'No-profile email')); ?></span>
                <div class="small text-muted mt-1"><?php echo e($contactCell ?: 'No cell number'); ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contactCell): ?><span class="badge <?php echo e($linkedProfile?->cellNr ? 'bg-label-success' : 'bg-label-info'); ?>"><?php echo e($linkedProfile?->cellNr ? 'Linked profile cell' : 'No-profile cell'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary roster-email-button mt-2 <?php echo e(filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? '' : 'd-none'); ?>" data-imported-email-action type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="imported_player" data-team-id="<?php echo e($regionTeam->id); ?>" data-slot-id="<?php echo e($slot->id); ?>" data-recipient="<?php echo e(trim($slot->name.' '.$slot->surname)); ?> · <?php echo e($contactEmail); ?>"><i class="ti ti-mail me-1" aria-hidden="true"></i>Email player</button>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No imported roster players have been added to this team yet.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="tab-pane fade" id="team-order-<?php echo e($regionTeam->id); ?>">
    <div class="p-3 border-bottom small text-muted">Change the playing order without changing names, linked profiles or payment state. Every move is audited.</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Roster rank</th><th>Player</th><th>Profile status</th><th>Move</th></tr></thead>
        <tbody class="imported-roster-order roster-order-sortable" data-reorder-url="<?php echo e(route('backend.team-selection.imported-players.reorder', [$event, $eventRegion, $regionTeam])); ?>" data-reorder-field="slot_ids">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $importedRoster; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr draggable="true" data-order-id="<?php echo e($slot->id); ?>" data-slot-id="<?php echo e($slot->id); ?>">
              <td><span class="badge bg-label-primary">Rank <?php echo e($slot->rank); ?></span></td>
              <td><span class="drag-handle me-2" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></span><strong data-imported-player-name><?php echo e(trim($slot->name.' '.$slot->surname)); ?></strong></td>
              <td><span class="badge <?php echo e($slot->player_profile ? 'bg-label-success' : 'bg-label-info'); ?>"><?php echo e($slot->player_profile ? 'Imported · Linked' : 'Imported · Unlinked'); ?></span></td>
              <td><div class="d-flex gap-1">
                <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" aria-label="Move <?php echo e(trim($slot->name.' '.$slot->surname)); ?> up" <?php if($loop->first): echo 'disabled'; endif; ?>><i class="ti ti-arrow-up"></i></button></form>
                <form method="POST" action="<?php echo e(route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" aria-label="Move <?php echo e(trim($slot->name.' '.$slot->surname)); ?> down" <?php if($loop->last): echo 'disabled'; endif; ?>><i class="ti ti-arrow-down"></i></button></form>
              </div></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-selection\_imported-roster.blade.php ENDPATH**/ ?>