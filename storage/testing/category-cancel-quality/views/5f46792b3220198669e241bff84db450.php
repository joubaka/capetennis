<?php ($typeView = $event->frontend_type_view); ?>
<?php ($typeTemplate = \App\Support\EventTypePresentation::template($event)); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($typeTemplate === 'interprovincial-trials'): ?>
  <?php echo $__env->make('frontend.event.eventTypes.interprovincial-trials', ['publishedCategories' => $interprovincialTrialCategories ?? collect()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php elseif($typeView === 'masters'): ?>
  <?php echo $__env->make('frontend.event.eventTypes.masters', ['invitations' => $mastersInvitations ?? collect()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php elseif(view()->exists('frontend.event.eventTypes.'.$typeView)): ?>
  <?php echo $__env->make('frontend.event.eventTypes.'.$typeView, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
  <div class="card">
    <div class="card-body">
      <div class="alert alert-warning mb-0" role="status">
        This event type is not yet available for public display.
      </div>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isIndividual()): ?>
  <?php echo $__env->make('frontend.event.partials.category-adjustments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($eventDraws ?? collect())->where('published', true)->isNotEmpty()
    && !$event->isInterprovincialTrials()
    && !in_array($typeView, ['individual', 'team', 'interpro', 'cavaliers_trials'], true)): ?>
  <?php echo $__env->make('frontend.event.partials.event-draws', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\_type-content.blade.php ENDPATH**/ ?>