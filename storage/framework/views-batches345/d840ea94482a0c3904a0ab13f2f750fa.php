<div class="mb-5">
  <h4 class="text-center my-3">🏆 <?php echo e($age); ?> Scoreboard</h4>

  <table class="table table-bordered align-middle text-center">
    <thead class="table-dark">
      <tr>
        <th>Region</th>
        <th>Singles</th>
        <th>Doubles</th>
        <th>Mixed</th>
        <th>Reverse</th>
        <th>Total</th>
      </tr>
    </thead>

    <tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['Girls', 'Boys', 'Overall']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gender): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($regions[$gender])): ?>
          <tr class="table-secondary">
            <td colspan="6" class="fw-bold text-start"><?php echo e(strtoupper($gender)); ?></td>
          </tr>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions[$gender]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region => $types): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="fw-semibold"><?php echo e($region); ?></td>
              <td><?php echo e($types['Singles']['points'] ?? 0); ?></td>
              <td><?php echo e($types['Doubles']['points'] ?? 0); ?></td>
              <td><?php echo e($types['Mixed']['points'] ?? 0); ?></td>
              <td><?php echo e($types['Reverse']['points'] ?? 0); ?></td>
              <td class="fw-bold"><?php echo e($types['Total']['points'] ?? 0); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody>
  </table>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\partials\age-table.blade.php ENDPATH**/ ?>