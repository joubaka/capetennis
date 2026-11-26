<?php ($pageConfigs = ['myLayout' => 'vertical']); ?>

<?php $__env->startSection('title', $title); ?>
<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<link rel="stylesheet" href="<?php echo e(asset('assets/css/draw-workspace.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/flexible-monrad.css')); ?>?v=<?php echo e(filemtime(public_path('css/flexible-monrad.css'))); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/flexible-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('css/flexible-workspace.css'))); ?>">
<?php echo $__env->make('backend.draw.partials.workspace-header', ['workspaceSurface' => 'flexible'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div id="flexible-draw-workspace" data-has-generated-draw="<?php echo e($config['state']['generated'] ? '1' : '0'); ?>">
  <nav class="rr-workspace-nav mb-3" aria-label="Draw workspace" hidden>
    <button type="button" data-flexible-tab="groups">Players &amp; Positions</button>
    <button type="button" data-flexible-tab="matrix">Draw &amp; Results</button>
    <button type="button" data-flexible-tab="schedule">Schedule</button>
    <button type="button" data-flexible-tab="settings">Settings</button>
  </nav>
  <section data-flexible-panel="editor" class="fm-surface">
    <h2 class="fm-workspace-print-title"><?php echo e($title); ?> · <?php echo e($draw->event->name); ?></h2>
    <section id="fm-generated-roster" class="p-3" hidden></section>
    <?php echo $__env->make('backend.draw.partials.flexible-monrad-editor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </section>
  <section data-flexible-panel="schedule" class="card" hidden>
    <div class="card-body">
      <h2 class="fm-workspace-print-title"><?php echo e($title); ?> · <?php echo e($draw->event->name); ?></h2>
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><h2 class="h5">Schedule &amp; Venues</h2><p class="text-muted mb-0">Timetable publication is separate from draw publication.</p></div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]] + (request('schedule_date') ? ['date' => request('schedule_date')] : []))); ?>">Schedule this draw</a>
          <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => request('schedule_date', 'all')])); ?>">Review / edit saved times</a>
          <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id])); ?>">Schedule multiple draws</a>
        </div>
      </div>
      <div id="fm-timetable"></div>
    </div>
  </section>
  <section data-flexible-panel="settings" class="card" hidden>
    <div class="card-body">
      <h2 class="h5">Settings</h2>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $draw)): ?>
        <form method="POST" action="<?php echo e(route('draws.update', $draw)); ?>" class="mb-3">
          <?php echo csrf_field(); ?>
          <label for="workspace-draw-name" class="form-label">Draw name</label>
          <div class="d-flex flex-wrap gap-2"><input id="workspace-draw-name" class="form-control" style="max-width:420px" name="name" value="<?php echo e($draw->drawName); ?>" required maxlength="255"><button class="btn btn-primary" type="submit">Save name</button></div>
        </form>
      <?php endif; ?>
      <p><?php echo e($title); ?></p>
      <p>Best of <?php echo e($config['state']['best_of']); ?> set(s). Starting positions and bracket size are managed in Players &amp; Positions.</p>
      <a class="btn btn-outline-primary" href="<?php echo e(route('draw.setup.show', $draw)); ?>">Review draw format</a>
      <p class="text-muted mt-3">Review the format to see the exact reset impact. Changing format requires an unlocked, unpublished draw and unpublished times; recorded results are protected. A confirmed reset can remove existing unplayed matches and times.</p>
      <a class="btn btn-primary mb-3" href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#groups">Continue to players</a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($config['workflow'] ?? null) === 'feed_in_monrad' && $config['state']['generated'] && $config['state']['challenge_stage_options']['base']): ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('enableRollingChallenges', $draw)): ?>
          <fieldset class="border rounded p-3 mb-3" id="fm-rolling-settings" aria-describedby="fm-rolling-help">
            <legend class="h6 float-none w-auto">Challenge matches</legend>
            <p id="fm-rolling-help">Choose the matches for this draw. Every selected match is skipped if the players have already met in this draw.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['base' => 'Base-position pairs', 'follow_on' => 'Follow-on matches for the top five']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($config['state']['challenge_stage_options'][$group]): ?>
                <h3 class="h6 mt-3"><?php echo e($heading); ?></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $config['state']['challenge_stage_options'][$group]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <label class="d-flex gap-2 align-items-center py-2">
                    <input type="checkbox" class="form-check-input mt-0" data-rolling-stage="<?php echo e($stage); ?>" <?php if(in_array($stage, $config['state']['rolling_challenge_stages'], true)): echo 'checked'; endif; ?>>
                    <span><?php echo e($label); ?></span>
                  </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p class="text-muted small">Current 3 vs 4 uses the positions after whichever earlier matches you select. “2 vs new 3” requires Current 3 vs 4 and only takes place if that match changes position 3. No other match is enabled automatically.</p>
            <p class="text-muted small">New matches need scheduling separately. A change to private saved times requires your confirmation. Started matches, results and published times are protected.</p>
            <button type="button" class="btn btn-outline-primary" id="fm-rolling-challenges">Save challenge choices</button>
            <p role="status" id="fm-rolling-feedback" class="small mt-2 mb-0"></p>
          </fieldset>
        <?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('editNotes', $draw)): ?>
        <form data-workspace-notes action="<?php echo e(route('backend.draw.update-notes', $draw)); ?>" method="POST">
          <?php echo csrf_field(); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = array_replace(['general' => ''], $draw->settings?->notes ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label class="form-label" for="workspace-note-<?php echo e($loop->index); ?>"><?php echo e(ucfirst(str_replace('_', ' ', $section))); ?> rules &amp; notes</label>
            <textarea class="form-control mb-3" rows="4" maxlength="5000" id="workspace-note-<?php echo e($loop->index); ?>" name="notes[<?php echo e($section); ?>]"><?php echo e($note); ?></textarea>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <button type="submit" class="btn btn-primary">Save rules &amp; notes</button>
        </form>
      <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($draw->settings?->notes ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($note): ?><h3 class="h6"><?php echo e(ucfirst(str_replace('_', ' ', $section))); ?></h3><p style="white-space:pre-wrap"><?php echo e($note); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
  <section data-flexible-panel="print" class="card" hidden>
    <div class="card-body">
      <h2 class="h5">Print draw</h2>
      <p>Print the same bracket and results shown in this workspace, with fixture references and final positions.</p>
      <button type="button" class="btn btn-primary" id="fm-workspace-print">Print draw &amp; results</button>
      <button type="button" class="btn btn-outline-secondary" id="fm-draw-only-print">Print draw only</button>
      <button type="button" class="btn btn-outline-secondary" id="fm-timetable-print">Print schedule</button>
      <p class="text-muted small mt-3 mb-0">The print dialog also lets you save a PDF. Share the published public link for live updates.</p>
    </div>
  </section>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\flexible-workspace.blade.php ENDPATH**/ ?>