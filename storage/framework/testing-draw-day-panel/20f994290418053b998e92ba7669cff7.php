<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->isTwoStageRoundRobin()): ?>
  <?php
    $firstStageIds = $draw->groups->where('round_robin_phase', 1)->pluck('id');
    $firstMatches = $draw->drawFixtures->where('stage', 'RR')->whereIn('draw_group_id', $firstStageIds);
    $firstComplete = $firstMatches->filter(fn ($fixture) => $fixture->fixtureResults->isNotEmpty())->count();
    $secondCreated = $draw->groups->contains('round_robin_phase', 2);
  ?>
  <section class="card card-body my-3 no-print" aria-labelledby="rr-second-stage-heading">
    <h6 id="rr-second-stage-heading">Stage 1 → Stage 2 round robin</h6>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($secondCreated): ?>
      <p class="mb-0">Stage 2 groups and matches are ready. First-stage results and membership are frozen. Schedule and score the second-stage matches to determine final positions.</p>
    <?php else: ?>
      <p><?php echo e($firstComplete); ?> of <?php echo e($firstMatches->count()); ?> first-stage results recorded. Complete every match and resolve tied positions, then review and confirm each group.</p>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
        <button type="button" class="btn btn-primary align-self-start progress-draw" data-url="<?php echo e(route('backend.draw.progress', $draw)); ?>" data-review-url="<?php echo e(route('backend.draw.progress-review', $draw)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" <?php if($draw->locked || $firstMatches->isEmpty() || $firstComplete !== $firstMatches->count()): echo 'disabled'; endif; ?>>Review &amp; progress to second round robin</button>
      <?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details class="mt-3"><summary>Qualification and progression rules</summary><p class="small mt-2 mb-0">All first-place finishers enter Stage 2 Group 1, all second-place finishers Group 2, and so on. Each group starts with fresh scores. Group 1 determines the highest final positions, followed by Group 2 and the remaining groups. Second-stage matches become available for scheduling after progression.</p></details>
  </section>
<?php elseif($draw->settings?->workflow === 'round_robin_playoffs'): ?>
  <?php
    $progressTotal = $draw->drawFixtures()->where('stage', 'RR')->count();
    $progressComplete = $draw->drawFixtures()->where('stage', 'RR')->whereHas('fixtureResults')->count();
    $progressReady = $progressTotal > 0 && $progressComplete === $progressTotal;
    $progressPlayoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->get(['registration1_id', 'registration2_id']);
    $progressHasQualifiers = $progressPlayoffs->contains(fn ($fixture) => $fixture->registration1_id || $fixture->registration2_id);
  ?>
  <section class="card card-body my-3 no-print" aria-labelledby="playoff-progression-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h6 id="playoff-progression-heading" class="mb-1">Group stage → playoffs <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressHasQualifiers): ?><span class="badge bg-label-success ms-2">Playoffs created</span><?php elseif($progressPlayoffs->isNotEmpty()): ?><span class="badge bg-label-secondary ms-2">Qualifiers pending</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h6>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked): ?>
          <p class="small text-muted mb-0">This draw is locked. Unlock it before progressing the playoffs.</p>
        <?php elseif($progressHasQualifiers && !$progressReady): ?>
          <p class="small mb-0">Playoffs already exist, but some group results are incomplete. Review the group results before refreshing the qualifiers.</p>
        <?php elseif($progressHasQualifiers): ?>
          <p class="small text-muted mb-0">The playoffs are created. Review the current bracket; refresh the qualifiers only if the standings have changed.</p>
        <?php elseif($progressReady): ?>
          <p class="small mb-0">All <?php echo e($progressTotal); ?> group results are complete. Review the standings and confirm progression to the playoffs.</p>
        <?php elseif($progressTotal > 0): ?>
          <p class="small text-muted mb-0"><?php echo e($progressComplete); ?> of <?php echo e($progressTotal); ?> group results complete. Finish the remaining results before progressing.</p>
        <?php else: ?>
          <p class="small text-muted mb-0">Create the group matches first, then complete their results before progressing to the playoffs.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressReady): ?>
          <button type="button" class="btn <?php echo e($progressHasQualifiers ? 'btn-outline-secondary' : 'btn-primary'); ?> progress-draw" data-url="<?php echo e(route('backend.draw.progress', $draw)); ?>" data-review-url="<?php echo e(route('backend.draw.progress-review', $draw)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>"><?php echo e($progressHasQualifiers ? 'Review & refresh playoffs' : 'Review & progress to playoffs'); ?></button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?>
    </div>
    <details class="mt-3"><summary>Qualification and progression rules</summary>
      <div class="small mt-2">
        <p>Complete all group results, review each group's final order and confirm every group. Qualifiers come from the saved positions below; the preview does not qualify players.</p>
        <ul>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = collect($draw->settings->playoff_config ?? [])->filter(fn ($config) => is_array($config) && ($config['enabled'] ?? false)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $progressConfig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <li><strong><?php echo e(is_string($progressConfig['name'] ?? null) ? $progressConfig['name'] : 'Playoffs'); ?>:</strong> <?php echo e(is_array($progressConfig['positions'] ?? null) ? 'positions '.implode(', ', array_filter($progressConfig['positions'], 'is_numeric')).' from each group' : 'qualifying positions must be configured'); ?>.</li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <li>Choose and save the qualifying positions in playoff settings.</li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
        <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#standings">Review group standings</a> · <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#settings">View saved playoff settings</a> · <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#notes">Event rules and notes</a>
      </div>
    </details>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/roundrobin/progression-status.blade.php ENDPATH**/ ?>