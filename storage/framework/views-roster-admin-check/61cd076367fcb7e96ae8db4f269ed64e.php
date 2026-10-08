<?php
    if (! function_exists('mnr')) {
        function mnr($fx) { return $fx?->match_nr ?? ''; }
    }
    if (! function_exists('name1')) {
        function name1($fx) {
            return $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
    }
    if (! function_exists('name2')) {
        function name2($fx) {
            return $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
    }
  function score($fx) {
    if (!$fx) return '';

    $sets = $fx->fixtureResults
        ->sortBy('set_nr')
        ->map(fn($s) => "{$s->registration1_score}-{$s->registration2_score}")
        ->implode(', ');

    return $sets ?: '';
}

  if (! function_exists('fxid')) {
    function fxid($fx) {
        return $fx?->id ?? '';
    }
}


function winnerName($fx) {
    if (!$fx->winner_registration) {
        return '';
    }

    // If winner = registration1
    if ($fx->winner_registration == $fx->registration1_id) {
        return $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
    }

    // If winner = registration2
    if ($fx->winner_registration == $fx->registration2_id) {
        return $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
    }

    // Should never happen, but safe fallback
    return '---';
}


?>


<svg class="ct-bracket-svg" width="1600" height="1600" viewBox="0 0 1600 1600">
    <?php echo $__env->make('draw.partials.bracket-svg-style', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($svg['main'])): ?>
        <text x="50" y="40" style="font-size:20px;font-weight:bold;">
            MAIN DRAW Position 1-2
        </text>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $svg['main']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $matches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                <?php echo $__env->make('svg.match', [
                    'fx'     => $m['fx'],
                    'x'      => $m['x'],
                    'y'      => $m['y'],
                    'height' => $m['height'] ?? 40
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



    
    <?php
        $plateOffset = 400;
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($svg['plate']) && count($svg['plate'])): ?>

        <text x="50" y="<?php echo e($plateOffset); ?>" style="font-size:20px;font-weight:bold;">
            PLATE DRAW Position 3-8
        </text>
 

<?php
    // Resolve playoff matches safely
    $m3009 = $svg['plate'][4][0] ?? null; // 3rd/4th
    $m3010 = $svg['plate'][4][1] ?? null; // 7th/8th  (CORRECTED)
    $m3011 = $svg['plate'][4][2] ?? null; // 5th/6th  (CORRECTED)
?>



<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($m3010): ?>
    <text
        x="<?php echo e($m3010['x']); ?>"
        y="<?php echo e($m3010['y'] + $plateOffset - 30); ?>"
        style="font-size:18px; "
    >
        Playoff 7/8
    </text>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($m3011): ?>
    <text
        x="<?php echo e($m3011['x']); ?>"
        y="<?php echo e($m3011['y'] + $plateOffset - 30); ?>"
        style="font-size:18px; "
    >
        Playoff 5/6
    </text>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $svg['plate']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $matches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                <?php echo $__env->make('svg.match', [
                    'fx'     => $m['fx'],
                    'x'      => $m['x'],
                    'y'      => $m['y'] + $plateOffset,
                    'height' => $m['height'] ?? 40
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

              

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($svg['consolation']) && count($svg['consolation'])): ?>

    <?php
        // find bottom of Plate Round 1 to place Consolation properly
        $plateR1 = $svg['plate'][1] ?? [];
        $bottomPlateY = 0;

        if (count($plateR1)) {
            $last = end($plateR1);
            $bottomPlateY = ($last['y'] + $last['height']) + $plateOffset;
        }

        $consOffset = $bottomPlateY + 260; 
    ?>

    <text x="50" y="<?php echo e($consOffset); ?>" style="font-size:20px;font-weight:bold;">
        CONSOLATION DRAW Position 9-12
    </text>

    
    <?php
        $c4004 = $svg['consolation'][2][1] ?? null;
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c4004): ?>
        <text
            x="<?php echo e($c4004['x']); ?>"
            y="<?php echo e($c4004['y'] + $consOffset - 30); ?>"
            style="font-size:18px;"
        >
            Playoff 11/12
        </text>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $svg['consolation']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round => $matches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

            <?php echo $__env->make('svg.match', [
                'fx'     => $m['fx'],
                'x'      => $m['x'],
                'y'      => $m['y'] + $consOffset,
                'height' => $m['height'] ?? 40
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


</svg>
<?php echo $__env->make('draw.partials.final-positions', ['draw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\roundrobin\draw-svg.blade.php ENDPATH**/ ?>