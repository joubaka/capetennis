<?php $__env->startSection('title', $plan->event->name.' · Camp planner'); ?>
<?php $__env->startSection('page-style'); ?>
<style>
.camp-planner {max-width:1200px}.camp-planner .camp-meta{display:flex;flex-wrap:wrap;gap:.6rem 1.5rem}.camp-planner .camp-team-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.6rem}.camp-planner label.camp-choice{display:flex;gap:.75rem;align-items:flex-start;padding:.8rem;border:1px solid #d9e2ec;border-radius:.4rem;cursor:pointer}.camp-planner input[type=checkbox]{width:1.2rem;height:1.2rem;flex-shrink:0;margin-top:.15rem}.camp-planner .camp-player{border-top:1px solid #e3e8ee;padding:.85rem 0}.camp-planner .camp-source{font-size:.85rem;color:#526477;overflow-wrap:anywhere}.camp-planner .camp-saved{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}.camp-planner .camp-saved>div{min-width:0}.camp-planner .btn{white-space:normal;min-height:44px}
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div data-camp-workflow class="container-xxl flex-grow-1 container-p-y camp-planner">
  <a href="<?php echo e(route('backend.practice-camps.index')); ?>">Practice camp planning</a>
  <h1 class="h3 mt-3"><?php echo e($plan->event->name); ?></h1>
  <a class="btn btn-outline-primary mb-3" href="<?php echo e(route('backend.camps.show', $plan)); ?>">Camp settings, coach nominations and invitations</a>
  <span class="badge bg-label-secondary mb-3">Private invitation draft</span>
  <div class="card mb-4"><div class="card-body">
    <div class="camp-meta">
      <span><strong><?php echo e($plan->event->start_date->format('j M Y')); ?> – <?php echo e($plan->event->end_date->format('j M Y')); ?></strong> · provisional</span>
      <span><?php echo e($plan->event->venue_notes ?: 'Venue not set'); ?></span><span><?php echo e($plan->entry_fee===null?'Fee not set':'Default fee R'.number_format($plan->entry_fee,2)); ?></span><span><?php echo e($plan->capacity===null?'Capacity not set':'Capacity '.$plan->capacity); ?></span><span><?php echo e($plan->event->email ?: 'Contact not set'); ?></span>
    </div>
    <p class="mt-3 mb-0">Choose source regions or teams, review the players, then add them to this camp’s draft. Team selection and camp entry remain separate.</p>
    <p class="small text-muted mb-0">No invitations are sent here. Coach nominations will require organiser approval before invitations.</p>
  </div></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><div class="alert alert-success" role="status"><?php echo e(session('status')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">1. Choose source teams</h2>
    <form method="GET" action="<?php echo e(route('backend.practice-camps.show', $plan)); ?>" class="mb-3">
      <label class="form-label" for="source-q">Find a source event</label>
      <div class="d-flex gap-2"><input class="form-control" id="source-q" name="source_q" value="<?php echo e(request('source_q')); ?>" placeholder="Event name, for example Platteland"><button class="btn btn-outline-primary">Search</button></div>
      <p class="small text-muted">Up to 50 matching events you manage are shown.</p>
    </form>
    <form method="GET" action="<?php echo e(route('backend.practice-camps.show', $plan)); ?>" class="mb-3">
      <label class="form-label" for="source-event">Source event</label>
      <select class="form-select mb-2" id="source-event" name="source_event_id" required>
        <option value="">Choose event</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source && !$events->contains('id', $source->id)): ?><option value="<?php echo e($source->id); ?>" selected><?php echo e($source->name); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($event->id); ?>" <?php if($source?->id === $event->id): echo 'selected'; endif; ?>><?php echo e($event->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <button class="btn btn-outline-primary">Show regions</button>
    </form>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source): ?>
      <form method="GET" action="<?php echo e(route('backend.practice-camps.show', $plan)); ?>" class="mb-3">
        <input type="hidden" name="source_event_id" value="<?php echo e($source->id); ?>">
        <label class="form-label" for="source-region">Region in <?php echo e($source->name); ?></label>
        <select class="form-select mb-2" id="source-region" name="region_id" required><option value="">Choose region</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($region->id); ?>" <?php if((int)request('region_id') === $region->id): echo 'selected'; endif; ?>><?php echo e($region->region_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
        <button class="btn btn-outline-primary">Show teams</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source && request('region_id')): ?>
      <form method="GET" action="<?php echo e(route('backend.practice-camps.show', $plan)); ?>">
        <input type="hidden" name="source_event_id" value="<?php echo e($source->id); ?>"><input type="hidden" name="region_id" value="<?php echo e(request('region_id')); ?>"><input type="hidden" name="preview" value="1">
        <p>Select the whole region or individual teams. Player choices are reviewed next.</p>
        <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-camp-toggle="teams">Select all teams</button>
        <div class="camp-team-list mb-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><label class="camp-choice"><input type="checkbox" name="team_ids[]" value="<?php echo e($team->id); ?>" <?php if(in_array($team->id, array_map('intval', request('team_ids', [])))): echo 'checked'; endif; ?>><span><?php echo e($team->name); ?><small class="d-block"><?php echo e($team->noProfile ? $team->team_players_no_profile->count() : $team->team_players->where('player_id', '>', 0)->count()); ?> roster places</small></span></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No event-scoped teams found in this region.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teams->isNotEmpty()): ?><button class="btn btn-primary">Review players from selected teams</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">2. Review <?php echo e(count($preview['rows'])); ?> candidates</h2>
    <p class="alert alert-info"><?php echo e($preview['notice']); ?></p>
    <p>These are roster and historical selection records, not an automatic eligibility decision. Check age, original selection and any placeholder identities before choosing players.</p>
    <form method="POST" action="<?php echo e(route('backend.practice-camps.add', $plan)); ?>"><?php echo csrf_field(); ?>
      <input type="hidden" name="revision" value="<?php echo e($plan->revision); ?>"><input type="hidden" name="fingerprint" value="<?php echo e($preview['fingerprint']); ?>"><input type="hidden" name="source_event_id" value="<?php echo e($source->id); ?>"><input type="hidden" name="region_id" value="<?php echo e(request('region_id')); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = request('team_ids', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $teamId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="team_ids[]" value="<?php echo e($teamId); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($preview['rows'])): ?><button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-camp-toggle="players">Select all current roster players</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <label class="camp-choice mb-2"><input type="checkbox" name="selected[]" value="<?php echo e($row['key']); ?>" data-camp-roster="<?php echo e(collect($row['sources'])->contains('kind', 'roster') ? '1' : '0'); ?>"><span><strong><?php echo e($row['name'] ?: 'Unnamed imported player'); ?></strong><small class="d-block"><?php echo e($row['identity']); ?></small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['sources']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $origin): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="d-block camp-source"><?php echo e($origin['team']); ?> · <?php echo e($origin['status']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin['original_rank'] ?? null): ?> · original ranking <?php echo e($origin['original_rank']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin['manual_selection'] ?? false): ?> · manually selected; ranking unavailable <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span></label>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No candidates found.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($preview['rows'])): ?><button class="btn btn-primary mt-2">Add selected players to private draft</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
  </div></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card"><div class="card-body">
    <h2 class="h5">Camp invitation draft · <?php echo e(count($plan->candidates)); ?> players</h2>
    <p>Existing player profiles are combined across sources. Imported roster identities remain separate until matched. Saved candidates are not yet approved nominees or invitations.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $plan->candidates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="camp-player camp-saved"><div><strong><?php echo e($row['name'] ?: 'Unnamed imported player'); ?></strong><small class="d-block"><?php echo e($row['identity']); ?></small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['sources']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $origin): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="camp-source"><?php echo e($origin['event']); ?> · <?php echo e($origin['region']); ?> · <?php echo e($origin['team']); ?> · <?php echo e($origin['status']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <form method="POST" action="<?php echo e(route('backend.practice-camps.remove', $plan)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><input type="hidden" name="revision" value="<?php echo e($plan->revision); ?>"><input type="hidden" name="key" value="<?php echo e($row['key']); ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Remove <?php echo e($row['name']); ?> from draft">Remove</button></form>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="mb-0">No players selected yet. Start with a source region above.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.practice-camps.feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script>
document.querySelectorAll('[data-camp-toggle]').forEach(button => button.addEventListener('click', () => {
  const selector = button.dataset.campToggle === 'teams' ? 'input[name="team_ids[]"]' : 'input[name="selected[]"][data-camp-roster="1"]';
  const boxes = Array.from(button.form.querySelectorAll(selector));
  const checked = boxes.some(box => !box.checked);
  boxes.forEach(box => { box.checked = checked; });
}));
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\practice-camps\show.blade.php ENDPATH**/ ?>