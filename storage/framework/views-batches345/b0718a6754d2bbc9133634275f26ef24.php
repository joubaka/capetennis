<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo e($event->name); ?> – Players by Region</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 12px;
      color: #000;
    }
    h2, h3, h4 {
      margin-bottom: 4px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: left;
    }
    th {
      background-color: #f2f2f2;
    }
    .region-header {
      background-color: #0056b3;
      color: white;
      padding: 8px;
      font-size: 15px;
      margin-top: 25px;
    }
    .team-header {
      background-color: #f8f9fa;
      border: 1px solid #ddd;
      padding: 6px;
      font-weight: bold;
      margin-top: 10px;
    }
    .badge {
      display: inline-block;
      padding: 3px 6px;
      border-radius: 4px;
      font-size: 11px;
    }
    .bg-success { background: #d4edda; color: #155724; }
    .bg-danger { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>

  <h2><?php echo e($event->name); ?> — Players by Region</h2>
  <p><strong>Date:</strong> <?php echo e(now()->format('d M Y')); ?></p>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="region-header"><?php echo e($region->region_name); ?></div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="team-header">
        <?php echo e($team->name); ?> 
        (Team ID: <?php echo e($team->id); ?>)
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->published): ?>
          <span class="badge bg-success">Published</span>
        <?php else: ?>
          <span class="badge bg-danger">Not Published</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Player Name</th>
            <th>Email</th>
            <th>Cell</th>
            <th>Pay Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $profiles = $team->players()->withPivot('rank','pay_status')->orderBy('team_players.rank')->get();
            $noProfiles = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get() : collect();
            $max = max($profiles->count(), $noProfiles->count());
          ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $max; $i++): ?>
            <?php
              $profile = $profiles[$i] ?? null;
              $noProfile = $team->noProfile ? ($noProfiles[$i] ?? null) : null;
              $payStatus = $profile?->pivot?->pay_status ?? 0;
            ?>
            <tr>
              <td><?php echo e($i + 1); ?></td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile): ?>
                  <?php echo e($profile->name); ?> <?php echo e($profile->surname); ?>

                <?php elseif($noProfile): ?>
                  <?php echo e($noProfile->name); ?> <?php echo e($noProfile->surname); ?>

                <?php else: ?>
                  —
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td><?php echo e($profile?->email ?? $noProfile?->email ?? '—'); ?></td>
              <td><?php echo e($profile?->cellNr ?? $noProfile?->cellNr ?? '—'); ?></td>
              <td>
                <span class="badge <?php echo e($payStatus ? 'bg-success' : 'bg-danger'); ?>">
                  <?php echo e($payStatus ? 'Paid' : 'Not Paid'); ?>

                </span>
              </td>
            </tr>
          <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\exports\all-players-pdf.blade.php ENDPATH**/ ?>