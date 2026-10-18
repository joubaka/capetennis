@if($savedCategoryDraw->pending_late_withdrawal_count > 0)
  <a class="btn btn-warning" href="{{ route('backend.draw.roundrobin.show', $savedCategoryDraw) }}">Review {{ $savedCategoryDraw->pending_late_withdrawal_count }} late {{ Str::plural('withdrawal', $savedCategoryDraw->pending_late_withdrawal_count) }}: {{ $savedCategoryDraw->drawName }}</a>
@elseif($savedCategoryDraw->published || $savedCategoryDraw->locked)
  <a class="btn btn-primary" href="{{ route('backend.draw.roundrobin.show', $savedCategoryDraw) }}">Open draw: {{ $savedCategoryDraw->drawName }}</a>
@else
  @can('event.manage', $event)
    <a data-draw-guide-open class="btn btn-primary" href="{{ route('draw.guide.show', [$event, $savedCategoryDraw]) }}">Continue setup: {{ $savedCategoryDraw->drawName }}</a>
  @else
    <a class="btn btn-outline-primary" href="{{ route('backend.draw.roundrobin.show', $savedCategoryDraw) }}">Open draw: {{ $savedCategoryDraw->drawName }}</a>
  @endcan
@endif
