<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['size' => 'small']));

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

foreach (array_filter((['size' => 'small']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if (! $__env->hasRenderedOnce('f33eb3df-d6ca-4f51-a3ee-7616f2f39f52')): $__env->markAsRenderedOnce('f33eb3df-d6ca-4f51-a3ee-7616f2f39f52'); ?>
<style>
  .layout-menu .menu-link:has([data-ctr-mark]) [data-player-plus-coming-soon] { font-size:.65rem!important;padding:.3rem .45rem!important;white-space:nowrap!important; }
  .layout-menu .menu-link:has([data-ctr-mark]) > div { min-width:0; }
</style>
<?php endif; ?>

<?php ($dimension = $size === 'heading' ? '3rem' : '1.8rem'); ?>
<svg <?php echo e($attributes->class(['ctr-mark'])->merge(['style' => 'display:inline-block;width:'.$dimension.';height:'.$dimension.';flex-shrink:0;vertical-align:middle;'])); ?> viewBox="0 0 112 112" fill="none" aria-hidden="true" focusable="false" data-ctr-mark>
  <circle cx="56" cy="56" r="55" fill="#f1f5f9" />
  <g stroke="#64748b">
    <circle cx="56" cy="56" r="53" stroke-width="2.4" stroke-dasharray="41 .8 22 .6 57 1.2" />
    <circle cx="56" cy="56" r="49.5" stroke-width=".7" />
    <circle cx="56" cy="56" r="45" stroke-width="2.6" stroke-dasharray=".9 4.99" />
    <circle cx="56" cy="56" r="40.5" stroke-width=".8" />
  </g>
  <text x="56" y="65" text-anchor="middle" fill="#475569" style="font:700 27px Arial,sans-serif;letter-spacing:1px">CTR</text>
</svg>
<?php /**PATH C:\wamp64\www\ct\resources\views/components/ctr-mark.blade.php ENDPATH**/ ?>