<div class="col-12">
  <div class="card-header mb-0">
    <h5 class="m-0 me-2 m-4"><?php echo e($team->name); ?> (ID: <?php echo e($team->id); ?>)</h5>
  </div>

  <div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int)$team->published !== 1): ?>
      <div class="mt-4 alert alert-danger" role="alert">
        Team not yet published!
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php
      $pivotMembers    = $team->team_players_no_profile; // pivot entries
      $profiledPlayers = $team->players->values();       // ordered real players
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pivotMembers->count() > 0 || $profiledPlayers->count() > 0): ?>
      <div class="table-responsive mt-3">
        <table class="table">
          <thead>
            <tr>
              <th>Nr</th>
              <th>Profile Name</th>
              <th>No-Profile Name</th>
              <th>Email</th>
              <th>Cell</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = max($pivotMembers->count(), $profiledPlayers->count()) ? range(0, max($pivotMembers->count(), $profiledPlayers->count()) - 1) : []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $pivot   = $pivotMembers[$i] ?? null;
                $profile = $pivot?->profile;

                // If no profile relation, but we have a real player at that slot
                if (!$profile && isset($profiledPlayers[$i])) {
                    $profile = $profiledPlayers[$i];
                }
              ?>
              <tr>
                <td><?php echo e($i + 1); ?></td>

                
                <td>
                  <?php echo e($profile ? $profile->name . ' ' . $profile->surname : '—'); ?>

                </td>

                
                <td class="no-profile-name" data-id="<?php echo e($pivot->id); ?>">
                  <?php echo e($pivot?->name ?? ''); ?> <?php echo e($pivot?->surname ?? ''); ?>

                </td>

                
                <td>
                  <?php echo e($profile->email ?? $pivot?->email ?? ''); ?>

                </td>

                
                <td>
                  <?php echo e($profile->cellNr ?? $pivot?->cell_nr ?? ''); ?>

                </td>
                

<td>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pivot): ?>
   

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$pivot->profile): ?>
      <button 
        type="button"
        class="btn btn-sm btn-outline-warning ms-2 edit-noprofile-btn"
        data-id="<?php echo e($pivot->id); ?>"
        data-name="<?php echo e($pivot->name); ?>"
        data-surname="<?php echo e($pivot->surname); ?>"
      >
        Change Dummy Sheet
      </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>



              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-light m-0 mt-3">
        No players in this team yet.
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>


<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\partials\team-no-profile.blade.php ENDPATH**/ ?>