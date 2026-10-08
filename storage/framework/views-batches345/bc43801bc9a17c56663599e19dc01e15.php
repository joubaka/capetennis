<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id' => 'navbarDropdown']));

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

foreach (array_filter((['id' => 'navbarDropdown']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<li class="nav-item dropdown">
  <a id="<?php echo e($id); ?>" <?php echo $attributes->merge(['class' => 'nav-link']); ?> role="button" data-toggle="dropdown" aria-expanded="false">
    <?php echo e($trigger); ?>

  </a>

  <div class="dropdown-menu dropdown-menu-right animate slideIn" aria-labelledby="<?php echo e($id); ?>">
    <?php echo e($content); ?>

  </div>
</li>
<?php /**PATH C:\wamp64\www\ct\resources\views\components\dropdown.blade.php ENDPATH**/ ?>