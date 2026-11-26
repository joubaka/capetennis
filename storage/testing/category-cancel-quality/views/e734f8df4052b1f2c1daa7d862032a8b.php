<?php
  $navigationDate = $data['date'];
  $navigationStart = $event->start_date ? \Carbon\Carbon::parse($event->start_date)->startOfDay() : \Carbon\Carbon::parse($navigationDate);
  $navigationEnd = $event->end_date ? \Carbon\Carbon::parse($event->end_date)->startOfDay() : $navigationStart->copy();
?>
<nav class="d-flex gap-2 overflow-auto pb-2 mb-3" aria-label="Planning days" data-schedule-day-navigation>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($navigationDay = $navigationStart->copy(), $navigationIndex = 0; $navigationDay->lte($navigationEnd) && $navigationIndex < 31; $navigationDay->addDay(), $navigationIndex++): ?>
    <?php ($navigationTarget = $navigationDay->toDateString()); ?>
    <a style="min-height:44px;flex-shrink:0" class="btn text-nowrap <?php echo e($navigationTarget === $navigationDate ? 'btn-primary' : 'btn-outline-primary'); ?>" <?php if($navigationTarget === $navigationDate): ?> aria-current="date" <?php endif; ?> href="<?php echo e(route($mode === 'whole-tie' ? 'backend.event-venue-schedule.calendar.full-day' : 'backend.event-venue-schedule.calendar.edit-day', ['event'=>$event->id,'date'=>$navigationTarget] + ($navigationDraw ? ['draw_id'=>$navigationDraw->id] : []) + (!empty($data['origin_draw_id']) ? ['origin_draw_id'=>$data['origin_draw_id']] : []))); ?>">Day <?php echo e($navigationIndex + 1); ?> - <?php echo e($navigationDay->format('D j M')); ?></a>
  <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</nav>
<p class="small text-muted">Day buttons open that day's saved choices. They do not move bookings from another day. Leave this page only after saving or discarding pending edits.</p>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\schedule\partials\day-navigation.blade.php ENDPATH**/ ?>