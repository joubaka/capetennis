<div class="p-3 border-bottom">
  <h5 class="mb-3 text-<?php echo e($color ?? 'secondary'); ?>"><?php echo e($age); ?></h5>

  <div class="table-responsive-sm">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="text-center" style="width: 40px;">#</th>
          <th>Region</th>
          <th class="text-end">Played</th>
          <th class="text-end">Wins</th>
          <th class="text-end">Losses</th>
          <th class="text-end">Points</th>
        </tr>
      </thead>
      <tbody>
        <?php $rank = 1; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $regionName => $stats): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td class="text-center fw-bold"><?php echo e($rank++); ?></td>
            <td class="fw-semibold text-truncate" style="max-width:120px"><?php echo e($regionName); ?></td>
            <td class="text-end"><span class="badge bg-label-secondary"><?php echo e($stats['played']); ?></span></td>
            <td class="text-end"><span class="badge bg-label-success"><?php echo e($stats['wins']); ?></span></td>
            <td class="text-end"><span class="badge bg-label-danger"><?php echo e($stats['losses']); ?></span></td>
            <td class="text-end"><span class="badge bg-label-<?php echo e($color ?? 'primary'); ?> fs-6"><?php echo e($stats['points']); ?></span></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\partials\table.blade.php ENDPATH**/ ?>