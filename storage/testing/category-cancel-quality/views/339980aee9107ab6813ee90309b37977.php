<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['score' => null, 'local' => false, 'title' => null, 'compact' => false, 'size' => 'standard', 'stage' => null, 'overall' => false, 'label' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['score' => null, 'local' => false, 'title' => null, 'compact' => false, 'size' => 'standard', 'stage' => null, 'overall' => false, 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $validScore = is_numeric($score) && is_finite((float) $score);
    $small = $size === 'small';
    $inline = $size === 'inline';
    $diameter = $inline ? '3rem' : ($small ? '4.5rem' : '7rem');
    $padding = $inline ? '.35rem' : ($small ? '.55rem' : '.75rem');
    $captionSize = $inline ? '.5rem' : ($small ? '.65rem' : '.7rem');
    // Stage is explicit playing-condition metadata, never inferred from age, score or category names.
    $ball = is_string($stage) ? strtolower(trim($stage)) : '';
    $stageLabels = ['red' => 'Red ball', 'orange' => 'Orange ball', 'green' => 'Green/Yellow ball', 'yellow' => 'Green/Yellow ball', 'standard' => 'Green/Yellow ball'];
    $stageKnown = isset($stageLabels[$ball]);
    $stampStage = in_array($ball, ['red', 'orange'], true) ? $ball : 'standard';
    [$ink, $paper, $ring] = match ($stampStage) {
        'red' => ['#c91822', '#fff0f0', '#ed2029'],
        'orange' => ['#b94a00', '#fff1df', '#ed7100'],
        default => ['#095c60', '#edf8f7', '#095c60'],
    };
    if ($overall) { [$ink, $paper, $ring] = ['#475569', '#f1f5f9', '#64748b']; $stageKnown = false; $stampStage = 'overall'; }
    $stageLabel = $overall ? 'CTR · Green/Yellow · all ages' : ($stageKnown ? ($label ?? $stageLabels[$ball]) : 'Ball stage not recorded');
?>
<span <?php echo e($attributes->class(['ctr-badge', 'ctr-badge--local' => $local])->merge(['style' => 'display:inline-flex;flex-direction:column;align-items:center;justify-content:center;position:relative;gap:.1rem;width:'.$diameter.';height:'.$diameter.';flex-shrink:0;box-sizing:border-box;padding:'.$padding.';margin-block:.15rem;margin-inline:.2rem;text-align:center;vertical-align:middle;white-space:nowrap;overflow-wrap:normal;word-break:normal;border:0;border-radius:50%;background:'.$paper.';color:'.$ink.'!important;font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums;', 'aria-label' => 'CTR '.($validScore ? number_format((float) $score, 1) : 'unavailable').($local && $validScore ? ' within-group only' : '').' · '.$stageLabel, 'title' => $title ?? ($local ? 'Provisional within-group score only; not comparable with other groups.' : ($overall ? $stageLabel : 'Cape Tennis Rating: provisional singles ability, not official ranking points.'))])); ?> data-ctr-badge data-ctr-stage="<?php echo e($stampStage); ?>" data-ctr-stage-known="<?php echo e($stageKnown ? 'true' : 'false'); ?>">
    
    <svg aria-hidden="true" focusable="false" viewBox="0 0 112 112" fill="none" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;color:<?php echo e($ring); ?>">
        <circle cx="56" cy="56" r="53" stroke="currentColor" stroke-width="2.4" stroke-dasharray="41 .8 22 .6 57 1.2" />
        <circle cx="56" cy="56" r="49.5" stroke="currentColor" stroke-width=".7" />
        <circle cx="56" cy="56" r="45" stroke="currentColor" stroke-width="2.6" stroke-dasharray=".9 4.99" />
        <circle cx="56" cy="56" r="40.5" stroke="currentColor" stroke-width=".8" />
    </svg>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($inline || $small)): ?><svg aria-hidden="true" width="<?php echo e($small ? 12 : 15); ?>" height="<?php echo e($small ? 12 : 15); ?>" viewBox="0 0 32 32" fill="none" style="flex-shrink:0"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <span style="font-size:<?php echo e($captionSize); ?>;letter-spacing:.12em;line-height:1">CTR</span>
    <span style="font-size:<?php echo e($validScore ? ($inline ? '.75rem' : ($small ? '1.1rem' : ($local ? '1.2rem' : '1.35rem'))) : '.65rem'); ?>;line-height:1.1"><?php echo e($validScore ? number_format((float) $score, 1) : (($inline || $small) ? '—' : 'unavailable')); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($validScore): ?><span style="font-size:<?php echo e($inline ? '.375rem' : '.6rem'); ?>;font-weight:500;line-height:1">/100</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($local && $validScore && !$inline && !$small): ?><span style="color:#754b0c;font-size:.5rem;font-weight:600;line-height:1.1;white-space:nowrap">within-group only</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</span>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stageKnown && in_array($ball, ['red', 'orange'], true)): ?><span class="small" style="color:<?php echo e($ink); ?>;font-size:.65rem;white-space:normal"><?php echo e($stageLabel); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($local && $validScore && ($inline || $small)): ?><span class="small" style="color:#754b0c;font-size:.65rem;white-space:normal">within-group only</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$validScore && ($inline || $small)): ?><span class="small" style="font-size:.65rem;white-space:normal">unavailable</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\ctr-badge.blade.php ENDPATH**/ ?>