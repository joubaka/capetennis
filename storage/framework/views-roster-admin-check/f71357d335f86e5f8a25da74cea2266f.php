<?php
  $eventWorkspaceIcon = $eventWorkspaceIcon ?? 'ti-trophy';
  $eventWorkspaceSubtitle = $eventWorkspaceSubtitle ?? null;
  $eventWorkspaceHomeUrl = $eventWorkspaceHomeUrl ?? route('admin.events.overview', $event);
  $eventWorkspaceShowHome = $eventWorkspaceShowHome ?? true;
?>
<div class="event-workspace-chrome no-print">
  <?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => $event->name,'eyebrow' => 'Tournament workspace','subtitle' => $eventWorkspaceSubtitle,'icon' => $eventWorkspaceIcon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->name),'eyebrow' => 'Tournament workspace','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($eventWorkspaceSubtitle),'icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($eventWorkspaceIcon)]); ?>
     <?php $__env->slot('meta', null, []); ?> 
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->start_date): ?>
        <span><i class="ti ti-calendar-event me-1" aria-hidden="true"></i><?php echo e($event->start_date->format('d M Y')); ?></span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->venue_name): ?><span><i class="ti ti-map-pin me-1" aria-hidden="true"></i><?php echo e($event->venue_name); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->status_label): ?><span class="badge bg-label-primary"><?php echo e($event->status_label); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php $__env->endSlot(); ?>
     <?php $__env->slot('actions', null, []); ?> 
      <a class="event-workspace-action" href="<?php echo e(route('events.show', $event)); ?>" target="_blank" rel="noopener">
        <i class="ti ti-world" aria-hidden="true"></i>
        <span>Public page</span>
        <i class="ti ti-external-link event-workspace-action__external" aria-hidden="true"></i>
      </a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventWorkspaceShowHome): ?>
        <a class="event-workspace-action" href="<?php echo e($eventWorkspaceHomeUrl); ?>">
          <i class="ti ti-home" aria-hidden="true"></i>
          <span>Event home</span>
        </a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php $__env->endSlot(); ?>
   <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
  <?php echo $__env->make('backend.event.partials.workspace-nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('schedule_adaptation_warning')): ?>
  <div class="alert alert-warning mb-3" role="status">
    <?php echo e(session('schedule_adaptation_warning')); ?>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
      <a class="alert-link ms-1" href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>">Review updated schedule</a>
    <?php endif; ?>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\header.blade.php ENDPATH**/ ?>