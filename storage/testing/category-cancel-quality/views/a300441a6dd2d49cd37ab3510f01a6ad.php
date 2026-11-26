<?php $__env->startSection('title', 'Replace with alternate'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-3" style="max-width:800px">
  <a href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">← Back to draw</a>
  <h1 class="h3 mt-3">Replace with alternate</h1>
  <p><strong><?php echo e($draw->drawName); ?></strong></p>
  <p>The alternate takes over the selected player’s positions before the first point of that player’s first match. A bye does not count as a played match. Matches elsewhere in the draw, including the opponent’s feeder match, do not prevent a replacement. Event entries and payments stay unchanged.</p>
  <p class="small text-muted">Cutoff follows <a href="https://www.itftennis.com/media/16911/2026-itf-world-tennis-tour-juniors-regulations.pdf" target="_blank" rel="noopener">ITF Juniors 2026, regulation 48(b)(iii)(d)</a>. The organiser must separately verify alternate/lucky-loser order and sign-in requirements.</p>
  <p class="alert alert-info">Alternates are managed privately for this draw. An in-app paid entry is not required. Replacing a player does not create an event entry or record a payment.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="GET" class="mb-4">
    <label for="alternate-search" class="form-label">Find an existing player by name</label>
    <div class="d-flex gap-2"><input id="alternate-search" class="form-control" name="q" value="<?php echo e($query); ?>" minlength="2" required maxlength="100"><button class="btn btn-outline-primary">Search</button></div>
    <small class="text-muted">Search returns up to 30 players. Refine the name if needed.</small>
  </form>
  <form method="POST" action="<?php echo e(route('draw.alternate.store', $draw)); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="q" value="<?php echo e($query); ?>">
    <label for="outgoing-registration" class="form-label">Current player to replace</label>
    <select id="outgoing-registration" name="outgoing_registration_id" class="form-select mb-3" required>
      <option value="">Choose current player</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($unavailable = $unavailableIds->contains($registration->id)); ?>
        <option value="<?php echo e($registration->id); ?>" <?php if($unavailable): echo 'disabled'; endif; ?> <?php if(old('outgoing_registration_id') == $registration->id): echo 'selected'; endif; ?>><?php echo e($registration->display_name); ?><?php echo e($unavailable ? ' — first match already started' : ''); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <label for="alternate-player" class="form-label">Incoming alternate</label>
    <select id="alternate-player" name="player_id" class="form-select mb-3" required>
      <option value=""><?php echo e($query ? 'Choose search result' : 'Search for the alternate above'); ?></option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($player->id); ?>" <?php if(old('player_id') == $player->id): echo 'selected'; endif; ?>><?php echo e($player->full_name); ?><?php echo e($player->dateOfBirth ? ' · '.\Carbon\Carbon::parse($player->dateOfBirth)->format('Y') : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($query && $players->isEmpty()): ?><p role="status">No matching players found.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <input type="hidden" name="show_alternate" value="0">
    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="show_alternate" value="1" <?php if(old('show_alternate', true)): echo 'checked'; endif; ?>><span class="form-check-label">Show “(alternate)” next to the incoming player’s name</span></label>
    <button class="btn btn-primary" <?php if($players->isEmpty()): echo 'disabled'; endif; ?>>Replace player with alternate</button>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\alternate.blade.php ENDPATH**/ ?>