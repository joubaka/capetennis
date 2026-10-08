
<table class="table table-sm table-borderless mb-0">
  <tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr class="health-row">
      <td style="width:2rem; text-align:center; vertical-align:middle">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['status'] === 'ok'): ?>     <span class="badge-ok"     style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        <?php elseif($item['status'] === 'warn'): ?>    <span class="badge-warn"    style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        <?php else: ?>                             <span class="badge-critical" style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </td>
      <td>
        <div class="health-value"><?php echo e($item['label']); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($item['detail'])): ?>
          <div class="health-detail"><?php echo e($item['detail']); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </td>
      <td class="text-end pe-3">
        <span class="health-value"><?php echo e($item['value']); ?></span>
      </td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </tbody>
</table>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\platform\_health_table.blade.php ENDPATH**/ ?>