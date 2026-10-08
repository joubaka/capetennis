<svg class="ct-bracket-svg" width="<?php echo e($svgData['totalWidth']); ?>" height="<?php echo e($svgData['totalHeight']); ?>" viewBox="0 0 <?php echo e($svgData['totalWidth']); ?> <?php echo e($svgData['totalHeight']); ?>" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMinYMin meet" >
    <?php echo $__env->make('draw.partials.bracket-svg-style', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .player-name { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; fill: #1e293b; font-weight: 600; }
        .score-green { font-family: monospace; font-size: 10px; fill: #059669; font-weight: 700; }
        .id-red { font-family: sans-serif; font-size: 9px; fill: #94a3b8; font-weight: 500; }
        .seed-origin { font-family: sans-serif; font-size: 8px; fill: #fff; font-weight: 700; }
        .seed-origin-bg { rx: 3; ry: 3; }
        .seed-origin-inline { font-family: sans-serif; font-size: 11px; fill: #6366f1; font-weight: 700; }
        .match-hit { fill: transparent; cursor: pointer; }
        .match-hit:hover { fill: rgba(99,102,241,0.07); }
        .match-score { font-family: monospace; font-size: 10px; fill: #059669; font-weight: 700; }
        .match-row-bg { fill: #ffffff; stroke: none; }
        @media print {
          .player-name { font-size: 15px !important; fill: #000 !important; font-weight: 700 !important; }
          .score-green { font-size: 13px !important; fill: #000 !important; }
          .id-red { display: none; }
          .seed-origin { font-size: 10px !important; fill: #000 !important; }
          .match-score { font-size: 12px !important; fill: #000 !important; }
          svg { background: #fff !important; }
        }
    </style>

    <?php
        $seedMap  = $svgData['seedOriginMap'] ?? [];
        $byeSlots = $svgData['byeSlots'] ?? [];
        $isEmpty  = !empty($emptyBracket);

        $isAdmin = auth()->check() && method_exists(auth()->user(), 'hasRole') && (
            auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-user')
        );

        // Build feeder map: for each fixture ID, which match_nr feeds winner/loser into it
        $winnerFeeders = [];
        $loserFeeders = [];
        foreach ($svgData['brackets'] as $_b) {
            foreach ($_b['rounds'] as $_matches) {
                foreach ($_matches as $_m) {
                    $_fx = $_m['fx'];
                    if ($_fx && $_fx->parent_fixture_id) {
                        $winnerFeeders[$_fx->parent_fixture_id][] = $_fx->match_nr;
                    }
                    if ($_fx && $_fx->loser_parent_fixture_id) {
                        $loserFeeders[$_fx->loser_parent_fixture_id][] = $_fx->match_nr;
                    }
                }
            }
            foreach ($_b['positionPlayoffs'] ?? [] as $_pp) {
                // Position playoff fixtures also feed into each other
                $_pfx = $_pp['fx'];
                if ($_pfx && $_pfx->parent_fixture_id) {
                    $winnerFeeders[$_pfx->parent_fixture_id][] = $_pfx->match_nr;
                }
                if ($_pfx && $_pfx->loser_parent_fixture_id) {
                    $loserFeeders[$_pfx->loser_parent_fixture_id][] = $_pfx->match_nr;
                }
            }
        }
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $svgData['brackets']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bracket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $bracketName = $bracket['name'] ?? 'Bracket';
            $bracketPositions = $bracket['positions'] ?? [];
            $bracketStartY = $bracket['startY'] ?? 100;
            $numRounds = $bracket['numRounds'] ?? 1;
            $posFrom = $bracket['posFrom'] ?? null;
            $posTo   = $bracket['posTo'] ?? null;

            $posLabel = ($posFrom && $posTo) ? "Pos {$posFrom}-{$posTo}" : '';
        ?>

        
        <text x="60" y="<?php echo e($bracketStartY - 38); ?>" style="font-family: 'Segoe UI', sans-serif; font-size: 15px; font-weight: 800; fill: #1e293b;">
            <?php echo e($bracketName); ?>

        </text>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($posLabel && $isAdmin): ?>
            <text x="60" y="<?php echo e($bracketStartY - 22); ?>" style="font-family: sans-serif; font-size: 10px; font-weight: 500; fill: #64748b;">
                <?php echo e($posLabel); ?>

            </text>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bracket['rounds']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rn => $rMatches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $firstMatch = $rMatches[0] ?? null;
                $roundLabel = $firstMatch['roundLabel'] ?? "R$rn";
                $roundX = $firstMatch ? $firstMatch['x'] : (60 + ($rn - 1) * 200);
            ?>
            <text x="<?php echo e($roundX + 95); ?>" y="<?php echo e($bracketStartY - 28); ?>" text-anchor="middle" style="font-family: sans-serif; font-size: 10px; font-weight: 600; fill: #64748b; letter-spacing: 0.5px;">
                <?php echo e(strtoupper($roundLabel)); ?>

            </text>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bracket['rounds']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roundNum => $matches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $matchIndex => $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $fx = $match['fx'];
                    $x = $match['x'];
                    $y = $match['y'];
                    $w = $match['width'];
                    $h = $match['height'];  
                    
                    $topLineY = $y; 
                    $bottomLineY = $y + $h;
                    $midY = $y + ($h / 2);
                    $rightX = $x + $w;
                    
                    // LINKING LOGIC: Calculate which line to connect to in next round
                    $nextRoundX = $x + 200; // ROUND_GAP constant
                    
                    // Determine if this is the top or bottom match of a pair
                    $isTopOfPair = ($matchIndex % 2 == 0);
                    
                    // Connect from midpoint to the appropriate line on next round match
                    $connectToY = $midY; // Default (for final round or debugging)
                    
                    $empty1 = is_null($fx?->registration1_id);
                    $empty2 = is_null($fx?->registration2_id);
                    $hasWinner = !is_null($fx?->winner_registration);
                    $isBye1 = $empty1 && ($roundNum === 1 || $hasWinner);
                    $isBye2 = $empty2 && ($roundNum === 1 || $hasWinner);
                    $p1 = $empty1 ? ($isBye1 ? 'BYE' : '---') : ($fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $p2 = $empty2 ? ($isBye2 ? 'BYE' : '---') : ($fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---');

                    // Feeder labels: show "W1001" / "L1002" when slot is TBD
                    $wFeed = $fx ? ($winnerFeeders[$fx->id] ?? []) : [];
                    $lFeed = $fx ? ($loserFeeders[$fx->id] ?? []) : [];
                    sort($wFeed);
                    sort($lFeed);
                    $feed1 = '';
                    $feed2 = '';
                    if ($empty1 && !$isBye1 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed1 = 'W' . $wFeed[0];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed1 = 'W' . $wFeed[0];
                        elseif (count($lFeed) >= 2) $feed1 = 'L' . $lFeed[0];
                        elseif (count($lFeed) === 1) $feed1 = 'L' . $lFeed[0];
                    }
                    if ($empty2 && !$isBye2 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed2 = 'W' . $wFeed[1];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed2 = 'L' . $lFeed[0];
                        elseif (count($lFeed) >= 2) $feed2 = 'L' . $lFeed[1];
                    }

                    $origin1 = $seedMap[$fx?->registration1_id] ?? null;
                    $origin2 = $seedMap[$fx?->registration2_id] ?? null;

                    // Seed source labels for round 1 (e.g. "A1", "C4")
                    $vSeedLabel1 = ($roundNum === 1) ? ($match['seedLabel1'] ?? null) : null;
                    $vSeedLabel2 = ($roundNum === 1) ? ($match['seedLabel2'] ?? null) : null;

                    if ($isEmpty) {
                        // Use virtual data from engine when fx is null
                        $vSL1 = $match['seedLabel1'] ?? null;
                        $vSL2 = $match['seedLabel2'] ?? null;
                        $vFL1 = $match['feederLabel1'] ?? null;
                        $vFL2 = $match['feederLabel2'] ?? null;

                        $p1 = ($roundNum === 1) ? ($origin1 ?? $vSL1 ?? '') : ($vFL1 ?? '');
                        $p2 = ($roundNum === 1) ? ($origin2 ?? $vSL2 ?? '') : ($vFL2 ?? '');
                        $feed1 = '';
                        $feed2 = '';
                        $origin1 = null;
                        $origin2 = null;
                    }
                    // Replace '---' with feeder label if available
                    if ($feed1 && ($p1 === '---' || $p1 === 'TBD')) $p1 = $feed1;
                    if ($feed2 && ($p2 === '---' || $p2 === 'TBD')) $p2 = $feed2;
                    $isFeeder1 = str_starts_with($p1, 'W') || str_starts_with($p1, 'L');
                    $isFeeder2 = str_starts_with($p2, 'W') || str_starts_with($p2, 'L');
                    $feederIsWin1 = str_starts_with($p1, 'W');
                    $feederIsWin2 = str_starts_with($p2, 'W');
                    $identity1 = !$empty1 && !$isFeeder1 && !$isEmpty;
                    $identity2 = !$empty2 && !$isFeeder2 && !$isEmpty;
                    $winner1 = $identity1 && $hasWinner && (int) $fx->winner_registration === (int) $fx->registration1_id;
                    $winner2 = $identity2 && $hasWinner && (int) $fx->winner_registration === (int) $fx->registration2_id;
                    $identityWidth1 = min(160, max(42, min(mb_strlen($p1), 22) * 7 + 12));
                    $identityWidth2 = min(160, max(42, min(mb_strlen($p2), 22) * 7 + 12));
                ?>

                <g>
                    
                    <rect x="<?php echo e($x); ?>" y="<?php echo e($topLineY); ?>" width="<?php echo e($w); ?>" height="<?php echo e($h); ?>" class="match-row-bg" fill="#ffffff" />

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identity1): ?>
                        <rect x="<?php echo e($x + 1); ?>" y="<?php echo e($topLineY - 20); ?>" width="<?php echo e($identityWidth1); ?>" height="17" rx="6" class="player-identity-bg<?php echo e($winner1 ? ' winner' : ''); ?>" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <text x="<?php echo e($x + 5); ?>" y="<?php echo e($topLineY - 5); ?>" class="player-name<?php echo e($identity1 ? ' player-identity-text' : ''); ?><?php echo e($winner1 ? ' winner' : ''); ?>"
                        <?php if($isBye1 && !$isEmpty): ?> style="fill: #94a3b8; font-style: italic;"
                        <?php endif; ?>><?php echo e(Str::limit($p1, \App\Services\Performance\PlayerRatingBadgeService::visible() ? 14 : 22)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration1?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin1 && $isAdmin): ?>
                        <?php $badgeX1 = $x + 5 + min(strlen($p1), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX1); ?>" y="<?php echo e($topLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX1 + 9); ?>" y="<?php echo e($topLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($origin1); ?></text>
                    <?php elseif(!$isEmpty && $vSeedLabel1 && !$isBye1): ?>
                        <?php $badgeX1 = $x + 5 + min(strlen($p1), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX1); ?>" y="<?php echo e($topLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX1 + 9); ?>" y="<?php echo e($topLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($vSeedLabel1); ?></text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <line x1="<?php echo e($x); ?>" y1="<?php echo e($topLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($topLineY); ?>" class="bracket-line" />

                    
                    <line x1="<?php echo e($x); ?>" y1="<?php echo e($bottomLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($bottomLineY); ?>" class="bracket-line" />
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identity2): ?>
                        <rect x="<?php echo e($x + 1); ?>" y="<?php echo e($bottomLineY - 20); ?>" width="<?php echo e($identityWidth2); ?>" height="17" rx="6" class="player-identity-bg<?php echo e($winner2 ? ' winner' : ''); ?>" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <text x="<?php echo e($x + 5); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="player-name<?php echo e($identity2 ? ' player-identity-text' : ''); ?><?php echo e($winner2 ? ' winner' : ''); ?>"
                        <?php if($isBye2 && !$isEmpty): ?> style="fill: #94a3b8; font-style: italic;"
                        <?php endif; ?>><?php echo e(Str::limit($p2, \App\Services\Performance\PlayerRatingBadgeService::visible() ? 14 : 22)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration2?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin2 && $isAdmin): ?>
                        <?php $badgeX2 = $x + 5 + min(strlen($p2), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX2); ?>" y="<?php echo e($bottomLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX2 + 9); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($origin2); ?></text>
                    <?php elseif(!$isEmpty && $vSeedLabel2 && !$isBye2): ?>
                        <?php $badgeX2 = $x + 5 + min(strlen($p2), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX2); ?>" y="<?php echo e($bottomLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX2 + 9); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($vSeedLabel2); ?></text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($topLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($bottomLineY); ?>" class="bracket-line" />

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roundNum < count($bracket['rounds'])): ?>
                        <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($midY); ?>" x2="<?php echo e($nextRoundX); ?>" y2="<?php echo e($midY); ?>" class="bracket-line" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roundNum == count($bracket['rounds'])): ?>
                        <?php
                            $winnerLineEndX = $rightX + 120;
                            $winnerId = $fx?->winner_registration;
                            $winnerName = '';
                            if ($winnerId) {
                                $winnerName = ($winnerId == $fx->registration1_id) ? $p1 : $p2;
                            }
                        ?>
                        
                        <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($midY); ?>" x2="<?php echo e($winnerLineEndX); ?>" y2="<?php echo e($midY); ?>" class="bracket-line" />
                        
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winnerName && !$isEmpty): ?>
                            <text x="<?php echo e($winnerLineEndX + 10); ?>" y="<?php echo e($midY + 5); ?>" class="player-name" style="fill: #176448; font-weight: 700;">
                                🏆 <?php echo e(Str::limit($winnerName, 30)); ?>

                            </text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php
                        $scoreStr = $fx?->fixtureResults?->sortBy('set_nr')->map(fn($r) => $r->registration1_score . '-' . $r->registration2_score)->implode('  ');
                        $canEnterScore = !$empty1 && !$empty2 && !$hasWinner;
                        $automaticBye = $hasWinner && $empty1 !== $empty2 && !$scoreStr;
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scoreStr && !$isEmpty): ?>
                        <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" class="match-score" text-anchor="middle"><?php echo e($scoreStr); ?></text>
                    <?php elseif($automaticBye && !$isEmpty): ?>
                        <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" class="automatic-note" text-anchor="middle">BYE · ADVANCES</text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isEmpty): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                            
                            <text x="<?php echo e($rightX - 6); ?>" y="<?php echo e($midY + 4); ?>" class="id-red" text-anchor="end">#<?php echo e($fx?->id); ?></text>
                            <text x="<?php echo e($x - 10); ?>" y="<?php echo e($midY + 4); ?>" class="id-red" text-anchor="end">(<?php echo e($fx?->match_nr); ?>)</text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEnterScore && $isAdmin): ?>
                            <rect x="<?php echo e($x); ?>" y="<?php echo e($topLineY - 18); ?>" width="<?php echo e($w); ?>" height="<?php echo e($h + 36); ?>"
                                  class="match-hit bracket-score-btn"
                                  data-fixture-id="<?php echo e($fx->id); ?>"
                                  data-home="<?php echo e(Str::limit($p1, 30)); ?>"
                                  data-away="<?php echo e(Str::limit($p2, 30)); ?>" />
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        
                        <?php $vNr = $match['virtualMatchNr'] ?? null; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vNr): ?>
                            <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" text-anchor="middle" style="font-family: 'Segoe UI', sans-serif; font-size: 10px; fill: #94a3b8; font-weight: 600;">M<?php echo e($vNr); ?></text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </g>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($bracket['positionPlayoffs'])): ?>
            
            <text x="60" y="<?php echo e($bracket['positionPlayoffs'][0]['y'] - 30); ?>" style="font-family: 'Segoe UI', sans-serif; font-size: 11px; font-weight: 700; fill: #64748b; letter-spacing: 0.5px;">POSITION PLAYOFFS</text>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bracket['positionPlayoffs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playoff): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $fx = $playoff['fx'];
                    $x = $playoff['x'];
                    $y = $playoff['y'];
                    $w = $playoff['width'];
                    $h = $playoff['height'];
                    
                    $topLineY = $y;
                    $bottomLineY = $y + $h;
                    $midY = $y + ($h / 2);
                    $rightX = $x + $w;
                    
                    $empty1 = is_null($fx?->registration1_id);
                    $empty2 = is_null($fx?->registration2_id);
                    $hasWinner = !is_null($fx?->winner_registration);
                    $fxByeSlots = $byeSlots[$fx?->id] ?? [];
                    $isBye1 = $empty1 && ($hasWinner || !empty($fxByeSlots['slot1']));
                    $isBye2 = $empty2 && ($hasWinner || !empty($fxByeSlots['slot2']));
                    $p1 = $empty1 ? ($isBye1 ? 'BYE' : '---') : ($fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $p2 = $empty2 ? ($isBye2 ? 'BYE' : '---') : ($fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $label = $playoff['label'] ?? '';
                    $isFinal = $playoff['isFinal'] ?? false;

                    // Feeder labels for position playoffs
                    $wFeed = $fx ? ($winnerFeeders[$fx->id] ?? []) : [];
                    $lFeed = $fx ? ($loserFeeders[$fx->id] ?? []) : [];
                    sort($wFeed);
                    sort($lFeed);
                    $feed1 = '';
                    $feed2 = '';
                    if ($empty1 && !$isBye1 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed1 = 'W' . $wFeed[0];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed1 = 'W' . $wFeed[0];
                        elseif (count($lFeed) >= 2) $feed1 = 'L' . $lFeed[0];
                        elseif (count($lFeed) === 1) $feed1 = 'L' . $lFeed[0];
                    }
                    if ($empty2 && !$isBye2 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed2 = 'W' . $wFeed[1];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed2 = 'L' . $lFeed[0];
                        elseif (count($lFeed) >= 2) $feed2 = 'L' . $lFeed[1];
                    }

                    $origin1 = $seedMap[$fx?->registration1_id] ?? null;
                    $origin2 = $seedMap[$fx?->registration2_id] ?? null;

                    // Seed source labels for position playoffs (always single-match, treat as round 1)
                    $vSeedLabel1 = $origin1 ?? null;
                    $vSeedLabel2 = $origin2 ?? null;

                    if ($isEmpty) {
                        // Use virtual feeder labels from engine
                        $vFL1 = $playoff['feederLabel1'] ?? null;
                        $vFL2 = $playoff['feederLabel2'] ?? null;
                        $p1 = $origin1 ?? $vFL1 ?? '';
                        $p2 = $origin2 ?? $vFL2 ?? '';
                        $feed1 = '';
                        $feed2 = '';
                        $origin1 = null;
                        $origin2 = null;
                    }
                    if ($feed1 && ($p1 === '---' || $p1 === 'TBD')) $p1 = $feed1;
                    if ($feed2 && ($p2 === '---' || $p2 === 'TBD')) $p2 = $feed2;
                    $isFeeder1 = str_starts_with($p1, 'W') || str_starts_with($p1, 'L');
                    $isFeeder2 = str_starts_with($p2, 'W') || str_starts_with($p2, 'L');
                    $feederIsWin1 = str_starts_with($p1, 'W');
                    $feederIsWin2 = str_starts_with($p2, 'W');
                    $identity1 = !$empty1 && !$isFeeder1 && !$isEmpty;
                    $identity2 = !$empty2 && !$isFeeder2 && !$isEmpty;
                    $winner1 = $identity1 && $hasWinner && (int) $fx->winner_registration === (int) $fx->registration1_id;
                    $winner2 = $identity2 && $hasWinner && (int) $fx->winner_registration === (int) $fx->registration2_id;
                    $identityWidth1 = min(160, max(42, min(mb_strlen($p1), 22) * 7 + 12));
                    $identityWidth2 = min(160, max(42, min(mb_strlen($p2), 22) * 7 + 12));
                ?>

                <g>
                    
                    <rect x="<?php echo e($x); ?>" y="<?php echo e($topLineY); ?>" width="<?php echo e($w); ?>" height="<?php echo e($h); ?>" class="match-row-bg" fill="#ffffff" />

                    
                    <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($y - 14); ?>" text-anchor="middle" style="font-family: 'Segoe UI', sans-serif; font-size: 10px; fill: #64748b; font-weight: 600; letter-spacing: 0.5px;">
                        <?php echo e(strtoupper($label)); ?>

                    </text>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identity1): ?>
                        <rect x="<?php echo e($x + 1); ?>" y="<?php echo e($topLineY - 20); ?>" width="<?php echo e($identityWidth1); ?>" height="17" rx="6" class="player-identity-bg<?php echo e($winner1 ? ' winner' : ''); ?>" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <text x="<?php echo e($x + 5); ?>" y="<?php echo e($topLineY - 5); ?>" class="player-name<?php echo e($identity1 ? ' player-identity-text' : ''); ?><?php echo e($winner1 ? ' winner' : ''); ?>"
                        <?php if($isBye1 && !$isEmpty): ?> style="fill: #94a3b8; font-style: italic;"
                        <?php endif; ?>><?php echo e(Str::limit($p1, \App\Services\Performance\PlayerRatingBadgeService::visible() ? 14 : 22)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration1?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin1 && $isAdmin): ?>
                        <?php $badgeX1 = $x + 5 + min(strlen($p1), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX1); ?>" y="<?php echo e($topLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX1 + 9); ?>" y="<?php echo e($topLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($origin1); ?></text>
                    <?php elseif(!$isEmpty && $vSeedLabel1 && !$isBye1): ?>
                        <?php $badgeX1 = $x + 5 + min(strlen($p1), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX1); ?>" y="<?php echo e($topLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX1 + 9); ?>" y="<?php echo e($topLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($vSeedLabel1); ?></text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <line x1="<?php echo e($x); ?>" y1="<?php echo e($topLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($topLineY); ?>" class="bracket-line" />

                    
                    <line x1="<?php echo e($x); ?>" y1="<?php echo e($bottomLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($bottomLineY); ?>" class="bracket-line" />
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identity2): ?>
                        <rect x="<?php echo e($x + 1); ?>" y="<?php echo e($bottomLineY - 20); ?>" width="<?php echo e($identityWidth2); ?>" height="17" rx="6" class="player-identity-bg<?php echo e($winner2 ? ' winner' : ''); ?>" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <text x="<?php echo e($x + 5); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="player-name<?php echo e($identity2 ? ' player-identity-text' : ''); ?><?php echo e($winner2 ? ' winner' : ''); ?>"
                        <?php if($isBye2 && !$isEmpty): ?> style="fill: #94a3b8; font-style: italic;"
                        <?php endif; ?>><?php echo e(Str::limit($p2, \App\Services\Performance\PlayerRatingBadgeService::visible() ? 14 : 22)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration2?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($origin2 && $isAdmin): ?>
                        <?php $badgeX2 = $x + 5 + min(strlen($p2), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX2); ?>" y="<?php echo e($bottomLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX2 + 9); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($origin2); ?></text>
                    <?php elseif(!$isEmpty && $vSeedLabel2 && !$isBye2): ?>
                        <?php $badgeX2 = $x + 5 + min(strlen($p2), 22) * 7 + 4; ?>
                        <rect x="<?php echo e($badgeX2); ?>" y="<?php echo e($bottomLineY - 14); ?>" width="18" height="11" fill="#6366f1" rx="2" />
                        <text x="<?php echo e($badgeX2 + 9); ?>" y="<?php echo e($bottomLineY - 5); ?>" class="seed-origin" text-anchor="middle"><?php echo e($vSeedLabel2); ?></text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($topLineY); ?>" x2="<?php echo e($rightX); ?>" y2="<?php echo e($bottomLineY); ?>" class="bracket-line" />

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFinal): ?>
                        <?php
                            $winnerLineEndX = $rightX + 100;
                            $winnerId = $fx?->winner_registration;
                            $winnerName = '';
                            if ($winnerId) {
                                $winnerName = ($winnerId == $fx->registration1_id) ? $p1 : $p2;
                            }
                        ?>
                        <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($midY); ?>" x2="<?php echo e($winnerLineEndX); ?>" y2="<?php echo e($midY); ?>" class="bracket-line" />
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winnerName && !$isEmpty): ?>
                            <text x="<?php echo e($winnerLineEndX + 10); ?>" y="<?php echo e($midY + 5); ?>" class="player-name" style="fill: #059669;">
                                <?php echo e(Str::limit($winnerName, 25)); ?>

                            </text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        
                        <line x1="<?php echo e($rightX); ?>" y1="<?php echo e($midY); ?>" x2="<?php echo e($x + 200); ?>" y2="<?php echo e($midY); ?>" class="bracket-line" />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php
                        $scoreStr = $fx?->fixtureResults?->sortBy('set_nr')->map(fn($r) => $r->registration1_score . '-' . $r->registration2_score)->implode('  ');
                        $canEnterScore = !$empty1 && !$empty2 && !$hasWinner;
                        $automaticBye = $hasWinner && $empty1 !== $empty2 && !$scoreStr;
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scoreStr && !$isEmpty): ?>
                        <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" class="match-score" text-anchor="middle"><?php echo e($scoreStr); ?></text>
                    <?php elseif($automaticBye && !$isEmpty): ?>
                        <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" class="automatic-note" text-anchor="middle">BYE · ADVANCES</text>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isEmpty): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                            
                            <text x="<?php echo e($rightX - 6); ?>" y="<?php echo e($midY + 4); ?>" class="id-red" text-anchor="end">#<?php echo e($fx?->id); ?></text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEnterScore && $isAdmin): ?>
                            <rect x="<?php echo e($x); ?>" y="<?php echo e($topLineY - 18); ?>" width="<?php echo e($w); ?>" height="<?php echo e($h + 36); ?>"
                                  class="match-hit bracket-score-btn"
                                  data-fixture-id="<?php echo e($fx->id); ?>"
                                  data-home="<?php echo e(Str::limit($p1, 30)); ?>"
                                  data-away="<?php echo e(Str::limit($p2, 30)); ?>" />
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        
                        <?php $vNr = $playoff['virtualMatchNr'] ?? null; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vNr): ?>
                            <text x="<?php echo e($x + ($w / 2)); ?>" y="<?php echo e($midY + 4); ?>" text-anchor="middle" style="font-family: 'Segoe UI', sans-serif; font-size: 10px; fill: #94a3b8; font-weight: 600;">M<?php echo e($vNr); ?></text>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </g>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</svg>
<?php echo $__env->make('draw.partials.final-positions', ['draw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\roundrobin\dynamic-bracket-svg.blade.php ENDPATH**/ ?>