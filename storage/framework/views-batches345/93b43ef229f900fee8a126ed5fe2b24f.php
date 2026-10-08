

<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <a href="<?php echo e(url()->previous()); ?>" class="btn btn-link mb-3">&larr; Back</a>

  <div class="card mb-3">
    <div class="card-header">
      <h5 class="m-0">Fixture #<?php echo e($team_fixture->id); ?></h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Event</dt>
        <dd class="col-sm-9"><?php echo e(optional(optional($team_fixture->draw)->event)->name ?? '—'); ?></dd>

        <dt class="col-sm-3">Draw</dt>
        <dd class="col-sm-9"><?php echo e(optional($team_fixture->draw)->drawName ?? '—'); ?></dd>

        <dt class="col-sm-3">Round / Tie</dt>
        <dd class="col-sm-9">
          <?php echo e($team_fixture->round_name ?? $team_fixture->round); ?> /
          <?php echo e($team_fixture->tie_name ?? $team_fixture->tie); ?>

        </dd>

        <dt class="col-sm-3">Home (Region)</dt>
        <dd class="col-sm-9"><?php echo e($team_fixture->region1Name->short_name ?? $team_fixture->region1Name->region_name ?? 'TBD'); ?></dd>

        <dt class="col-sm-3">Away (Region)</dt>
        <dd class="col-sm-9"><?php echo e($team_fixture->region2Name->short_name ?? $team_fixture->region2Name->region_name ?? 'TBD'); ?></dd>

        <dt class="col-sm-3">Scheduled</dt>
        <dd class="col-sm-9">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team_fixture->scheduled_at): ?>
            <?php echo e(\Carbon\Carbon::parse($team_fixture->scheduled_at)->format('Y-m-d H:i')); ?>

          <?php else: ?>
            —
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </dd>

        <dt class="col-sm-3">Venue</dt>
        <dd class="col-sm-9"><?php echo e(optional($team_fixture->venue)->name ?? '—'); ?></dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="m-0">Match Players</h6>
    </div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team_fixture->fixturePlayers->isEmpty()): ?>
        <div class="alert alert-info mb-0">No player rows linked to this fixture yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>Home</th>
                <th></th>
                <th>Away</th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team_fixture->fixturePlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $fp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  // profile player objects (nullable)
                  $homeProfile = $fp->player1 ?? null;
                  $awayProfile = $fp->player2 ?? null;
                  // no-profile lookup (fallback)
                  $homeNo = $fp->team1_no_profile_id ? \App\Models\NoProfileTeamPlayer::find($fp->team1_no_profile_id) : null;
                  $awayNo = $fp->team2_no_profile_id ? \App\Models\NoProfileTeamPlayer::find($fp->team2_no_profile_id) : null;

                  // region labels (prefer short_name)
                  $homeRegion = $team_fixture->region1Name->short_name ?? $team_fixture->region1Name->region_name ?? '—';
                  $awayRegion = $team_fixture->region2Name->short_name ?? $team_fixture->region2Name->region_name ?? '—';

                  $homeName = $homeProfile?->full_name
                    ?? ($homeNo?->name . ' ' . ($homeNo?->surname ?? ''))
                    ?? 'TBD';

                  $awayName = $awayProfile?->full_name
                    ?? ($awayNo?->name . ' ' . ($awayNo?->surname ?? ''))
                    ?? 'TBD';
                ?>

                <tr>
                  <td><?php echo e($i + 1); ?></td>
                  <td>
                    <?php echo e($homeName); ?> <small class="text-muted">(<?php echo e($homeRegion); ?>)</small>
                  </td>
                  <td class="text-center">vs</td>
                  <td>
                    <?php echo e($awayName); ?> <small class="text-muted">(<?php echo e($awayRegion); ?>)</small>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\show.blade.php ENDPATH**/ ?>