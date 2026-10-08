<?php
  $registrations = $registrations->values(); // Ensure numeric indexes
  $players = $registrations->map(fn($r) => $r->players->first()?->full_name ?? 'TBD')->values();
  $numPlayers = $players->count();
  $cellWidth = 70;
  $cellHeight = 30;
  $offsetX = 160;
  $offsetY = 40;
  $svgWidth = $offsetX + ($numPlayers + 2) * $cellWidth + 30; // +2 for Total & Position
  $svgHeight = $offsetY + $numPlayers * $cellHeight + 40;

  $boxFixtures = $draw->drawFixtures->filter(fn($f) => $f->draw_group_id == $boxNumber);

  // Pre-compute match and game wins
  $playerStats = collect();
  foreach ($registrations as $reg) {
    $regId = $reg->id;
    $matchWins = 0;
    $gameWins = 0;

    foreach ($boxFixtures as $fixture) {
      foreach ($fixture->fixtureResults as $result) {
        if ($result->winner_registration === $regId) $matchWins++;
        if ($fixture->registration1_id === $regId) {
          $gameWins += $result->registration1_score;
        } elseif ($fixture->registration2_id === $regId) {
          $gameWins += $result->registration2_score;
        }
      }
    }

    $playerStats->push([
      'reg_id' => $regId,
      'matchWins' => $matchWins,
      'gameWins' => $gameWins,
      'name' => $reg->players->first()?->full_name ?? 'TBD',
    ]);
  }

  // Sort by matchWins DESC, then gameWins DESC, then head-to-head
  $sorted = $playerStats->sort(function ($a, $b) use ($boxFixtures) {
    if ($a['matchWins'] !== $b['matchWins']) {
      return $b['matchWins'] <=> $a['matchWins'];
    }
    if ($a['gameWins'] !== $b['gameWins']) {
      return $b['gameWins'] <=> $a['gameWins'];
    }

    // Tie-break: head-to-head winner
    $fixture = $boxFixtures->first(function ($f) use ($a, $b) {
      return ($f->registration1_id === $a['reg_id'] && $f->registration2_id === $b['reg_id']) ||
             ($f->registration1_id === $b['reg_id'] && $f->registration2_id === $a['reg_id']);
    });

    if ($fixture && $fixture->fixtureResults->count()) {
      $winner = $fixture->fixtureResults->last()?->winner_registration;
      if ($winner === $a['reg_id']) return -1;
      if ($winner === $b['reg_id']) return 1;
    }

    return 0;
  })->values();

  // Assign positions
  $positionMap = $sorted->pluck('reg_id')->flip()->map(fn($i) => $i + 1);
?>

<div class="matrix-box" id="box-matrix-<?php echo e($boxNumber); ?>">
  <h6 class="text-center">Box <?php echo e($boxNumber); ?></h6>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($numPlayers === 0): ?>
    <p class="text-muted text-center">No players in this box.</p>
  <?php else: ?>
    <svg width="<?php echo e($svgWidth); ?>" height="<?php echo e($svgHeight); ?>" xmlns="http://www.w3.org/2000/svg">
      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <text x="<?php echo e($offsetX + $i * $cellWidth + 10); ?>" y="25"
              font-size="12" font-family="Helvetica"
              transform="rotate(-45 <?php echo e($offsetX + $i * $cellWidth + 10); ?>,25)">
          <?php echo e(\Illuminate\Support\Str::limit($name, 10)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrations[$i]->players->first()): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $registrations[$i]->players->first()->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($registrations[$i]->players->first()->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </text>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <text x="<?php echo e($offsetX + $numPlayers * $cellWidth + 5); ?>" y="15" font-size="12">M/G</text>
      <text x="<?php echo e($offsetX + ($numPlayers + 1) * $cellWidth + 5); ?>" y="15" font-size="12">Position</text>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row => $rowName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $rowRegId = $registrations[$row]->id;
          $matchWins = $playerStats->firstWhere('reg_id', $rowRegId)['matchWins'];
          $gameWins = $playerStats->firstWhere('reg_id', $rowRegId)['gameWins'];
          $position = $positionMap[$rowRegId];
        ?>

        
        <text x="10" y="<?php echo e($offsetY + $row * $cellHeight + 20); ?>" font-size="12"><?php echo e($rowName); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrations[$row]->players->first()): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $registrations[$row]->players->first()->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($registrations[$row]->players->first()->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col => $colName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $colRegId = $registrations[$col]->id;
            $x = $offsetX + $col * $cellWidth;
            $y = $offsetY + $row * $cellHeight;
            $isDiagonal = $row === $col;

            $fixture = $boxFixtures->first(function ($f) use ($rowRegId, $colRegId) {
              return ($f->registration1_id === $rowRegId && $f->registration2_id === $colRegId) ||
                     ($f->registration1_id === $colRegId && $f->registration2_id === $rowRegId);
            });

            $score = '';
            if ($fixture && $fixture->fixtureResults->count()) {
              $score = $fixture->fixtureResults->map(function ($r) use ($fixture, $rowRegId) {
                return $fixture->registration1_id === $rowRegId
                  ? "{$r->registration1_score}-{$r->registration2_score}"
                  : "{$r->registration2_score}-{$r->registration1_score}";
              })->implode(', ');
            }
          ?>

          <rect x="<?php echo e($x); ?>" y="<?php echo e($y); ?>" width="<?php echo e($cellWidth); ?>" height="<?php echo e($cellHeight); ?>"
                fill="<?php echo e($isDiagonal ? '#000' : '#fff'); ?>" stroke="#000" />

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isDiagonal): ?>
            <text x="<?php echo e($x + 5); ?>" y="<?php echo e($y + 20); ?>" font-size="12"><?php echo e($score ?: '-'); ?></text>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php $x = $offsetX + $numPlayers * $cellWidth; ?>
        <rect x="<?php echo e($x); ?>" y="<?php echo e($offsetY + $row * $cellHeight); ?>" width="<?php echo e($cellWidth); ?>" height="<?php echo e($cellHeight); ?>" fill="#eee" stroke="#000" />
        <text x="<?php echo e($x + 5); ?>" y="<?php echo e($offsetY + $row * $cellHeight + 20); ?>" font-size="12"><?php echo e("{$matchWins} / {$gameWins}"); ?></text>

        
        <?php $x = $offsetX + ($numPlayers + 1) * $cellWidth; ?>
        <rect x="<?php echo e($x); ?>" y="<?php echo e($offsetY + $row * $cellHeight); ?>" width="<?php echo e($cellWidth); ?>" height="<?php echo e($cellHeight); ?>" fill="#cfc" stroke="#000" />
        <text x="<?php echo e($x + 20); ?>" y="<?php echo e($offsetY + $row * $cellHeight + 20); ?>" font-size="12"><?php echo e($position); ?></text>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </svg>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\single-box-matrix.blade.php ENDPATH**/ ?>