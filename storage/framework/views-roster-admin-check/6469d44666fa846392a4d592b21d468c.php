<style>
    .matrix-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
    }

    .matrix-box {
        flex: 0 0 48%;
        border: 1px solid #ccc;
        padding: 10px;
        background-color: #fdfdfd;
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.1);
    }

    @media print {
    /* Existing styles (optional reference) */
    .btn, .nav, .nav-tabs, .card-header, .card-footer, .form-control, select, label {
      display: none !important;
    }

    /* New additions to hide menu and user icon */
    .layout-navbar,      /* top bar */
    .layout-menu-toggle, /* mobile toggle button */
    .layout-menu,        /* left menu */
    .user-dropdown,      /* avatar icon (SU) */
    .header-navbar,      /* full top header if needed */
    .navbar-nav,         /* nav items like user dropdown */
    .dropdown,           /* any dropdowns */
    .nav-item.dropdown,
    .nav-link.dropdown-toggle {
      display: none !important;
    }

    /* Optional cleanup */
    body {
      background: white;
    }

    .layout-content-wrapper {
      margin-left: 0 !important;
    }
        .matrix-grid {
      page-break-inside: avoid;
    }

    .matrix-box {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    /* Add page break after last box (if needed) */
    .matrix-box:last-child {
      page-break-after: always;
    }

  }
</style>

<?php
    $boxes = $draw->registrations->filter(fn($r) => $r->pivot->box_number)->groupBy(fn($r) => $r->pivot->box_number);
?>
<div class="text-end mb-3">
    <button onclick="window.print()" class="btn btn-outline-primary">
        🖨️ Print Boxes
    </button>
</div>

<div class="matrix-grid mt-4">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $boxes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $boxNumber => $registrations): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $players = $registrations->map(fn($r) => $r->players->first()?->full_name ?? 'TBD')->values();
            $playerMap = $registrations->mapWithKeys(fn($r, $i) => [$r->id => $players[$i]]);
            $numPlayers = $players->count();
            $cellWidth = 70;
            $cellHeight = 30;
            $offsetX = 160;
            $offsetY = 60;
            $labelHeight = 50;
            $svgWidth = $offsetX + ($numPlayers + 2) * $cellWidth + 20;
            $svgHeight = $labelHeight + $offsetY + $numPlayers * $cellHeight + 20;

            $boxFixtures = $draw->drawFixtures->filter(fn($f) => $f->draw_group_id == $boxNumber);

            // calculate player stats
            $stats = [];
            foreach ($registrations as $reg) {
                $rid = $reg->id;
                $wins = 0;
                $games = 0;

                foreach ($boxFixtures as $f) {
                    foreach ($f->fixtureResults as $r) {
                        if ($r->winner_registration === $rid) {
                            $wins++;
                        }

                        if ($f->registration1_id === $rid) {
                            $games += $r->registration1_score;
                        } elseif ($f->registration2_id === $rid) {
                            $games += $r->registration2_score;
                        }
                    }
                }
                $stats[$rid] = ['wins' => $wins, 'games' => $games];
            }

            // sort by wins, then games, then head-to-head
            $rankings = collect($stats)
                ->map(fn($s, $rid) => ['rid' => $rid] + $s)
                ->sort(function ($a, $b) use ($boxFixtures) {
                    if ($a['wins'] !== $b['wins']) {
                        return $b['wins'] <=> $a['wins'];
                    }
                    if ($a['games'] !== $b['games']) {
                        return $b['games'] <=> $a['games'];
                    }

                    $fixture = $boxFixtures->first(function ($f) use ($a, $b) {
                        return ($f->registration1_id === $a['rid'] && $f->registration2_id === $b['rid']) ||
                            ($f->registration1_id === $b['rid'] && $f->registration2_id === $a['rid']);
                    });

                    if ($fixture && $fixture->fixtureResults->first()) {
                        return $fixture->fixtureResults->first()->winner_registration === $a['rid'] ? -1 : 1;
                    }

                    return 0;
                })
                ->values()
                ->pluck('rid')
                ->flip();
        ?>

        <div class="matrix-box" id="box-matrix-<?php echo e($boxNumber); ?>">
            <h6 class="text-center">Box <?php echo e($boxNumber); ?></h6>

            <svg width="<?php echo e($svgWidth); ?>" height="<?php echo e($svgHeight); ?>" style="overflow: visible;"
                xmlns="http://www.w3.org/2000/svg">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $textX = $offsetX + $i * $cellWidth + 20; ?>
                    <text x="<?php echo e($textX); ?>" y="45" transform="rotate(-45 <?php echo e($textX); ?>,45)" font-size="12"
                        font-family="Helvetica">
                        <?php echo e(Str::limit($name, 14)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrations[$i]->players->first()): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
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

                
                <?php
                    $totalX = $offsetX + $numPlayers * $cellWidth;
                    $posX = $totalX + $cellWidth;
                ?>
                <text x="<?php echo e($totalX + 5); ?>" y="25" font-size="12" font-family="Helvetica">M/G</text>
                <text x="<?php echo e($posX + 5); ?>" y="25" font-size="12" font-family="Helvetica">Position</text>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row => $rowName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $rowRegId = $registrations[$row]->id; ?>
                    <text x="10" y="<?php echo e($offsetY + $row * $cellHeight + 20); ?>" font-size="12" font-family="Helvetica">
                        <?php echo e($rowName); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrations[$row]->players->first()): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
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
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </text>

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
                                $score = $fixture->fixtureResults
                                    ->map(function ($r) use ($fixture, $rowRegId) {
                                        return $fixture->registration1_id === $rowRegId
                                            ? "{$r->registration1_score}-{$r->registration2_score}"
                                            : "{$r->registration2_score}-{$r->registration1_score}";
                                    })
                                    ->implode(', ');
                            }
                        ?>

                        <rect x="<?php echo e($x); ?>" y="<?php echo e($y); ?>" width="<?php echo e($cellWidth); ?>"
                            height="<?php echo e($cellHeight); ?>" fill="<?php echo e($isDiagonal ? '#000' : '#fff'); ?>" stroke="#000" />

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isDiagonal): ?>
                            <text x="<?php echo e($x + 5); ?>" y="<?php echo e($y + 20); ?>" font-size="12"
                                font-family="Helvetica">
                                <?php echo e($score ?: '-'); ?>

                            </text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php
                        $x = $totalX;
                        $y = $offsetY + $row * $cellHeight;
                        $summary = $stats[$rowRegId]['wins'] . ' / ' . $stats[$rowRegId]['games'];
                    ?>
                    <rect x="<?php echo e($x); ?>" y="<?php echo e($y); ?>" width="<?php echo e($cellWidth); ?>"
                        height="<?php echo e($cellHeight); ?>" fill="#f0f0f0" stroke="#000" />
                    <text x="<?php echo e($x + 5); ?>" y="<?php echo e($y + 20); ?>" font-size="12" font-family="Helvetica">
                        <?php echo e($summary); ?>

                    </text>

                    
                    <?php
                        $x = $posX;
                        $rank = $rankings[$rowRegId] + 1;
                    ?>
                    <rect x="<?php echo e($x); ?>" y="<?php echo e($y); ?>" width="<?php echo e($cellWidth); ?>"
                        height="<?php echo e($cellHeight); ?>" fill="#d5fcd5" stroke="#000" />
                    <text x="<?php echo e($x + 20); ?>" y="<?php echo e($y + 20); ?>" font-size="12" font-family="Helvetica">
                        <?php echo e($rank); ?>

                    </text>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </svg>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\roundrobin-matrix.blade.php ENDPATH**/ ?>