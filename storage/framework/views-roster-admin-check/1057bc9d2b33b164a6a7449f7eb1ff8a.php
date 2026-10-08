<div class="mb-5">
  <h5 class="mb-3"><?php echo e($age); ?></h5>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gender => $regions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h6 class="text-muted fw-bold mb-2"><?php echo e(strtoupper($gender)); ?></h6>

    <div class="table-responsive mb-4">
      <table class="table table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Region</th>
            <th class="text-center">Played</th>
            <th class="text-center">Wins</th>
            <th class="text-center">Losses</th>
            <th class="text-center">Points</th>
            <th class="text-center">Breakdown<br><small>(Boys / Girls / Mixed)</small></th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $regionName = $rank;
              $played = $row['Total']['played'] ?? 144;
              $wins = $row['Total']['wins'] ?? 0;
              $losses = $row['Total']['losses'] ?? 0;
              $points = $row['Total']['points'] ?? 0;

              $boys = $row['Breakdown']['boys_points'] ?? 0;
              $girls = $row['Breakdown']['girls_points'] ?? 0;
              $mixed = $row['Breakdown']['mixed_points'] ?? 0;
            ?>

            <tr>
              <td><?php echo e($loop->iteration); ?></td>
              <td><?php echo e(Str::limit($regionName, 40)); ?></td>
              <td class="text-center"><span class="badge bg-label-secondary"><?php echo e($played); ?></span></td>
              <td class="text-center"><span class="badge bg-label-success"><?php echo e($wins); ?></span></td>
              <td class="text-center"><span class="badge bg-label-danger"><?php echo e($losses); ?></span></td>
              <td class="text-center"><span class="badge bg-label-primary"><?php echo e($points); ?></span></td>
              <td class="text-center">
                <span class="badge bg-label-info me-1"><?php echo e($boys); ?></span>
                <span class="badge bg-label-warning me-1"><?php echo e($girls); ?></span>
                <span class="badge bg-label-secondary"><?php echo e($mixed); ?></span>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\partials\age-gender-table.blade.php ENDPATH**/ ?>