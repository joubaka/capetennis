<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['rating' => []]));

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

foreach (array_filter((['rating' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $index = $rating['confidence_index'] ?? null;
    $valid = is_numeric($index) && is_finite((float) $index) && $index >= 0 && $index <= 100;
    $label = $rating['confidence_label'] ?? ($valid ? ($index < 40 ? 'Low' : ($index < 70 ? 'Medium' : 'High')) : 'Not recorded');
    $colours = match ($label) {
        'Low' => ['#fff0ee', '#8c2820', '#e6aaa3'],
        'Medium' => ['#fff4d6', '#735000', '#d7b765'],
        'High' => ['#e4f4eb', '#1d6240', '#8dbda2'],
        default => ['#eef1f4', '#455568', '#b5c0ca'],
    };
    $explanation = 'Evidence strength and freshness, not rating accuracy or chance of winning. '.($rating['confidence_explanation'] ?? '');
?>
<span <?php echo e($attributes->class(['ctr-confidence-badge'])->merge(['title' => trim($explanation), 'aria-label' => 'Evidence confidence: '.($valid ? (int) round($index).'%. ' : '').$label.'. '.trim($explanation)])); ?> data-ctr-confidence style="display:inline-block;max-width:100%;padding:.3rem .5rem;border:1px solid <?php echo e($colours[2]); ?>;border-radius:.4rem;background:<?php echo e($colours[0]); ?>;color:<?php echo e($colours[1]); ?>;font-weight:700;line-height:1.35;overflow-wrap:anywhere"><?php echo e($valid ? (int) round($index).'%' : $label); ?> evidence confidence</span>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\ctr-confidence-badge.blade.php ENDPATH**/ ?>