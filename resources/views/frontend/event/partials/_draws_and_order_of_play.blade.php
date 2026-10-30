{{-- resources/views/frontend/event/partials/_draws_and_order_of_play.blade.php --}}
@once
<style>
  .event-play-card { border: 1px solid #dce4ee; border-radius: 12px; overflow: hidden; box-shadow: 0 3px 14px rgba(24, 47, 78, .04); }
  .event-play-card > .card-header { padding: 20px 24px 0; border: 0; background: transparent; }
  .event-play-card > .card-header + .card-body { padding: 24px; }
  .event-play-title { margin: 0; color: #243b55; font-size: 1.125rem; font-weight: 700; line-height: 1.4; }
  .event-play-section-title { color: #243b55; font-size: .9375rem; font-weight: 700; margin-bottom: 12px; }
  .event-play-group-title { margin: 18px 0 8px; color: #617287; font-size: .8125rem; font-weight: 600; }
  .event-published-draw-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 10px; }
  .event-published-draw-row { display: flex; align-items: center; gap: 8px; min-width: 0; flex-wrap: wrap; border: 1px solid #dce4ee; border-radius: 8px; padding: 6px; }
  .event-published-draw-link.btn { display: flex; flex: 1 1 180px; flex-direction: column; align-items: flex-start; gap: 5px; padding: 10px; color: #173f7a; background: transparent; border: 0; border-radius: 5px; box-shadow: none; font-size: .9375rem; font-weight: 600; line-height: 1.5; min-height: 44px; min-width: 0; white-space: normal; text-align: left; }
  .event-published-draw-link.btn:hover { background: #f2f6fb; color: #123366; }
  .event-published-draw-name { overflow-wrap: anywhere; }
  .event-published-draw-time { color: #526b60; font-size: .8125rem; font-weight: 400; }
  .event-play-card .event-draw-score { min-height: 44px; padding: 10px 12px; color: #344d69; border: 1px solid #dce4ee; background: #f7f9fc; box-shadow: none; font-size: .8125rem; white-space: normal; }
  .event-play-card .event-draw-score:hover { background: #eaf0f7; color: #173f7a; }
  .event-play-card .event-published-draw-link.btn, .event-play-card .event-draw-score.btn { box-shadow: none; }
  .event-play-card a:focus-visible { outline: 3px solid #6b9cd3; outline-offset: 3px; }
  .event-play-venues { border-top: 1px solid #e6ebf2; padding-top: 20px; }
  .event-play-venue { padding: 14px 16px; border-radius: 8px; background: #f7f9fc; }
  .event-play-venue-name { color: #344d69; font-size: .875rem; font-weight: 600; margin-bottom: 10px; overflow-wrap: anywhere; }
  .event-play-venue .btn { min-height: 44px; display: inline-flex; align-items: center; font-size: .8125rem; white-space: normal; text-align: left; }
  @media (max-width: 575.98px) { .event-play-card > .card-header { padding: 18px 18px 0; } .event-play-card > .card-header + .card-body { padding: 18px; } }
  @media (max-width: 399.98px) { .event-published-draw-row { flex-direction: column; align-items: stretch; } .event-published-draw-link.btn { flex-basis: auto; } .event-play-card .event-draw-score { display: flex; align-items: center; justify-content: center; width: 100%; } }
</style>
@endonce
<div class="card event-play-card mb-4">
  <div class="card-header">
    <h5 class="event-play-title">Draws and order of play</h5>
  </div>

  <div class="card-body">
    @if($event->isTeam() && $event->published && $event->standings_published)
      <div class="border rounded p-3 mb-3" data-event-standings-card>
        <h5 class="mb-2">Team standings</h5>
        <p class="mb-3">See positions by age group, total points and matches won / lost, with boys’, girls’ and mixed results combined.</p>
        <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.events.standings', $event) }}">View standings and match statistics</a>
      </div>
    @endif
    @include('frontend.event.partials._venue-scoring')

    {{-- ✅ Published Draws --}}
<div class="mb-3">
  <h6 class="event-play-section-title">Published draws</h6>

  @php
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
    <h6 class="event-play-group-title">{{ $typeName }}</h6>

    <div class="event-published-draw-list">
      @foreach($draws as $draw)
        @php
          $publishedDays = $publishedDayLabels->get($draw->id, '');
        @endphp
        <div class="event-published-draw-row">
          <a href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id) }}"
             class="btn btn-sm event-published-draw-link">
            <span class="event-published-draw-name">{{ $draw->drawName }}</span>
            @if($publishedDays !== '')
              <span class="event-published-draw-time">
                <i class="ti ti-clock me-1" aria-hidden="true"></i>{{ $publishedDays }}
              </span>
            @endif
          </a>
          @php

            $canScoreEvent = $canScoreEvent ?? (auth()->check() && auth()->user()->can('event.score', $event));
          @endphp
          @if($canScoreEvent)
            <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]) }}"
               class="btn btn-sm event-draw-score"
               title="Score {{ $draw->drawName }}">
              <i class="ti ti-clipboard-check me-1" aria-hidden="true"></i>Score draw<span class="visually-hidden">: {{ $draw->drawName }}</span>
            </a>
          @endif
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> They will appear here when released; match times may follow later.</div>
  @endforelse
  @php
    $publicVenueRows = $publicRowsByDraw->flatten(1)->filter(fn ($row) => !empty($row['venue_id']) && !empty($row['venue_name']))->groupBy('venue_id');
  @endphp
  @if($publicVenueRows->isNotEmpty())
    <section class="event-play-venues mt-4" aria-label="Published venues and order of play">
      <h6 class="event-play-section-title">Venues and schedules</h6>
      <div class="d-grid gap-3">
        @foreach($publicVenueRows->sortBy(fn ($rows) => $rows->first()['venue_name']) as $venueId => $venueRows)
          <div class="event-play-venue">
            <div class="event-play-venue-name"><i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $venueRows->first()['venue_name'] }}</div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-primary" href="{{ route('fixtures.venue', [$event->id, $venueId]) }}">View fixtures<span class="visually-hidden">: {{ $venueRows->first()['venue_name'] }}</span></a>
              @foreach($venueRows->pluck('scheduled_at')->map(fn ($time) => \Carbon\Carbon::parse($time)->toDateString())->unique()->sort() as $venueDate)
                <a class="btn btn-outline-secondary" href="{{ route('fixtures.order', [$event->id, $venueId, $venueDate]) }}">{{ \Carbon\Carbon::parse($venueDate)->format('D j M') }} order of play<span class="visually-hidden">: {{ $venueRows->first()['venue_name'] }}</span></a>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif
    

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
