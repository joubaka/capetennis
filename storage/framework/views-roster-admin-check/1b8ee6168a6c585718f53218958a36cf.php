<?php
    $ageGroups = $registration->categoryEvents
        ->filter(fn ($categoryEvent) => $registeredEvent && $categoryEvent->event_id == $registeredEvent->id)
        ->map(fn ($categoryEvent) => $categoryEvent->category?->name)
        ->filter(fn ($name) => filled($name))
        ->unique()
        ->values();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ageGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ageGroup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <span class="badge bg-label-primary"><?php echo e($ageGroup); ?></span>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <span class="text-muted">Not recorded</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\multiend\partials\registration-age-groups.blade.php ENDPATH**/ ?>