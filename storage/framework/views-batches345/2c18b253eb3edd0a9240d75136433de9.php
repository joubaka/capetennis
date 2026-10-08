<style>
  .nmr {
      fill: red;
      font-weight: bold;
      font-size: 9px;
  }
  .score-svg {
      font-size: 11px;
      fill: green;
      font-weight: 600;
  }
  .svg_name {
      font-size: 12px;
      font-family: Helvetica;
      font-weight: bold;
  }
  .sched {
      font-size: 9px;
      fill: orange;
      font-family: Helvetica;
  }
</style>

<?php
    $registration1 = $fx?->registration1;
    $registration2 = $fx?->registration2;
    $player1Name = $registration1?->players?->pluck('full_name')->join(' / ') ?: ($registration2 ? 'BYE' : '---');
    $player2Name = $registration2?->players?->pluck('full_name')->join(' / ') ?: ($registration1 ? 'BYE' : '---');
    $winnerRegistration = (int) ($fx?->winner_registration ?? 0);
    $automaticBye = $winnerRegistration > 0
        && (($registration1 && ! $registration2) || (! $registration1 && $registration2));
?>

<g transform="translate(<?php echo e($x); ?>, <?php echo e($y); ?>)">

    
    <text x="-3" y="<?php echo e(($height / 2) + 4); ?>" class="nmr" text-anchor="end">
        (<?php echo e(mnr($fx)); ?>)
    </text>

    
    <text 
        x="148" 
        y="<?php echo e($height/2); ?>" 
        class="nmr"
        text-anchor="end"
    >
        #<?php echo e(fxid($fx)); ?>

    </text>

    
    <line x1="0" y1="0" x2="150" y2="0" stroke="black"/>
    <line x1="0" y1="<?php echo e($height); ?>" x2="150" y2="<?php echo e($height); ?>" stroke="black"/>
    <line x1="150" y1="0" x2="150" y2="<?php echo e($height); ?>" stroke="black"/>

    
    <?php echo $__env->make('draw.partials.svg-player-identity', [
        'name' => $player1Name,
        'x' => 10,
        'y' => -3,
        'maxWidth' => 136,
        'isBye' => ! $registration1 && (bool) $registration2,
        'isWinner' => $registration1 && $winnerRegistration === (int) $registration1->id,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <text x="10" y="<?php echo e($height/2); ?>" class="score-svg">
        <?php echo e($automaticBye ? 'BYE · ADVANCES' : score($fx)); ?>

    </text>

    
    <?php
        $sch = $fx->orderOfPlay ?? null;

        if ($sch && $sch->time) {
            $day   = \Carbon\Carbon::parse($sch->time)->format('D');
            $time  = \Carbon\Carbon::parse($sch->time)->format('H:i');
            $venue = $sch->venue->name ?? '';
            $display = trim("$day $time" . ($venue ? " • $venue" : ""));
        } else {
            $display = '';
        }
        $scheduleY = $height - 5;
    ?>

    <text 
        x="75"
        y="<?php echo e($scheduleY); ?>"
        class="sched"
        text-anchor="middle"
    >
        <?php echo e($display); ?>

    </text>

    
    <?php echo $__env->make('draw.partials.svg-player-identity', [
        'name' => $player2Name,
        'x' => 10,
        'y' => $height + 13,
        'maxWidth' => 136,
        'isBye' => ! $registration2 && (bool) $registration1,
        'isWinner' => $registration2 && $winnerRegistration === (int) $registration2->id,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

</g>


<?php
    $mn = $fx->match_nr;
    $needsWinnerLine = in_array($mn, [
        2003, 3009, 3010, 3011,
        4003, 4004
    ]);

    $boxWidth = 150;
    $lineWidth = 120;
    $winner = winnerName($fx);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($needsWinnerLine): ?>
    
    <line
        x1="<?php echo e($x + $boxWidth); ?>"
        y1="<?php echo e($y + ($height / 2)); ?>"
        x2="<?php echo e($x + $boxWidth + $lineWidth); ?>"
        y2="<?php echo e($y + ($height / 2)); ?>"
        stroke="black"
        stroke-width="2"
    />

    
    <text
        x="<?php echo e($x + $boxWidth + $lineWidth - 90); ?>"
        y="<?php echo e($y + ($height / 2) - 6); ?>"
        class="svg_name bracket-winner"
    >
        <?php echo e($winner); ?>

    </text>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\svg\match.blade.php ENDPATH**/ ?>