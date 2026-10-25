<div class="card {{ $directoryClass ?? '' }} p-3 p-sm-4">
  <div class="card-body p-0 pb-2">
    <div class="badge {{ $directoryBadgeClass ?? 'bg-label-primary' }} mb-3" @if($directoryAlert ?? false) role="alert" @endif>
      Click on a Region below to register
    </div>
  </div>

  @if($regions->isNotEmpty())
    @include('frontend.event.partials._region_team_picker', ['regions' => $regions])
  @else
    <div class="alert alert-secondary mt-3">
      Regions are not configured for this event.
    </div>
  @endif
</div>
