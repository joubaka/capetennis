


<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.team-fixtures.partials.participant-revision-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset(mix('js/insert-score.js'))); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('title', 'Enter Fixture Scores'); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .score-tie-heading{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:.75rem}
  .score-tie-side{padding:1rem;border-radius:.5rem;overflow-wrap:anywhere;font-size:clamp(1.15rem,3vw,1.6rem);font-weight:700;line-height:1.3}
  .score-tie-home{background:#e4f3ef;color:#125b50;border-left:4px solid #16796b}
  .score-tie-away{background:#eaf0fc;color:#244a87;border-left:4px solid #3569b8}
  .score-tie-versus{color:#566477;font-size:.85rem;font-weight:700}
  @media(max-width:575px){.score-tie-heading{gap:.4rem}.score-tie-side{padding:.75rem .5rem}}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl py-4">
    <h2 class="mb-3">Enter Scores</h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($matrixDraw ?? null): ?>
      <a class="btn btn-outline-primary mb-3" href="<?php echo e(route('frontend.fixtures.enter-scores', $matrixDraw)); ?>">Back to round-robin matrix</a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedTie ?? null): ?>
        <?php
          $homeRegion = $selectedTie->homeTeam?->regions;
          $awayRegion = $selectedTie->awayTeam?->regions;
          $combinedSides = !empty($matrixDraw->team_draw_selection['mixed_sides']);
          $homeHeading = $combinedSides ? $selectedTie->home_side_name : (trim((string) $homeRegion?->short_name) ?: ($homeRegion?->region_name ?: $selectedTie->home_side_name));
          $awayHeading = $combinedSides ? $selectedTie->away_side_name : (trim((string) $awayRegion?->short_name) ?: ($awayRegion?->region_name ?: $selectedTie->away_side_name));
        ?>
        <section class="mb-4" aria-label="Region matchup">
          <h3 class="score-tie-heading mb-2">
            <span class="score-tie-side score-tie-home" title="<?php echo e($selectedTie->home_side_name); ?>"><?php echo e($homeHeading); ?></span>
            <span class="score-tie-versus">VS</span>
            <span class="score-tie-side score-tie-away" title="<?php echo e($selectedTie->away_side_name); ?>"><?php echo e($awayHeading); ?></span>
          </h3>
          <p class="small text-muted mb-0"><?php echo e($matrixDraw->drawName); ?> · Round <?php echo e($selectedTie->round_nr); ?></p>
        </section>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Scheduled</th>
                            <th>#</th>
                            <th class="d-none d-sm-table-cell">Round</th>
                            <th class="d-none d-md-table-cell">Match #</th>
                            <th>Home</th>
                            <th></th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="d-none d-lg-table-cell">Venue</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $homeNames = [];
                            $awayNames = [];
                            $homeRegionShort = $fx->region1Name?->short_name ?? null;
                            $awayRegionShort = $fx->region2Name?->short_name ?? null;
                            
                        ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx->fixturePlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fpRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                // HOME
                                if ($fpRow->team1_id && $fpRow->player1) {
                                    $name = $fpRow->player1->full_name;
                                    if($homeRegionShort) $name.=" ({$homeRegionShort})";
                                    $homeNames[]=$name;
                                }
                                elseif ($fpRow->team1_no_profile_id) {
                                    $np = \App\Models\NoProfileTeamPlayer::find($fpRow->team1_no_profile_id);
                                    if($np){
                                        $name = trim($np->name.' '.$np->surname);
                                        if($homeRegionShort) $name.=" ({$homeRegionShort})";
                                        $homeNames[]=$name;
                                    }
                                }
                                // AWAY
                                if ($fpRow->team2_id && $fpRow->player2) {
                                    $name = $fpRow->player2->full_name;
                                    if($awayRegionShort) $name.=" ({$awayRegionShort})";
                                    $awayNames[]=$name;
                                }
                                elseif ($fpRow->team2_no_profile_id) {
                                    $np2 = \App\Models\NoProfileTeamPlayer::find($fpRow->team2_no_profile_id);
                                    if($np2){
                                        $name = trim($np2->name.' '.$np2->surname);
                                        if($awayRegionShort) $name.=" ({$awayRegionShort})";
                                        $awayNames[]=$name;
                                    }
                                }
                            ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php
                            $homeLabel = count($homeNames)?collect($homeNames)->implode(' + '):'TBD';
                            $awayLabel = count($awayNames)?collect($awayNames)->implode(' + '):'TBD';
                            $display = $fx->scheduled_at ?? null;
                            [$homeClass, $awayClass] = \App\Support\ResultPresentation::classes($fx);
                        ?>
                        <tr id="row-<?php echo e($fx->id); ?>">
                            <td class="fw-bold">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($display): ?>
                                    <?php echo e(\Carbon\Carbon::parse($display)->format('Y-m-d H:i')); ?>

                                <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e($fx->id); ?></td>
                            <td class="d-none d-sm-table-cell"><?php echo e($fx->round_nr); ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e($fx->home_rank_nr); ?></td>
                            <td class="home-cell <?php echo e($homeClass); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->home_rank_nr): ?> (<?php echo e($fx->home_rank_nr); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo e($homeLabel); ?>

                                
                            </td>
                            <td class="text-center" style="width:32px;">
                                <span class="badge bg-light border text-secondary">vs</span>
                            </td>
                            <td class="away-cell <?php echo e($awayClass); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->away_rank_nr): ?> (<?php echo e($fx->away_rank_nr); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo e($awayLabel); ?>

                                
                            </td>
                            <td id="result-col-<?php echo e($fx->id); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php echo e($r->team1_score); ?>-<?php echo e($r->team2_score); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <span class="text-muted">No result</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="d-none d-lg-table-cell"><?php echo e(optional($fx->venue)->name ?? '—'); ?></td>
                            <td class="text-end" id="actions-col-<?php echo e($fx->id); ?>">
                                <?php echo $__env->make('frontend.fixtures.partials.actions', [
                                    'fixture' => $fx,
                                    'homeLabel' => $homeLabel,
                                    'awayLabel' => $awayLabel
                                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Score Modal -->
<div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <form id="editScoreForm" method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="fixture_id" id="editFixtureId">
        <div class="modal-header">
          <h5 class="modal-title">Enter Score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong id="fixtureTeams"></strong></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Set</th>
                  <th>Home</th>
                  <th>Away</th>
                </tr>
              </thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 3; $i++): ?>
                  <tr>
                    <td>Set <?php echo e($i); ?></td>
                    <td><input type="number" class="form-control form-control-sm" name="set<?php echo e($i); ?>_home" id="set<?php echo e($i); ?>Home" min="0"></td>
                    <td><input type="number" class="form-control form-control-sm" name="set<?php echo e($i); ?>_away" id="set<?php echo e($i); ?>Away" min="0"></td>
                  </tr>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer flex-column flex-sm-row">
          <button type="button" class="btn btn-outline-secondary w-100 mb-2 mb-sm-0" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary w-100">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.winner-home { background-color: rgba(40,167,69,.25)!important; }
.loser-home { background-color: rgba(220,53,69,.25)!important; }
.draw-cell { background-color: rgba(255,193,7,.25)!important; }
@media (max-width: 576px) {
    #editScoreModal .modal-dialog { margin: 0; }
    .table th, .table td { font-size: 0.85rem; padding: 0.25rem; }
    .modal-content { border-radius: 0; }
    .modal-header, .modal-footer { padding: 0.75rem; }
    .modal-footer .btn { font-size: 1rem; }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixtures\enter-score.blade.php ENDPATH**/ ?>