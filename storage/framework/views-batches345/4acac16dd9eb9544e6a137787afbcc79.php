<div class="p-3 border-bottom mb-4">
  <h5 class="mb-3 text-<?php echo e($color); ?>"><?php echo e($title); ?></h5>

  <div class="table-responsive">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Region</th>
          <th class="text-end">Singles</th>
          <th class="text-end">Doubles</th>
          <th class="text-end">Reverse</th>
          <th class="text-end">Mixed</th>
          <th class="text-end">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $regionName => $types): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td class="fw-semibold"><?php echo e($regionName); ?></td>
            <td class="text-end"><span class="badge bg-label-secondary"><?php echo e($types['Singles']['points'] ?? 0); ?></span></td>
            <td class="text-end"><span class="badge bg-label-secondary"><?php echo e($types['Doubles']['points'] ?? 0); ?></span></td>
            <td class="text-end"><span class="badge bg-label-secondary"><?php echo e($types['Reverse']['points'] ?? 0); ?></span></td>
            <td class="text-end"><span class="badge bg-label-secondary"><?php echo e($types['Mixed']['points'] ?? 0); ?></span></td>
            <td class="text-end"><span class="badge bg-label-<?php echo e($color); ?> fs-6"><?php echo e($types['Total']['points'] ?? 0); ?></span></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\partials\breakdown-table.blade.php ENDPATH**/ ?>