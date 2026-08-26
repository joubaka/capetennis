@php($typeView = $event->frontend_type_view)

@if($typeView === 'masters')
  @include('frontend.event.eventTypes.masters', ['invitations' => $mastersInvitations ?? collect()])
@elseif(view()->exists('frontend.event.eventTypes.'.$typeView))
  @include('frontend.event.eventTypes.'.$typeView)
@else
  <div class="card">
    <div class="card-body">
      <div class="alert alert-warning mb-0" role="status">
        This event type is not yet available for public display.
      </div>
    </div>
  </div>
@endif
