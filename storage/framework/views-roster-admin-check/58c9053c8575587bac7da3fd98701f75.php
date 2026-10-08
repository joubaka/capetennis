<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id', 'maxWidth', 'modal' => false]));

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

foreach (array_filter((['id', 'maxWidth', 'modal' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
$id = $id ?? md5($attributes->wire('model'));

switch ($maxWidth ?? '') {
    case 'sm':
        $maxWidth = ' modal-sm';
        break;
    case 'md':
        $maxWidth = '';
        break;
    case 'lg':
        $maxWidth = ' modal-lg';
        break;
    case 'xl':
        $maxWidth = ' modal-xl';
        break;
    case '2xl':
    default:
        $maxWidth = '';
        break;
}
?>

<!-- Modal -->
<div x-data="{
        show: <?php if ((object) ($attributes->wire('model')) instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e($attributes->wire('model')->value()); ?>')<?php echo e($attributes->wire('model')->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e($attributes->wire('model')); ?>')<?php endif; ?>.defer,
    }" x-init="() => {
        let modal = $('#<?php echo e($id); ?>');
        $watch('show', value => {
            if (value) {
                modal.modal('show')
            } else {
                modal.modal('hide')
            }
        });

        modal.on('hide.bs.modal', function () {
            show = false
        })
    }" wire:ignore.self class="modal fade" tabindex="-1" id="<?php echo e($id); ?>" aria-labelledby="<?php echo e($id); ?>"
  aria-hidden="true" x-ref="<?php echo e($id); ?>">
  <div class="modal-dialog<?php echo e($maxWidth); ?>">
    <?php echo e($slot); ?>

  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\components\modal.blade.php ENDPATH**/ ?>