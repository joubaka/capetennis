<svg xmlns="http://www.w3.org/2000/svg" width="<?php echo e($layout['width']); ?>" height="<?php echo e($layout['height']); ?>" viewBox="0 0 <?php echo e($layout['width']); ?> <?php echo e($layout['height']); ?>" preserveAspectRatio="xMinYMin meet">
  <rect width="100%" height="100%" fill="#ffffff"/>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $layout['boards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $board): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <text x="0" y="<?php echo e($board['sectionTop'] + 18); ?>" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="15" font-weight="700"><?php echo e($board['section']); ?></text>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $board['roundHeadings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <text x="<?php echo e($heading['x']); ?>" y="<?php echo e($heading['y']); ?>" fill="#405064" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" font-weight="700"><?php echo e(strtoupper($heading['label'])); ?></text>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $board['connections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($mid = ($line['x1'] + $line['x2']) / 2); ?>
      <path data-ct-edge="" d="M <?php echo e($line['x1']); ?> <?php echo e($line['y1']); ?> H <?php echo e($mid); ?> V <?php echo e($line['y2']); ?> H <?php echo e($line['x2']); ?>" fill="none" stroke="#111111" stroke-width="1"/>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $board['cards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <path data-ct-edge="" d="M <?php echo e($card['x']); ?> <?php echo e($card['top']); ?> H <?php echo e($card['x'] + $card['width']); ?> V <?php echo e($card['bottom']); ?> H <?php echo e($card['x']); ?>" fill="none" stroke="#111111" stroke-width="1"/>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $card['participants']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot => $participant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($rowY = ($slot ? $card['bottom'] : $card['top']) - 28); ?>
        <?php ($palette = match($participant['style']) {
          'winner' => ['fill' => '#f1f3f3', 'stroke' => '#c7cdd0', 'text' => '#111111'],
          'withdrawn' => ['fill' => '#fff0ee', 'stroke' => '#f1a7a1', 'text' => '#b42318'],
          'source' => ['fill' => '#fff4d6', 'stroke' => '#fff4d6', 'text' => '#765e2a'],
          default => ['fill' => '#eaf5fc', 'stroke' => '#b9d8ee', 'text' => '#155d91'],
        }); ?>
        <rect x="<?php echo e($card['x'] + 6); ?>" y="<?php echo e($rowY + 3); ?>" width="<?php echo e($participant['width']); ?>" height="22" rx="5" fill="<?php echo e($palette['fill']); ?>" stroke="<?php echo e($palette['stroke']); ?>" stroke-width="1"/>
        <text x="<?php echo e($card['x'] + 12); ?>" y="<?php echo e($rowY + 18); ?>" fill="<?php echo e($palette['text']); ?>" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700"><?php echo e($participant['label']); ?></text>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($card['scores'][$slot] !== ''): ?>
          <text x="<?php echo e($card['x'] + $card['width'] - 7); ?>" y="<?php echo e($rowY + 18); ?>" text-anchor="end" fill="#176448" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700"><?php echo e($card['scores'][$slot]); ?></text>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <rect x="<?php echo e($card['x'] + $card['width'] - 38); ?>" y="<?php echo e($card['middle'] - 25); ?>" width="38" height="14" fill="#ffffff"/>
      <text x="<?php echo e($card['x'] + $card['width'] - 5); ?>" y="<?php echo e($card['middle'] - 15); ?>" text-anchor="end" fill="#64748b" font-family="DejaVu Sans, Arial, sans-serif" font-size="8">Match <?php echo e($card['number']); ?></text>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($card['schedule']): ?>
        <rect x="<?php echo e($card['x'] + 52); ?>" y="<?php echo e($card['middle'] - 10); ?>" width="<?php echo e($card['width'] - 58); ?>" height="13" fill="#ffffff"/>
        <text x="<?php echo e($card['x'] + $card['width'] - 5); ?>" y="<?php echo e($card['middle']); ?>" text-anchor="end" fill="#176448" font-family="DejaVu Sans, Arial, sans-serif" font-size="7" font-weight="700"><?php echo e($card['schedule']); ?></text>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($card['note']): ?>
        <text x="<?php echo e($card['x'] + 7); ?>" y="<?php echo e($card['bottom'] + 11); ?>" fill="#64748b" font-family="DejaVu Sans, Arial, sans-serif" font-size="7"><?php echo e($card['note']); ?></text>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $board['endpoints']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $endpoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <rect x="<?php echo e($endpoint['x'] - 4); ?>" y="<?php echo e($endpoint['y']); ?>" width="200" height="32" fill="#ffffff"/>
      <text x="<?php echo e($endpoint['x']); ?>" y="<?php echo e($endpoint['y'] + 11); ?>" fill="#607968" font-family="DejaVu Sans, Arial, sans-serif" font-size="7" font-weight="700"><?php echo e(strtoupper($endpoint['label'])); ?></text>
      <text x="<?php echo e($endpoint['x']); ?>" y="<?php echo e($endpoint['y'] + 26); ?>" fill="#111111" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700"><?php echo e($endpoint['name']); ?></text>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($layout['positions']): ?>
    <text x="0" y="<?php echo e($layout['positions_y']); ?>" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="12" font-weight="700">Final positions</text>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $layout['positions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $position): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($x = ($index % 8) * 125); ?>
      <?php ($y = $layout['positions_y'] + 12 + intdiv($index, 8) * 34); ?>
      <rect x="<?php echo e($x); ?>" y="<?php echo e($y); ?>" width="118" height="27" rx="2" fill="#f7faf8" stroke="#b7cbc0" stroke-width="0.8"/>
      <text x="<?php echo e($x + 6); ?>" y="<?php echo e($y + 11); ?>" fill="#155f50" font-family="DejaVu Sans, Arial, sans-serif" font-size="8" font-weight="700"><?php echo e($position['position']); ?></text>
      <text x="<?php echo e($x + 22); ?>" y="<?php echo e($y + 18); ?>" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="8"><?php echo e(\Illuminate\Support\Str::limit($position['name'], 19)); ?></text>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</svg>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\pdf\partials\flexible-monrad-board-svg.blade.php ENDPATH**/ ?>