<?php
  $ready = true;
  try { $planner->validateProposal($plan->proposal); } catch (\Illuminate\Validation\ValidationException $e) { $ready = false; $blocked = $e->validator->errors()->first(); }
  $stale = $plan->expires_at->isPast() || !hash_equals($plan->fingerprint, $source['fingerprint']);
  $savedExcluded = $plan->proposal['excluded_category_ids'] ?? [];
  $displayExcluded = array_map('intval', old('excluded_categories', $savedExcluded));
?>
<section id="ctr-review" aria-labelledby="ctr-board-heading">
  <h2 class="h5" id="ctr-board-heading">Review your groups</h2>
  <p class="small text-muted">Drag players between groups, or choose a group and press Move. Players without a usable CTR start in the last groups. Move them if you know their level. Aim for three matches per player, up to four; any two-match groups need your explicit review.</p>
  <form id="ctr-placement-form" method="POST" action="<?php echo e(route('backend.ctr-planner.review', $event)); ?>" data-event-id="<?php echo e($event->id); ?>" data-has-errors="<?php echo e($errors->any() ? '1' : '0'); ?>" data-stale="<?php echo e($stale ? '1' : '0'); ?>"><?php echo csrf_field(); ?>
    <input type="hidden" name="token" value="<?php echo e($plan->token); ?>"><input type="hidden" name="revision" value="<?php echo e($planner->revision($plan)); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($plan->proposal['rows'])->groupBy('category_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryId => $categoryRows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $six = $categoryRows->count() === 6;
        $straightBlock = collect($plan->proposal['straight_blocks'] ?? [])->firstWhere('category_id', (int) $categoryId);
        $straightGroups = $straightBlock['groups'] ?? [];
        $displayRows = $categoryRows->map(function ($row) { $row['group'] = (int) old('placements.'.$row['entry_id'], $row['group']); return $row; });
        $displayGroups = $planner->groups(['rows' => $displayRows->all()]);
        $groupNumbers = array_column($displayGroups, 'number');
        $excluded = in_array((int) $categoryId, $displayExcluded, true);
        $differentBands = $categoryRows->pluck('component')->filter()->unique()->count() > 1;
      ?>
      <section class="ctr-category-board" data-ctr-category data-category-id="<?php echo e($categoryId); ?>" data-six="<?php echo e($six ? '1' : '0'); ?>" data-straight-groups="<?php echo e(implode(',', $straightGroups)); ?>" data-player-total="<?php echo e($categoryRows->count()); ?>">
        <div class="ctr-board-heading">
          <div><h3 class="h5 mb-1"><?php echo e($categoryRows->first()['category']); ?></h3><p class="small text-muted mb-0"><?php echo e(['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'Green/Yellow CTR'][$categoryRows->first()['pool']] ?? 'Ball level needs review'); ?> · <?php echo e($categoryRows->count()); ?> players</p></div>
          <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="excluded_categories[]" value="<?php echo e($categoryId); ?>" data-exclude-category <?php if($excluded): echo 'checked'; endif; ?>><span class="form-check-label">Exclude from this draw plan</span></label>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryRows->count() < 3): ?><p class="alert alert-warning py-2" data-category-minimum <?php if($excluded): ?> hidden <?php endif; ?>>Not enough players. At least three are needed. You can exclude this category from the plan; its entries and payments are kept.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($differentBands): ?><p class="small text-muted">Separate comparison bands are shown. Their CTR values cannot be compared with each other.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($straightGroups): ?><p class="alert alert-info py-2" data-six-description data-ctr-competition data-kind="straight_finals">Groups <?php echo e(implode(' and ', $straightGroups)); ?> form one six-player competition, three players each. After the round robins: A1 vs B1 for the winner, A2 vs B2 for places 3–4, A3 vs B3 for places 5–6. Nine matches, one winner prize and one sportsmanship prize.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="small text-muted" data-excluded-note <?php if(!$excluded): ?> hidden <?php endif; ?>>Excluded from creation. All players remain registered. <span data-exclusion-next-step hidden>Changes save automatically. <a href="#ctr-board-status" data-review-exclusion>Check save status</a>.</span></p>
        <div class="ctr-group-board" data-group-board>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $displayGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $display): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="ctr-group-card" data-ctr-group data-category-id="<?php echo e($categoryId); ?>" data-group-id="<?php echo e($display['number']); ?>" data-straight="<?php echo e(in_array($display['number'], $straightGroups, true) ? '1' : '0'); ?>">
              <div class="ctr-group-header"><h4>Group <?php echo e($display['number']); ?></h4><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-group <?php if(count($display['rows'])): ?> hidden <?php endif; ?>>Remove empty group</button></div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($display['number'], $straightGroups, true)): ?><p class="small text-primary mb-1">Shared six-player competition · Group <?php echo e(array_search($display['number'], $straightGroups, true) === 0 ? 'A' : 'B'); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <p class="ctr-group-meta"><span data-group-count><?php echo e(count($display['rows'])); ?></span> players · <span data-group-matches><?php echo e(count($display['rows']) * (count($display['rows']) - 1) / 2); ?></span> round-robin matches · <span data-group-games><?php echo e(count($display['rows']) - 1 + (in_array($display['number'], $straightGroups, true) ? 1 : 0)); ?></span> matches per player
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($display['number'], $straightGroups, true)): ?>
                  , including the final
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </p>
              <ul class="ctr-player-list" data-player-list aria-label="Group <?php echo e($display['number']); ?> players">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $display['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php echo $__env->make('backend.draw.ctr-player-card', ['displayGroup' => $display['number']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </ul>
              <p class="ctr-group-warning" data-group-warning <?php if(count($display['rows']) >= 4 && count($display['rows']) <= 5 || count($display['rows']) === 3 && in_array($display['number'], $straightGroups, true)): ?> hidden <?php endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($display['rows']) === 3 && !in_array($display['number'], $straightGroups, true)): ?>
                  2 matches per player — below the target of three. Review this exception.
                <?php else: ?>
                  Each included group needs 3–5 players.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </p>
            </article>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-add-group <?php if($six): ?> hidden <?php endif; ?>>Add an empty group</button>
      </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="ctr-manual-review" data-manual-review <?php if(!collect($plan->proposal['rows'])->pluck('component')->filter()->unique()->count()): ?> hidden <?php endif; ?>>
      <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="manual_reviewed" value="1" data-manual-ack <?php if(old('manual_reviewed', $plan->proposal['manual_reviewed'])): echo 'checked'; endif; ?>><span class="form-check-label">I reviewed placements involving different CTR comparison bands.</span></label>
      <p class="small text-muted mt-1 mb-0">Required when a group or linked competition spans ratings that cannot be compared.</p>
    </div>
    <div class="ctr-manual-review" data-short-review>
      <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="short_matches_reviewed" value="1" data-shortfall-ack <?php if(old('short_matches_reviewed', $plan->proposal['short_matches_reviewed'] ?? false)): echo 'checked'; endif; ?>><span class="form-check-label">I accept two matches per player in the highlighted independent three-player groups, below the target of three.</span></label>
      <p class="small text-muted mt-1 mb-0">You can instead rebalance the groups or exclude the category from this plan. No rematches are added automatically.</p>
    </div>
    <div id="ctr-board-status" role="status" aria-live="polite"></div>
    <div id="ctr-save-error" class="alert alert-danger" role="alert" hidden><p data-save-error-message class="mb-2"></p><a id="ctr-reload-plan" href="<?php echo e(route('backend.ctr-planner.show', [$event, 'plan' => $plan->token])); ?>">Reload saved plan</a><span class="small d-block mt-1">Reloading discards changes that have not been saved.</span></div>
    <div class="ctr-review-bar"><div><p id="ctr-prize-total"><?php echo e(count($competitions)); ?> winner prizes + <?php echo e(count($competitions)); ?> sportsmanship prizes = <?php echo e(count($competitions) * 2); ?> prizes</p><p class="small text-muted mb-0" id="ctr-programme-total"><?php echo e(count($competitions)); ?> competitions · <?php echo e(array_sum(array_column($competitions, 'matches'))); ?> matches</p></div><button id="ctr-review-changes" class="btn btn-outline-primary" type="submit">Save changes &amp; review</button></div>
    <noscript><p class="alert alert-info">Choose each player's destination group, then press Save changes &amp; review to see the updated groups.</p></noscript>
  </form>
  <p class="alert alert-warning" id="ctr-blocked" <?php if($ready): ?> hidden <?php endif; ?>><?php echo e($blocked ?? ''); ?></p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stale): ?><p class="alert alert-warning">This preview expired or uses an earlier grouping rule. Press Plan all draws for a fresh preview.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="POST" action="<?php echo e(route('backend.ctr-planner.apply', $event)); ?>" id="ctr-confirm-form" class="mb-4"><?php echo csrf_field(); ?>
    <input type="hidden" name="token" value="<?php echo e($plan->token); ?>"><input type="hidden" name="revision" value="<?php echo e($planner->revision($plan)); ?>">
    <button id="ctr-confirm" class="btn btn-primary" type="submit" <?php if(!$ready || $stale || $errors->any()): echo 'disabled'; endif; ?>>Confirm and create all draws</button>
    <p class="small text-muted mt-2">Changes save automatically on this page. Wait for Saved before confirming. Confirmation creates the saved plan privately, without scheduling or publication. Plans expire after 30 minutes.</p>
  </form>
</section>
<template id="ctr-empty-group-template"><article class="ctr-group-card" data-ctr-group><div class="ctr-group-header"><h4></h4><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-group>Remove empty group</button></div><p class="ctr-group-meta"><span data-group-count>0</span> players · <span data-group-matches>0</span> round-robin matches · <span data-group-games>0</span> matches per player</p><ul class="ctr-player-list" data-player-list></ul><p class="ctr-group-warning" data-group-warning hidden>Each included group needs 3–5 players.</p></article></template>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\ctr-group-board.blade.php ENDPATH**/ ?>