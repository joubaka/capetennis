<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($name); ?></title>
  <style>
    body { margin:0; padding:24px; font-family:Arial,sans-serif; color:#172e45; background:#fff; }
    .print-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin-bottom:24px; }
    .print-toolbar button { min-height:44px; padding:10px 18px; background:#172e45; color:white; border:0; border-radius:6px; font:inherit; cursor:pointer; }
    .print-sheet { overflow-x:auto; }
    .print-day-form { display:flex; flex-wrap:wrap; align-items:end; gap:12px; margin-bottom:16px; }
    .print-day-form label { display:block; margin-bottom:4px; }
    .print-day-form select, .print-day-form button, .print-day-form a { min-height:44px; box-sizing:border-box; padding:10px 14px; font:inherit; }
    .print-day-form a { color:#172e45; border:1px solid #172e45; border-radius:6px; text-decoration:none; }
    .venue-print-section { margin-bottom:24px; }
    @media print { @page { size:A4 landscape; margin:7mm; } body { padding:0; } .print-toolbar, .print-day-form { display:none; } .print-sheet { overflow:visible; } thead { display:table-header-group; } tr { break-inside:avoid; } .venue-print-section + .venue-print-section { page-break-before:always; break-before:page; } }
  </style>
</head>
<body>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($age)): ?>
  <form class="print-day-form" method="get" action="<?php echo e(route('headoffice.venuePrintPack', $event)); ?>">
    <input type="hidden" name="age" value="<?php echo e($age); ?>">
    <div><label for="age-venue-print-day">Day to print</label><select name="date" id="age-venue-print-day">
      <option value="" <?php if(empty($selectedDate)): echo 'selected'; endif; ?>>All days</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($day); ?>" <?php if($selectedDate === $day): echo 'selected'; endif; ?>><?php echo e(\Carbon\Carbon::parse($day)->format('l j M Y')); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedDate && !$availableDays->contains($selectedDate)): ?>
      <option value="<?php echo e($selectedDate); ?>" selected><?php echo e(\Carbon\Carbon::parse($selectedDate)->format('l j M Y')); ?> — no scheduled matches</option>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select></div>
    <button type="submit">Show day</button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedDate): ?><a href="<?php echo e(route('headoffice.venuePrintPack', ['event' => $event, 'age' => $age])); ?>">All days</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a href="<?php echo e(route('headoffice.printOptions', $event)); ?>">Back to print options</a>
  </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($draw)): ?>
  <form class="print-day-form" method="get" action="<?php echo e(route('fixture.create.pdf')); ?>">
    <input type="hidden" name="fixtures" value="<?php echo e($draw->id); ?>"><input type="hidden" name="preview" value="1">
    <div><label for="team-print-day">Day to print</label><select name="date" id="team-print-day">
      <option value="" <?php if(empty($selectedDate)): echo 'selected'; endif; ?>>All days</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($day); ?>" <?php if($selectedDate === $day): echo 'selected'; endif; ?>><?php echo e(\Carbon\Carbon::parse($day)->format('l j M Y')); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedDate && !$availableDays->contains($selectedDate)): ?>
      <option value="<?php echo e($selectedDate); ?>" selected><?php echo e(\Carbon\Carbon::parse($selectedDate)->format('l j M Y')); ?> — no scheduled matches</option>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select></div>
    <button type="submit">Show day</button>
    <a href="<?php echo e(route('fixture.create.pdf', ['fixtures' => $draw->id, 'date' => $selectedDate])); ?>">Download PDF</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedDate): ?><a href="<?php echo e(route('fixture.create.pdf', ['fixtures' => $draw->id, 'preview' => 1])); ?>">All days</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </form>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="print-toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button><span>Use your browser’s print dialog to choose a printer or save this sheet as a PDF.</span></div>
  <main class="print-sheet">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($venueSections)): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $venueSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <section class="venue-print-section" data-venue-id="<?php echo e($section['venue']->id); ?>">
        <?php echo $__env->make('backend.draw.pdf.pdf-team', ['name' => $name.' · '.$section['venue']->name, 'fixtures' => $section['fixtures']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </section>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <h1><?php echo e($name); ?></h1><p>No fixtures found for <?php echo e($selectedDate ?: 'all days'); ?>.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
      <?php echo $__env->make('backend.draw.pdf.pdf-team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </main>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\pdf\team-print-preview.blade.php ENDPATH**/ ?>