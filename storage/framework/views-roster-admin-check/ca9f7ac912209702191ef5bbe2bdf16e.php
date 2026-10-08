


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.team-fixtures.partials.participant-revision-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset(mix('js/draw-fixtures-show.js'))); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<style>
.team-fixtures-view .fixture-region { display: flex; align-items: center; gap: .45rem; font-weight: 700; color: #18324b; margin-bottom: .4rem; letter-spacing: .03em; }
.team-fixtures-view .fixture-region-logo { width: 28px; height: 28px; flex: 0 0 28px; object-fit: contain; }
.team-fixtures-view .fixture-players { display: flex; flex-wrap: wrap; gap: .35rem; max-width: 22rem; }
.team-fixtures-view .fixture-player-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .6rem; border: 1px solid #dce5ef; border-radius: .5rem; background: #f4f7fb; color: #334b66; font-size: .85rem; line-height: 1.4; white-space: normal; }
.team-fixtures-view .fixture-player-link:hover { background: #e7effa; border-color: #8daed7; color: #173e71; }
.team-fixtures-view .fixture-player-link:focus-visible { outline: 3px solid #497ab8; outline-offset: 2px; }
.team-fixtures-view .fixture-rank { color: #596c83; font-variant-numeric: tabular-nums; }
.team-fixtures-view .table > tbody > tr > td { padding: .8rem .9rem; }
.team-fixtures-view .table > thead > tr > th { padding: .85rem .9rem; white-space: nowrap; }
.team-fixtures-view .fixture-id { color: #697a8d; font-size: .8rem; }
.team-fixtures-view .fixture-group td { background: #edf2f8; font-weight: 600; color: #18324b; padding: .65rem .9rem !important; }
.team-fixtures-view .winner-home { background-color: #e8f5eb !important; }
.team-fixtures-view .loser-home { background-color: #fff1f0 !important; }
.team-fixtures-view .draw-cell { background-color: #fff8e4 !important; }
@media (max-width: 767px) {
  .team-fixtures-view .fixture-secondary { display: none; }
  .team-fixtures-view .table { min-width: 650px; }
  .team-fixtures-view .fixture-players { max-width: 15rem; }
}
</style>


<div class="container-xxl team-fixtures-view">
<div class="card">
<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div><h4 class="mb-1">Team fixtures</h4><p class="text-muted mb-0"><?php echo e($event?->name ?? 'Manage match lineups and scores'); ?></p></div>
  <span class="badge bg-label-primary"><?php echo e(number_format($fixtures->total())); ?> <?php echo e(\Illuminate\Support\Str::plural('fixture', $fixtures->total())); ?></span>
</div>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">

<thead class="table-light">
<tr>
<th class="fixture-secondary">#</th>
<th class="fixture-secondary">Draw</th>
<th class="fixture-secondary">Round</th>
<th>Match</th>
<th>Home</th>
<th>Away</th>
<th>Result</th>
<th>Scheduled</th>
<th>Venue</th>
<th class="text-end">Actions</th>
</tr>
</thead>

<tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

<?php
$homeClass = '';
$awayClass = '';

// Determine if this is a v2 tie-based rubber
$isV2 = !is_null($fx->team_tie_id);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->fixtureResults->count()): ?>
<?php $winner = $fx->winnerSide(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winner === 'home'): ?>
<?php $homeClass='winner-home'; $awayClass='loser-home'; ?>
<?php elseif($winner === 'away'): ?>
<?php $homeClass='loser-home'; $awayClass='winner-home'; ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php
$home = $fx->lineup_display['home'];
$away = $fx->lineup_display['away'];
$homeLabel = $home['region'].' — '.collect($home['players'])->pluck('name')->implode(' + ');
$awayLabel = $away['region'].' — '.collect($away['players'])->pluck('name')->implode(' + ');
$display = $fx->scheduled_at;
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first || $fixtures[$loop->index - 1]->draw_id !== $fx->draw_id || $fixtures[$loop->index - 1]->round_nr !== $fx->round_nr || $fixtures[$loop->index - 1]->tie_nr !== $fx->tie_nr): ?>
<tr class="fixture-group">
  <td colspan="10" class="d-none d-md-table-cell"><?php echo e($fx->draw?->drawName ?? 'Draw'); ?> <span class="mx-2 text-muted">/</span> Round <?php echo e($fx->round_nr); ?> <span class="mx-2 text-muted">/</span> <?php echo $__env->make('backend.team-fixtures.partials.region-badge', ['lineup' => $home], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <span class="mx-1">vs</span> <?php echo $__env->make('backend.team-fixtures.partials.region-badge', ['lineup' => $away], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
  <td colspan="7" class="d-md-none"><?php echo e($fx->draw?->drawName ?? 'Draw'); ?> / Round <?php echo e($fx->round_nr); ?> / <?php echo $__env->make('backend.team-fixtures.partials.region-badge', ['lineup' => $home], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <span class="mx-1">vs</span> <?php echo $__env->make('backend.team-fixtures.partials.region-badge', ['lineup' => $away], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
</tr>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<tr id="row-<?php echo e($fx->id); ?>">
<td class="fixture-secondary fixture-id"><?php echo e($fx->id); ?></td>
<td class="fixture-secondary"><?php echo e(optional($fx->draw)->drawName ?? '—'); ?></td>
<td class="fixture-secondary"><?php echo e($fx->round_nr); ?></td>
<td><?php echo e($fx->home_rank_nr ?? ($isV2 ? ($fx->rubber_name ?? $fx->rubber_code ?? '—') : '—')); ?></td>

<td class="home-cell <?php echo e($homeClass); ?>">
<?php echo $__env->make('backend.team-fixtures.partials.side-badges', ['fixture' => $fx, 'side' => 'home'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>

<td class="away-cell <?php echo e($awayClass); ?>">
<?php echo $__env->make('backend.team-fixtures.partials.side-badges', ['fixture' => $fx, 'side' => 'away'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>

<td id="result-col-<?php echo e($fx->id); ?>">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
<?php echo e($r->team1_score); ?>-<?php echo e($r->team2_score); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
<span class="badge bg-label-secondary">Awaiting score</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>

<td>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($display): ?>
<?php echo e(\Carbon\Carbon::parse($display)->format('Y-m-d H:i')); ?>

<?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>

<td><?php echo e(optional($fx->venue)->name ?? '—'); ?></td>

<td class="text-end">
  <a href="javascript:void(0);"
     id="edit-btn-<?php echo e($fx->id); ?>"
     class="btn btn-sm btn-outline-primary edit-score-btn"
     data-id="<?php echo e($fx->id); ?>" data-participant-revision="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($fx)); ?>"
     data-action="<?php echo e(route('backend.team-fixtures.update', $fx->id)); ?>"
     data-home="<?php echo e(e($homeLabel)); ?>"
     data-away="<?php echo e(e($awayLabel)); ?>"
     <?php $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
       data-set<?php echo e($r->set_nr); ?>_home="<?php echo e($r->team1_score); ?>"
       data-set<?php echo e($r->set_nr); ?>_away="<?php echo e($r->team2_score); ?>"
     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  >
    Edit score
  </a>
</td>
</tr>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="10" class="text-center">No fixtures found.</td></tr>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody>

</table>
</div>
<div class="px-3 pt-3"><?php echo e($fixtures->links()); ?></div>
</div>
</div>

  <!-- Edit Score Modal --> <div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-dialog-centered">     <div class="modal-content">       <form id="editScoreForm" method="POST" action="">         <?php echo csrf_field(); ?>         <?php echo method_field('PUT'); ?>         <div class="modal-header">           <h5 class="modal-title">Edit Score</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="fixtureTeams"></strong></p>           <div class="table-responsive">             <table class="table table-sm align-middle">               <thead>                 <tr>                   <th>Set</th>                   <th>Home</th>                   <th>Away</th>                 </tr>               </thead>               <tbody>                 <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 3; $i++): ?>                   <tr>                     <td>Set <?php echo e($i); ?></td>                     <td><input type="number" class="form-control" name="set<?php echo e($i); ?>_home" id="set<?php echo e($i); ?>Home" min="0"></td>                     <td><input type="number" class="form-control" name="set<?php echo e($i); ?>_away" id="set<?php echo e($i); ?>Away" min="0"></td>                   </tr>                 <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>               </tbody>             </table>           </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save</button>         </div>       </form>     </div>   </div> </div>  <!-- Edit Players Modal --> <div class="modal fade" id="editPlayersModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-lg modal-dialog-centered">     <div class="modal-content">       <form id="editPlayersForm" method="POST" action="">         <?php echo csrf_field(); ?>         <?php echo method_field('PUT'); ?>         <div class="modal-header">           <h5 class="modal-title">Edit Players</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="playersFixtureTeams"></strong></p>           <div class="row"> <div class="col-md-6">   <label class="form-label">Home Players</label>   <select class="form-select select2"            name="home_players[]"            id="homePlayers"            data-fixture-type="<?php echo e($team_fixture->fixture_type ?? 'singles'); ?>"            multiple>     <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allPlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>       <option value="<?php echo e($player->id); ?>"><?php echo e($player->full_name); ?></option>     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>   </select> </div>  <div class="col-md-6">   <label class="form-label">Away Players</label>   <select class="form-select select2"            name="away_players[]"            id="awayPlayers"            data-fixture-type="<?php echo e($team_fixture->fixture_type ?? 'singles'); ?>"            multiple>     <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allPlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>       <option value="<?php echo e($player->id); ?>"><?php echo e($player->full_name); ?></option>     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>   </select> </div>             </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save Players</button>         </div>       </form>     </div>   </div> </div>



<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\index.blade.php ENDPATH**/ ?>