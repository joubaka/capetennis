@php($typeView = $event->frontend_type_view)
@php($typeTemplate = \App\Support\EventTypePresentation::template($event))

@if($typeTemplate === 'interprovincial-trials')
  @include('frontend.event.eventTypes.interprovincial-trials', ['publishedCategories' => $interprovincialTrialCategories ?? collect()])
@elseif($typeView === 'masters')
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

@if($event->isIndividual())
  @include('frontend.event.partials.category-adjustments')
@endif

{{-- Trials already provides its own responsive draws and programme sections. --}}
@if(($eventDraws ?? collect())->where('published', true)->isNotEmpty()
    && !$event->isInterprovincialTrials()
    && !in_array($typeView, ['individual', 'team', 'interpro', 'cavaliers_trials'], true))
  @include('frontend.event.partials.event-draws')
@endif
