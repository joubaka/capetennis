<div class="card event-section-card mb-4">
  <div class="card-body event-card-padding p-4 p-xl-5">
    <div class="event-section-heading mb-4">
      <span class="event-section-icon" aria-hidden="true">
        <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 10v6m0-9v.01" />
        </svg>
      </span>
      <div>
        <h4 class="mb-1">Event information</h4>
        <p class="text-muted mb-0">Please review these details before entering the tournament.</p>
      </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled(strip_tags($event->information ?? ''))): ?>
      <div class="event-information-content">
        <?php echo $event->information; ?>

      </div>
    <?php else: ?>
      <div class="alert alert-secondary mb-0" role="status">
        The organiser has not added further event information yet.
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\event-information.blade.php ENDPATH**/ ?>