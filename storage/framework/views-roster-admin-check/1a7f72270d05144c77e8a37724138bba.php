<?php $__env->startSection('title', $venue->name . ' – Fixtures'); ?>

<?php $__env->startSection('content'); ?>
<style>
  .winner-home {
    background-color: rgba(40, 167, 69, 0.25) !important;
    color: #155724 !important;
  }
  .loser-home {
    background-color: rgba(220, 53, 69, 0.25) !important;
    color: #721c24 !important;
  }
  .draw-cell {
    background-color: rgba(255, 193, 7, 0.25) !important;
    color: #856404 !important;
  }

  @media (max-width: 768px) {
    /* Hide Draw and Status columns on mobile to save space since we added Time to the front */
    th:nth-child(2), td:nth-child(2), 
    th:nth-child(7), td:nth-child(7) { 
      display: none !important;
    }
    
    .btn-sm {
      padding: 0.15rem 0.3rem !important;
    }

    .badge {
      font-size: 0.7rem !important;
    }
  }
</style>

<?php
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'Plat' => 'bg-label-primary',
            'Wine' => 'bg-label-info',
            'Drak' => 'bg-label-success',
            'Eden' => 'bg-label-warning',
            'BO' => 'bg-label-danger',
            'WP' => 'bg-label-dark',
        ];

        $palette = [
            'bg-label-primary','bg-label-success','bg-label-warning',
            'bg-label-danger','bg-label-info','bg-label-dark','bg-label-secondary'
        ];

        return $map[$short] ?? $palette[abs(crc32($short)) % count($palette)];
    }
}

if (!function_exists('team_label')) {
    function team_label($team, $noProfileTeam) {
        $names = [];
        if ($team && $team->count()) {
            foreach ($team as $player) {
                $names[] = $player->full_name;
            }
        }
        if ($noProfileTeam && $noProfileTeam->count()) {
            foreach ($noProfileTeam as $np) {
                $names[] = trim($np->name . ' ' . $np->surname);
            }
        }
        return count($names) ? implode(' + ', $names) : 'TBD';
    }
}
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($venues) && $venues->count()): ?>
  <div class="mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="fw-bold me-2">Jump to venue:</span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $v->id])); ?>"
           class="btn btn-sm <?php echo e($venue->id == $v->id ? 'btn-primary' : 'btn-outline-primary'); ?>">
          <?php echo e($v->name); ?>

        </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h3 class="mb-0">
      <?php echo e($event->name); ?> – Fixtures at <?php echo e($venue->name); ?>

    </h3>
    <div class="d-flex gap-2">
      <a href="<?php echo e(route('events.show', $event->id)); ?>" class="btn btn-sm btn-danger">Back to Event</a>
    </div>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle" style="min-width: 900px;">
        <thead class="table-dark sticky-top">
          <tr>
            <th>Time</th>
            <th>Draw</th>
            <th>Team 1</th>
            <th>Team 2</th>
            <th class="text-center">Score</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $homeClass = $awayClass = '';
              $status = 'Pending';
              if ($fx->fixtureResults && $fx->fixtureResults->count()) {
                  $winner = $fx->winnerSide();
                  if ($winner === 'home') {
                      $homeClass = 'winner-home'; $awayClass = 'loser-home'; $status = 'Home Win';
                  } elseif ($winner === 'away') {
                      $homeClass = 'loser-home'; $awayClass = 'winner-home'; $status = 'Away Win';
                  } else {
                      $status = 'In progress';
                  }
              }

            ?>
            <tr id="row-<?php echo e($fx->id); ?>">
              <td class="fw-bold">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->scheduled_at): ?>
                  <?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('D H:i')); ?>

                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td><?php echo e(optional($fx->draw)->drawName ?? '-'); ?></td>
              <td class="home-cell <?php echo e($homeClass); ?>">
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              </td>
              <td class="away-cell <?php echo e($awayClass); ?>">
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              </td>
              <td id="result-col-<?php echo e($fx->id); ?>" class="text-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $fx->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                  <span class="badge bg-info text-dark me-1" style="font-size: 0.75rem;">
                    <?php echo e($r->team1_score); ?> - <?php echo e($r->team2_score); ?>

                  </span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                  <span class="text-muted">No Score</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <span class="badge <?php echo e($status == 'Pending' ? 'bg-secondary' : ($status == 'Draw' ? 'bg-warning' : ($status == 'Home Win' ? 'bg-success' : 'bg-primary'))); ?>">
                  <?php echo e($status); ?>

                </span>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No fixtures scheduled for this venue.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\byVenue.blade.php ENDPATH**/ ?>