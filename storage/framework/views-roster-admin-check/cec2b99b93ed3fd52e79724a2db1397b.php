<?php
    $fixtureRegistration = null;
    if (isset($fixture) && isset($slot)) {
        $fixtureRegistration = (int) $slot === 1 ? $fixture?->registration1 : $fixture?->registration2;
    }
    $registration = $registration ?? $fixtureRegistration;
    $opponent = null;
    if (isset($fixture) && isset($slot)) {
        $opponent = (int) $slot === 1 ? $fixture?->registration2 : $fixture?->registration1;
    }
    $resolvedName = $registration?->players?->pluck('full_name')->join(' / ');
    $label = trim((string) ($name ?? $resolvedName ?? ''));
    if ($label === '' && $opponent) {
        $label = 'BYE';
    }
    $isBye = (bool) ($isBye ?? false) || strtoupper($label) === 'BYE';
    $isPlaceholder = $label === '' || $label === '---';
    $isWinner = (bool) ($isWinner ?? (
        isset($fixture) && $registration
            && (int) ($fixture?->winner_registration ?? 0) === (int) $registration->id
    ));
    $maxWidth = (float) ($maxWidth ?? 176);
    $textX = (float) $x;
    $baselineY = (float) $y;
    $display = $isBye ? 'BYE' : ($isPlaceholder ? '---' : $label);
    $badgeCount = 0;
    if (!$isBye && !$isPlaceholder && $registration && \App\Services\Performance\PlayerRatingBadgeService::visible()) {
        foreach ($registration->players as $ratedPlayer) {
            if (app(\App\Services\Performance\PlayerRatingBadgeService::class)->forPlayer($ratedPlayer->id, $draw ?? ($fixture ?? null)?->draw)) { $badgeCount++; }
        }
    }
    $badgeWidth = $badgeCount * 75;
    $badgeDisplay = $badgeCount ? \Illuminate\Support\Str::limit($display, max(5, (int) floor(($maxWidth - $badgeWidth - 14) / 6.8))) : $display;
    $estimatedWidth = min($maxWidth, max(36, (mb_strlen($badgeDisplay) * 6.8) + 14 + $badgeWidth));
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBye || $isPlaceholder): ?>
    <text x="<?php echo e($textX); ?>" y="<?php echo e($baselineY); ?>" class="player-name <?php echo e($isBye ? 'bye' : ''); ?>"><?php echo e($display); ?></text>
<?php else: ?>
    <rect
        x="<?php echo e($textX - 5); ?>"
        y="<?php echo e($baselineY - 14); ?>"
        width="<?php echo e($estimatedWidth); ?>"
        height="17"
        rx="5"
        class="player-identity-bg <?php echo e($isWinner ? 'winner' : ''); ?>"
    />
    <text x="<?php echo e($textX); ?>" y="<?php echo e($baselineY); ?>" <?php if($badgeCount): ?> textLength="<?php echo e(max(30, $maxWidth - 14)); ?>" lengthAdjust="spacingAndGlyphs" <?php endif; ?> class="player-name player-identity-text <?php echo e($isWinner ? 'winner' : ''); ?>"><title><?php echo e($display); ?></title><?php echo e($badgeDisplay); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $registration->players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw ?? ($fixture ?? null)?->draw ?? null,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw ?? ($fixture ?? null)?->draw ?? null),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php endif; ?></text>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\draw\partials\svg-player-identity.blade.php ENDPATH**/ ?>