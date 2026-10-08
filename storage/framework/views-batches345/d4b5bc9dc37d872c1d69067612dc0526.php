<?php ($typeView = $event->frontend_type_view); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isInterprovincialTrials()): ?>
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
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\_type-content.blade.php ENDPATH**/ ?>