<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?php echo e($name); ?></title>
<style>@page { margin:7mm; } .venue-print-section + .venue-print-section { page-break-before:always; }</style>
</head><body>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($scheduleSource)): ?><p><?php echo e($scheduleSource === 'published' ? 'Published schedule' : 'Working schedule'); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($venueSections)): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $venueSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <section class="venue-print-section">
      <?php echo $__env->make('backend.draw.pdf.pdf-team', ['name' => $name.' - '.$section['venue']->name, 'fixtures' => $section['fixtures']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <h1><?php echo e($name); ?></h1><p>No fixtures found for <?php echo e($selectedDate ?: 'all days'); ?>.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
  <?php echo $__env->make('backend.draw.pdf.pdf-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\pdf\venue-sheets.blade.php ENDPATH**/ ?>