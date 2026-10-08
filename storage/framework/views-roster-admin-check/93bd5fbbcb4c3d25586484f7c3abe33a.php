<tr id="row-<?php echo e($fx->id); ?>">
  <td><?php echo e($fx->id); ?></td>
  <td><?php echo e(optional($fx->draw)->drawName ?? '—'); ?></td>
  <td><?php echo e($fx->round_nr ?? '—'); ?></td>
  <td><?php echo e($fx->tie_nr ?? '—'); ?></td>

  <?php
    $homeNames = [];
    $awayNames = [];
    $homeRegionShort = $fx->region1Name?->short_name ?? null;
    $awayRegionShort = $fx->region2Name?->short_name ?? null;
  ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx->fixturePlayers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fp->team1_id && $fp->player1): ?>
      <?php
        $n = $fp->player1->full_name ?? ($fp->player1->name ?? '');
        if ($homeRegionShort) $n .= " ({$homeRegionShort})";
        $homeNames[] = $n;
      ?>
    <?php elseif($fp->team1_no_profile_id && $fp->noProfile1): ?>
      <?php
        $n = trim($fp->noProfile1->name . ' ' . $fp->noProfile1->surname);
        if ($homeRegionShort) $n .= " ({$homeRegionShort})";
        $homeNames[] = $n;
      ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fp->team2_id && $fp->player2): ?>
      <?php
        $n2 = $fp->player2->full_name ?? ($fp->player2->name ?? '');
        if ($awayRegionShort) $n2 .= " ({$awayRegionShort})";
        $awayNames[] = $n2;
      ?>
    <?php elseif($fp->team2_no_profile_id && $fp->noProfile2): ?>
      <?php
        $n2 = trim($fp->noProfile2->name . ' ' . $fp->noProfile2->surname);
        if ($awayRegionShort) $n2 .= " ({$awayRegionShort})";
        $awayNames[] = $n2;
      ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php
    $homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
    $awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';
  ?>

  <td class="home-cell"><?php echo e($homeLabel); ?></td>
  <td class="away-cell"><?php echo e($awayLabel); ?></td>
  <td><?php echo e($fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i') : '—'); ?></td>
  <td><?php echo e(optional($fx->venue)->name ?? '—'); ?></td>
</tr>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\partials\replace_player_fixture_row.blade.php ENDPATH**/ ?>