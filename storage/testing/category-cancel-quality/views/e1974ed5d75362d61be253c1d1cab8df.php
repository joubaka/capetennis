<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->isTwoStageRoundRobin()): ?>
  <?php
    $completion = app(\App\Services\Draw\RoundRobinProgressReadiness::class)->forDraw($draw);
    $firstTotal = $completion['round_robin']['total'];
    $firstComplete = $completion['round_robin']['completed'];
    $secondCreated = $draw->hasConfirmedSecondRoundRobin();
  ?>
  <section class="card card-body my-3 no-print" aria-labelledby="rr-second-stage-heading" data-rr-progression data-progress-keep-visible="1" data-progress-destination="<?php echo e($completion['destination']); ?>" data-has-qualifiers="<?php echo e($secondCreated ? '1' : '0'); ?>">
    <h6 id="rr-second-stage-heading"><?php echo e($draw->hasHybridRoundRobin() ? 'Stage 2 · Top-four playoffs and positions 5 onward' : 'Stage 1 → Stage 2 round robin'); ?></h6>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($secondCreated): ?>
      <p class="mb-0">Stage 2 groups and matches are ready. First-stage results and membership are frozen. Schedule and score the second-stage matches to determine final positions.</p>
    <?php else: ?>
      <p data-progress-message><?php echo e($firstComplete); ?> of <?php echo e($firstTotal); ?> first-stage results recorded. <?php echo e($firstTotal > 0 && $firstComplete === $firstTotal && !$completion['ready'] ? $completion['message'] : 'Complete every match and resolve tied positions, then review and confirm each group.'); ?></p>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
        <button type="button" class="btn btn-primary align-self-start progress-draw" data-url="<?php echo e(route('backend.draw.progress', $draw)); ?>" data-review-url="<?php echo e(route('backend.draw.progress-review', $draw)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" <?php if($draw->locked || !$completion['ready']): echo 'disabled'; endif; ?>>Review &amp; progress to <?php echo e($completion['destination']); ?></button>
      <?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->hasHybridRoundRobin()): ?>
      <p class="small mt-3 mb-0">A1 plays B2 and B1 plays A2 in the semi-finals. Winners play for 1st–2nd; losers play for 3rd–4th. The remaining players form one round-robin group for positions 5 onward. Their mutual first-stage results carry forward once; only opponents they have not yet played require new matches. Carried results remain frozen in their original matches.</p>
    <?php else: ?>
      <details class="mt-3"><summary>Qualification and progression rules</summary><p class="small mt-2 mb-0">All first-place finishers enter Stage 2 Group 1, all second-place finishers Group 2, and so on. Each group starts with fresh scores. Group 1 determines the highest final positions, followed by Group 2 and the remaining groups. Second-stage qualification placeholders can be scheduled before results. Confirmed progression fills in the players without changing saved times or courts.</p></details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php elseif($draw->settings?->workflow === 'round_robin_playoffs'): ?>
  <?php
    $completion = app(\App\Services\Draw\RoundRobinProgressReadiness::class)->forDraw($draw);
    $progressTotal = $completion['round_robin']['total'];
    $progressComplete = $completion['round_robin']['completed'];
    $progressReady = $completion['ready'];
    $progressPlayoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->get(['registration1_id', 'registration2_id']);
    $progressHasQualifiers = $progressPlayoffs->contains(fn ($fixture) => $fixture->registration1_id || $fixture->registration2_id);
  ?>
  <section class="card card-body my-3 no-print" aria-labelledby="playoff-progression-heading" data-rr-progression data-progress-destination="playoffs" data-has-qualifiers="<?php echo e($progressHasQualifiers ? '1' : '0'); ?>">
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
          <p class="small mb-0" data-progress-message>All <?php echo e($progressTotal); ?> group results are complete. Review the standings and confirm progression to the playoffs.</p>
        <?php elseif($progressTotal > 0): ?>
          <p class="small text-muted mb-0" data-progress-message><?php echo e($progressComplete); ?> of <?php echo e($progressTotal); ?> group results complete. Finish the remaining results before progressing.</p>
        <?php else: ?>
          <p class="small text-muted mb-0">Create the group matches first, then complete their results before progressing to the playoffs.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('progress', $draw)): ?>
          <button type="button" class="btn <?php echo e($progressHasQualifiers ? 'btn-outline-secondary' : 'btn-primary'); ?> progress-draw <?php echo e(!$progressReady ? 'd-none' : ''); ?>" data-url="<?php echo e(route('backend.draw.progress', $draw)); ?>" data-review-url="<?php echo e(route('backend.draw.progress-review', $draw)); ?>" data-draw-name="<?php echo e($draw->drawName); ?>" <?php if(!$progressReady || $draw->locked): echo 'disabled'; endif; ?>><?php echo e($progressHasQualifiers ? 'Review & refresh playoffs' : 'Review & progress to playoffs'); ?></button>
      <?php endif; ?>
    </div>
    <details class="mt-3"><summary>Qualification and progression rules</summary>
      <div class="small mt-2">
        <p>Complete all group results, review each group's final order and confirm every group. Qualifiers come from the saved positions below; the preview does not qualify players.</p>
        <ul>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Draw\InterproRoundRobinFormat::selected($draw)): ?>
            <li>A1–D1 and B1–C1 contest the title. A2–D3, B2–C3, C2–B3 and D2–A3 cross over. The two surviving crossover players meet the losing championship semifinalists to decide positions 3–6; the other branches decide 7–12.</li>
            <li>Fourth place finishes after the group stage. First result stands: repeat pairings carry their original result and require no court booking. Confirmed group results and completed playoff results are protected against direct changes.</li>
          <?php else: ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = collect($draw->settings->playoff_config ?? [])->filter(fn ($config) => is_array($config) && ($config['enabled'] ?? false)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $progressConfig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <li><strong><?php echo e(is_string($progressConfig['name'] ?? null) ? $progressConfig['name'] : 'Playoffs'); ?>:</strong> <?php echo e(is_array($progressConfig['positions'] ?? null) ? 'positions '.implode(', ', array_filter($progressConfig['positions'], 'is_numeric')).' from each group' : 'qualifying positions must be configured'); ?>.</li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <li>Choose and save the qualifying positions in playoff settings.</li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
        <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#standings">Review group standings</a> · <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#settings">View saved playoff settings</a> · <a href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>#notes">Event rules and notes</a>
      </div>
    </details>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\roundrobin\progression-status.blade.php ENDPATH**/ ?>