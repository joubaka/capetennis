@isset($pathwayEvents)
<section class="mt-3" aria-labelledby="pathway-events-heading" data-pathway-upcoming-events>
  <h3 class="h6" id="pathway-events-heading">Upcoming events for your stage</h3>
  @if($pathwayEvents['stage'])
    <p class="small text-muted">Matches for {{ $pathway['presentation']['stage'] }}@if($pathway['presentation']['state'] === 'update_pending') · based on your latest saved rating; update pending@endif. Check age, division, invitation and entry requirements with the organiser. A stage match does not guarantee entry.</p>
    @forelse($pathwayEvents['events'] as $option)
      <article class="pathway-stage mb-2" style="--stage-ink:#145c8d" data-pathway-event="{{ $option['id'] }}">
        <h4 class="h6 mb-1"><a href="{{ $option['url'] }}">{{ $option['name'] }}</a></h4>
        <p class="small mb-1">{{ $option['date'] }} · {{ $option['venue'] }} · {{ $option['status'] }}</p>
        <p class="small mb-1">Matching categories: {{ implode(', ', $option['categories']) }}</p>
        <a class="small d-inline-flex align-items-center" style="min-height:44px" href="{{ $option['url'] }}">Check entry requirements</a>
      </article>
    @empty
      <p class="small">No upcoming published events with confirmed matching stage details are listed here yet. Browse events or ask your coach about other options.</p>
    @endforelse
    @if($pathwayEvents['limited'])<p class="small text-muted">More upcoming events may be available. <a href="{{ route('home') }}">Browse all events</a>.</p>@endif
  @else
    <p class="small text-muted">Stage-specific events will appear once comparable rating evidence and a confirmed stage are available. You can still browse events and check requirements with the organiser.</p>
  @endif
</section>
@endisset
