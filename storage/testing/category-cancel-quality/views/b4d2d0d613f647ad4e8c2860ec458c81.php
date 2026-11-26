<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($regionSummary) && $regionSummary->isNotEmpty()): ?>
  <section class="result-region-summary" aria-label="Regional team results summary">
    <h3>Regional team results</h3>
    <p>Individual match wins / matches played for the selected age group and Setup filters.</p>
    <ul>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li><strong><?php echo e($region['name']); ?></strong><span>Won <?php echo e($region['wins']); ?>/<?php echo e($region['played']); ?> · Lost <?php echo e($region['losses']); ?> <span class="result-region-percentage">(<?php echo e($region['win_percentage'] !== null ? number_format($region['win_percentage'], 1).'%' : '—'); ?>)</span></span></li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<details class="result-rules">
  <summary>How the ranking works</summary>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($criteria['preset'] ?? 'legacy') === 'platteland_v1'): ?>
  <p><strong><?php echo e($criteria['label']); ?> (version <?php echo e($criteria['version']); ?>)</strong>. Roster bands 1–2, 3–4 and subsequent pairs come first. Completed singles and reverse singles count together. In each confirmed same-team pair, the higher player receives one win credit, with no sets added. Within each band, actual wins plus this credit decide the order. Equal totals on the same actual team follow roster position first; different teams use exact set win percentage. Equal percentages need manual review. An unbeaten player's actual played results may trigger an adjacent higher-band comparison; the roster credit does not count as a played match. This never promotes a player automatically. Event Settings control optional played-match and higher-band win limits and excluded regions.</p>
  <?php elseif(($criteria['preset'] ?? 'legacy') === \App\Services\TeamSelectionCriteria::INTER_DISTRICTS): ?>
  <p><strong><?php echo e($criteria['label']); ?> (version <?php echo e($criteria['version']); ?>)</strong>. Roster bands come first. Within a band, points come first, then set difference. Balanced head-to-head results can resolve an exact tie between different teams. Teammates follow roster order only when points and set difference are equal. Positions 1–2, 3–4, 5–6, 7–8 and 9–10 earn 100, 35, 12, 2 and 1 point per win respectively. A confirmed same-team pair can still give its higher player one automatic roster win at positions 1, 3, 5 and 7. That credit adds no match or set result. Adjacent-band comparisons support manual review.</p>
  <?php else: ?>
  <p>Roster bands come first: 1–2, then 3–4, 5–6, and 7–8. In each confirmed same-team pair, the higher roster player (1, 3, 5 or 7) receives one automatic roster win. Points equal actual completed match wins plus this one roster win, multiplied by the band weight: 100, 35, 12 or 2. Four actual matches plus the roster win therefore give five effective opportunities to earn points. Missing or uncertain pair evidence receives no automatic win. Actual match wins, losses and sets remain unchanged. For equal points, teammates with an unambiguous roster follow their roster positions before set difference. Players from different teams are compared by direct wins when every tied pair has the same nonzero number of counted matches. Unresolved ties require review at the selection cutoff. Candidate regions choose players; excluded result regions remove entire matches involving either side. Adjacent-band comparisons support manual review and do not promote players automatically. Comments are optional, including when changing order or selection.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</details>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ranking->isEmpty()): ?>
  <div class="result-empty" role="status"><i class="ti ti-scoreboard" aria-hidden="true"></i><strong>No completed singles results</strong><p>Check the regions and match formats in Setup, or choose another age group. Incomplete matches and doubles are excluded.</p></div>
<?php else: ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ranking->contains('cutoff_tie', true)): ?>
    <div class="result-review-notice" role="status"><strong>Review the selection cutoff tie.</strong> <?php echo e(($criteria['preset'] ?? 'legacy') === 'platteland_v1' ? 'Credited wins and set win percentage are equal.' : 'Both points and set difference are equal.'); ?> Choose your selection; add a comment if useful.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <h6>Calculated ranking</h6>
  <p class="result-heading-hint">Results stay in calculated order here. Use Reorder players to arrange your private draft and add optional comments.</p>
  <div class="result-list-columns" aria-hidden="true">
    <span>Pick</span><span>Rank</span><span>Player / record</span><span>Points</span>
  </div>
  <ol data-teams-table class="result-player-list" aria-label="Calculated player ranking and suggested top 10">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ranking; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $wonAllCountedBandMatches = !$player['band_attribution_uncertain'] && $player['own_band_record']['wins'] > 0 && $player['own_band_record']['losses'] === 0;
      $rating = $player['cape_tennis_rating'] ?? null;
      $ratingLabel = ($rating['overall'] ?? false) ? 'Overall CTR' : 'Cohort CTR';
    ?>
    <li data-result-player="<?php echo e($player['id']); ?>" data-player-name="<?php echo e($player['name']); ?>" data-player-region="<?php echo e($player['region']); ?>" data-player-suggested="<?php echo e($player['suggested'] ? '1' : '0'); ?>" data-player-cutoff="<?php echo e($player['cutoff_tie'] ? '1' : '0'); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['result-player-card', 'result-player-card-lost-all' => $player['lost_all_counted_band_matches'], 'result-player-card-won-all' => $wonAllCountedBandMatches]); ?>">
      <div class="result-player-heading">
        <label class="result-player-select"><input type="checkbox" class="form-check-input" data-selection-player value="<?php echo e($player['id']); ?>" <?php echo e($player['suggested'] ? 'checked' : ''); ?>><span class="visually-hidden">Select <?php echo e($player['name']); ?></span></label>
        <span class="result-position" title="Calculated position"><?php echo e($player['position']); ?></span>
        <div class="result-player-identity">
          <div class="result-player-name">
            <strong><?php echo e($player['name']); ?></strong>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_numeric($rating['score'] ?? null) && is_finite((float) $rating['score'])): ?>
              <span class="result-tag result-player-ctr" title="Informational rating within this comparison group"><?php echo e($ratingLabel); ?> <?php echo e(number_format((float) $rating['score'], 1)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating['snapshot_stale']): ?> · Stale <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><span class="visually-hidden"> · Within-group rating, informational only</span></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <span><?php echo e($player['region']); ?> · <?php echo e(implode(', ', $player['teams'])); ?></span>
          <span>Roster <?php echo e(implode(', ', $player['ranks'])); ?> · Band <?php echo e($player['band']); ?> · <?php echo e($player['wins']); ?>W / <?php echo e($player['losses']); ?>L</span>
        </div>
        <div class="result-player-points"><strong><?php echo e($player['points']); ?></strong><span>points</span></div>
      </div>
      <div class="result-player-tags">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['suggested']): ?><span class="result-tag result-tag-selected">Suggested top 10</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['cutoff_tie']): ?><span class="result-tag result-tag-review">Review cutoff tie</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['starting_credit']): ?><span class="result-tag result-tag-selected">Automatic roster win +1 · Roster <?php echo e($player['rank']); ?> over <?php echo e($player['rank'] + 1); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['higher_rank_wins']): ?><span class="result-tag"><?php echo e($player['higher_rank_wins']); ?> win(s) over higher-rostered opponents</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['lost_all_counted_band_matches']): ?><span class="result-tag result-tag-review">Review: lost all counted matches in Band <?php echo e($player['band']); ?> · 0W / <?php echo e($player['own_band_record']['losses']); ?>L</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($wonAllCountedBandMatches): ?><span class="result-tag result-tag-selected">Won all counted band matches · <?php echo e($player['own_band_record']['wins']); ?>W / 0L</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($player['platteland_manual_review'])): ?><span class="result-tag result-tag-review">Manual comparison needed: unbeaten played record against adjacent higher-band results</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <details class="result-player-evidence">
        <summary>Evidence &amp; comment</summary>
        <div class="result-evidence-body">
      <dl class="result-metrics">
        <div><dt>Points per win</dt><dd><?php echo e($player['points_per_win']); ?></dd></div>
        <div><dt>Singles wins</dt><dd><?php echo e($player['singles_wins']); ?></dd></div>
        <div><dt>Reverse wins</dt><dd><?php echo e($player['reverse_singles_wins']); ?></dd></div>
        <div><dt>Match wins</dt><dd><?php echo e($player['wins']); ?></dd></div>
        <div><dt>Sets won / lost</dt><dd><?php echo e($player['sets_won']); ?> / <?php echo e($player['sets_lost']); ?></dd></div>
        <div><dt>Set win percentage</dt><dd><?php echo e($player['sets_won'] + $player['sets_lost'] > 0 ? number_format(100 * $player['sets_won'] / ($player['sets_won'] + $player['sets_lost']), 1).'%' : '—'); ?></dd></div>
        <div><dt>Set difference</dt><dd><?php echo e($player['set_difference'] > 0 ? '+' : ''); ?><?php echo e($player['set_difference']); ?></dd></div>
      </dl>
      <p class="result-heading-hint">Points: (<?php echo e($player['wins']); ?> actual wins + <?php echo e($player['starting_credit']); ?> automatic roster win) × <?php echo e($player['points_per_win']); ?> = <?php echo e($player['points']); ?>. <?php echo e($player['roster_credit_reason'] ?? ''); ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating !== null): ?>
        <p class="result-review-notice">
          <strong><?php echo e($ratingLabel); ?> · <?php echo e($rating['cohort']); ?></strong>:
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating['score'] !== null): ?>
            <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'small','score' => $rating['score'],'overall' => $rating['overall'] ?? false,'local' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'small','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['score']),'overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['overall'] ?? false),'local' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?> · Rating rank <?php echo e($rating['position']); ?> · Comparison group <?php echo e($rating['component']); ?> · Overall playing cohort.
          <?php else: ?>
            Unavailable. <?php echo e($rating['unavailable_reason']); ?>

          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          Informational only.
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating['as_of']): ?> Saved as of <?php echo e($rating['as_of']); ?>.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating['snapshot_stale']): ?> Saved rating is stale; awaiting refresh.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['head_to_head']['tied_players'] > 1 && !$player['same_team_tiebreak']): ?>
        <p class="result-review-notice">Head-to-head among tied players: <?php echo e($player['head_to_head']['wins']); ?>W / <?php echo e($player['head_to_head']['losses']); ?>L. <?php echo e($player['head_to_head']['reason']); ?></p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <details class="result-review-notice result-review-details"><summary>Compare adjacent bands</summary><p>Direct records below use counted matches only. Overall records may involve different opponents and do not establish relative strength. These comparisons do not change the band order.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['adjacent_band_comparisons']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comparison): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="result-review-records"><strong><?php echo e(ucfirst($comparison['direction'])); ?> Band <?php echo e($comparison['band']); ?></strong>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparison['direct_matches']): ?>
            <span>Direct meetings: <?php echo e($comparison['direct_wins']); ?>W / <?php echo e($comparison['direct_losses']); ?>L · Sets <?php echo e($comparison['sets_won']); ?> / <?php echo e($comparison['sets_lost']); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparison['direction'] === 'higher' && $comparison['direct_wins']): ?><strong>Review: <?php echo e($comparison['direct_wins']); ?> direct win(s) over the next higher band</strong><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $comparison['opponents']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($opponent['name']); ?> · Roster <?php echo e(implode(', ', $opponent['ranks'])); ?> · <?php echo e($opponent['wins']); ?>W / <?php echo e($opponent['losses']); ?>L · Sets <?php echo e($opponent['sets_won']); ?> / <?php echo e($opponent['sets_lost']); ?> from this player's perspective</span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php else: ?><p>No direct meetings with this band in the counted matches.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $comparison['candidate_records']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($record['name']); ?> · Roster <?php echo e(implode(', ', $record['ranks'])); ?> · Overall <?php echo e($record['wins']); ?>W / <?php echo e($record['losses']); ?>L · Sets <?php echo e($record['sets_won']); ?> / <?php echo e($record['sets_lost']); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </details>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($player['platteland_manual_review'])): ?>
        <details class="result-evidence"><summary>Platteland manual comparison — no automatic promotion</summary><p>This player won all <?php echo e($player['wins']); ?> counted played matches. Review other results before deciding whether to change the private draft.</p><ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['platteland_manual_review']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $other): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($other['name']); ?> · Band <?php echo e($other['band']); ?> · <?php echo e($other['wins']); ?>W / <?php echo e($other['losses']); ?>L</li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['cross_band_review']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <details class="result-review-notice result-review-details"><summary><strong>Review consecutive roster records</strong> · <?php echo e($review['team']); ?> · Ranks <?php echo e(implode(', ', $review['ranks'])); ?></summary><p>Each consecutive player has at least two completed matches and more wins than losses in this setup. Different opponents can affect these records; the band-first order is retained.</p><div class="result-review-records"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $review['records']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank => $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span>Rank <?php echo e($rank); ?>: <?php echo e($record['wins']); ?>W / <?php echo e($record['losses']); ?>L</span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($player['ranks']) > 1): ?><p class="result-review-notice">Roster ranks differ across teams. Ranking uses rank <?php echo e($player['rank']); ?>; review before selection.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['band_attribution_uncertain']): ?> Ranks span different bands, so the lost-all-in-band flag is withheld.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="result-player-disclosures">
        <details class="result-reason"><summary>Comment (optional) <span>Add context if useful; comments are never required</span></summary><label>Comment for <?php echo e($player['name']); ?><input class="form-control" type="text" maxlength="2000" data-selection-reason="<?php echo e($player['id']); ?>" placeholder="Add an optional comment"></label></details>
        <details class="result-matches"><summary>Match results <span><?php echo e(count($player['matches'])); ?> completed</span></summary><ul>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['matches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><span class="result-match-outcome <?php echo e($match['won'] ? 'is-win' : 'is-loss'); ?>"><?php echo e($match['won'] ? 'Won' : 'Lost'); ?></span><div><strong><?php echo e($match['opponent']); ?></strong><span><?php echo e($match['region']); ?> · Roster <?php echo e($match['opponent_rank']); ?> · <?php echo e($match['format'] === 'reverse_singles' ? 'Reverse Singles' : 'Singles'); ?></span></div><span class="result-match-score">Home–away: <?php echo e($match['score']); ?></span></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul></details>
      </div>
        </div>
      </details>
    </li>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ol>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_table\result-selection.blade.php ENDPATH**/ ?>