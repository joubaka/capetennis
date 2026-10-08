<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo e($event->name); ?> - <?php echo e(($printType ?? 'pack') === 'venue' ? 'Per-Venue Order of Play' : 'Draw Pack'); ?></title>
  <style>
    @page { size: A4 landscape; margin: 11mm 10mm 13mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5pt; line-height: 1.3; }
    h1, h2, h3, h4, p { margin-top: 0; }
    h1 { font-size: 25pt; line-height: 1.05; margin-bottom: 4mm; }
    h2 { font-size: 15pt; color: #163a64; margin-bottom: 3mm; }
    h3 { font-size: 10pt; color: #163a64; margin: 4mm 0 2mm; }
    h4 { font-size: 8.5pt; color: #405064; margin: 3mm 0 1.5mm; }
    small, .muted { color: #657184; }
    .page { page-break-before: always; }
    .cover { page-break-before: auto; padding: 10mm; border: 1.5pt solid #163a64; }
    .cover-kicker { color: #237f69; font-size: 10pt; font-weight: 700; letter-spacing: 1.6pt; text-transform: uppercase; }
    .cover-rule { width: 28mm; border-top: 3pt solid #237f69; margin: 8mm 0; }
    .event-dates { font-size: 12pt; margin-bottom: 10mm; }
    .stats { width: 100%; border-collapse: separate; border-spacing: 3mm; margin: 0 -3mm 8mm; }
    .stats td { width: 25%; padding: 5mm; border: 1pt solid #cdd5df; background: #f5f8fb; }
    .stats strong { display: block; font-size: 18pt; color: #163a64; }
    .contents { margin-top: 5mm; }
    .contents-row { break-inside: avoid; padding: 1.8mm 0; border-bottom: .5pt solid #d8dee7; }
    .section-head { display: table; width: 100%; padding-bottom: 2.5mm; border-bottom: 1.5pt solid #163a64; margin-bottom: 3mm; }
    .section-head > div { display: table-cell; vertical-align: bottom; }
    .section-meta { text-align: right; color: #657184; }
    table.data { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4mm; }
    table.data th, table.data td { border: .6pt solid #8d98a8; padding: 1.8mm 1.5mm; vertical-align: middle; }
    table.data th { color: #fff; background: #163a64; font-size: 7.6pt; text-align: left; }
    table.data tbody tr:nth-child(even) td { background: #f6f8fa; }
    table.data tr { page-break-inside: avoid; }
    .center { text-align: center !important; }
    .nowrap { white-space: nowrap; }
    .result-cell { min-height: 7mm; font-weight: 700; }
    .stage-label { color: #405064; font-size: 6.8pt; font-weight: 700; }
    .empty-note { padding: 8mm; border: 1pt dashed #9eabba; text-align: center; color: #657184; }
    .matrix-wrap { page-break-inside: avoid; }
    table.matrix { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4mm; }
    table.matrix th, table.matrix td { border: .6pt solid #8d98a8; padding: 1.2mm .8mm; height: 8mm; text-align: center; font-size: 7pt; overflow: hidden; }
    table.matrix thead th { color: #163a64; background: #edf3f8; }
    table.matrix tbody th { width: 34mm; color: #155f50; background: #edf7f3; text-align: left; }
    table.matrix .diagonal { background: #293443; }
    .standing { width: auto !important; min-width: 125mm; }
    .standing th, .standing td { padding: 1.4mm 2mm !important; }
    .pack-warning { margin: 3mm 0; padding: 3mm; border-left: 3pt solid #d49b22; background: #fff8e7; }
    .status-line { margin-top: 1.5mm; }
    .status { display: inline-block; margin: 0 1.5mm 1mm 0; padding: 1mm 2mm; border: .6pt solid #9eabba; color: #405064; font-size: 6.8pt; font-weight: 700; text-transform: uppercase; }
    .status.ready { border-color: #237f69; color: #155f50; background: #edf7f3; }
    .status.attention { border-color: #d49b22; color: #7b5510; background: #fff8e7; }
    .notes-block { margin: 0 0 3mm; padding: 3mm; border: .6pt solid #cdd5df; background: #f8fafc; page-break-inside: avoid; }
    .notes-block p { margin: 0; white-space: pre-line; }
    .pathway { width: 100%; border-collapse: separate; border-spacing: 2mm; table-layout: fixed; margin: 0 -2mm 4mm; }
    .pathway th { padding: 1.5mm; color: #163a64; background: #edf3f8; font-size: 7pt; text-align: center; }
    .pathway td { padding: 0; vertical-align: top; }
    .pathway .round-heading { margin-bottom: 2mm; padding: 1.5mm; color: #163a64; background: #edf3f8; font-size: 7pt; font-weight: 700; text-align: center; }
    .match-card { min-height: 18mm; margin-bottom: 2mm; padding: 2mm; border: .8pt solid #8d98a8; background: #fff; page-break-inside: avoid; }
    .match-card strong { color: #163a64; }
    .match-card .advance { margin-top: 1.5mm; padding-top: 1mm; border-top: .5pt solid #d8dee7; color: #657184; font-size: 6.5pt; }
    .matrix-ledger { page-break-inside: auto; }
    .fixture-list { page-break-before: always; }
    caption { height: 0; overflow: hidden; color: transparent; font-size: 0; }
    .footer { position: fixed; left: 0; right: 0; bottom: -8mm; color: #7b8593; font-size: 7pt; border-top: .5pt solid #ccd3dc; padding-top: 1.5mm; }
    .footer .page-number { float: right; }
    .footer .page-number:after { content: counter(page); }
    .no-print { position: fixed; top: 12px; right: 12px; z-index: 5; }
    .no-print button { border: 0; border-radius: 5px; padding: 10px 16px; color: #fff; background: #163a64; font: 600 14px Arial, sans-serif; cursor: pointer; }
    .venue-page:first-of-type { page-break-before: auto; }
    .venue-page h2 { font-size: 13pt; margin: 0; }
    .venue-page .cover-kicker { font-size: 7pt; letter-spacing: 0; margin-bottom: 1mm; }
    .venue-page table.data td { padding: 1mm 1.5mm; line-height: 1.2; overflow-wrap: anywhere; }
    .venue-page thead { display: table-header-group; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($autoPrint): ?>
  <div class="no-print"><button type="button" onclick="window.print()">Print draw pack</button></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="footer">
  <?php echo e($event->name); ?> - <?php echo e(($printType ?? 'pack') === 'venue' ? 'Per-Venue Order of Play' : 'Draw Pack'); ?> - Generated <?php echo e(now()->format('d M Y H:i')); ?>

  <span class="page-number">Page </span>
</div>

<?php
  $totalMatches = $draws->sum(fn ($draw) => count($draw['oops']));
  $allFixtures = $draws->flatMap(fn ($draw) => $draw['oops']);
  $scheduledMatches = $allFixtures->filter(fn ($fixture) =>
    filled($fixture['scheduled_at']) && filled($fixture['venue']) && filled($fixture['court'])
  )->count();
  $unscheduledMatches = $totalMatches - $scheduledMatches;
  $timedButIncomplete = $allFixtures->filter(fn ($fixture) =>
    filled($fixture['scheduled_at']) && (!filled($fixture['venue']) || !filled($fixture['court']))
  )->count();
  $draftDraws = $draws->where('published', false)->count();
  $unpublishedSchedules = $draws->where('schedule_published', false)->count();
  $eventDate = $event->start_date
    ? $event->start_date->format('d M Y').($event->end_date && !$event->end_date->equalTo($event->start_date) ? ' - '.$event->end_date->format('d M Y') : '')
    : 'Dates to be confirmed';
  $stageLabels = [
    'RR' => 'Round Robin', 'FM' => 'Flexible Monrad', 'MAIN' => 'Main Draw',
    'PLATE' => 'Plate', 'CONS' => 'Consolation', 'BOWL' => 'Bowl',
    'SHIELD' => 'Shield', 'SPOON' => 'Spoon',
  ];
  $venueOnly = ($printType ?? 'pack') === 'venue';
  $venueSchedules = $schedule
    ->filter(fn ($fixture) => filled($fixture['venue']))
    ->groupBy(fn ($fixture) => $fixture['venue_id'] ?? $fixture['venue'])
    ->sortKeys();
  $venueScheduleMatches = $venueSchedules->flatten(1);
  $excludedVenueMatches = $draws->flatMap(fn ($draw) => collect($draw['oops'])
    ->filter(fn ($fixture) => !filled($fixture['scheduled_at']) || !filled($fixture['venue']))
    ->map(fn ($fixture) => $fixture + ['draw_name' => $draw['name']]))
    ->values();
  $notInVenueCopies = $excludedVenueMatches->count();
  $incompleteVenueCopies = $venueScheduleMatches->filter(fn ($fixture) => !filled($fixture['court']))->count();
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueOnly): ?>
<p class="muted"><?php echo e($event->name); ?> · <?php echo e(($scheduleSource ?? 'published') === 'working' ? 'Working preview — verify publication before courtside use' : 'Published order of play — same schedule as the public venue list'); ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($scheduleSource ?? 'published') === 'working' && $notInVenueCopies): ?><p class="muted"><?php echo e($notInVenueCopies); ?> matches excluded because time or venue is unassigned.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueSchedules->isEmpty()): ?><div class="empty-note">No matches for the selected day, venue and draws.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueSchedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venueKey => $venueFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php
    $venue = $venueFixtures->first()['venue'];
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueFixtures->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture['scheduled_at'])->format('Y-m-d')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date => $dayFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dayFixtures->chunk(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pageIndex => $pageFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <section class="page venue-page" <?php if($loop->parent->parent->first && $loop->parent->first && $loop->first): ?> style="page-break-before: auto" <?php endif; ?>>
      <header class="section-head">
        <div><p class="cover-kicker">Venue order of play</p><h2><?php echo e($venue); ?></h2></div>
        <div class="section-meta"><strong><?php echo e(\Carbon\Carbon::parse($date)->format('l, d M Y')); ?></strong><br><?php echo e($dayFixtures->count()); ?> <?php echo e(Str::plural('match', $dayFixtures->count())); ?> · Sheet <?php echo e($pageIndex + 1); ?> / <?php echo e((int) ceil($dayFixtures->count() / 8)); ?></div>
      </header>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($autoPrint && ($scheduleSource ?? 'published') === 'published'): ?>
        <p class="no-print"><a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $pageFixtures->first()['venue_id'] ?? null, 'schedule_source' => 'published', 'date' => $date, 'draw_ids' => $draws->pluck('id')->all()])); ?>">Online scoring — published schedule</a></p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <table class="data">
        <caption><?php echo e($venue); ?> order of play for <?php echo e(\Carbon\Carbon::parse($date)->format('l, d M Y')); ?></caption>
        <thead><tr><th scope="col" style="width:9%">Time</th><th scope="col" style="width:8%">Court</th><th scope="col" style="width:17%">Draw</th><th scope="col" style="width:8%">Match</th><th scope="col" style="width:24%">Player 1</th><th scope="col" style="width:24%">Player 2</th><th scope="col" style="width:10%">Result</th></tr></thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageFixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td class="nowrap"><strong><?php echo e(\Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i')); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture['duration']): ?><br><small><?php echo e($fixture['duration']); ?> min</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
            <td><?php echo e($fixture['court'] ?: 'TBA'); ?></td>
            <td><?php echo e($fixture['draw_name']); ?></td>
            <td><span class="stage-label"><?php echo e($stageLabels[$fixture['stage']] ?? ($fixture['stage'] ?: 'Draw')); ?></span><br>M<?php echo e($fixture['match_nr'] ?? $fixture['id']); ?></td>
            <td><?php echo e($fixture['home']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($fixture['home_region'])): ?><br><small><?php echo e($fixture['home_region']); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
            <td><?php echo e($fixture['away']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($fixture['away_region'])): ?><br><small><?php echo e($fixture['away_region']); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
            <td class="result-cell"><?php echo e($fixture['score'] ?: '____  ____  ____'); ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
<section class="cover">
  <p class="cover-kicker">Complete Draw Pack</p>
  <h1><?php echo e($event->name); ?></h1>
  <div class="cover-rule"></div>
  <p class="event-dates"><?php echo e($eventDate); ?></p>

  <table class="stats" role="presentation">
    <tr>
      <td><strong><?php echo e($draws->count()); ?></strong><span>Draws</span></td>
      <td><strong><?php echo e($totalMatches); ?></strong><span>Total matches</span></td>
      <td><strong><?php echo e($scheduledMatches); ?></strong><span>Fully scheduled</span></td>
      <td><strong><?php echo e($unscheduledMatches); ?></strong><span>Still to schedule</span></td>
    </tr>
  </table>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unscheduledMatches > 0): ?>
    <div class="pack-warning"><strong>Scheduling check:</strong> <?php echo e($unscheduledMatches); ?> <?php echo e(Str::plural('match', $unscheduledMatches)); ?> in this pack <?php echo e($unscheduledMatches === 1 ? 'is' : 'are'); ?> not yet fully assigned a date, venue and court.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timedButIncomplete): ?> <?php echo e($timedButIncomplete); ?> already <?php echo e($timedButIncomplete === 1 ? 'has' : 'have'); ?> a time but still <?php echo e($timedButIncomplete === 1 ? 'needs' : 'need'); ?> a venue or court.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draftDraws || $unpublishedSchedules): ?>
    <div class="pack-warning"><strong>Publication check:</strong> <?php echo e($draftDraws); ?> draft <?php echo e(Str::plural('draw', $draftDraws)); ?> and <?php echo e($unpublishedSchedules); ?> unpublished <?php echo e(Str::plural('schedule', $unpublishedSchedules)); ?> are included. Verify this copy before courtside use.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <h2>Pack contents</h2>
  <div class="contents">
    <div class="contents-row"><strong>Master order of play</strong><br><small>All selected draws, grouped by day and venue</small></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="contents-row"><strong><?php echo e($draw['name']); ?></strong><br><small><?php echo e($draw['format']); ?> - <?php echo e(count($draw['oops'])); ?> <?php echo e(Str::plural('match', count($draw['oops']))); ?> - <?php echo e($draw['published'] ? 'Published draw' : 'Draft draw'); ?> - <?php echo e($draw['schedule_published'] ? 'Published schedule' : 'Unpublished schedule'); ?></small></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</section>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($schedule->isNotEmpty()): ?>
  <section class="page">
    <header class="section-head">
      <div><p class="cover-kicker">Master order of play</p><h2>All scheduled times</h2></div>
      <div class="section-meta"><?php echo e($schedule->count()); ?> timed <?php echo e(Str::plural('match', $schedule->count())); ?></div>
    </header>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $schedule->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture['scheduled_at'])->format('Y-m-d')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date => $dayFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <h3><?php echo e(\Carbon\Carbon::parse($date)->format('l, d M Y')); ?></h3>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dayFixtures->groupBy(fn ($fixture) => $fixture['venue'] ?: 'Venue to be confirmed'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue => $venueFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <h4><?php echo e($venue); ?> - <?php echo e($venueFixtures->count()); ?> <?php echo e(Str::plural('match', $venueFixtures->count())); ?></h4>
        <table class="data">
          <caption><?php echo e(\Carbon\Carbon::parse($date)->format('l, d M Y')); ?> at <?php echo e($venue); ?></caption>
          <thead><tr><th scope="col" style="width:10%">Time</th><th scope="col" style="width:9%">Court</th><th scope="col" style="width:17%">Draw</th><th scope="col" style="width:8%">Match</th><th scope="col" style="width:23%">Player 1</th><th scope="col" style="width:23%">Player 2</th><th scope="col" style="width:10%">Result</th></tr></thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueFixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="nowrap"><strong><?php echo e(\Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i')); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture['duration']): ?><br><small><?php echo e($fixture['duration']); ?> min</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td><?php echo e($fixture['court'] ?: 'TBA'); ?></td>
              <td><?php echo e($fixture['draw_name']); ?></td>
              <td><span class="stage-label"><?php echo e($stageLabels[$fixture['stage']] ?? ($fixture['stage'] ?: 'Draw')); ?></span><br>M<?php echo e($fixture['match_nr'] ?? $fixture['id']); ?></td>
              <td><?php echo e($fixture['home']); ?></td>
              <td><?php echo e($fixture['away']); ?></td>
              <td class="result-cell"><?php echo e($fixture['score']); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php else: ?>
  <section class="page">
    <header class="section-head"><div><p class="cover-kicker">Master order of play</p><h2>Schedule</h2></div></header>
    <div class="empty-note">No selected matches have an applied date, venue and court yet.</div>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <section class="page">
    <header class="section-head">
      <div><p class="cover-kicker">Draw sheet</p><h2><?php echo e($draw['name']); ?></h2></div>
      <div class="section-meta"><strong><?php echo e($draw['format']); ?></strong><br><?php echo e(count($draw['oops'])); ?> <?php echo e(Str::plural('match', count($draw['oops']))); ?>

        <div class="status-line"><span class="status <?php echo e($draw['published'] ? 'ready' : 'attention'); ?>"><?php echo e($draw['published'] ? 'Published draw' : 'Draft draw'); ?></span><span class="status <?php echo e($draw['schedule_published'] ? 'ready' : 'attention'); ?>"><?php echo e($draw['schedule_published'] ? 'Schedule published' : 'Schedule unpublished'); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw['locked']): ?><span class="status ready">Locked</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
      </div>
    </header>

    <h3>Rules and notes</h3>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="notes-block"><h4><?php echo e($label); ?></h4><p><?php echo e($note); ?></p></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty-note">No draw-specific rules or notes have been saved.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($draw['groups'])): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($draw['groups'])->sortBy('name'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $players = collect($group['registrations'])->sortBy(fn ($registration) => $registration['pivot']['seed'] ?? 9999)->values();
          $groupFixtures = collect($draw['rrFixtures'][$group['id']] ?? []);
        ?>
        <div class="<?php echo e($players->count() <= 12 ? 'matrix-wrap' : 'matrix-ledger-wrap'); ?>">
          <h3>Box <?php echo e($group['name']); ?> - Results matrix</h3>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($players->count() <= 12): ?>
          <table class="matrix">
            <caption><?php echo e($draw['name']); ?> Box <?php echo e($group['name']); ?> results matrix</caption>
            <thead><tr><th scope="col">Player</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th scope="col"><?php echo e($player['display_name']); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><th scope="col">W</th></tr></thead>
            <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rowPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <th scope="row"><?php echo e($rowPlayer['display_name']); ?></th>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $columnPlayer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rowPlayer['id'] === $columnPlayer['id']): ?>
                    <td class="diagonal"></td>
                  <?php else: ?>
                    <?php
                      $result = $groupFixtures->first(fn ($fixture) =>
                        ($fixture['r1_id'] === $rowPlayer['id'] && $fixture['r2_id'] === $columnPlayer['id']) ||
                        ($fixture['r1_id'] === $columnPlayer['id'] && $fixture['r2_id'] === $rowPlayer['id'])
                      );
                    ?>
                    <?php
                      $orientedScore = collect($result['all_sets'] ?? [])->map(function ($set) use ($result, $rowPlayer) {
                        [$home, $away] = array_pad(explode('-', $set, 2), 2, '');
                        return (int) $result['r1_id'] === (int) $rowPlayer['id'] ? "{$home}-{$away}" : "{$away}-{$home}";
                      })->implode(', ');
                    ?>
                    <td><?php echo e($orientedScore); ?></td>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <td><?php echo e($groupFixtures->filter(fn ($fixture) => (int) ($fixture['winner'] ?? 0) === (int) $rowPlayer['id'])->count()); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
          <?php else: ?>
            <div class="pack-warning"><strong>Large box:</strong> a <?php echo e($players->count()); ?>-player grid would be unreadable on A4, so this pack uses the results ledger below.</div>
            <table class="data matrix-ledger">
              <caption><?php echo e($draw['name']); ?> Box <?php echo e($group['name']); ?> large-box results ledger</caption>
              <thead><tr><th scope="col">Match</th><th scope="col">Player 1</th><th scope="col">Player 2</th><th scope="col">Result</th></tr></thead>
              <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $groupFixtures->sortBy('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td>M<?php echo e($fixture['id']); ?></td><td><?php echo e($players->firstWhere('id', $fixture['r1_id'])['display_name'] ?? 'TBD'); ?></td><td><?php echo e($players->firstWhere('id', $fixture['r2_id'])['display_name'] ?? 'TBD'); ?></td><td><?php echo e($fixture['score']); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
            </table>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php echo $__env->make('backend.draw.pdf.partials.pathway-board', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($draw['oops'])): ?>
      <?php
        $fixtures = collect($draw['oops'])->sortBy(fn ($fixture) => sprintf(
          '%s|%08d',
          $fixture['scheduled_at'] ?: '9999-12-31 23:59:59',
          (int) ($fixture['match_nr'] ?? $fixture['id'])
        ))->values();
      ?>
        <div class="fixture-list">
        <h3><?php echo e($draw['name']); ?> - Fixtures in scheduled order</h3>
        <table class="data">
          <caption><?php echo e($draw['name']); ?> fixtures in scheduled order</caption>
          <thead><tr><th scope="col" style="width:7%">Match</th><th scope="col" style="width:9%">Stage</th><th scope="col" style="width:19%">Player 1</th><th scope="col" style="width:19%">Player 2</th><th scope="col" style="width:9%">Date</th><th scope="col" style="width:8%">Time</th><th scope="col" style="width:12%">Venue</th><th scope="col" style="width:7%">Court</th><th scope="col" style="width:10%">Result</th></tr></thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td>M<?php echo e($fixture['match_nr'] ?? $fixture['id']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture['round']): ?><br><small>Round <?php echo e($fixture['round']); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td><span class="stage-label"><?php echo e($stageLabels[$fixture['stage']] ?? str($fixture['stage'] ?: 'Draw')->headline()); ?></span></td>
              <td><?php echo e($fixture['home']); ?></td>
              <td><?php echo e($fixture['away']); ?></td>
              <td class="nowrap"><?php echo e($fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('d M Y') : 'TBA'); ?></td>
              <td class="nowrap"><?php echo e($fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') : 'TBA'); ?></td>
              <td><?php echo e($fixture['venue'] ?: 'TBA'); ?></td>
              <td><?php echo e($fixture['court'] ?: 'TBA'); ?></td>
              <td class="result-cell"><?php echo e($fixture['score']); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
        </div>
    <?php else: ?>
      <div class="empty-note">This draw has no generated fixtures yet.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($includeStandings && count($draw['standings'])): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($draw['groups'])->sortBy('name'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($standings = collect($draw['standings'][$group['id']] ?? [])->values()); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standings->isNotEmpty()): ?>
          <h3><?php echo e($draw['name']); ?> - Box <?php echo e($group['name']); ?> standings</h3>
          <table class="data standing">
            <caption><?php echo e($draw['name']); ?> Box <?php echo e($group['name']); ?> standings</caption>
            <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">W</th><th scope="col">L</th><th scope="col">Sets won</th><th scope="col">Sets lost</th><th scope="col">Games won</th><th scope="col">Games lost</th></tr></thead>
            <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $standings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($index + 1); ?></td><td><?php echo e($row['player']); ?></td><td><?php echo e($row['wins']); ?></td><td><?php echo e($row['losses']); ?></td><td><?php echo e($row['sets_won']); ?></td><td><?php echo e($row['sets_lost']); ?></td><td><?php echo e($row['games_won'] ?? 0); ?></td><td><?php echo e($row['games_lost'] ?? 0); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
          </table>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\pdf\draw-pack.blade.php ENDPATH**/ ?>