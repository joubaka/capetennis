<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer): ?>
  <?php echo $__env->make('frontend.my-tennis.player-overview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php
    $dateOfBirth = substr((string) $selectedPlayer->dateOfBirth, 0, 10);
    $validBirthDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)
        && checkdate((int) substr($dateOfBirth, 5, 2), (int) substr($dateOfBirth, 8, 2), (int) substr($dateOfBirth, 0, 4));
  ?>
  <section class="card mb-4" aria-labelledby="my-tennis-profile-details">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
          <h2 id="my-tennis-profile-details" class="h5 mb-1">Player details</h2>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile['message'] ?? null): ?>
            <p class="small text-muted mb-0"><?php echo e($profile['message']); ?></p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <a class="btn btn-primary" href="<?php echo e(route('player.profile.edit', ['player' => $selectedPlayer, 'from' => 'my-tennis'])); ?>">Edit profile</a>
      </div>
      <dl class="ct-my-tennis-profile-fields mb-0">
        <div><dt>Name</dt><dd><?php echo e($selectedPlayer->full_name); ?></dd></div>
        <div><dt>Date of birth</dt><dd><?php echo e($validBirthDate ? \Illuminate\Support\Carbon::parse($dateOfBirth)->format('j F Y') : 'Not added'); ?></dd></div>
        <div><dt>Gender</dt><dd><?php echo e([1 => 'Male', 2 => 'Female'][$selectedPlayer->gender] ?? 'Not added'); ?></dd></div>
        <div><dt>Email</dt><dd><?php echo e($selectedPlayer->email ?: 'Not added'); ?></dd></div>
        <div><dt>Mobile number</dt><dd><?php echo e($selectedPlayer->cellNr ?: 'Not added'); ?></dd></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer->email_2 || $selectedPlayer->email_3): ?>
          <div><dt>Notification emails</dt>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer->email_2): ?><dd><?php echo e($selectedPlayer->email_2); ?></dd><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer->email_3): ?><dd><?php echo e($selectedPlayer->email_3); ?></dd><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </dl>
    </div>
  </section>
  <?php echo $__env->make('frontend.my-tennis.teamsheet-connection', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
  <section class="card mb-4">
    <div class="card-body">
      <h2 class="h5">Your player profile</h2>
      <p class="text-muted">Choose a linked player to view their details. Cape Tennis support can help with access to an existing player.</p>
      <a class="btn btn-primary" href="<?php echo e(route('my.tennis', ['tab' => 'manage'])); ?>">Manage player access</a>
    </div>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\profile-details.blade.php ENDPATH**/ ?>