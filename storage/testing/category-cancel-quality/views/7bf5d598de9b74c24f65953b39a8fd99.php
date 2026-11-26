<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user') || (auth()->user()?->hasRole('admin') && auth()->user()->is_event_admin($event->id))): ?>
        <?php echo $__env->make('backend.nominations.cavaliers-roster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\cavaliers_trials_show.blade.php ENDPATH**/ ?>