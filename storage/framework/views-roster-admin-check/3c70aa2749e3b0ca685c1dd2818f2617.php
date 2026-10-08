
<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Replace Player in Remaining Fixtures</h5>

      <form id="replacePlayerForm" method="POST" action="<?php echo e(route('backend.team-fixtures.replacePlayer')); ?>">
        <?php echo csrf_field(); ?>

        
        <div class="mb-3">
          <label class="form-label">Event</label>
          <select name="event_id" id="eventSelect" class="form-select" required>
            <option value="">Select event</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($ev->id); ?>" <?php if(isset($event) && $event->id == $ev->id): ?> selected <?php endif; ?>><?php echo e($ev->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        
        <div class="mb-3">
          <label class="form-label">Replace (existing)</label>
          <select id="oldSelect" name="old_id" class="form-select" style="width:100%;" required>
            <option value="">Select player or no-profile</option>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event) && $event->regions && $event->regions->count()): ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <optgroup label="<?php echo e($region->name); ?>">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams->where('region_id', $region->id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->team_players && $team->team_players->count()): ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->team_players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($tp->player)): ?>
                          <?php
                            $rankVal = $tp->rank ?? ($tp->player->rank ?? '');
                          ?>
                          <option value="p_<?php echo e($tp->player->id); ?>"
                                  data-team="<?php echo e($team->id); ?>"
                                  data-region="<?php echo e($region->short_name ?? $region->name); ?>"
                                  data-rank="<?php echo e($rankVal); ?>">
                            <?php echo e($tp->player->full_name); ?> — <?php echo e($region->short_name ?? $region->name); ?> / <?php echo e($team->name); ?>

                          </option>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $noProfiles->where('team_id', $team->id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $np): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="np_<?php echo e($np->id); ?>"
                              data-team="<?php echo e($team->id); ?>"
                              data-region="<?php echo e($region->short_name ?? $region->name); ?>"
                              data-rank="<?php echo e($np->rank ?? ''); ?>">
                        NP: <?php echo e(trim($np->name.' '.$np->surname)); ?> — <?php echo e($region->short_name ?? $region->name); ?> / <?php echo e($team->name); ?>

                      </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </optgroup>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
              
              <optgroup label="Registered players">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="p_<?php echo e($p->id); ?>" data-team="" data-region="" data-rank="<?php echo e($p->rank ?? ''); ?>">
                    <?php echo e($p->name); ?> <?php echo e($p->surname); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </optgroup>
              <optgroup label="No-profile players">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $noProfiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $np): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="np_<?php echo e($np->id); ?>" data-team="<?php echo e($np->team_id); ?>" data-region="" data-rank="<?php echo e($np->rank ?? ''); ?>">
                    NP: <?php echo e(trim($np->name.' '.$np->surname)); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </optgroup>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
          <div class="form-text">Choose the existing player/no-profile entry to replace. Selecting a player will show all remaining fixtures they appear in below.</div>
        </div>

        
        <div class="mb-3">
          <label class="form-label">Fixtures (remaining / no result)</label>
          <div id="playerFixturesContainer" class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Draw</th>
                  <th>Round</th>
                  <th>Tie</th>
                  <th>Home</th>
                  <th>Away</th>
                  <th>Scheduled</th>
                  <th>Venue</th>
                </tr>
              </thead>
              <tbody id="playerFixturesBody">
                <tr><td colspan="8" class="text-muted small">Select a player to list fixtures.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        
        <div class="mb-3">
          <label class="form-label">Replacement (new)</label>
          <div class="input-group">
            <select id="newSelect" name="new_id" class="form-select" style="width:100%;" required>
              <option value="">Select replacement</option>
              <optgroup label="Registered players">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="p_<?php echo e($p->id); ?>" data-team="" data-region="" data-rank="<?php echo e($p->rank ?? ''); ?>"><?php echo e($p->name); ?> <?php echo e($p->surname); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </optgroup>
              <optgroup label="No-profile players">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $noProfiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $np): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="np_<?php echo e($np->id); ?>" data-team="<?php echo e($np->team_id); ?>" data-rank="<?php echo e($np->rank ?? ''); ?>">
                    NP: <?php echo e(trim($np->name.' '.$np->surname)); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($np->team): ?> (<?php echo e($np->team->name); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </optgroup>
            </select>
            <button type="button" id="addNoProfileBtn" class="btn btn-outline-secondary" title="Add no-profile player">Add NP</button>
          </div>
          <div class="form-text">Replacement may be a registered player or a no-profile entry.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Side</label>
          <select name="side" class="form-select">
            <option value="both">Both</option>
            <option value="home">Home only</option>
            <option value="away">Away only</option>
          </select>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Apply Replacement</button>
          <a href="<?php echo e(route('headOffice.show', $event->id ?? (optional($events->first())->id) )); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="addNpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="addNpForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" id="npRank" name="rank" value="">
        <div class="modal-header">
          <h5 class="modal-title">Add No-Profile Player</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Team</label>
            <select id="npTeam" name="team_id" class="form-select">
              <option value="">None</option>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($teams)): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">First name</label>
            <input type="text" id="npName" name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Surname</label>
            <input type="text" id="npSurname" name="surname" class="form-control">
          </div>
          <div class="mb-2">
            <small class="text-muted">Rank will be set to the replaced player's rank by default.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $__env->startSection('page-script'); ?>
<script>
  (function ($) {
    const $replaceForm = $('#replacePlayerForm');
    const $addNpModal = $('#addNpModal');

    // Initialize Select2 on all selects in the main form
    $replaceForm.find('select').each(function () {
      if (this.id === 'npTeam') return;
      $(this).select2({
        dropdownParent: $replaceForm,
        width: '100%',
        allowClear: true,
        placeholder: 'Select...',
        minimumResultsForSearch: 0
      });
    });

    // Initialize Select2 in modal
    $('#npTeam').select2({
      dropdownParent: $('#addNpModal'),
      width: '100%',
      allowClear: true,
      placeholder: 'Select team (optional)',
      minimumResultsForSearch: 0
    });

    // If event changed, reload page with event query
    $('#eventSelect').on('change', function () {
      const v = $(this).val();
      const url = new URL(window.location.href);
      if (v) url.searchParams.set('event', v); else url.searchParams.delete('event');
      window.location.href = url.toString();
    });

    // When a player is selected, fetch fixtures for this player + event
    $('#oldSelect').on('change', function () {
      const player = $(this).val();
      const eventId = $('#eventSelect').val();
      const $body = $('#playerFixturesBody');
      $body.html('<tr><td colspan="8" class="text-muted small">Loading fixtures…</td></tr>');

      // Prefill modal fields: team and rank
      const $selected = $('#oldSelect option:selected');
      const presetTeam = $selected.data('team') || '';
      const presetRank = $selected.data('rank') || '';

      $('#npTeam').val(presetTeam).trigger('change');
      $('#npRank').val(presetRank);

      if (!player || !eventId) {
        $body.html('<tr><td colspan="8" class="text-muted small">Select an event and player to list fixtures.</td></tr>');
        return;
      }

      $.ajax({
        url: '<?php echo e(route("backend.team-fixtures.playerFixtures")); ?>',
        method: 'GET',
        data: { event_id: eventId, player: player },
        success: function (res) {
          if (!res.success) {
            $body.html('<tr><td colspan="8" class="text-danger small">Error loading fixtures.</td></tr>');
            return;
          }
          const fixtures = res.fixtures || [];
          if (!fixtures.length) {
            $body.html('<tr><td colspan="8" class="text-muted small">No remaining fixtures found for this player.</td></tr>');
            return;
          }
          let rows = '';
          fixtures.forEach(f => {
            rows += `<tr>
              <td>${f.id}</td>
              <td>${f.draw || '—'}</td>
              <td>${f.round ?? ''}</td>
              <td>${f.tie ?? ''}</td>
              <td>${f.home}</td>
              <td>${f.away}</td>
              <td>${f.scheduled ?? '—'}</td>
              <td>${f.venue ?? '—'}</td>
            </tr>`;
          });
          $body.html(rows);
        },
        error: function (xhr) {
          console.error(xhr);
          $body.html('<tr><td colspan="8" class="text-danger small">Error fetching fixtures — check console.</td></tr>');
        }
      });
    });

    // Open modal and preset fields when Add NP button clicked
    $('#addNoProfileBtn').on('click', function () {
      const $sel = $('#oldSelect option:selected');
      const presetTeam = $sel.data('team') || '';
      const presetRank = $sel.data('rank') || '';
      if (presetTeam) $('#npTeam').val(presetTeam).trigger('change');
      $('#npRank').val(presetRank);
      const modal = new bootstrap.Modal(document.getElementById('addNpModal'));
      modal.show();
    });

    // Submit create no-profile via AJAX
    $('#addNpForm').on('submit', function (e) {
      e.preventDefault();
      const teamId = $('#npTeam').val() || null;
      const name = $('#npName').val().trim();
      const surname = $('#npSurname').val().trim();
      const rank = $('#npRank').val() || null;

      if (!name) {
        toastr.warning('Name is required', 'Validation');
        return;
      }

      $.ajax({
        url: '<?php echo e(route("backend.team-fixtures.noProfile.create")); ?>',
        method: 'POST',
        data: {
          _token: '<?php echo e(csrf_token()); ?>',
          team_id: teamId,
          name: name,
          surname: surname,
          rank: rank
        },
        success: function (res) {
          if (!res.success) {
            toastr.error('Failed to create no-profile player', 'Error');
            return;
          }
          const value = 'np_' + res.id;
          const label = res.label + (res.team_id ? ' (' + ($('#npTeam option:selected').text()) + ')' : '');

          // Add to replacement select
          const $newOpt = $('<option/>', { value: value, text: label, selected: true })
            .attr('data-team', res.team_id || '')
            .attr('data-rank', res.rank || '');
          $('#newSelect').append($newOpt).trigger('change');

          const $oldOpt = $('<option/>', { value: value, text: label, selected: false })
            .attr('data-team', res.team_id || '')
            .attr('data-rank', res.rank || '');
          $('#oldSelect').append($oldOpt).trigger('change');

          // Close modal and reset
          const modalEl = document.getElementById('addNpModal');
          bootstrap.Modal.getInstance(modalEl).hide();
          $('#npName').val(''); $('#npSurname').val(''); $('#npTeam').val('').trigger('change'); $('#npRank').val('');

          toastr.success('No-profile player created: ' + res.label, 'Success');
        },
        error: function (xhr) {
          console.error(xhr);
          toastr.error('Error creating no-profile player. See console.', 'Error');
        }
      });
    });

    // Form submit — standard POST (not AJAX) so session flash works on redirect
    // The controller will redirect to headOffice.show with success message
  })(jQuery);
</script>
<?php $__env->stopSection(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\replace-player.blade.php ENDPATH**/ ?>