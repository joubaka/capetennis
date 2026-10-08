<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['on']));

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

foreach (array_filter((['on']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="alert alert-success" role="alert" x-data="{ shown: false, timeout: null }"
  x-init="window.Livewire.find('<?php echo e($_instance->getId()); ?>').on('<?php echo e($on); ?>', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000);  })"
  x-show.transition.opacity.out.duration.1500ms="shown" style="display: none;"
  <?php echo e($attributes->merge(['class' => 'small'])); ?>>
  <div class="alert-body">
    <?php echo e($slot->isEmpty() ? 'Saved.' : $slot); ?>

  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\components\action-message.blade.php ENDPATH**/ ?>