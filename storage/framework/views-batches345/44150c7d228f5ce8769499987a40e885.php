<div class="card mb-4"><div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ability['snapshot_stale'] ?? false): ?><p class="alert alert-warning text-dark">Last successful update: <?php echo e($ability['snapshot_as_of'] ?? 'Pending'); ?>. Awaiting the nightly refresh.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <h2 class="h4">Cape Tennis Shared Ability · provisional index</h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ability['reason']): ?>
        <p class="alert alert-warning text-dark"><?php echo e($ability['reason']); ?></p>
    <?php elseif($ability['headline']): ?>
        <?php ($estimate = $ability['headline']); ?>
        <p class="h2"><?php echo e($estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100'); ?></p>
        <p><?php echo e($estimate['cohort']); ?> · singles · comparison group <?php echo e($estimate['component']); ?> · <?php echo e($estimate['component_players']); ?> connected players</p>
        <p>Evidence confidence: <?php echo e(\App\Services\Performance\AbilityConfidenceDisplay::label((int) $estimate['confidence_index'], $estimate)); ?>. <?php echo e($estimate['played']); ?> played matches and <?php echo e($estimate['inferred']); ?> weaker inferred finish comparisons for this player.</p>
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
        <p class="small">Low, Medium and High describe the strength and freshness of recorded evidence, not rating accuracy or win probability. The nightly update reduces it as evidence ages. Dates use the match schedule where credible, otherwise conservative event start dates (<?php echo e($estimate['proxy_dated_matches']); ?> matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['played'] === 0): ?><p>With little direct evidence, the model pulls this estimate toward 50. That does not establish average playing strength.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['reason']): ?><p><?php echo e($estimate['reason']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
        <p class="h3">Unrated</p><p>No eligible connected individual singles evidence.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="alert alert-info text-dark">Compare only players in the same cohort and comparison group. This uncalibrated 0–100 index is not a win probability. Disconnected groups and separate performance scores cannot be compared.</p>
    <p>Calculated <?php echo e($ability['built_at']); ?> · <a href="<?php echo e(route('backend.player-performance.show', $player->id)); ?>">View shared comparison evidence</a></p>
</div></div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\player-performance\ability-card.blade.php ENDPATH**/ ?>