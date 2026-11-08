<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $publicationDraw->event)): ?>
  <?php
    $publicationEvent = $publicationDraw->event;
    $publicationScope = ['event' => $publicationEvent->id, 'draw_id' => $publicationDraw->id];
    $publicationDays = [];
    if ($publicationEvent->start_date) {
      $dayStart = \Carbon\Carbon::parse($publicationEvent->start_date)->startOfDay();
      $dayEnd = $publicationEvent->end_date ? \Carbon\Carbon::parse($publicationEvent->end_date)->startOfDay() : $dayStart->copy();
      for ($date = $dayStart->copy(), $number = 1; $date->lte($dayEnd) && $number <= 31; $date->addDay(), $number++) {
        $publicationDays[$date->toDateString()] = 'Day '.$number.' · '.$date->format('l j M Y');
      }
    }
  ?>
  <details id="draw-day-publication-<?php echo e($publicationDraw->id); ?>" class="<?php echo e($dayPublicationClass ?? 'card card-body mb-3'); ?>"
    data-draw-day-publication data-draw-id="<?php echo e($publicationDraw->id); ?>" data-initial-unconfirmed="true" data-event-id="<?php echo e($publicationEvent->id); ?>"
    data-event-days="<?php echo e(json_encode($publicationDays)); ?>"
    <?php if($workspaceDayPublication ?? false): ?> data-workspace-day-publication="true" <?php endif; ?>
    data-status-url="<?php echo e(route('backend.event-venue-schedule.calendar.publication-status', $publicationScope)); ?>"
    data-publish-url="<?php echo e(route('backend.event-venue-schedule.calendar.publish', $publicationEvent)); ?>"
    data-hide-url="<?php echo e(route('backend.event-venue-schedule.calendar.hide', $publicationEvent)); ?>"
    data-calendar-url="<?php echo e(route('backend.event-venue-schedule.calendar', $publicationScope)); ?>">
    <summary class="py-2"><span class="fw-semibold">Schedule by day</span><span class="small text-muted ms-2">This draw only</span></summary>
    <p class="small text-muted mt-2">Review, publish or hide this draw's match times for a day across all venues. Hiding keeps saved bookings. A moved match replaces its previous public time when you publish the new day.</p>
    <p class="small text-muted">Players see times after the draw and any required team ties are published.</p>
    <div class="small mb-3" data-day-feedback role="status" aria-live="polite">Open to check saved and published days.</div>
    <button type="button" class="btn btn-sm btn-outline-primary mb-3" data-day-status-retry hidden>Retry day status check</button>
    <div class="row g-3" data-day-cards></div>
    <template data-day-card-template>
      <?php echo $__env->make('backend.headOffice.partials.day-publication-card', ['event' => $publicationEvent, 'day' => '', 'dayDrawId' => $publicationDraw->id, 'schedulePublicationRevision' => '', 'counts' => ['saved' => 0, 'published' => 0, 'matched' => 0, 'pending' => 0, 'status' => 'Not scheduled']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </template>
    <noscript><a href="<?php echo e(route('backend.event-venue-schedule.calendar', $publicationScope)); ?>">Open this draw’s saved calendar</a></noscript>
  </details>
  <?php echo $__env->make('backend.schedule.partials.day-publication-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/schedule/partials/draw-day-publication.blade.php ENDPATH**/ ?>