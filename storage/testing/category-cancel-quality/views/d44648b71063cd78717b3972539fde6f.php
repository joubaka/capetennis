

<?php $__env->startSection('title', 'Player Profile'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="card">
    <div class="card-header"><a href="<?php echo e(URL::previous()); ?>" class="btn btn-primary">Back</a></div>

</div>
<div class="card">
    <div class="card-header"><h3><?php echo e($player->getFullNameAttribute()); ?></h3></div>
    <div class="card-body">
      <p class="small text-muted">Showing up to <?php echo e($results['limit'] ?? 100); ?> recent team result records.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($results['has_more'] ?? false): ?> Older records are available through the event results pages.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $results['fixture']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

<?php [$homeClass, $awayClass] = \App\Support\ResultPresentation::classes($result->fixture); ?>
<p><?php echo e($result->created_at->format('j F , Y')); ?> <span class="badge bg-label-success"> <?php echo e($result->fixture->draw->events->name); ?></span> <span class="<?php echo e($homeClass); ?>"><?php echo e($result->team1->getFullNameAttribute()); ?><?php if (isset($component)) { $__componentOriginal2c336395b6812522970387a8085dd1d4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2c336395b6812522970387a8085dd1d4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.result-label','data' => ['outcome' => $homeClass]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('result-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['outcome' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($homeClass)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $attributes = $__attributesOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__attributesOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $component = $__componentOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__componentOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?></span> vs <span class="<?php echo e($awayClass); ?>"><?php echo e($result->team2->getFullNameAttribute()); ?><?php if (isset($component)) { $__componentOriginal2c336395b6812522970387a8085dd1d4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2c336395b6812522970387a8085dd1d4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.result-label','data' => ['outcome' => $awayClass]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('result-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['outcome' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($awayClass)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $attributes = $__attributesOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__attributesOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2c336395b6812522970387a8085dd1d4)): ?>
<?php $component = $__componentOriginal2c336395b6812522970387a8085dd1d4; ?>
<?php unset($__componentOriginal2c336395b6812522970387a8085dd1d4); ?>
<?php endif; ?></span> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $result->fixture->teamResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php echo e($r->team1_score. '-'.$r->team2_score.';'); ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> </p>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-muted">No recorded team results for this player.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

</div>



</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player\player_results.blade.php ENDPATH**/ ?>