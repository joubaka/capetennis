<?php $__env->startSection('title', 'Check existing profiles'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-4">
  <h1 class="h4">Check existing profiles · <?php echo e($scopeName); ?></h1>
  <p><?php echo e($event->name); ?> · Choose an earlier event you manage, review the identities, then confirm the links. Already-linked players are skipped.</p>
  <a class="btn btn-outline-secondary mb-3" href="<?php echo e(route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $regionId])); ?>">Back to teams</a>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="mb-1"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card mb-4"><div class="card-body">
    <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-3">
      <div><label for="event-search" class="form-label">Find an earlier event</label><input id="event-search" name="q" value="<?php echo e(request('q')); ?>" maxlength="100" class="form-control" type="search"></div>
      <button class="btn btn-outline-primary">Search events</button>
    </form>
    <form method="GET">
      <label for="source-event" class="form-label">Previous event</label>
      <select name="source_event_id" id="source-event" required class="form-select mb-3">
        <option value="">Choose an event</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source && !$events->contains('id', $source->id)): ?><option value="<?php echo e($source->id); ?>" selected><?php echo e($source->name); ?> · <?php echo e($source->start_date); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($option->id); ?>" <?php if($source?->id === $option->id): echo 'selected'; endif; ?>><?php echo e($option->name); ?> · <?php echo e($option->start_date); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <button class="btn btn-primary">Check matching players</button>
    </form>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($events->isEmpty()): ?><p class="text-muted mt-3">No earlier events found that you can manage. Try another search.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="mt-3"><?php echo e($events->links('pagination::bootstrap-5')); ?></div>
  </div></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
    <h2 class="h5">Matches from <?php echo e($source->name); ?></h2>
    <p>Names are matched exactly, ignoring case and spacing. Unique matches without detected problems are selected for your review. Duplicates and possible identity problems require your choice. You can skip any player. Confirm this page before moving to the next page. No profiles are created.</p>
    <p class="d-flex flex-wrap gap-2"><span class="badge bg-label-success"><?php echo e($preview['rows']->whereNotNull('selectedPlayerId')->count()); ?> selected on this page</span><span class="badge bg-label-secondary"><?php echo e($preview['slots']->total()); ?> imported roster players in <?php echo e($isRegion ? 'this region' : 'this team'); ?></span></p>
    <form method="POST" action="<?php echo e($storeUrl); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="source_event_id" value="<?php echo e($source->id); ?>">
      <input type="hidden" name="roster_page" value="<?php echo e($page); ?>">
      <input type="hidden" name="fingerprint" value="<?php echo e($fingerprint); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php ($slot = $row['slot']); ?>
        <fieldset class="card mb-3"><div class="card-body">
          <legend class="h6">Rank <?php echo e($slot->rank); ?> · <?php echo e($slot->name); ?> <?php echo e($slot->surname); ?></legend>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isRegion): ?><p class="small text-muted"><?php echo e($slot->team->name); ?> · <?php echo e($slot->team->category?->category?->name); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$slot->player_profile): ?>
            <p class="small text-muted">Imported date of birth: <?php echo e($slot->date_of_birth?->format('Y-m-d') ?: 'Not provided'); ?> · Imported email: <?php echo e($slot->email ?: 'Not provided'); ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $warning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="text-warning"><?php echo e($warning); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slot->player_profile): ?><p class="mb-0">Already linked · Profile #<?php echo e($slot->player_profile); ?>. Skipped.</p>
          <?php elseif($row['overflow']): ?><p class="mb-0">More than 20 matching profiles. Skip this player and use individual profile search to review.</p>
          <?php elseif($row['candidates']->isEmpty()): ?><p class="mb-0 text-muted">No matching participant in this event. Skipped. Try another event or the individual profile search.</p>
          <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['candidates']->count() > 1): ?><p class="text-warning">Multiple profiles match this name. Choose only after checking their details.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['selectedPlayerId']): ?><p class="text-success">Unique match selected. Check the details before confirming.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <label class="d-flex gap-2 mb-3"><input type="radio" name="links[<?php echo e($slot->id); ?>]" value="" <?php if(!$row['selectedPlayerId']): echo 'checked'; endif; ?>><span>Skip this player</span></label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['candidates']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <label class="d-flex gap-2 border rounded p-3 mb-2" style="overflow-wrap:anywhere">
                <input type="radio" name="links[<?php echo e($slot->id); ?>]" value="<?php echo e($candidate->id); ?>" <?php if($row['selectedPlayerId'] === $candidate->id): echo 'checked'; endif; ?> <?php if($candidate->link_problems): echo 'disabled'; endif; ?>>
                <span><strong><?php echo e($candidate->full_name); ?> · Profile #<?php echo e($candidate->id); ?></strong><br>
                  Born: <?php echo e($candidate->dateOfBirth ?: 'Not recorded'); ?> · Gender: <?php echo e($candidate->gender ?: 'Not recorded'); ?><br>
                  Email: <?php echo e($candidate->email ?: 'Not recorded'); ?><br>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $candidate->source_teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="d-block"><?php echo e($context); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $candidate->source_categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="d-block"><?php echo e($context); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $candidate->identity_warnings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $warning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="d-block text-warning">Review: <?php echo e($warning); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $candidate->link_problems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $problem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="d-block text-danger">Cannot link: <?php echo e($problem); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
              </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div></fieldset>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No imported players on this page.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirm_links" value="1" required><span>I have checked these identities and confirm the selected profile links.</span></label>
      <button class="btn btn-primary">Confirm selected profile links</button>
    </form>
    <div class="mt-3"><?php echo e($preview['slots']->links('pagination::bootstrap-5')); ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-selection\profile-matches.blade.php ENDPATH**/ ?>