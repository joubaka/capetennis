<?php $__env->startSection('title', 'Public schedule preview – '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-3"><h3>Public schedule preview</h3><p class="text-muted">Only published match times and currently public draws appear here. Private saved changes are excluded.</p><a class="btn btn-outline-primary mb-3" href="<?php echo e(route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope)); ?>">Back to saved schedule</a>
<p class="small text-muted d-md-none">Swipe the match table sideways to see every column.</p><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Day / time</th><th>Venue / court</th><th>Draw</th><th>Participants</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td class="text-nowrap"><?php echo e(substr($row['scheduled_at'],0,16)); ?></td><td><?php echo e($row['venue_name']); ?> / <?php echo e($row['court']); ?></td><td><?php echo e($row['draw_name']); ?></td><td><?php if (isset($component)) { $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.scheduled-participants','data' => ['row' => $row]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('scheduled-participants'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['row' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $attributes = $__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__attributesOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e)): ?>
<?php $component = $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e; ?>
<?php unset($__componentOriginald032f6f37ccfe5a3c340f472d904fe2e); ?>
<?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="text-muted py-4">No public match times in this view.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\schedule\public-schedule-preview.blade.php ENDPATH**/ ?>