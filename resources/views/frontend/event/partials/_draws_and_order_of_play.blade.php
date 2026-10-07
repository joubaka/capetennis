{{-- resources/views/frontend/event/partials/_draws_and_order_of_play.blade.php --}}
@once
<style>
  .event-published-draw-link.btn { background: #fff; color: #173f7a; border: 1px solid #173f7a; font-weight: 600; min-height: 44px; white-space: normal; text-align: left; }
  .event-published-draw-link.btn:hover, .event-published-draw-link.btn:focus-visible { background: #173f7a; color: #fff; }
  .event-published-draw-link.btn .badge { background: #e8eff8 !important; color: #173f7a !important; white-space: normal; }
  .event-published-draw-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 10px; }
  .event-published-draw-row { display: flex; align-items: center; gap: 8px; min-width: 0; flex-wrap: wrap; }
  .event-published-draw-link.btn { display: flex; flex: 1 1 220px; align-items: center; justify-content: space-between; gap: 10px; padding: 12px; font-size: 1rem; line-height: 1.4; min-width: 0; flex-wrap: wrap; }
  .event-published-draw-name { overflow-wrap: anywhere; }
  .event-published-draw-link.btn .badge { margin-left: 0 !important; font-size: .8125rem; line-height: 1.4; padding: 5px 8px; }
  .event-published-draw-row > .btn-light { min-height: 44px; }
  .event-published-draw-link.btn .badge.draw-scheduled { background: #eef6f1 !important; color: #235c31 !important; border: 1px solid #b9d7c1; }
  .event-published-draw-link.btn .badge.draw-partly-scheduled { background: #fff6e8 !important; color: #805214 !important; border: 1px solid #e7cfa7; }
</style>
@endonce
<div class="card mb-4">
  <div class="card-header">
    <small class="card-text text-uppercase">Draws and Order of Play</small>
  </div>

  <div class="card-body">
    @if($event->isTeam() && $event->published && $event->standings_published)
      <a class="btn btn-outline-primary mb-3" style="min-height:44px" href="{{ route('frontend.events.standings', $event) }}">Team standings and match totals</a>
    @endif
    @include('frontend.event.partials._venue-scoring')

    {{-- ✅ Published Draws --}}
<div class="mb-3">
  <h6 class="fw-bold">Published Draws</h6>

  @php
    $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
    $publicStartTimes = $event->exists
        ? app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($event, includeParticipants: false)->groupBy('draw_id')->map(fn ($rows) => $rows->min('scheduled_at'))
        : collect();
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
    // Keep type sections intact; each section follows its earliest public match.
    $publishedDrawGroups = $publishedDraws->groupBy(fn ($draw) => $draw->draw_types?->drawTypeName ?? 'Other')
        ->sort(function ($left, $right) use ($publicStartTimes) {
            $firstTime = fn ($draws) => $draws->map(fn ($draw) => $publicStartTimes->get($draw->id))->filter()->min() ?? '9999';
            return strcmp($firstTime($left), $firstTime($right))
                ?: ($right->max('drawType_id') <=> $left->max('drawType_id'))
                ?: strnatcasecmp($left->first()->draw_types?->drawTypeName ?? 'Other', $right->first()->draw_types?->drawTypeName ?? 'Other');
        });
  @endphp

  @forelse(
      $publishedDrawGroups
      as $typeName => $draws
  )
    <h6 class="mt-3">{{ $typeName }}</h6>

    <div class="event-published-draw-list">
      @foreach($draws as $draw)
        @php
          $publishedDays = $publishedDayLabels->get($draw->id, '');
          $schedulePublished = $publishedDays !== '';
          $matchCount = (int) $draw->team_match_count + (int) $draw->individual_match_count;
          $scheduledCount = (int) $draw->scheduled_team_match_count + (int) $draw->scheduled_individual_match_count;
          $fullyScheduled = $matchCount > 0 && $scheduledCount === $matchCount;
          $scheduleLabel = $schedulePublished ? 'Times available · '.$publishedDays : ($fullyScheduled ? 'Scheduled · times not published' : ($scheduledCount > 0 ? 'Partly scheduled · times not published' : 'Not scheduled yet'));
          $scheduleClass = $schedulePublished || $fullyScheduled ? 'draw-scheduled' : ($scheduledCount > 0 ? 'draw-partly-scheduled' : '');
        @endphp
        <div class="event-published-draw-row">
          <a href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id) }}"
             class="btn btn-sm event-published-draw-link">
            <span class="event-published-draw-name">{{ $draw->drawName }}</span>
            <span class="badge {{ $scheduleClass }} ms-1">
              {{ $scheduleLabel }}
            </span>
          </a>
          @php

            $canScoreEvent = $canScoreEvent ?? (auth()->check() && auth()->user()->can('event.score', $event));
          @endphp
          {{-- debug removed: dd() halts execution. Use @dump($var) or @dd($var) during local debugging --}}
          @if($canScoreEvent)
            <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]) }}"
               class="btn btn-sm btn-light border"
               title="Score {{ $draw->drawName }}">
              <i class="bi bi-clipboard-data"></i> Score
            </a>
          @endif
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> They will appear here when released; match times may follow later.</div>
  @endforelse
    

</div>

    {{-- 🚧 Unpublished Draws (Admin / Super-user / Convenor only) --}}
    @if($eventDraws->where('published', false)->count())
      @php
        $user = auth()->user();
        $canViewUnpublished = $user && (
          (method_exists($user, 'isConvenorForEvent') && $user->isConvenorForEvent($event->id)) ||
          (method_exists($user, 'is_convenor') && $user->is_convenor($event->id)) ||
          (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-user')))
        );
      @endphp

      @if($canViewUnpublished)
        <div class="mt-4">
          <h6 class="fw-bold text-danger">Unpublished Draws</h6>

          @foreach($eventDraws->where('published', false)->sortByDesc('drawType_id')->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)
            <h6 class="mt-3">{{ $typeName }}</h6>
            <div class="d-flex flex-wrap gap-2">
              @foreach($draws as $draw)
                <a href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id) }}"
                   class="btn btn-sm btn-outline-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
                  {{ $draw->drawName }}
                  <span class="badge bg-danger ms-1">Unpublished</span>
                  @if($draw->scheduleIsPublished())<span class="badge bg-info ms-1">Times preview</span>@endif
                </a>
              @endforeach
            </div>
          @endforeach
        </div>
      @endif
    @endif

 

 


   
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
