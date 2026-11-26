<?php $__env->startSection('title', 'Import selected team - '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceActive' => 'teams', 'eventWorkspaceSubtitle' => 'Import selected teams and reserves from a finalized event'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card mb-3"><div class="card-body">
  <h3>Import selected team + reserves</h3>
  <p>Choose a source event, then review its approved selected team or select source regions. Players and reserves keep their saved order.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewErrors): ?><div class="alert alert-danger" role="alert"><strong>Unable to review these teams.</strong><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $previewErrors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($message); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="GET" action="<?php echo e(route('backend.selection-import.index', $event)); ?>" id="source-event-form" class="row g-3 align-items-end">
    <input type="hidden" name="choose_regions" value="1">
    <div class="col-12 col-md-8"><label class="form-label" for="source-event">Source event</label><select id="source-event" name="source_event_id" class="form-select" required><option value="">Choose a source event</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sourceEvent && !$sources->contains('id', $sourceEvent->id)): ?><option value="<?php echo e($sourceEvent->id); ?>" selected><?php echo e($sourceEvent->name); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($source->id); ?>" <?php if((int)request('source_event_id') === (int)$source->id): echo 'selected'; endif; ?>><?php echo e($source->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div class="col-12 col-md-4"><button class="btn btn-primary" style="min-height:44px">Show source options</button></div>
  </form>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sources->isEmpty()): ?><p class="mt-3 mb-0">No source team events are available for you to manage.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="mt-3"><?php echo e($sources->withQueryString()->links('pagination::bootstrap-5')); ?></div>
</div></div>
<p id="source-choices-status" role="status" class="mt-3" hidden></p>
<div id="source-choices" aria-live="polite">
<?php echo $__env->make('backend.adminPage.partials.selection-import-source-choices', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
<form id="selection-import-review" method="POST" action="<?php echo e(route('backend.selection-import.store', $event)); ?>" class="card mb-3"><div class="card-body">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="finalization_id" value="<?php echo e($finalization->id); ?>">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importMode === 'approved_team'): ?>
    <input type="hidden" name="import_mode" value="approved_team">
    <input type="hidden" name="source_event_id" value="<?php echo e($sourceEvent->id); ?>">
    <p class="alert alert-info">Importing the approved selected team from <?php echo e($sourceEvent->name); ?>, including all reserves.</p>
  <?php elseif($selectedRegionIds): ?>
    <input type="hidden" name="import_mode" value="regions">
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $selectedRegionIds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $selectedRegionId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="source_region_ids[]" value="<?php echo e($selectedRegionId); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <h4><?php echo e($preview['source']->name); ?></h4><p>Finalized <?php echo e($finalization->finalized_at->format('d M Y H:i')); ?>. Only selected players and reserves are included.</p>
  <label for="destination-region" class="form-label">Destination region</label><select id="destination-region" class="form-select mb-3" name="region_id" required><option value="">Choose the region these teams represent</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($region->id); ?>" <?php if((int)old('region_id') === (int)$region->id): echo 'selected'; endif; ?>><?php echo e($region->region_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regions->isEmpty()): ?><div class="alert alert-warning">Add a region to this event before importing teams.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <section class="border rounded p-3 mb-3" aria-label="<?php echo e($group['label']); ?>"><h5><?php echo e($group['label']); ?> · <?php echo e($group['main_count']); ?> team players + <?php echo e($group['reserve_count']); ?> reserves</h5>
    <p class="small">Source regions: <?php echo e($sourceRegions->whereIn('id', $group['source_region_ids'])->pluck('region_name')->implode(', ')); ?></p>
    <label class="form-label" for="destination-<?php echo e($key); ?>">Destination team</label><select id="destination-<?php echo e($key); ?>" name="mapping[<?php echo e($key); ?>]" class="form-select mb-3" data-destination-team><option value="0">Create a team in the chosen region</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['teams']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($team->id); ?>" data-region-id="<?php echo e($team->region_id); ?>" <?php if((int)old('mapping.'.$key) === (int)$team->id): echo 'selected'; endif; ?>><?php echo e($team->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
    <ol class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['players']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($player['name']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['status'] === 'reserve'): ?><span class="badge bg-label-warning">Reserve</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['profile_link_review'] ?? false): ?><span class="badge bg-label-info">Review profile link after import</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ol>
  </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p>Existing teams must be empty and private. New rosters start unpaid and private. Main players and reserves keep their finalized order.</p>
  <label class="d-block mb-3"><input type="checkbox" name="confirm_import" value="1" required> I have reviewed these teams and their destination region.</label>
  <p id="regions-changed" class="alert alert-warning" role="status" hidden>Regions changed. Click Review selected regions again before importing.</p>
  <button class="btn btn-primary" id="import-selected-regions" data-import-mode="<?php echo e($importMode); ?>" style="min-height:44px" <?php if($regions->isEmpty()): echo 'disabled'; endif; ?>>Import teams and reserves</button>
</div></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $imports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $import): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<section class="card mb-3"><div class="card-body"><h4>Imported teams · <?php echo e($import->region->region_name); ?></h4><p><?php echo e($import->imported_at->format('d M Y H:i')); ?> · Source event #<?php echo e($import->finalization->event_id); ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $import->groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><h5><?php echo e($group['team_name']); ?></h5><ol><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['players']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($player['name']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['status'] === 'reserve'): ?><span class="badge bg-label-warning">Reserve</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ol><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<a class="btn btn-outline-primary" href="<?php echo e(route('admin.events.teams', $event)); ?>">Manage event rosters</a>
</div></section>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo e($imports->withQueryString()->links('pagination::bootstrap-5')); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const choices = document.getElementById('source-choices');
  const status = document.getElementById('source-choices-status');
  const sourceForm = document.getElementById('source-event-form');
  const sourceSelect = document.getElementById('source-event');
  const applyMode = () => {
    const mode = choices.querySelector('input[name="source-choice-mode"]:checked')?.value;
    choices.querySelectorAll('[data-source-mode]').forEach(panel => panel.hidden = panel.dataset.sourceMode !== mode);
  };
  applyMode();
  choices.addEventListener('change', event => {
    if (event.target.matches('input[name="source-choice-mode"]')) {
      applyMode();
      document.getElementById('selection-import-review')?.remove();
    }
    if (event.target.matches('input[name="source_region_ids[]"][type="checkbox"]')) {
      const importButton = document.getElementById('import-selected-regions');
      if (importButton?.dataset.importMode === 'approved_team') return;
      if (importButton) importButton.disabled = true;
      const notice = document.getElementById('regions-changed');
      if (notice) notice.hidden = false;
    }
  });
  let sourceRequest = 0;
  let sourceAbort;
  const loadSource = async () => {
    const request = ++sourceRequest;
    sourceAbort?.abort();
    sourceAbort = new AbortController();
    choices.replaceChildren();
    document.getElementById('selection-import-review')?.remove();
    if (!sourceSelect.value) {
      choices.removeAttribute('aria-busy');
      status.hidden = false;
      status.textContent = 'Choose a source event to see its approved team and regions.';
      return;
    }
    status.hidden = false;
    status.textContent = 'Loading source options…';
    choices.setAttribute('aria-busy', 'true');
    const url = new URL(sourceForm.action);
    url.searchParams.set('source_event_id', sourceSelect.value);
    url.searchParams.set('source_choices', '1');
    try {
      const response = await fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: sourceAbort.signal, credentials: 'same-origin', cache: 'no-store' });
      if (!response.ok || response.redirected || response.headers.get('X-Selection-Source-Choices') !== '1') throw new Error('Source options unavailable');
      const html = await response.text();
      if (request !== sourceRequest) return;
      choices.innerHTML = html;
      applyMode();
      status.textContent = 'Source options loaded. Choose the approved selected team or source regions.';
    } catch (error) {
      if (request !== sourceRequest || error.name === 'AbortError') return;
      status.textContent = 'Unable to load source options. Try again using Show source options.';
    } finally {
      if (request === sourceRequest) choices.removeAttribute('aria-busy');
    }
  };
  sourceSelect.addEventListener('change', loadSource);
  sourceForm.addEventListener('submit', event => {
    event.preventDefault();
    loadSource();
  });
  const region = document.getElementById('destination-region');
  if (!region) return;
  const filterTeams = () => document.querySelectorAll('[data-destination-team]').forEach(select => {
    Array.from(select.options).forEach(option => {
      if (!option.dataset.regionId) return;
      option.disabled = option.dataset.regionId !== region.value;
      option.hidden = option.disabled;
      if (option.selected && option.disabled) select.value = '0';
    });
  });
  region.addEventListener('change', filterTeams);
  filterTeams();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\selection-import.blade.php ENDPATH**/ ?>