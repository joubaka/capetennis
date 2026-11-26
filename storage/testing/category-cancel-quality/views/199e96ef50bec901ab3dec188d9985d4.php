
<?php $__env->startSection('title', 'Team ties – '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<style>[data-tie-card][hidden],[data-tie-round-group][hidden]{display:none!important;}</style>
<div class="container-xxl">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo e($draw->drawName); ?>: team ties</h1>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff'): ?>
      <p class="text-muted mb-0"><?php echo e($draw->formatDisplayLabel()); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="d-flex flex-wrap gap-2">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?><a data-draw-guide-open class="btn btn-primary" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Continue setup</a><?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.schedule', $draw)): ?><a class="btn btn-outline-primary" href="<?php echo e(isset($draw->team_draw_selection['competition']) ? route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]) : route('backend.team-schedule.page', $draw)); ?>">Schedule rubbers</a><?php endif; ?>
      <a class="btn btn-outline-primary" href="<?php echo e(route('backend.team-draw.standings', $draw)); ?>">Standings</a>
    </div>
  </div>
  <?php echo $__env->make('backend.schedule.partials.draw-day-publication', ['publicationDraw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('backend.team-draw.partials.organiser-decisions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Services\TeamRoundRobinMatrixService::class)->supports($draw)): ?>
    <?php echo $__env->make('backend.team-draw.partials.round-robin-matrix', ['matrix' => app(\App\Services\TeamRoundRobinMatrixService::class)->forDraw($draw)], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($draw->team_draw_selection['competition'])): ?>
    <?php
      $competition = $draw->team_draw_selection['competition'];
      $playoffReadiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
    ?>
    <?php echo $__env->make('backend.team-draw.partials.playoff-readiness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="card card-body mb-3">
      <h2 class="h5"><?php echo e(($competition['phase'] ?? '') === 'playoff' ? 'Placement playoffs' : 'One round-robin group'); ?></h2>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->team_scoring_rules['undecided'] ?? false): ?>
        <p>Scoring: <strong>To be decided.</strong> Scheduling and lineup review are available. Confirm scoring before publishing ties or entering results.</p>
      <?php else: ?><p>Scoring has been confirmed for this phase.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-draw.updateTeamDraw', $draw)): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$draw->published && !$draw->locked && !$draw->teamTies()->whereNotNull('published_at')->exists()): ?>
          <a class="btn btn-outline-primary align-self-start" href="<?php echo e(route('team-draw.competition.scoring', $draw)); ?>">Review and confirm scoring</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($competition['phase'] ?? '') === 'round_robin'): ?>
        <p class="mt-3">Complete every encounter and resolve tied standings before qualifying teams. Playoff qualification freezes these round-robin results.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($competition['playoff_draw_id'])): ?>
          <a href="<?php echo e(route('backend.team-draw.operations', $competition['playoff_draw_id'])); ?>">Open placement playoffs</a>
        <?php else: ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-draw.createFormat', $draw->event)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playoffReadiness['draft_playoff'] ?? null): ?>
              <a class="btn btn-outline-primary mb-3" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $playoffReadiness['draft_playoff']])); ?>">Open draft playoff programme</a>
            <?php else: ?>
              <form method="POST" action="<?php echo e(route('team-draw.competition.draft-playoffs', $draw)); ?>" class="mb-3"><?php echo csrf_field(); ?><button class="btn btn-outline-primary" <?php if(!($playoffReadiness['can_prepare'] ?? false)): echo 'disabled'; endif; ?>>Prepare draft playoff fixtures</button></form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <form method="POST" action="<?php echo e(route('team-draw.competition.playoffs', $draw)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" <?php if(!($playoffReadiness['can_create'] ?? false)): echo 'disabled'; endif; ?>><?php echo e(($playoffReadiness['draft_playoff'] ?? null) ? 'Confirm qualifiers & fill draft fixtures' : 'Create 1st/2nd and 3rd/4th playoffs'); ?></button></form>
          <?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->isPlacementDraft()): ?><div class="alert alert-info mt-3">Proposed playoff programme: schedule the round-robin position placeholders. Confirm qualifiers in the round-robin phase to fill these fixtures, retaining saved times and courts. Scoring and publication require confirmed teams.</div>
          <a class="btn btn-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>">Plan proposed playoff schedule</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <a href="<?php echo e(route('backend.team-draw.standings', $competition['source_draw_id'])); ?>">Round-robin standings</a>
        <p class="mt-2">Tie 1 decides places 1 and 2. Tie 2 decides places 3 and 4. A level match total remains unresolved and must be reviewed by the organiser.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $placementTie): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($outcome = app(\App\Services\TeamStandingsService::class)->tieOutcome($placementTie)); ?>
          <p><?php echo e($placementTie->tie_nr === 1 ? '1st / 2nd' : '3rd / 4th'); ?>:
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outcome['complete'] && $outcome['winner_team_id']): ?>
              <?php echo e($outcome['winner_team_id'] === $placementTie->home_team_id ? $placementTie->home_side_name : $placementTie->away_side_name); ?> wins (<?php echo e($outcome['home']); ?>–<?php echo e($outcome['away']); ?>)
            <?php else: ?> Awaiting a decisive result <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </p>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p><strong>1. Review players → 2. Validate tie → 3. Publish tie.</strong></p>
  <p>Review the players for each rubber, validate the complete tie, then publish it before entering scores. Draw publication controls public visibility separately.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked || $draw->published): ?><div class="alert alert-info">This draw is protected. Tie validation and publication changes are unavailable.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div id="team-tie-message" class="d-none" role="status" aria-live="polite"></div>
  <div class="card card-body mb-3"><div class="row g-3">
    <div class="col-md-6"><label for="tie-round-filter" class="form-label">Round</label><select id="tie-round-filter" class="form-select"><option value="">All rounds</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ties->pluck('round_nr')->unique()->sort(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($round); ?>">Round <?php echo e($round); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div class="col-md-6"><label for="tie-status-filter" class="form-label">Tie status</label><select id="tie-status-filter" class="form-select"><option value="">All statuses</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ties->pluck('status')->unique(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($status); ?>"><?php echo e(ucfirst($status)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
  </div><p class="small mt-2 mb-0" data-tie-filter-count role="status" aria-live="polite"></p></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ties->groupBy('round_nr'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roundNumber => $roundTies): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<section data-tie-round-group><h2 class="h5 mt-4">Round <?php echo e($roundNumber); ?></h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roundTies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tie): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($protected = $draw->isPlacementDraft() || $draw->locked || $draw->published || $tie->isLocked() || $tie->published_at || $tie->winner_team_id); ?>
      <details class="card mb-3" data-tie-card data-round="<?php echo e($roundNumber); ?>" data-status="<?php echo e($tie->status); ?>">
        <summary class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2" style="min-height:44px">
          <div><h3 class="h6 mb-1"><?php echo e($tie->home_side_name ?? 'Home'); ?> vs <?php echo e($tie->away_side_name ?? 'Away'); ?></h3>
            <span class="badge bg-label-secondary"><?php echo e(ucfirst($tie->status)); ?></span>
          </div>
        </summary>
          <div class="d-flex flex-wrap gap-2 p-3">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('validateTie', $tie)): ?>
              <button type="button" class="btn btn-sm btn-outline-primary team-tie-action" data-url="<?php echo e(route('team-draw.ties.validate', $tie)); ?>" <?php if($protected || $tie->status === 'validated'): echo 'disabled'; endif; ?>>Validate tie</button>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publishTie', $tie)): ?>
              <button type="button" class="btn btn-sm btn-primary team-tie-action" data-url="<?php echo e(route('team-draw.ties.publish', $tie)); ?>" <?php if($protected || $tie->status !== 'validated'): echo 'disabled'; endif; ?>>Publish tie</button>
            <?php endif; ?>
          </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>Rubber</th><th>Match</th><th>Schedule</th><th>Actions</th></tr></thead>
          <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $tie->rubbers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rubber): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            <tr><td><?php echo e($rubber->rubber_sequence); ?></td><td><?php echo e($rubber->rubber_name ?? ucwords(str_replace('_', ' ', $rubber->rubber_code ?? 'Match'))); ?></td>
              <td><?php echo e($rubber->scheduled_at?->format('d M H:i') ?? 'Unscheduled'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rubber->court_label): ?><span class="text-muted"><?php echo e($rubber->court_label); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td><div class="d-flex flex-wrap gap-2">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.view', $rubber)): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.team-fixtures.show', $rubber)); ?>">Review players</a><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($draw->isPlacementDraft())): ?>
                  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.saveScore', $rubber)): ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo e(route('frontend.fixtures.enter-scores', $draw)); ?>">Enter scores</a>
                  <?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.update', $rubber)): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.team-fixtures.edit', $rubber)); ?>">Edit schedule</a><?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><tr><td colspan="4">No rubbers have been generated for this tie.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
        </table></div>
      </details>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="alert alert-info">No team ties have been generated.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script>
const tieCards = Array.from(document.querySelectorAll('[data-tie-card]'));
function filterTies() {
  const round = document.getElementById('tie-round-filter').value, status = document.getElementById('tie-status-filter').value;
  let shown = 0;
  tieCards.forEach(card => { card.hidden = Boolean((round && card.dataset.round !== round) || (status && card.dataset.status !== status)); if (!card.hidden) shown++; });
  document.querySelectorAll('[data-tie-round-group]').forEach(group => { group.hidden = !Array.from(group.querySelectorAll('[data-tie-card]')).some(card => !card.hidden); });
  document.querySelector('[data-tie-filter-count]').textContent = `${shown} of ${tieCards.length} ties shown`;
}
document.getElementById('tie-round-filter').addEventListener('change', filterTies);
document.getElementById('tie-status-filter').addEventListener('change', filterTies);
filterTies();
</script>
<script src="<?php echo e(asset('js/team-tie-operations.js')); ?>?v=<?php echo e(filemtime(public_path('js/team-tie-operations.js'))); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-draw\operations.blade.php ENDPATH**/ ?>