<?php $__env->startSection('title', 'Print options'); ?>
<?php $__env->startSection('page-style'); ?>
<style>
  .print-options .btn, .print-options .form-select, .print-options .form-control, .print-options .print-choice { min-height:44px; }
  .print-options .print-choice { display:flex; align-items:center; gap:.75rem; padding:.5rem; }
  .print-options .print-choice input { flex-shrink:0; }
  .print-options .btn { white-space:normal; }
  .print-options { font-size:14px; }
  .print-group-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr)); gap:1rem; }
  .print-group { border:1px solid #d9e2eb; border-radius:10px; background:#fff; min-width:0; }
  .print-group summary { min-height:56px; padding:14px 16px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; font-weight:600; list-style:none; }
  .print-group summary::-webkit-details-marker { display:none; }
  .print-group summary::after { content:'⌄'; font-size:20px; }
  .print-group[open] summary::after { content:'⌃'; }
  .print-group summary:focus-visible { outline:2px solid #172e45; outline-offset:2px; }
  .print-group-body { padding:0 16px 16px; }
  .print-draw-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:14px 0; border-top:1px solid #e4eaf0; }
  .print-draw-name { flex:1 1 160px; overflow-wrap:anywhere; }
  .print-draw-actions { display:flex; gap:8px; flex-wrap:wrap; }
  .print-venue-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); grid-auto-rows:1fr; gap:12px; }
  .print-venue-tool { display:flex; min-height:96px; min-width:0; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px solid #e4eaf0; border-radius:8px; }
  .print-venue-tool span { flex:1; min-width:0; overflow-wrap:anywhere; }
  .print-venue-tool .btn { flex:0 0 76px; width:76px; min-height:44px; }
  @media(max-width:991px) { .print-venue-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media(max-width:575px) { .print-venue-grid { grid-template-columns:minmax(0,1fr); } }
  @media(max-width:575px) { .print-draw-actions { width:100%; } .print-draw-actions .btn { flex:1; } }
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceActive' => 'draws', 'eventWorkspaceIcon' => 'ti-printer', 'eventWorkspaceSubtitle' => 'Draws, age groups and venue printing'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="print-options">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h2>Print options</h2><p class="mb-0 text-muted">Choose what to print for <?php echo e($event->name); ?>. Preview a sheet or download a PDF.</p></div>
    <a class="btn btn-outline-primary" href="<?php echo e(route('headOffice.show', $event)); ?>">Back to draws</a>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isEmpty()): ?>
    <div class="alert alert-info">Create a draw to make draw printing available.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php ($hasTeamDraws = $event->draws->contains(fn ($draw) => $draw->isTeamDraw())); ?>
  <?php ($individualDraws = $event->draws->reject(fn ($draw) => $draw->isTeamDraw())); ?>
  <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Print sections">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasTeamDraws): ?><a class="btn btn-outline-primary" href="#venue-print-tools">Venues</a><a class="btn btn-outline-primary" href="#team-print-tools">Team draws by age</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($individualDraws->isNotEmpty()): ?><a class="btn btn-outline-primary" href="#individual-print-options">Individual draw packs</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </nav>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasTeamDraws): ?>
  <section class="card mb-4" id="venue-print-tools"><div class="card-body">
    <h3 class="h5">Venue sheets</h3><p class="text-muted">Scheduled team matches and lineups, grouped by venue. Open a sheet, then choose Print / Save PDF.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $venueGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ageLabel => $ageVenues): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-2">
      <h4 class="h6 mb-0"><?php echo e($ageLabel); ?></h4>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ageLabel !== 'Other venues'): ?>
      <a class="btn btn-outline-primary" href="<?php echo e(route('headoffice.venuePrintPack', ['event' => $event, 'age' => (int) substr($ageLabel, 6)])); ?>" target="_blank" rel="noopener" aria-label="Print all venues for <?php echo e($ageLabel); ?>">Print all venues</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ageVenues->isEmpty()): ?><p class="small text-muted">These matches use shared venues listed under a younger age above.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="print-venue-grid">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ageVenues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="print-venue-tool"><span><?php echo e($venue->name); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueAges->get($venue->id)->isNotEmpty()): ?><small class="d-block text-muted"><?php echo e($venueAges->get($venue->id)->map(fn ($age) => 'U'.$age)->implode(' · ')); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span><a class="btn btn-primary" href="<?php echo e(route('headoffice.venue.fixtures', ['event' => $event, 'venue' => $venue])); ?>" target="_blank" rel="noopener" aria-label="Print venue <?php echo e($venue->name); ?>">Print</a></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="mb-0">No venue matches have been scheduled yet.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($individualDraws->isNotEmpty()): ?>
  <form id="individual-print-options" action="<?php echo e(route('headoffice.drawPack', $event)); ?>" method="get" target="_blank" class="card mb-4">
    <div class="card-body">
      <h3 class="h5">Individual draws</h3>
      <p class="text-muted">Select all draws, an age group, or individual draws. Bracket PDFs require only Flexible Monrad draws.</p>
      <label class="print-choice"><input type="checkbox" data-select-all checked> Select all individual draws</label>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($choices = $draws->reject(fn ($draw) => $draw->isTeamDraw())); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($choices->isNotEmpty()): ?>
        <details class="print-group mb-3" data-print-group>
          <summary><?php echo e($label); ?> <span class="badge bg-label-primary"><?php echo e($choices->count()); ?> draws</span></summary>
          <div class="print-group-body">
          <label class="print-choice"><input type="checkbox" data-select-group checked> Select this age group</label>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $choices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="print-choice"><input type="checkbox" name="draw_ids[]" value="<?php echo e($draw->id); ?>" checked> <?php echo e($draw->drawName); ?></label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </details>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label for="print-type" class="form-label">Print layout</label><select id="print-type" name="print_type" class="form-select">
          <option value="pack">Complete draw pack: players, fixtures and schedule</option>
          <option value="venue">Venue order of play / match schedule</option>
          <option value="bracket">Flexible Monrad brackets (PDF)</option>
          <option value="matrix">Round-robin matrices (PDF)</option>
          <option value="fixtures">Individual fixtures only (PDF)</option>
          <option value="combined">Fixtures and matrices (PDF)</option>
        </select></div>
        <div class="col-md-6"><label for="schedule-source" class="form-label">Venue schedule version</label><select id="schedule-source" name="schedule_source" class="form-select"><option value="published">Published schedule</option><option value="working">Working schedule</option></select></div>
        <div class="col-md-6"><label for="print-date" class="form-label">Venue schedule day</label><input class="form-control" id="print-date" name="date" type="date"><small class="text-muted">Leave blank for every day.</small></div>
        <div class="col-md-6"><label for="print-venue" class="form-label">Venue schedule venue</label><select class="form-select" id="print-venue" name="venue_id"><option value="">All venues</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>"><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
      </div>
      <p class="small text-muted">Day, venue and schedule version apply to venue order of play. Printing does not publish draws or match times.</p>
      <input type="hidden" name="include_standings" value="0">
      <label class="print-choice mb-3"><input type="checkbox" name="include_standings" value="1"> Include standings</label>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit" name="download" value="0">Preview / print</button><button class="btn btn-outline-primary" type="submit" name="download" value="1">Download PDF</button></div>
      <p class="small text-muted mt-2 mb-0" id="print-layout-help">Use your browser’s Print or Save as PDF from the preview.</p>
    </div>
  </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasTeamDraws): ?>
  <section id="team-print-tools" class="mb-4">
    <h3 class="h5">Team draws by age group</h3><p class="text-muted">Expand an age group to print a draw’s team fixtures, players and scores, or download its PDF.</p>
    <div class="print-group-grid">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($teamDraws = $draws->filter(fn ($draw) => $draw->isTeamDraw() && auth()->user()->can('fixture.view', $draw))); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamDraws->isNotEmpty()): ?>
      <details class="print-group">
        <summary><?php echo e($label); ?> <span class="badge bg-label-primary"><?php echo e($teamDraws->count()); ?> draws</span></summary>
        <div class="print-group-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="print-draw-row">
            <strong class="print-draw-name"><?php echo e($draw->drawName ?: 'Draw #'.$draw->id); ?></strong>
            <div class="print-draw-actions">
              <a class="btn btn-primary" href="<?php echo e(route('fixture.create.pdf', ['fixtures' => $draw->id, 'preview' => 1])); ?>" target="_blank" rel="noopener" aria-label="Print <?php echo e($draw->drawName ?: 'Draw #'.$draw->id); ?>">Print</a>
              <a class="btn btn-outline-primary" href="<?php echo e(route('fixture.create.pdf', ['fixtures' => $draw->id])); ?>" aria-label="Download PDF for <?php echo e($draw->drawName ?: 'Draw #'.$draw->id); ?>">Download PDF</a>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script>
document.getElementById('individual-print-options')?.addEventListener('change', function (event) {
  if (event.target.matches('[data-select-all]')) this.querySelectorAll('input[type="checkbox"]:not([name="include_standings"])').forEach(input => input.checked = event.target.checked);
  if (event.target.matches('[data-select-group]')) event.target.closest('[data-print-group]').querySelectorAll('[name="draw_ids[]"]').forEach(input => input.checked = event.target.checked);
  const sync = (toggle, inputs) => {
    const selected = Array.from(inputs).filter(input => input.checked).length;
    toggle.checked = selected === inputs.length;
    toggle.indeterminate = selected > 0 && selected < inputs.length;
  };
  this.querySelectorAll('[data-print-group]').forEach(group => sync(group.querySelector('[data-select-group]'), group.querySelectorAll('[name="draw_ids[]"]')));
  sync(this.querySelector('[data-select-all]'), this.querySelectorAll('[name="draw_ids[]"]'));
  if (event.target.id === 'print-type') {
    const pdfOnly = ['bracket', 'matrix', 'combined', 'fixtures'].includes(event.target.value);
    this.querySelector('[name="download"][value="0"]').disabled = pdfOnly;
    document.getElementById('print-layout-help').textContent = pdfOnly ? 'This layout is available as a PDF download.' : 'Use your browser’s Print or Save as PDF from the preview.';
  }
});
document.getElementById('individual-print-options')?.addEventListener('submit', function (event) {
  if (!this.querySelector('[name="draw_ids[]"]:checked')) { event.preventDefault(); alert('Select at least one draw.'); return; }
  this.action = ['matrix', 'combined', 'fixtures'].includes(document.getElementById('print-type').value) ? <?php echo json_encode(route('headoffice.printDrawsPdf', $event), 512) ?> : <?php echo json_encode(route('headoffice.drawPack', $event), 512) ?>;
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\print-options.blade.php ENDPATH**/ ?>