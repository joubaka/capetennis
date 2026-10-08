<?php
    /** @var \App\Models\Fixture|null $fixture */
    $fx = $fixture ?? null;

    $x1    = $x;            // left
    $x2    = $x + 200;      // right edge of the match box
    $topY  = $y;
    $botY  = $y + 40;
    $midY  = ($topY + $botY) / 2;
?>


<line x1="<?php echo e($x1); ?>" y1="<?php echo e($topY); ?>" x2="<?php echo e($x2); ?>" y2="<?php echo e($topY); ?>" stroke="black" />
<line x1="<?php echo e($x1); ?>" y1="<?php echo e($botY); ?>" x2="<?php echo e($x2); ?>" y2="<?php echo e($botY); ?>" stroke="black" />


<text x="<?php echo e($x1 + 10); ?>" y="<?php echo e($topY - 5); ?>"  class="name"><?php echo e($name($fx, 1)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration1?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw ?? $fx?->draw ?? null,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw ?? $fx?->draw ?? null),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>
<text x="<?php echo e($x1 + 10); ?>" y="<?php echo e($botY - 5); ?>" class="name"><?php echo e($name($fx, 2)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fx?->registration2?->players ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ratedPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $ratedPlayer->id,'context' => $draw ?? $fx?->draw ?? null,'svg' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratedPlayer->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw ?? $fx?->draw ?? null),'svg' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></text>


<line x1="<?php echo e($x2); ?>" y1="<?php echo e($topY); ?>" x2="<?php echo e($x2); ?>" y2="<?php echo e($botY); ?>" stroke="black"/>


<line x1="<?php echo e($x2); ?>" y1="<?php echo e($midY); ?>" x2="<?php echo e($x2 + 80); ?>" y2="<?php echo e($midY); ?>" stroke="black"/>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\match-box.blade.php ENDPATH**/ ?>