
<?php $__env->startSection('title', 'Edit draw day schedule'); ?>
<?php $__env->startSection('page-style'); ?><style>[data-draw-day-editor] .btn, [data-draw-day-editor] .form-check { min-height:44px; } [data-draw-day-editor] .form-check {display:flex;align-items:center;gap:.5rem;padding-right:.5rem;}</style><?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-3" data-draw-day-editor>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($originDraw->isTeamDraw())): ?><?php echo $__env->make('backend.draw.partials.workflow', ['draw'=>$originDraw, 'workflowContext'=>'schedule'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <a class="btn btn-outline-primary mb-3" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'draw_id'=>$draw->id, 'date'=>$data['date']] + $originContext)); ?>">Back to current schedule</a>
  <a class="btn btn-outline-primary mb-3" href="<?php echo e($originDraw->isTeamDraw() ? route('draw.guide.show', [$event, $originDraw, 'schedule_date'=>$data['date']]) : route('backend.draw.roundrobin.show', [$originDraw, 'schedule_date'=>$data['date']]).'#schedule'); ?>"><?php echo e($originDraw->id !== $draw->id ? 'Back to original draw' : 'Back to this draw'); ?> - <?php echo e($originDraw->drawName); ?> · Schedule</a>
  <?php echo $__env->make('backend.schedule.partials.day-navigation', ['mode'=>'draw-day', 'navigationDraw'=>$draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <h3><?php echo e($draw->drawName); ?> · <?php echo e(\Carbon\Carbon::parse($data['date'])->format('D j M Y')); ?></h3>
  <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Scheduling method"><span class="align-self-center">Schedule by:</span><span class="btn btn-primary" aria-current="page">Position band / draw</span><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>$data['date'], 'draw_id'=>$draw->id] + $originContext)); ?>">Whole tie · full day across venues</a></nav>
  <p>Change venues and times for this draw's already scheduled matches on this day. Other draws and days keep their times. Played matches stay fixed. Preview first, then save. Published times change only when you publish updates.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rows->isEmpty()): ?><div class="alert alert-info">This draw has no saved times on this day. Use <a href="<?php echo e(route('backend.event-venue-schedule.index', ['event'=>$event->id, 'draw_ids'=>[$draw->id], 'date'=>$data['date']] + $originContext)); ?>">Add matches to this day</a> to schedule a new batch.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card card-body mb-3">
    <details class="mb-3" id="day-add-venues">
      <summary class="btn btn-outline-primary">Add venues</summary>
      <form id="day-add-venue-form" class="border rounded p-3 mt-2" action="<?php echo e(route('backend.event-venue-schedule.venues', $event)); ?>" method="post">
        <?php echo csrf_field(); ?>
        <p class="small text-muted">Add a venue and courts to this event, then select it below. Your current day settings stay in place.</p>
        <div class="row g-3">
          <div class="col-12 col-md-4"><label class="form-label" for="day-venue-mode">Venue</label><select id="day-venue-mode" class="form-select"><option value="existing">Existing venue</option><option value="new">Create new venue</option></select></div>
          <div class="col-12 col-md-4" id="day-existing-venue-panel"><label class="form-label" for="day-existing-venue">Choose venue</label><select id="day-existing-venue" name="venue_id" class="form-select" required><option value="">Choose existing venue</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allVenues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>"><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
          <div class="col-12 col-md-4" id="day-new-venue-panel" hidden><label class="form-label" for="day-new-venue">New venue name</label><input id="day-new-venue" name="name" class="form-control" maxlength="191" disabled required></div>
          <div class="col-6 col-md-2"><label class="form-label" for="day-new-courts">Courts</label><input id="day-new-courts" name="courts" class="form-control" type="number" min="1" max="100" value="1" required></div>
          <div class="col-6 col-md-2"><label class="form-label" for="day-new-ball">Ball type</label><select id="day-new-ball" name="ball_type" class="form-select"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['standard','yellow','green','orange','red']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type); ?>"><?php echo e(ucfirst($type)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
        </div>
        <button class="btn btn-primary mt-3" id="day-add-venue-submit">Add venue</button>
        <div id="day-add-venue-status" class="mt-2" role="status" aria-live="polite"></div>
      </form>
    </details>
    <form method="post" id="draw-day-form">
      <?php echo csrf_field(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $originContext; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contextKey=>$contextValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($contextKey); ?>" value="<?php echo e($contextValue); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <input type="hidden" name="draw_id" value="<?php echo e($draw->id); ?>"><input type="hidden" name="date" value="<?php echo e($data['date']); ?>">
      <input type="hidden" name="court_labels_present" value="1">
      <div class="row g-3 mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['start_time'=>'Start time', 'end_time'=>'Finish by']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="col-6 col-lg-2"><label class="form-label" for="day-<?php echo e($key); ?>"><?php echo e($label); ?></label><input class="form-control" id="day-<?php echo e($key); ?>" type="time" name="<?php echo e($key); ?>" value="<?php echo e(old($key, $values[$key])); ?>" required></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['duration'=>'Match minutes', 'player_rest'=>'Player rest minutes', 'court_gap'=>'Court gap minutes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="col-6 col-lg-2"><label class="form-label" for="day-<?php echo e($key); ?>"><?php echo e($label); ?></label><input class="form-control" id="day-<?php echo e($key); ?>" type="number" name="<?php echo e($key); ?>" min="<?php echo e($key==='duration'?1:0); ?>" max="<?php echo e($key==='court_gap'?120:480); ?>" value="<?php echo e(old($key, $values[$key])); ?>" required></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <fieldset class="mb-3"><legend class="h5">Venues for this draw and day</legend><p class="small text-muted">Choose destination venues. This does not change venue assignments for other days or age groups.</p>
        <div class="row g-3" id="day-venue-list">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($chosenCourts = old('court_labels', $values['court_labels'])[$venue->id] ?? []); ?>
          <div class="col-12 col-md-6"><div class="border rounded p-3" data-day-venue="<?php echo e($venue->id); ?>">
            <label class="form-check"><input class="form-check-input day-venue-choice" type="checkbox" name="venue_ids[]" value="<?php echo e($venue->id); ?>" <?php if(in_array($venue->id, old('venue_ids', $values['venue_ids']))): echo 'checked'; endif; ?>><span class="form-check-label"><?php echo e($venue->name); ?></span></label>
            <label class="form-label" for="court-count-<?php echo e($venue->id); ?>">Number of courts for this day</label>
            <input class="form-control day-court-count mb-2" id="court-count-<?php echo e($venue->id); ?>" type="number" min="1" max="<?php echo e(count($courtOptions[$venue->id] ?? [])); ?>" value="<?php echo e(count($chosenCourts)); ?>">
            <div class="d-flex flex-wrap gap-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $courtOptions[$venue->id] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $court): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label class="form-check"><input class="form-check-input day-court-label" type="checkbox" name="court_labels[<?php echo e($venue->id); ?>][]" value="<?php echo e($court); ?>" <?php if(in_array($court, $chosenCourts)): echo 'checked'; endif; ?>><span class="form-check-label">Court <?php echo e($court); ?></span></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($courtOptions[$venue->id])): ?><p class="small text-danger mb-0">No active event courts available.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <p class="small text-muted mt-2 mb-0">The count selects the first available courts. Select individual labels to use different courts. These choices apply only to this draw and day.</p>
      </fieldset>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->isTeamDraw()): ?>
      <fieldset class="mb-3"><legend class="h5">Position bands for this day</legend>
        <p class="small text-muted">Assign roster positions to selected destination venues. With no bands, the planner uses the selected courts normally. Missing roster positions produce a preview warning.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bandNotice): ?><div class="alert alert-info"><?php echo e($bandNotice); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div id="day-position-bands">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = array_values(old('bands', $values['bands'])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index=>$band): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="row g-2 align-items-end mb-2 day-band-row">
            <div class="col-6 col-md-2"><label class="form-label">From position<input class="form-control" type="number" min="1" max="100" name="bands[<?php echo e($index); ?>][min_rank]" value="<?php echo e($band['min_rank']); ?>" required></label></div>
            <div class="col-6 col-md-2"><label class="form-label">To position<input class="form-control" type="number" min="1" max="100" name="bands[<?php echo e($index); ?>][max_rank]" value="<?php echo e($band['max_rank']); ?>" required></label></div>
            <div class="col-12 col-md-6"><label class="form-label w-100">Destination venue<select class="form-select day-band-venue" name="bands[<?php echo e($index); ?>][venue_id]" required><option value="">Choose destination venue</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>" <?php if((int)$band['venue_id']===$venue->id): echo 'selected'; endif; ?>><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></label></div>
            <div class="col-12 col-md-2"><button type="button" class="btn btn-outline-danger remove-day-band mb-2">Remove</button></div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <button class="btn btn-outline-primary mb-3" id="add-day-band" type="button">Add position band</button>
        <label class="form-label d-block" for="day-cross-band">When players span different bands</label>
        <select class="form-select" id="day-cross-band" name="cross_band_policy"><option value="highest_ranked" <?php if(old('cross_band_policy',$values['cross_band_policy'])==='highest_ranked'): echo 'selected'; endif; ?>>Use the highest-ranked player's venue</option><option value="manual" <?php if(old('cross_band_policy',$values['cross_band_policy'])==='manual'): echo 'selected'; endif; ?>>Require manual venue placement</option></select>
      </fieldset>
      <template id="day-band-template"><div class="row g-2 align-items-end mb-2 day-band-row">
        <div class="col-6 col-md-2"><label class="form-label">From position<input class="form-control" type="number" min="1" max="100" data-band-field="min_rank" required></label></div>
        <div class="col-6 col-md-2"><label class="form-label">To position<input class="form-control" type="number" min="1" max="100" data-band-field="max_rank" required></label></div>
        <div class="col-12 col-md-6"><label class="form-label w-100">Destination venue<select class="form-select day-band-venue" data-band-field="venue_id" required><option value="">Choose destination venue</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>"><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></label></div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-danger remove-day-band mb-2" type="button">Remove</button></div>
      </div></template>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-primary" name="action" value="preview" <?php if($rows->isEmpty()): echo 'disabled'; endif; ?>>Preview this draw and day</button>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
        <input type="hidden" name="revision" value="<?php echo e($preview['revision']); ?>">
        <button class="btn btn-success" name="action" value="save" id="save-day-preview" <?php if(count($preview['unscheduled'])>0 || count($preview['matches'])===0): echo 'disabled'; endif; ?>>Save preview</button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
    <?php echo $__env->make('backend.schedule.partials.change-review', ['originalRows'=>$rows->keyBy('fixture_key')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="alert alert-info"><?php echo e(count($preview['matches'])); ?> proposed changes · <?php echo e(count($preview['unscheduled'])); ?> matches could not fit. Saved times remain unchanged until you save.</div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $warning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="alert alert-warning"><?php echo e($warning); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['unscheduled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="alert alert-warning">Match <?php echo e($match['fixture_id'] ?? $match['match'] ?? ''); ?>: <?php echo e($match['reason'] ?? 'Cannot fit in this day. Adjust the window or venues.'); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <h5>Proposed schedule</h5>
    <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Match</th><th>Time</th><th>Venue / court</th><th>Participants</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['matches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><?php ($current = $rows->firstWhere('fixture_key', $match['fixture_key'])); ?><td>Round <?php echo e($match['round'] ?? $current['round_nr'] ?? 1); ?><?php echo e(!empty($current['tie_nr']) ? ' · Tie '.$current['tie_nr'] : ''); ?> · Match <?php echo e($match['match'] ?? $current['match_nr'] ?? ''); ?></td><td><?php echo e(substr($match['scheduled_at'],11,5)); ?></td><td><?php echo e($venues->firstWhere('id',(int)$match['venue_id'])?->name); ?> / <?php echo e($match['court']); ?></td><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($current): ?><?php if (isset($component)) { $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.scheduled-participants','data' => ['row' => $current]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('scheduled-participants'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['row' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($current)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $attributes = $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $component = $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <h5>Current saved schedule</h5>
  <div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Venue / court</th><th>Match</th><th>Participants</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e(substr($row['scheduled_at'],11,5)); ?></td><td><?php echo e($row['venue_name']); ?> / <?php echo e($row['court']); ?></td><td><?php echo e(!empty($row['round_nr']) ? 'Round '.$row['round_nr'].' · ' : ''); ?><?php echo e(!empty($row['tie_nr']) ? 'Tie '.$row['tie_nr'].' · ' : ''); ?>Match <?php echo e($row['match_nr'] ?? ''); ?></td><td><?php if (isset($component)) { $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.scheduled-participants','data' => ['row' => $row]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('scheduled-participants'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['row' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $attributes = $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $component = $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.schedule.partials.day-navigation-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.schedule.partials.unsaved-guard', ['editorId'=>'draw-day-form', 'pendingPreview'=>(bool) $preview || session()->hasOldInput()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script>
(() => {
  const form = document.getElementById('draw-day-form');
  const invalidate = () => { const save=document.getElementById('save-day-preview'); if(save) { save.disabled=true; save.textContent='Preview again to save changes'; } };
  const venueForm = document.getElementById('day-add-venue-form');
  const venueMode = document.getElementById('day-venue-mode');
  venueMode.addEventListener('change', () => {
    const creating = venueMode.value === 'new';
    document.getElementById('day-existing-venue-panel').hidden = creating;
    document.getElementById('day-new-venue-panel').hidden = !creating;
    document.getElementById('day-existing-venue').disabled = creating;
    document.getElementById('day-new-venue').disabled = !creating;
  });
  let addingVenue = false;
  venueForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (addingVenue || !venueForm.reportValidity()) return;
    addingVenue = true;
    const button = document.getElementById('day-add-venue-submit');
    const status = document.getElementById('day-add-venue-status');
    button.disabled = true;
    status.textContent = 'Adding venue…';
    status.className = 'mt-2';
    try {
      const response = await fetch(venueForm.action, {method:'POST', headers:{Accept:'application/json'}, body:new FormData(venueForm)});
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to add venue. Please try again.');
      const venue = result.venue;
      if (!document.querySelector(`[data-day-venue="${Number(venue.id)}"]`)) {
        const column = document.createElement('div');
        column.className = 'col-12 col-md-6';
        // Names and court labels are inserted as text, never as HTML.
        const card = document.createElement('div');
        card.className = 'border rounded p-3'; card.dataset.dayVenue = venue.id;
        const choiceLabel = document.createElement('label'); choiceLabel.className = 'form-check';
        const choice = document.createElement('input'); choice.type = 'checkbox'; choice.className = 'form-check-input day-venue-choice'; choice.name = 'venue_ids[]'; choice.value = venue.id; choice.checked = true;
        const name = document.createElement('span'); name.className = 'form-check-label'; name.textContent = venue.name;
        choiceLabel.append(choice, name); card.append(choiceLabel);
        const countLabel = document.createElement('label'); countLabel.className = 'form-label'; countLabel.htmlFor = `court-count-${venue.id}`; countLabel.textContent = 'Number of courts for this day';
        const count = document.createElement('input'); count.type = 'number'; count.className = 'form-control day-court-count mb-2'; count.id = countLabel.htmlFor; count.min = 1; count.max = venue.court_labels.length; count.value = venue.court_labels.length;
        card.append(countLabel, count);
        const courts = document.createElement('div'); courts.className = 'd-flex flex-wrap gap-2';
        venue.court_labels.forEach(label => {
          const wrapper = document.createElement('label'); wrapper.className = 'form-check';
          const input = document.createElement('input'); input.type = 'checkbox'; input.className = 'form-check-input day-court-label'; input.name = `court_labels[${venue.id}][]`; input.value = label; input.checked = true;
          const text = document.createElement('span'); text.className = 'form-check-label'; text.textContent = `Court ${label}`;
          wrapper.append(input, text); courts.append(wrapper);
        });
        card.append(courts); column.append(card); document.getElementById('day-venue-list').append(column);
        const selects = [...form.querySelectorAll('.day-band-venue'), ...document.getElementById('day-band-template')?.content.querySelectorAll('.day-band-venue') || []];
        selects.forEach(select => { const option = document.createElement('option'); option.value = venue.id; option.textContent = venue.name; select.append(option); });
      }
      document.getElementById('day-existing-venue').querySelector(`option[value="${Number(venue.id)}"]`)?.remove();
      document.getElementById('day-new-venue').value = '';
      syncVenues(); invalidate();
      status.className = 'mt-2 text-success'; status.textContent = result.message + ' Selected below. Add another venue or close Add venues.';
    } catch (error) {
      status.className = 'mt-2 text-danger'; status.textContent = error.message;
    } finally { addingVenue = false; button.disabled = false; }
  });
  const syncVenues = () => {
    const selected = [...form.querySelectorAll('.day-venue-choice:checked')].map(input=>input.value);
    form.querySelectorAll('[data-day-venue]').forEach(card => {
      const enabled=selected.includes(card.dataset.dayVenue);
      card.querySelectorAll('.day-court-count, .day-court-label').forEach(input=>input.disabled=!enabled);
    });
    form.querySelectorAll('.day-band-venue').forEach(select=>{
      select.querySelectorAll('option').forEach(option=>option.disabled=option.value!=='' && !selected.includes(option.value));
      if(select.value && !selected.includes(select.value)) select.value='';
    });
  };
  form.addEventListener('input',invalidate);
  form.addEventListener('change',event=>{
    if(event.target.matches('.day-venue-choice')) syncVenues();
    const card=event.target.closest('[data-day-venue]');
    if(card && event.target.matches('.day-court-count')) card.querySelectorAll('.day-court-label').forEach((input,index)=>input.checked=index<Number(event.target.value));
    if(card && event.target.matches('.day-court-label')) card.querySelector('.day-court-count').value=card.querySelectorAll('.day-court-label:checked').length;
    invalidate();
  });
  let bandIndex=<?php echo e(count(old('bands',$values['bands']))); ?>;
  document.getElementById('add-day-band')?.addEventListener('click',()=>{
    const list=document.getElementById('day-position-bands');
    if(list.children.length>=50) return;
    const row=document.getElementById('day-band-template').content.cloneNode(true);
    row.querySelectorAll('[data-band-field]').forEach(input=>input.name=`bands[${bandIndex}][${input.dataset.bandField}]`);
    bandIndex++;list.appendChild(row);syncVenues();invalidate();
  });
  form.addEventListener('click',event=>{ if(event.target.closest('.remove-day-band')) { event.target.closest('.day-band-row').remove();invalidate(); } });
  syncVenues();
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\schedule\edit-draw-day.blade.php ENDPATH**/ ?>