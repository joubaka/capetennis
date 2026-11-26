<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['current' => 1]));

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

foreach (array_filter((['current' => 1]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<style>
  .pp-stepper{margin:0 0 1.5rem;color:#172e45;overflow-wrap:anywhere}.pp-stepper ol{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;list-style:none;padding:0;margin:0}.pp-stepper li{border-top:3px solid #dce5ef;padding-top:.75rem;color:#617482;font-size:.875rem;line-height:1.4}.pp-stepper li[aria-current=step]{border-color:#004177;color:#172e45;font-weight:700}.pp-stepper li[data-complete=true]{border-color:#004177}.pp-stepper a{color:#004177;display:inline-flex;align-items:center;min-height:44px;gap:.5rem}.pp-stepper span{display:inline-flex;align-items:center;gap:.5rem;min-height:44px}.pp-stepper b{display:inline-grid;place-items:center;border:1px solid #c7d7e6;border-radius:50%;width:1.7rem;height:1.7rem;flex-shrink:0;font-size:.8rem}.pp-stepper [aria-current=step] b{background:#004177;border-color:#004177;color:#fff}.pp-stepper a:focus-visible{outline:3px solid #ff9f43;outline-offset:3px}@media(max-width:575.98px){.pp-stepper ol{grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem 1rem}.pp-stepper li{padding-top:.3rem}}
.pp-stepper [hidden]{display:none!important}.pp-stepper button{display:inline-flex;align-items:center;gap:.5rem;background:none;border:0;padding:0;min-height:44px;color:#004177;font:inherit;text-align:left}.pp-stepper button:focus-visible{outline:3px solid #ff9f43;outline-offset:3px}
</style>
<nav class="pp-stepper" aria-label="Player Plus steps" data-plus-stepper>
  <ol>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [1 => 'Discover Player Plus', 2 => 'Choose a player', 3 => 'Choose a plan', 4 => 'Review & pay']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $number => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li data-plus-step="<?php echo e($number); ?>" data-complete="<?php echo e($number < $current ? 'true' : 'false'); ?>" <?php if($number === (int) $current): ?> aria-current="step" <?php endif; ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($number < $current && $number <= 2): ?>
          <a href="<?php echo e($number === 1 ? route('ctr.index') : route('player-plus.index')); ?>"><b aria-hidden="true"><?php echo e($number); ?></b><?php echo e($label); ?></a>
        <?php else: ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($number === 3 && (int) $current === 3): ?><button type="button" data-plus-step-back hidden><b aria-hidden="true">3</b><?php echo e($label); ?></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <span><b aria-hidden="true"><?php echo e($number); ?></b><?php echo e($label); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ol>
</nav>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\player-plus-stepper.blade.php ENDPATH**/ ?>