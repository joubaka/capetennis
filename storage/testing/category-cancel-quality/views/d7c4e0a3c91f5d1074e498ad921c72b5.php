<?php
    $configData = Helper::appClasses();
?>

<?php $__env->startSection('title', 'Player performance'); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page mx-auto" style="max-width: 72rem; font-size: 1rem; line-height: 1.55">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<header class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <p class="text-body-secondary mb-1">Player performance</p>
        <h1 class="h2 mb-1"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></h1>
        <p class="text-body-secondary mb-0">Evidence as of <?php echo e($performance['as_of']); ?></p>
    </div>
    <a class="btn btn-outline-primary" href="<?php echo e(route('backend.player-performance.directory')); ?>">Find another player</a>
</header>
<?php echo $__env->make('backend.player-performance.ability-card', ['performanceDetailPage' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.card', ['performanceDetailPage' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div id="rating-evidence">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ability['cohorts']->isNotEmpty()): ?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Age-specific comparison evidence</h2>
    <p>See what the player’s results show within each age group. Compare these estimates only within the same age and comparison group. The singles CTR above is shown separately.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ability['cohorts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $estimate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <section class="border rounded p-3 mb-3">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
            <h3 class="h5 mb-0"><?php echo e($estimate['cohort']); ?></h3>
            <strong class="fs-5"><?php echo e($estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100'); ?></strong>
        </div>
        <p class="mt-2 mb-2"><strong><?php echo e(\App\Services\Performance\AbilityConfidenceDisplay::label((int) $estimate['confidence_index'], $estimate)); ?> confidence</strong> · <?php echo e($estimate['played']); ?> played matches · <?php echo e($estimate['direct_opponents']); ?> different opponents</p>
        <details>
        <summary class="text-primary py-2">See evidence and comparable players</summary>
        <dl class="row g-2 my-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['Matches in the last 90 days' => $estimate['recent_played'], 'Events played' => $estimate['played_events'], 'Last match date (may be estimated)' => $estimate['last_direct_match'] ?? 'None']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-12 col-sm">
                    <dt class="small"><?php echo e($label); ?></dt>
                    <dd class="fw-semibold mb-0"><?php echo e($value); ?></dd>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </dl>
        <h4 class="h6 mt-3">Estimates within this group</h4>
        <ul class="list-group list-group-flush mb-3">
            <?php echo $__env->make('backend.player-performance.partials.evidence-score-bar', ['name' => $player->name.' '.$player->surname, 'score' => $estimate['score'], 'currentPlayer' => true, 'profileUrl' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $estimate['comparators']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comparator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php echo $__env->make('backend.player-performance.partials.evidence-score-bar', ['name' => $comparator['name'], 'score' => $comparator['score'], 'currentPlayer' => false, 'profileUrl' => route('backend.player-performance.show', $comparator['id'])], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li class="list-group-item">No comparable players in this sample.</li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
        <details class="mb-2">
        <summary>Evidence sources and calculation details</summary>
        <p class="mt-2">Comparison group <?php echo e($estimate['component']); ?></p>
        <p class="small"><?php echo e($estimate['confidence_explanation'] ?? ''); ?></p>
        <p>Own same-cohort evidence: <?php echo e($estimate['recent_played']); ?> matches in the last 90 days; <?php echo e(number_format($estimate['effective_played'], 2)); ?> recency-weighted matches; <?php echo e($estimate['direct_opponents']); ?> distinct opponents across <?php echo e($estimate['played_events']); ?> played events.</p>
        <p>Last direct-match date (schedule/event proxy): <?php echo e($estimate['last_direct_match'] ?? 'None'); ?>. Last eligible evidence: <?php echo e($estimate['last_eligible_activity'] ?? 'None'); ?>. Confidence as of <?php echo e($estimate['confidence_as_of']); ?>.</p>
        <p><?php echo e($estimate['baseline_status'] ?? 'No connected main-trial baseline'); ?>.
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['baseline_source'] ?? null): ?>
                <?php echo e($estimate['baseline_source']['event_name']); ?> · <?php echo e($estimate['baseline_source']['date']); ?> · <?php echo e($estimate['baseline_source']['covered_players']); ?>/<?php echo e($estimate['baseline_source']['trial_players']); ?> trial players in the largest connected benchmark group. This is a model-derived trial-match reference, not a published finishing rank.
            <?php else: ?>
                This local estimate has no connected main-trial reference. Compare only within this cohort and comparison group.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
        <p class="small">Low, Medium and High describe the strength and freshness of recorded evidence, not rating accuracy or win probability. Inactivity reduces it daily. Dates use the match schedule where credible, otherwise conservative event start dates (<?php echo e($estimate['proxy_dated_matches']); ?> matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        <p>This player: <?php echo e($estimate['played']); ?> played matches; <?php echo e($estimate['inferred']); ?> weaker finish-order comparisons. Comparison group: <?php echo e($estimate['component_players']); ?> players, <?php echo e($estimate['component_played']); ?> played matches, <?php echo e($estimate['component_inferred']); ?> inferred comparisons. <?php echo e($estimate['bridge_count']); ?> narrow connections; <?php echo e($estimate['division_links']); ?> inferred A/B links. These links are not played matches.</p>
        <details class="mb-3"><summary>Players connecting multiple event fields</summary>
            <p>Shared entrants connect event evidence, including regional and open tournaments. Event names and regions confer no strength bonus. These are provenance samples, not claims of head-to-head matches.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $estimate['anchors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $anchor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <p><a href="<?php echo e(route('backend.player-performance.show', $anchor['id'])); ?>"><?php echo e($anchor['name']); ?></a>: <?php echo e(collect($anchor['events'])->map(fn ($event) => $event['name'].' ('.$event['date'].')')->implode('; ')); ?></p>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p>No multi-event connection in the retained provenance sample.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </details>
        </details>
        </details>
        </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details><summary>How age-group estimates are calculated</summary>
        <p>The latest completed, eligible main Cavaliers trial (reviewed event type 5) provides a model-derived played-match benchmark from its largest connected group; no published trial finishing rank is invented. Trial targets are fixed, with a decaying weight-2 prior. Supporting played evidence has a per-player incident budget of one per event, so extra logging alone does not raise ability. Actual later events can adjust the reference. Unanchored groups remain local and cannot be compared as trial-calibrated.</p>
        <p>Completed public individual and verified team singles matches use a bounded score-margin target: close wins provide less separation than dominant wins. The winner target runs from 0.75 to 1 using the average normalized margin of each set; the loser receives the complement. Deciding tiebreak points are normalized separately from games. Custom or ambiguous score units retain outcome-only evidence. Match weights, event budgets and confidence counts stay unchanged. Published finishing fields supply weaker, explicitly inferred field-relative rank constraints (weight 0.2, centered target span ±1). Adjacent rank links are topology only, not played matches. Unique complete disjoint A/B fields can form one ordered field. When same-event played outcomes already connect the whole field, its rank constraint is omitted. All evidence has a 180-day half-life using own scheduled or conservative event-start dates. Open, A and B have no fixed score bands.</p>
        <p>A regularized Bradley–Terry model with separate quadratic ordinal-field constraints estimates strength relative to connected opponents and published finishing fields. Rank targets and weights are uncalibrated pilot assumptions. The fixed display transform is 100 × logistic(strength). Each comparison group is centered, so 50 is a model midpoint, not a certified standard. Different groups, ages, ball types and contexts are not comparable. Sparse results and inferred-only connections remain limited confidence; a score is withheld if calculation does not converge or processing limits are exceeded.</p>
        <p>Shared ability includes verified individual and team singles sources. Doubles and team standings remain separate. Public legacy scores are accepted only when complete canonical outcomes and exact identities can be verified; stored statuses/types are never changed. Saved snapshots refresh in the background; publication flags are checked on every request. Earlier model snapshots remain Pending until a compatible calculation is saved.</p>
    </details>
</div></div>
<?php else: ?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Age-specific comparison evidence</h2>
    <p class="mb-0">No age-specific comparison evidence is available for this player.</p>
</div></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<div id="performance-results">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $performance['disciplines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discipline => $preview): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
    $wins = $preview['cohorts']->sum('wins');
    $losses = $preview['cohorts']->sum('losses');
    $matches = $wins + $losses;
?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h4"><?php echo e(ucfirst($discipline)); ?></h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['truncated']): ?><p class="alert alert-warning">Limited to the latest 50 fields for this player. Older fields within the window were omitted.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($matches): ?>
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="bg-label-success rounded p-2 px-3"><strong class="fs-4"><?php echo e($wins); ?></strong> <?php echo e(Str::plural('win', $wins)); ?></div></div>
            <div class="col-6"><div class="bg-label-danger rounded p-2 px-3"><strong class="fs-4"><?php echo e($losses); ?></strong> <?php echo e(Str::plural('loss', $losses)); ?></div></div>
        </div>
        <div class="progress mb-2" style="height:.55rem" aria-hidden="true">
            <div class="progress-bar bg-success" style="width:<?php echo e(100 * $wins / $matches); ?>%"></div>
            <div class="progress-bar bg-danger" style="width:<?php echo e(100 * $losses / $matches); ?>%"></div>
        </div>
        <p class="small text-muted mb-2"><?php echo e($matches); ?> eligible published <?php echo e(Str::plural('match', $matches)); ?></p>
    <?php else: ?>
        <p class="mb-2">No eligible published <?php echo e($discipline); ?> matches.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details>
    <summary class="text-primary py-2">See finishing results and head-to-head matches</summary>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['cohorts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cohort): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <p><strong><?php echo e(number_format($cohort['score'], 1)); ?>/100 — provisional</strong> · <?php echo e($cohort['cohort']); ?> (<?php echo e($cohort['band']); ?>) · <?php echo e($cohort['finish_count']); ?> finishes / <?php echo e($cohort['match_count']); ?> matches (<?php echo e($cohort['wins']); ?> wins, <?php echo e($cohort['losses']); ?> losses) across <?php echo e($cohort['count']); ?> events · Latest <?php echo e($cohort['last_played']); ?></p>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p>Unrated: no eligible <?php echo e($discipline); ?> results.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="mt-2"><?php echo e($preview['finish_count']); ?> eligible finishes and <?php echo e($preview['match_count']); ?> eligible matches counted from all published history. Evidence below shows the latest <?php echo e($preview['evidence']->count()); ?> of <?php echo e($preview['evidence_count']); ?> finish records and <?php echo e($preview['match_evidence']->count()); ?> of <?php echo e($preview['match_evidence_count']); ?> match records; older eligible results still count.</p>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Event / date</th><th>Category / division</th><th>Finish / field</th><th>Points</th><th>Evidence</th></tr></thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['evidence']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr><td><?php echo e($result['event']); ?><br><?php echo e($result['date']); ?></td><td><?php echo e($result['category']); ?><br><?php echo e($result['tier'] ?? 'Unknown'); ?> · <?php echo e($result['division_source']); ?></td><td><?php echo e($result['position'] ?? 'No finish'); ?> / <?php echo e($result['field_capped'] ? 'at least ' : ''); ?><?php echo e($result['field_size']); ?> saved ranks<br><?php echo e($result['unranked_entries']); ?> other unranked entries</td><td><?php echo e($result['points'] === null ? '—' : number_format($result['points'], 1)); ?></td><td><?php echo e($result['reason'] ?? 'Included'); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5">No published finishing results or category memberships.</td></tr>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table></div>
    <h3 class="h5">Head-to-head matches</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Event / date</th><th>Category / source</th><th>Score / outcome</th><th>Points</th><th>Evidence</th></tr></thead><tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['match_evidence']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr><td><?php echo e($match['event']); ?><br><?php echo e($match['date']); ?></td><td><?php echo e($match['category']); ?><br><?php echo e($match['source']); ?></td><td class="<?php echo e($match['reason'] ? '' : ($match['won'] ? 'winner-home' : 'loser-home')); ?>">vs <?php echo e($match['opponents']); ?><br><?php echo e($match['score']); ?> (this player first)<br><?php echo e($match['reason'] ? 'Excluded' : ($match['won'] ? 'Win' : 'Loss')); ?></td><td><?php echo e($match['points'] === null ? '—' : number_format($match['points'], 1)); ?></td><td><?php echo e($match['reason'] ?? 'Included'); ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5">No publicly visible match evidence.</td></tr>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody></table></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['reasons']): ?>
        <details><summary>All-history exclusions</summary><ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $preview['reasons']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reason => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($reason); ?>: <?php echo e($count); ?> records</li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </details>
</div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<details class="mb-4"><summary class="text-primary py-2">How the Performance Score is calculated</summary>
<p class="mt-2">Pilot v2 uses all published history. A scores 50–100, B 0–50, and unlabelled Open cohorts 0–100 separately. Finishes use the saved ranked field, which can exclude unranked entrants; this is not a starter count. Each event's average finish points and band-scaled match win rate contribute equally when both exist, then events are weighted with a 180-day half-life. An ongoing event uses the as-of date until its end date. A main category is A only when uniquely paired with matching B. Ages, genders, Masters, Open and team contexts remain separate. Doubles reflect partnerships. Match scores require published draws/events and verifiable completed score evidence; imported team identities are excluded.</p>
</details>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\show.blade.php ENDPATH**/ ?>