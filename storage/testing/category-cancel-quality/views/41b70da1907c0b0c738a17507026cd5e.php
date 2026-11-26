
<style><?php echo file_get_contents(public_path('css/flexible-monrad.css')); ?>

<?php echo file_get_contents(public_path('css/tennis-bracket.css')); ?></style>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['js/tennis-bracket-layout.js', 'js/tennis-bracket.js', 'js/interpro-bracket.js']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<script><?php echo str_ireplace('</script', '<\/script', file_get_contents(public_path($module))); ?></script>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\draw\partials\interpro-print-assets.blade.php ENDPATH**/ ?>