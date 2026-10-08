<?php
  $fullyPublished = $counts['status'] === 'Published';
  $dayAction = $fullyPublished ? 'hide' : 'publish';
  $dayActionLabel = $fullyPublished ? 'Hide day' : (($counts['published'] > 0 || $counts['status'] === 'Updates not published') ? 'Publish updates' : 'Publish day');
?>
<div class="col-12 col-md-6 col-xl-4" data-day-card data-date="<?php echo e($day); ?>"><div class="border rounded p-3 h-100">
  <h6 data-day-label><?php echo e($day ? \Carbon\Carbon::parse($day)->format('l j M Y') : ''); ?></h6>
  <p class="mb-2"><strong data-day-status><?php echo e($counts['status']); ?></strong></p>
  <p class="small mb-1" data-day-counts><?php echo e($counts['saved']); ?> saved match times · <?php echo e($counts['published']); ?> published snapshot times</p>
  <p class="small mb-1" data-day-pending><?php echo e($counts['matched']); ?> saved times match the published snapshots · <?php echo e($counts['pending']); ?> matches with pending changes</p>
  <p class="small text-muted">Whole day · all venues and draws</p>
  <div class="d-flex flex-wrap gap-2 align-items-start">
    <a class="btn btn-sm btn-outline-primary" data-day-review href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $day])); ?>">Review day</a>
    <form method="post" data-day-action-form data-main-day-action action="<?php echo e(route('backend.event-venue-schedule.calendar.'.$dayAction, $event)); ?>">
      <?php echo csrf_field(); ?><input type="hidden" name="date" value="<?php echo e($day); ?>"><input type="hidden" name="revision" value="<?php echo e($schedulePublicationRevision); ?>">
      <button type="submit" data-day-toggle class="btn btn-sm <?php echo e($fullyPublished ? 'btn-outline-danger' : 'btn-success'); ?>" aria-pressed="<?php echo e($fullyPublished ? 'true' : 'false'); ?>" data-disabled="<?php echo e(($fullyPublished ? $counts['published'] === 0 : $counts['saved'] === 0) ? 'true' : 'false'); ?>" <?php if($fullyPublished ? $counts['published'] === 0 : $counts['saved'] === 0): echo 'disabled'; endif; ?>><?php echo e($dayActionLabel); ?></button>
    </form>
    <details data-day-hide-menu <?php if($fullyPublished || $counts['published'] === 0): ?> hidden <?php endif; ?>>
      <summary class="btn btn-sm btn-outline-secondary">More</summary>
      <form class="mt-2" method="post" data-day-action-form action="<?php echo e(route('backend.event-venue-schedule.calendar.hide', $event)); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="date" value="<?php echo e($day); ?>"><input type="hidden" name="revision" value="<?php echo e($schedulePublicationRevision); ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger" data-disabled="<?php echo e($counts['published'] === 0 ? 'true' : 'false'); ?>" <?php if($counts['published'] === 0): echo 'disabled'; endif; ?>>Hide times</button>
      </form>
    </details>
  </div>
</div></div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\partials\day-publication-card.blade.php ENDPATH**/ ?>