<?php echo $__env->make('backend.draw.partials.alternate-button', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="container-xxl py-3" style="max-width:1000px" data-draw-guide data-draw-guide-url="<?php echo e($guideUrl ?? route('draw.guide.show', [$event, $draw])); ?>">
  <a href="<?php echo e(route('headOffice.show', $event)); ?>" class="btn btn-link px-0 mb-2">← Back to draws</a>
  <h1 class="h3"><?php echo e($draw->drawName); ?></h1>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($scheduleDate)): ?><a class="btn btn-outline-primary mb-3" style="min-height:44px" href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event'=>$event->id,'draw_id'=>$draw->id,'date'=>$scheduleDate])); ?>">Resume saved day - <?php echo e(\Carbon\Carbon::parse($scheduleDate)->format('D j M Y')); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff'): ?>
    <p class="text-muted"><?php echo e($draw->formatDisplayLabel()); ?></p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="text-muted"><?php echo e($guide['team'] ? 'Set up your team draw.' : 'Choose the category and format, then place your players.'); ?> Save each step before leaving; you can continue here later.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked): ?><div class="alert alert-warning">This draw is locked. Review is available; use the existing workspace controls before changing protected setup.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $guide['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $warning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="alert alert-warning"><?php echo e($warning); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guide['competition']): ?>
    <?php
      $playoffReadiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
    ?>
    <?php echo $__env->make('backend.team-draw.partials.playoff-readiness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <section class="card card-body mb-3">
      <h2 class="h5">Competition phases</h2>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guide['sourceDraw']): ?>
        <p><?php echo e($draw->isPlacementDraft() ? 'Proposed playoff programme: schedule the position placeholders now. Confirm qualifiers from the round robin to fill these same fixtures without losing times or courts. Scoring and publication remain unavailable until qualification.' : 'The round-robin results determined these playoff pairings.'); ?></p>
        <a class="btn btn-outline-primary align-self-start" href="<?php echo e(route('draw.guide.show', [$event, $guide['sourceDraw']])); ?>">Review round-robin phase</a>
      <?php elseif($guide['linkedPlayoff']): ?>
        <p>Round-robin and playoff setup have separate schedules and publication reviews.</p>
        <a class="btn btn-primary align-self-start" href="<?php echo e(route('draw.guide.show', [$event, $guide['linkedPlayoff']])); ?>">Continue playoff programme &amp; schedule</a>
      <?php else: ?>
        <p>Current phase: round robin. Prepare the draft placement fixtures now to plan the programme through Saturday. Confirmed qualifiers fill those same fixtures and retain their times and courts.</p>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-draw.createFormat', $event)): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playoffReadiness['draft_playoff'] ?? null): ?>
            <a class="btn btn-primary align-self-start mb-2" href="<?php echo e(route('draw.guide.show', [$event, $playoffReadiness['draft_playoff']])); ?>">Open draft playoff programme</a>
          <?php else: ?>
            <form method="POST" action="<?php echo e(route('team-draw.competition.draft-playoffs', $draw)); ?>" class="mb-3"><?php echo csrf_field(); ?><button class="btn btn-primary" <?php if(!($playoffReadiness['can_prepare'] ?? false)): echo 'disabled'; endif; ?>>Prepare draft playoff fixtures</button></form>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <a class="btn btn-outline-primary align-self-start mb-2" href="<?php echo e(route('backend.team-draw.standings', $draw)); ?>">Review qualifiers</a>
          <form method="POST" action="<?php echo e(route('team-draw.competition.playoffs', $draw)); ?>"><?php echo csrf_field(); ?><button class="btn btn-outline-primary" <?php if(!($playoffReadiness['can_create'] ?? false)): echo 'disabled'; endif; ?>><?php echo e(($playoffReadiness['draft_playoff'] ?? null) ? 'Confirm qualifiers & fill draft fixtures' : 'Confirm qualifiers & create playoffs'); ?></button></form>
        <?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->team_scoring_rules['undecided'] ?? false): ?>
        <a class="btn btn-outline-primary align-self-start mt-3" href="<?php echo e(route('team-draw.competition.scoring', $draw)); ?>">Confirm scoring before publication</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php
    $publicationUrl = !$guide['team'] ? $guide['workspace'].'?review=publication#matrix' : $guide['steps'][5]['url'];
    $visibleSteps = $guide['team'] ? $guide['steps'] : [
      1 => array_merge($guide['steps'][2], ['label' => 'Format']),
      2 => array_merge($guide['steps'][3], ['label' => 'Players & Positions', 'url' => $guide['workspace'].'#groups']),
      3 => array_merge($guide['steps'][3], ['label' => 'Draw & Results', 'url' => $guide['workspace'].'#matrix']),
      4 => array_merge($guide['steps'][4], ['label' => 'Schedule']),
      5 => array_merge($guide['steps'][5], ['label' => 'Review & publish', 'url' => $publicationUrl]),
    ];
    $displayStep = $guide['team'] ? $guide['next'] : ([1 => 0, 2 => 1, 3 => 2, 4 => 4, 5 => 5][$guide['next']]);
    $phaseOneReady = !$guide['team'] && $guide['steps'][3]['complete'];
    $current = $guide['steps'][$guide['next']];
  ?>
  <ol class="list-unstyled d-flex flex-wrap gap-2 mb-4" aria-label="Draw setup progress">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $visibleSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li class="border rounded px-3 py-2 <?php echo e($index === $displayStep ? 'border-primary' : ''); ?>" <?php if($index === $displayStep): ?> aria-current="step" <?php endif; ?>>
        <span class="small"><?php echo e($guide['team'] ? $index + 1 : $index); ?>. <?php echo e($step['label']); ?></span>
        <span class="d-block small <?php echo e($step['complete'] ? 'text-success' : 'text-muted'); ?>"><?php echo e($step['complete'] ? 'Complete' : ($index === $displayStep ? 'Continue here' : 'Still to do')); ?></span>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ol>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($phaseOneReady): ?>
    <section class="card card-body mb-3">
      <h2 class="h4">Draw and players ready</h2>
      <p>Your generated draw is saved. Open it to review the players and matches.</p>
        <h3 class="h5" id="draw-schedule-next"><?php echo e($guide['scheduleComplete'] ? 'Step 5 of 5 · Review & publish' : 'Step 4 of 5 · Schedule'); ?></h3>
        <p><?php echo e($guide['steps'][4]['detail']); ?></p>
        <a class="btn btn-primary align-self-start" data-next-schedule href="<?php echo e($guide['scheduleComplete'] ? route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'draw_id'=>$draw->id, 'date'=>$scheduleDate ?? 'all']) : $guide['steps'][4]['url']); ?>"><?php echo e($guide['scheduleComplete'] ? 'Review saved schedule' : 'Schedule matches'); ?></a>
        <a class="btn btn-outline-secondary align-self-start mt-2" href="<?php echo e($guide['workspace']); ?>">Open draw</a>
        <p class="small text-muted mt-3 mb-2">Saving keeps the programme private. Review and publish the draw and its match times separately when ready.</p>
        <a class="align-self-start" href="<?php echo e($publicationUrl); ?>">Review publication controls</a>
      <a class="align-self-start mt-3" href="<?php echo e(route('headOffice.show', $event)); ?>">Back to draws</a>
    </section>
  <?php else: ?>
  <section class="card card-body mb-3">
    <p class="small text-primary mb-1">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$guide['team'] && $displayStep === 0): ?>
        Before you start · Choose a category
      <?php else: ?>
        Step <?php echo e($guide['team'] ? $guide['next'] + 1 : $displayStep); ?> of <?php echo e($guide['team'] ? 6 : 5); ?>

      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>
    <h2 class="h4"><?php echo e($guide['team'] ? $current['label'] : ($displayStep === 0 ? 'Category' : $visibleSteps[$displayStep]['label'])); ?></h2>
    <p><?php echo e(!$guide['team'] && $guide['next'] === 3 ? 'Place your players, save the assignments and generate the matches.' : $current['detail']); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guide['next'] === 1 && !$guide['team'] && !$draw->category_event_id && !$draw->locked && !$draw->published && !$draw->oop_published): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->isEmpty()): ?>
        <p>No player categories are configured for this event yet.</p>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-category.manage', $event)): ?>
          <a class="btn btn-primary align-self-start" href="<?php echo e(route('admin.events.categories', $event)); ?>">Open event categories &amp; entries</a>
        <?php else: ?>
          <p class="mb-0">Ask an event administrator to add the player categories, then return to this saved draw.</p>
        <?php endif; ?>
      <?php else: ?>
      <form data-draw-guide-form method="POST" action="<?php echo e(route('draw.guide.category', [$event, $draw])); ?>"><?php echo csrf_field(); ?>
        <label for="guide-category" class="form-label">Player category</label>
        <select id="guide-category" name="category_event_id" class="form-select mb-3" required><option value="">Choose a category</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category->id); ?>"><?php echo e($category->category?->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
        <button class="btn btn-primary">Save category &amp; continue</button>
      </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php elseif($guide['next'] === 3 && $guide['team'] && !$guide['generated'] && !$draw->locked && !$draw->published): ?>
      <p><?php echo e($guide['participants']); ?> teams are selected. Review the teams, format and pair choices before creating the ties.</p>
      <a href="<?php echo e(route('draws.manage', $draw)); ?>" class="btn btn-outline-primary align-self-start mb-3">Review teams &amp; format</a>
      <form data-draw-guide-form method="POST" action="<?php echo e(route('draw.guide.generate-team', [$event, $draw])); ?>"><?php echo csrf_field(); ?>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_generation" value="1" required><span class="form-check-label">I have reviewed the selected teams and want to generate their ties.</span></label>
        <button class="btn btn-primary">Generate team ties &amp; continue</button>
      </form>
    <?php else: ?>
      <a href="<?php echo e($current['url']); ?>" class="btn btn-primary align-self-start"><?php echo e($guide['next'] === 5 ? 'Review publication controls' : 'Continue to '.($guide['team'] ? $current['label'] : ($displayStep === 0 ? 'category' : $visibleSteps[$displayStep]['label']))); ?></a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="small text-muted mt-3 mb-0">Saving setup does not publish changes. Review the current draw and schedule publication separately.</p>
  </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <details class="card card-body"><summary>Review saved setup<?php echo e($guide['team'] ? ' or open another step' : ''); ?></summary><div class="d-grid gap-2 mt-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $visibleSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="btn btn-outline-secondary text-start" href="<?php echo e($step['url']); ?>"><?php echo e($guide['team'] ? $index + 1 : $index); ?>. <?php echo e($step['label']); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/partials/guide-content.blade.php ENDPATH**/ ?>