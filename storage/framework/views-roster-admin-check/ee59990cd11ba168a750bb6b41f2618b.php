<tbody>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $category->nominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr data-nominationid="<?php echo e($nomination->id); ?>">
      <td><strong><?php echo e($k + 1); ?></strong></td>
      <td><?php echo e($nomination->player->name); ?> <?php echo e($nomination->player->surname); ?></td>
      <td><?php echo e($nomination->player->email); ?></td>
      <td><span class="badge bg-label-primary me-1"><?php echo e($nomination->player->cellNr); ?></span></td>
      <td>
        <span class="btn btn-sm btn-secondary sendEmail"
              data-bs-target="#createEmail"
              data-bs-toggle="modal"
              data-email="<?php echo e($nomination->player->email); ?>"
              data-totype="one">
          <i class="ti ti-pencil me-1"></i>Email Player
        </span>
        <span class="btn btn-sm btn-danger nomination-remove"
              data-id="<?php echo e($nomination->id); ?>"
              data-player="<?php echo e($nomination->player->name); ?> <?php echo e($nomination->player->surname); ?>"
              data-categoryeventid="<?php echo e($category->id); ?>">
          <i class="ti ti-trash me-1"></i>Remove
        </span>
      </td>
    </tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr><td colspan="5" class="text-center text-muted">No nominations yet</td></tr>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\nominations\partials\table.blade.php ENDPATH**/ ?>