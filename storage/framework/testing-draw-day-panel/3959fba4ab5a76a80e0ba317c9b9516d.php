
<?php $__env->startSection('title', 'Saved schedule – '.$event->name); ?>
<?php $__env->startSection('page-style'); ?>
<style>
[data-saved-calendar] nav a { flex-shrink:0; }
[data-saved-calendar] .btn, [data-saved-calendar] select { min-height:44px; }
[data-saved-calendar] summary { min-height:44px; cursor:pointer; }
[data-saved-calendar] .calendar-filters { padding:.65rem 1rem; }
[data-saved-calendar] .calendar-table td { vertical-align:middle; }
[data-saved-calendar] .calendar-table th { white-space:nowrap; }
[data-saved-calendar] .calendar-publication { border-left:3px solid #287d74; }
[data-saved-calendar] .publication-toggle { display:flex; flex-wrap:wrap; gap:4px; padding:4px; border:1px solid #dbe2eb; border-radius:10px; background:#f5f7fa; }
[data-saved-calendar] .publication-toggle form { display:flex; margin:0; flex:1; }
[data-saved-calendar] .publication-toggle .btn { width:100%; border-radius:7px; min-width:120px; }
[data-saved-calendar] .publication-toggle .btn[aria-pressed="true"] { opacity:1; box-shadow:0 1px 3px #172b4d20; }
@media (max-width:767.98px) {
  [data-saved-calendar] .calendar-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); }
  [data-saved-calendar] .calendar-table, [data-saved-calendar] .calendar-table tbody { display:block; }
  [data-saved-calendar] .calendar-table tr { display:block; padding:.75rem 1rem; border-bottom:1px solid #dbe2eb; }
  [data-saved-calendar] .calendar-table td { display:grid; grid-template-columns:6rem minmax(0,1fr); gap:.5rem; padding:.3rem 0; border:0; overflow-wrap:anywhere; white-space:normal!important; }
  [data-saved-calendar] .calendar-table td::before { content:attr(data-label); font-weight:600; }
  [data-saved-calendar] .calendar-table td[colspan] { display:block; }
}
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php
  $pendingPlayoffs = $pendingPlayoffs ?? collect();
  $planningDraws = !empty($scope['draw_id']) ? $draws->where('id', (int) $scope['draw_id']) : $draws;
  $teamPlanning = $planningDraws->contains(fn ($draw) => $draw->isTeamDraw()) || ($planningDraws->isEmpty() && $event->isTeam());
  $openTeamProgramme = $date !== 'all' && $planningDraws->isNotEmpty()
    && $planningDraws->every(fn ($draw) => $draw->isTeamDraw() && app(\App\Services\Scheduling\ScheduleProgramme::class)->key($draw) !== null)
    && empty($scope['venue_id']) && empty($scope['draw_id']) && empty($scope['origin_draw_id'])
    && in_array($date, app(\App\Services\Scheduling\ScheduleProgramme::class)->eventDates($event), true);
?>
<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="container-xxl py-3" data-saved-calendar>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h3 class="mb-1">Saved schedule</h3><p class="text-muted mb-0"><?php echo e($event->name); ?></p></div><a class="btn btn-outline-primary" href="<?php echo e(route('headOffice.show', $event)); ?>">Back to draws</a></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <h5 class="mb-1">Choose a day</h5>
  <p class="small text-muted mb-2">Open a day to review saved matches, add bookings, or change which times players can see.</p>
  <nav class="d-flex gap-2 overflow-auto pb-2 mb-3" aria-label="Schedule days" data-schedule-day-navigation>
    <a class="btn text-nowrap <?php echo e($date === 'all' ? 'btn-primary' : 'btn-outline-primary'); ?>" href="<?php echo e(route('backend.event-venue-schedule.calendar',['event'=>$event->id]+array_replace($scope,['date'=>'all']))); ?>">All days</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $dayDate = \Carbon\Carbon::parse($day)->startOfDay();
        $dayNumber = $event->start_date ? (int) \Carbon\Carbon::parse($event->start_date)->startOfDay()->diffInDays($dayDate) + 1 : $loop->iteration;
      ?>
      <a class="btn text-nowrap <?php echo e($day === $date ? 'btn-primary' : 'btn-outline-primary'); ?>" <?php if($day === $date): ?> aria-current="date" <?php endif; ?> href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event'=>$event->id]+array_replace($scope,['date'=>$day]))); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dayNumber > 0): ?>Day <?php echo e($dayNumber); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($dayDate->format('D j M')); ?> <span class="badge bg-white text-primary ms-1"><?php echo e($count); ?> saved</span></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <span class="text-muted">No schedule dates yet.</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </nav>
  <p class="small mb-2"><strong>Viewing:</strong> <?php echo e($date === 'all' ? 'All days' : \Carbon\Carbon::parse($date)->format('D j M Y')); ?> · <?php echo e(!empty($scope['venue_id']) ? ($venues->firstWhere('id', (int) $scope['venue_id'])?->name ?? 'Selected venue') : 'All venues'); ?> · <?php echo e(!empty($scope['draw_id']) ? ($draws->firstWhere('id', (int) $scope['draw_id'])?->drawName ?? 'Selected draw') : 'All draws'); ?></p>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($date !== 'all' && $savedCount > 0): ?>
      <a class="btn btn-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event'=>$event->id]+$scope+['replan_saved'=>1])); ?>">Replan this day’s saved matches</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a class="btn btn-primary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event'=>$event->id] + ($date !== 'all' ? [($openTeamProgramme ? 'programme_date' : 'date')=>$date] : []) + (!empty($scope['venue_id']) ? ['venue_id'=>$scope['venue_id']] : []) + (!empty($scope['draw_id']) ? ['draw_ids'=>[$scope['draw_id']]] : []) + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : []))); ?>"><?php echo e($date === 'all' ? 'Schedule more matches' : 'Add matches to this day'); ?></a>
    <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>$date !== 'all' ? $date : ($event->start_date ? \Carbon\Carbon::parse($event->start_date)->toDateString() : now()->toDateString())] + (!empty($scope['draw_id']) ? ['draw_id'=>$scope['draw_id']] : []) + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : []))); ?>"><?php echo e($teamPlanning ? 'Replan selected rounds - whole ties' : 'Replan selected rounds - full day'); ?></a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($date !== 'all' && !empty($scope['draw_id'])): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar.edit-day', ['event'=>$event->id, 'date'=>$date, 'draw_id'=>$scope['draw_id']] + (!empty($scope['origin_draw_id']) ? ['origin_draw_id'=>$scope['origin_draw_id']] : []))); ?>">Edit saved times - this draw and day</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <p class="small text-muted"><?php echo e($openTeamProgramme ? 'Add matches opens the team planner with this day selected.' : ($teamPlanning ? 'Add matches opens position-band planning.' : 'Add matches opens match and round planning.')); ?> Edit saved times includes only this draw's saved matches on the selected day. <?php echo e($teamPlanning ? 'Whole-tie' : 'Full-day'); ?> replanning can include unscheduled matches and draws across all venues; calendar filters do not restrict it.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($scope['origin_draw_id']) || !empty($scope['draw_id'])): ?><a class="btn btn-outline-primary mb-3" href="<?php echo e(route('draw.guide.show', [$event, $draws->firstWhere('id', (int) ($scope['origin_draw_id'] ?? $scope['draw_id']))] + ($date !== 'all' ? ['schedule_date'=>$date] : []))); ?>"><?php echo e(!empty($scope['origin_draw_id']) && ($scope['origin_draw_id'] != ($scope['draw_id'] ?? null)) ? 'Back to original draw' : 'Back to this draw'); ?> - <?php echo e($draws->firstWhere('id', (int) ($scope['origin_draw_id'] ?? $scope['draw_id']))?->drawName); ?> - setup &amp; publication</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php
    $publishedCount = (int) ($publicationCounts['Published'] ?? 0);
    $privateCount = (int) ($publicationCounts['Private'] ?? 0);
    $updatedCount = (int) ($publicationCounts['Updates private'] ?? 0);
    $blockedCount = (int) ($publicationCounts['Not publicly visible'] ?? 0);
    $hasPublishedTimes = $publishedCount + $updatedCount + $blockedCount + $retainedCount > 0;
    $fullyPrivate = !$hasPublishedTimes;
    $fullyPublished = $hasPublishedTimes && $privateCount === 0 && $updatedCount === 0 && ($retainedCount === 0);
    $publicationLabel = ($savedCount === 0) && ($retainedCount === 0) ? 'No saved times' : ($fullyPrivate ? 'Private' : ($privateCount > 0 ? 'Partly published' : ($updatedCount > 0 || ($retainedCount > 0) ? 'Published · updates private' : ($blockedCount > 0 ? 'Published · visibility blocked' : 'Published'))));
  ?>
  <section class="card calendar-publication mb-3" id="calendar-publication" aria-label="Schedule publication"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><div class="d-flex flex-wrap align-items-center gap-2"><strong>Public schedule</strong><span class="badge <?php echo e($fullyPrivate ? 'bg-label-secondary' : ($fullyPublished && $blockedCount === 0 ? 'bg-label-success' : 'bg-label-warning')); ?>"><?php echo e($publicationLabel); ?></span></div><div class="small text-muted mt-2"><?php echo e($publishedCount); ?> published times · <?php echo e($privateCount); ?> private <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($updatedCount): ?> · <?php echo e($updatedCount); ?> with private updates <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($blockedCount): ?> · <?php echo e($blockedCount); ?> not publicly visible <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($retainedCount > 0)): ?> · <?php echo e($retainedCount); ?> previous public times retained <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><div class="small text-muted">Draws and team ties must also be published before players can see their times.</div></div><div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.calendar.preview',['event'=>$event->id]+$scope)); ?>">Preview public schedule</a>
    <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar.audit', ['event'=>$event->id]+$scope)); ?>">Run schedule audit</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($date !== 'all'): ?>
      <span class="small text-muted w-100">Publication scope: <?php echo e(\Carbon\Carbon::parse($date)->format('D j M Y')); ?> · <?php echo e(!empty($scope['venue_id']) ? ($venues->firstWhere('id', (int) $scope['venue_id'])?->name ?? 'Selected venue') : 'all venues'); ?> · <?php echo e(!empty($scope['draw_id']) ? ($draws->firstWhere('id', (int) $scope['draw_id'])?->drawName ?? 'Selected draw') : 'all draws'); ?>. Updates apply to this scope. A moved match also replaces its previous public time, even on another day or at another venue.</span>
      <div class="publication-toggle" role="group" aria-label="Public match time visibility">
        <form method="post" action="<?php echo e(route('backend.event-venue-schedule.calendar.hide',$event)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="revision" value="<?php echo e($revision); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scope; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><button type="submit" class="btn <?php echo e($fullyPrivate ? 'btn-primary' : 'btn-outline-secondary'); ?>" aria-pressed="<?php echo e($fullyPrivate ? 'true' : 'false'); ?>" <?php if($fullyPrivate): echo 'disabled'; endif; ?>><i class="ti ti-eye-off me-1" aria-hidden="true"></i><?php echo e($fullyPrivate ? 'Hidden from players' : 'Hide times'); ?></button></form>
        <form method="post" action="<?php echo e(route('backend.event-venue-schedule.calendar.publish',$event)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="revision" value="<?php echo e($revision); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scope; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><button type="submit" class="btn <?php echo e($fullyPublished ? 'btn-success' : 'btn-outline-success'); ?>" aria-pressed="<?php echo e($fullyPublished ? 'true' : 'false'); ?>" <?php if($fullyPublished || (($savedCount === 0) && ($retainedCount === 0))): echo 'disabled'; endif; ?>><i class="ti ti-world me-1" aria-hidden="true"></i><?php echo e($updatedCount || ($retainedCount > 0) ? 'Publish updates' : ($fullyPublished ? 'Published' : 'Publish')); ?></button></form>
      </div>
    <?php else: ?><span class="small text-muted align-self-center">Choose a day above to publish or hide times in the selected venue and draw scope.</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div></section>
  <p class="small text-muted mb-3">Hiding times keeps all saved bookings and results. Saved changes stay private until published. Existing public times stay visible until you publish updates or hide them.</p>
  <details class="card calendar-filters mb-3" <?php if($date !== 'all' && empty($scope['draw_id'])): ?> open <?php endif; ?>>
    <summary style="min-height:44px">Venue and draw filters</summary>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($date !== 'all' && empty($scope['draw_id'])): ?><p class="small mt-2 mb-0">Choose a draw to edit only its saved times for this day. To <?php echo e($teamPlanning ? 'regroup whole ties' : 'replan rounds'); ?> and include unscheduled matches, use Replan selected rounds above.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="get" class="mt-3"><div class="row g-3 align-items-end">
    <input type="hidden" name="date" value="<?php echo e($date); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($scope['origin_draw_id'])): ?><input type="hidden" name="origin_draw_id" value="<?php echo e($scope['origin_draw_id']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="col-12 col-md-5"><label class="form-label" for="calendar-venue">Venue</label><select class="form-select" id="calendar-venue" name="venue_id"><option value="">All venues</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>" <?php if((int)($scope['venue_id']??0)===$venue->id): echo 'selected'; endif; ?>><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div class="col-12 col-md-5"><label class="form-label" for="calendar-draw">Draw / age group</label><select class="form-select" id="calendar-draw" name="draw_id"><option value="">All draws</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($draw->id); ?>" <?php if((int)($scope['draw_id']??0)===$draw->id): echo 'selected'; endif; ?>><?php echo e($draw->drawName); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100">Show schedule</button></div>
  </div></form>
  </details>
  <div class="d-flex flex-wrap justify-content-between gap-2 small text-muted mb-2"><span><?php echo e($savedCount); ?> saved matches in this view - showing <?php echo e($rows->count()); ?></span><span><?php echo e($unscheduledCount); ?> existing matches without a day or time<?php echo e(!empty($scope['draw_id']) ? ' in this draw' : ' across the event'); ?></span></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingPlayoffs->isNotEmpty()): ?>
    <div class="alert alert-warning" role="status">
      <strong>Playoff preparation is still needed.</strong> The booking count covers existing fixtures only.
      <ul class="mb-0 mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pendingPlayoffs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pending): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li><?php echo e($pending['draw_name']); ?>: <?php echo e(count($pending['missing_stages'])); ?> configured playoff stage(s) have no fixtures.
          <a href="<?php echo e($pending['partly_prepared'] ? route('backend.draw.roundrobin.show', $pending['draw_id']).'#settings' : route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$pending['draw_id']]])); ?>"><?php echo e($pending['partly_prepared'] ? 'Review playoff setup' : 'Prepare playoffs in scheduling'); ?></a>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
    </div>
  <?php elseif($unscheduledCount === 0): ?>
    <p class="small text-success">All existing unfinished matches have saved times. Playoff player identities may still be undecided.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card"><div class="table-responsive"><table class="table calendar-table mb-0"><caption class="visually-hidden">Saved matches for the selected day and filters</caption><thead><tr><th scope="col">Time</th><th scope="col">Venue / court</th><th scope="col">Draw / match</th><th scope="col">Participants</th><th scope="col">Public visibility</th></tr></thead><tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr id="match-<?php echo e(str_replace(':','-',$row['fixture_key'])); ?>"><td class="text-nowrap" data-label="Time"><div><?php echo e($date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5)); ?></div></td><td data-label="Venue / court"><div><?php echo e($row['venue_name']); ?><div class="small text-muted">Court <?php echo e($row['court']); ?></div></div></td><td data-label="Draw / match"><div><?php echo e($row['draw_name']); ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($row['match_type'])): ?><div class="fw-semibold"><?php echo e($row['match_type']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="small text-muted"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($row['round_nr'])): ?>Round <?php echo e($row['round_nr']); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($row['tie_nr'])): ?>Tie <?php echo e($row['tie_nr']); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> Match <?php echo e($row['match_nr'] ?? $row['fixture_id']); ?></div></div></td><td data-label="Participants"><div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['fixture_kind'] === 'team' && isset($row['lineup'])): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['home', 'away']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->first)): ?><div class="small text-muted my-1">vs</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $row['lineup'][$side]['players'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->first)): ?> / <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><strong><?php echo e($player['name']); ?></strong><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><span class="text-muted">Players not assigned yet</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
          <div class="small text-muted"><?php echo e(($row['lineup'][$side]['region'] ?? '') ?: (($row['lineup'][$side]['region_name'] ?? '') ?: ($row['teams'][$side] ?? 'Team to be confirmed'))); ?></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?>
        <?php if (isset($component)) { $__componentOriginald032f6f37ccfe5a3c340f472d904fe2e = $component; } ?>
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
<?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></td><td data-label="Visibility"><div><span class="badge <?php echo e($row['publication_state']==='Published'?'bg-label-success':'bg-label-warning'); ?>"><?php echo e($row['publication_state']); ?></span></div></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="text-muted py-4">No saved matches match these filters. Use <?php echo e($date === 'all' ? 'Schedule more matches' : 'Add matches to this day'); ?> above to add matches here.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </tbody></table></div></div>
  <?php echo e($savedPages->links()); ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($retainedCount > 0): ?>
    <div class="alert alert-info mt-3">
      <strong>Previous times are still published</strong>
      <p class="mb-2 small">These bookings were moved or removed privately. Publish updates or hide the previous times to remove them from the public schedule.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $retained; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="small"><?php echo e($row['draw_name']); ?> · match <?php echo e($row['fixture_id']); ?> · <?php echo e($date === 'all' ? substr($row['scheduled_at'],0,16) : substr($row['scheduled_at'],11,5)); ?> · <?php echo e($row['venue_name']); ?> / <?php echo e($row['court']); ?> · <?php echo e($row['visible'] ? 'Visible to players' : 'Visibility blocked'); ?></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo e($retainedPages->links()); ?>

  <details class="card card-body mt-3" id="calendar-tools"><summary style="min-height:44px">More schedule tools</summary>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><p class="text-muted mb-0"><?php echo e($unscheduledCount); ?> matches remain without a day or time<?php echo e(!empty($scope['draw_id']) ? ' in this draw' : ' across the event'); ?>.</p><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar.audit', ['event'=>$event->id]+$scope)); ?>">Run schedule audit</a></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($date !== 'all'): ?>
      <details class="mt-3"><summary style="min-height:44px">Clear saved times for this day</summary><p class="small text-muted">Clear only this day<?php echo e(!empty($scope['draw_id']) ? ' in the selected draw' : ' across all draws'); ?><?php echo e(!empty($scope['venue_id']) ? ' at the selected venue' : ''); ?>. Fixtures and results remain. Played matches and locked draws cannot be cleared. Published times remain until you publish updates or hide them.</p>
        <form method="post" action="<?php echo e(route('backend.event-venue-schedule.calendar.clear', $event)); ?>" onsubmit="return confirm('Clear all saved match times in this day and selected filters? Fixtures and results remain. Published times remain until you publish updates or hide them.');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="revision" value="<?php echo e($revision); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scope; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <button class="btn btn-outline-danger" <?php if(($savedCount === 0)): echo 'disabled'; endif; ?>>Clear this day's schedule</button>
        </form>
      </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($scope['draw_id'])): ?>
  <?php ($guideDraw = $event->draws()->find($scope['draw_id'])); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guideDraw): ?><a data-draw-guide-open class="btn btn-primary m-3" href="<?php echo e(route('draw.guide.show', [$event, $guideDraw] + ($date !== 'all' ? ['schedule_date'=>$date] : []))); ?>"><?php echo e($guideDraw->isTeamDraw() ? 'Step 6 · Review setup & publication' : 'Next: review setup & publication'); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </details>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.schedule.partials.day-navigation-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views/backend/schedule/saved-calendar.blade.php ENDPATH**/ ?>