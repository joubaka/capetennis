
<?php if (! $__env->hasRenderedOnce('de08cac2-a717-45bb-bc8f-366a338a876e')): $__env->markAsRenderedOnce('de08cac2-a717-45bb-bc8f-366a338a876e'); ?>
<style>
  .event-play-card { border: 1px solid #dce4ee; border-radius: 12px; overflow: hidden; box-shadow: 0 3px 14px rgba(24, 47, 78, .04); }
  .event-play-card > .card-header { padding: 20px 24px 0; border: 0; background: transparent; }
  .event-play-card > .card-header + .card-body { padding: 24px; }
  .event-play-title { margin: 0; color: #243b55; font-size: 1.125rem; font-weight: 700; line-height: 1.4; }
  .event-play-section-title { color: #243b55; font-size: .9375rem; font-weight: 700; margin-bottom: 12px; }
  .event-play-group-title, .event-published-draw-list { --format-accent: #475569; --format-tint: #f1f5f9; }
  .event-published-draw-list + .event-play-group-title { margin-top: 24px; }
  .event-play-card .format-singles { --format-accent: #233d66; --format-tint: #eef3fa; }
  .event-play-card .format-reverse-singles { --format-accent: #854315; --format-tint: #fff3df; }
  .event-play-card .format-doubles { --format-accent: #15594c; --format-tint: #e7f6ef; }
  .event-play-card .format-mixed-doubles { --format-accent: #762a54; --format-tint: #fbedf5; }
  .event-play-group-title { margin: 0 0 10px; padding: 10px 14px; border-radius: 8px; background: var(--format-accent); font-size: .9375rem; font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; }
  .event-play-card h6.event-play-group-title { color: #fff; }
  .event-published-draw-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: 6px; }
  .event-published-draw-row { --draw-accent: #344d69; --draw-tint: #edf2f7; --draw-edge: #cbd5e1; display: flex; min-width: 0; border: 1px solid var(--draw-edge); border-radius: 7px; overflow: hidden; }
  .event-published-draw-row.draw-cobalt { --draw-accent: #184a9c; --draw-tint: #edf3ff; --draw-edge: #bfd0ed; }
  .event-published-draw-row.draw-plum { --draw-accent: #733b83; --draw-tint: #f7effa; --draw-edge: #dbc5e2; }
  .event-published-draw-row.draw-teal { --draw-accent: #11665e; --draw-tint: #eaf7f4; --draw-edge: #b9dbd4; }
  .event-published-draw-row.is-scheduled { border-left: 3px solid var(--draw-accent); background: var(--draw-tint); }
  .event-published-draw-row.is-unscheduled { --draw-accent: #475569; --draw-edge: #94a3b8; border-style: dashed; background: #fff; }
  .event-play-card .event-published-draw-link.btn { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 6px; padding: 6px 8px; color: var(--draw-accent); border: 0; border-radius: 0; min-height: 44px; min-width: 0; white-space: normal; text-align: left; }
  .event-draw-summary { display: grid; gap: 3px; min-width: 0; overflow-wrap: anywhere; }
  .event-published-draw-name { font-size: .875rem; font-weight: 700; line-height: 1.25; }
  .event-draw-details { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 6px; color: #40536b; font-size: .75rem; line-height: 1.3; }
  .event-draw-format { border-radius: 3px; padding: 1px 4px; background: var(--format-tint); color: var(--format-accent); font-size: .6875rem; font-weight: 700; }
  .event-play-card .event-draw-score.btn { display: inline-flex; align-items: center; flex-shrink: 0; min-height: 44px; padding: 0 8px; color: var(--draw-accent); border: 0; border-left: 1px solid var(--draw-edge); border-radius: 0; font-size: .75rem; }
  .event-play-card .event-published-draw-link.btn:hover, .event-play-card .event-draw-score.btn:hover { color: var(--draw-accent); background: rgba(15, 23, 42, .06); }
  .event-play-card .event-published-draw-link.btn, .event-play-card .event-draw-score.btn { box-shadow: none; }
  .event-play-card a:focus-visible { outline: 3px solid #6b9cd3; outline-offset: 3px; }
  .event-play-card .event-published-draw-row a:focus-visible { outline: 3px solid var(--draw-accent); outline-offset: -4px; }
  @media (max-width: 575.98px) { .event-play-card > .card-header { padding: 18px 18px 0; } .event-play-card > .card-header + .card-body { padding: 18px; } }
</style>
<?php endif; ?>
<div class="card event-play-card mb-4">
  <div class="card-header">
    <h5 class="event-play-title">Draws</h5>
  </div>

  <div class="card-body">
    <?php
      $publicStandings = $event->isTeam() && $event->published && $event->standings_published;
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicStandings || ($canViewPrivateStandings ?? false)): ?>
      <div class="border rounded p-3 mb-3" data-event-standings-card>
        <h5 class="mb-2">Team standings <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($publicStandings)): ?><span class="badge bg-label-secondary text-dark">Private</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
        <p class="mb-3">See positions by age group, total points and matches won / lost, with boys’, girls’ and mixed results combined.</p>
        <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route($publicStandings ? 'frontend.events.standings' : 'frontend.events.private-standings', $event)); ?>">View standings and match statistics</a>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->make('frontend.event.partials._venue-scoring', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
<div class="mb-3">
  <h6 class="event-play-section-title">Published draws</h6>

  <?php
    $drawTypeKey = fn ($typeName) => match (strtolower(trim(preg_replace('/^team\s*[-–]\s*/iu', '', $typeName)))) {
        'singles' => 'singles',
        'singles reverse', 'reverse singles' => 'reverse-singles',
        'doubles' => 'doubles',
        'mixed doubles' => 'mixed-doubles',
        default => 'other',
    };
    // Insertion order sets the display priority for the recognised disciplines.
    $drawFormatLabels = [
        'singles' => 'Singles',
        'reverse-singles' => 'Singles Reverse',
        'doubles' => 'Doubles',
        'mixed-doubles' => 'Mixed Doubles',
    ];
    $drawFormatPriorities = array_flip(array_keys($drawFormatLabels));
    $drawTypePriority = fn ($typeName) => $drawFormatPriorities[$drawTypeKey($typeName)] ?? count($drawFormatPriorities);
    $publicRowsByDraw = $event->exists
        ? app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($event, includeParticipants: false)->groupBy('draw_id')
        : collect();
    $publicStartTimes = $publicRowsByDraw->map(fn ($rows) => $rows->min('scheduled_at'));
    $publishedDayLabels = $publicRowsByDraw->map(function ($rows) {
        $starts = $rows->groupBy(fn ($row) => \Carbon\Carbon::parse($row['scheduled_at'])->toDateString())
            ->map(fn ($dayRows) => $dayRows->min('scheduled_at'))->sortKeys();
        $weekdays = $starts->map(fn ($time) => \Carbon\Carbon::parse($time)->format('l'));
        $dayFormat = $weekdays->unique()->count() === $weekdays->count() ? 'l' : 'l j M';
        return $starts->map(fn ($time) => \Carbon\Carbon::parse($time)->format($dayFormat).' from '.\Carbon\Carbon::parse($time)->format('H:i'))->join(', ');
    });
    $publishedDraws = $eventDraws
        ->where('published', true)
        ->sort(function ($left, $right) use ($publicStartTimes) {
            $timeOrder = strcmp($publicStartTimes->get($left->id, '9999'), $publicStartTimes->get($right->id, '9999'));
            if ($timeOrder !== 0) return $timeOrder;
            $age = function ($draw) {
                return preg_match('/\b(?:u\s*\/?\s*|under\s*[- ]?)(\d{1,2})\b/i', $draw->drawName, $matches)
                    ? (int) $matches[1] : PHP_INT_MAX;
            };
            return ($age($left) <=> $age($right))
                ?: strnatcasecmp($left->drawName, $right->drawName)
                ?: ($left->id <=> $right->id);
        });
    // Discipline order is fixed; public match times still order draws within sections.
    $publishedDrawGroups = $publishedDraws->groupBy(fn ($draw) => $draw->draw_types?->drawTypeName ?? 'Other')
        ->sort(function ($left, $right) use ($publicStartTimes, $drawTypePriority) {
            $firstTime = fn ($draws) => $draws->map(fn ($draw) => $publicStartTimes->get($draw->id))->filter()->min() ?? '9999';
            return ($drawTypePriority($left->first()->draw_types?->drawTypeName ?? 'Other') <=> $drawTypePriority($right->first()->draw_types?->drawTypeName ?? 'Other'))
                ?: strcmp($firstTime($left), $firstTime($right))
                ?: ($right->max('drawType_id') <=> $left->max('drawType_id'))
                ?: strnatcasecmp($left->first()->draw_types?->drawTypeName ?? 'Other', $right->first()->draw_types?->drawTypeName ?? 'Other');
        });
  ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $publishedDrawGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
      $drawFormat = $drawTypeKey($typeName);
    ?>
    <h6 class="event-play-group-title format-<?php echo e($drawFormat); ?>"><?php echo e($typeName); ?></h6>

    <div class="event-published-draw-list format-<?php echo e($drawFormat); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $publishedDays = $publishedDayLabels->get($draw->id, '');
          // Visual identity follows wording already visible in the draw name only.
          $drawCategory = preg_match('/\b(boys?|girls?|mixed|men|women)\b/i', $draw->drawName, $categoryMatch)
              ? strtolower($categoryMatch[1]) : null;
          $drawPalette = match ($drawCategory) {
              'boy', 'boys', 'men' => 'cobalt',
              'girl', 'girls', 'women' => 'plum',
              'mixed' => 'teal',
              default => 'neutral',
          };
        ?>
        <article class="event-published-draw-row draw-<?php echo e($drawPalette); ?> <?php echo e($publishedDays !== '' ? 'is-scheduled' : 'is-unscheduled'); ?>">
          <a href="<?php echo e($draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id)); ?>"
             class="btn btn-sm event-published-draw-link">
            <span class="event-draw-summary">
              <span class="visually-hidden">View draw:</span>
              <span class="event-published-draw-name"><?php echo e($draw->drawName); ?></span>
              <span class="event-draw-details">
                <span class="event-published-draw-time">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publishedDays !== ''): ?>
                    <i class="ti ti-clock me-1" aria-hidden="true"></i><?php echo e($publishedDays); ?>

                  <?php else: ?>
                    Times not published
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($drawFormatLabels[$drawFormat])): ?>
                  <span class="event-draw-format"><?php echo e($drawFormatLabels[$drawFormat]); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </span>
            </span>
            <i class="ti ti-arrow-right" aria-hidden="true"></i>
          </a>
          <?php

            $canScoreEvent = $canScoreEvent ?? (auth()->check() && auth()->user()->can('event.score', $event));
          ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canScoreEvent): ?>
            <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id])); ?>"
               class="btn btn-sm event-draw-score"
               title="Score <?php echo e($draw->drawName); ?>">
              <i class="ti ti-clipboard-check me-1" aria-hidden="true"></i>Score<span class="visually-hidden"> draw: <?php echo e($draw->drawName); ?></span>
            </a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </article>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> They will appear here when released; match times may follow later.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventDraws->where('published', false)->count()): ?>
      <?php
        $user = auth()->user();
        $canViewUnpublished = $user && (
          (method_exists($user, 'isConvenorForEvent') && $user->isConvenorForEvent($event->id)) ||
          (method_exists($user, 'is_convenor') && $user->is_convenor($event->id)) ||
          (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-user')))
        );
      ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished): ?>
        <div class="mt-4">
          <h6 class="fw-bold text-danger">Unpublished Draws</h6>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventDraws->where('published', false)->sortByDesc('drawType_id')->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other')->sortBy(fn ($draws, $typeName) => $drawTypePriority($typeName)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <h6 class="mt-3"><?php echo e($typeName); ?></h6>
            <div class="d-flex flex-wrap gap-2">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id)); ?>"
                   class="btn btn-sm btn-outline-<?php echo e($draw->draw_types?->btn_color ?? 'secondary'); ?>">
                  <?php echo e($draw->drawName); ?>

                  <span class="badge bg-danger ms-1">Unpublished</span>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->scheduleIsPublished()): ?><span class="badge bg-info ms-1">Times preview</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </a>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

 

 


   
  </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\_draws_and_order_of_play.blade.php ENDPATH**/ ?>