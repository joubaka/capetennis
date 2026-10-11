@auth
  @if($event->isTeam())
    @foreach(app(\App\Services\AgeGroupConvenerStandingsService::class)->assignedAges($event, auth()->user()) as $convenerAge)
      <a class="btn btn-outline-primary mb-3" style="min-height:44px" href="{{ route('frontend.events.convener-standings', [$event, $convenerAge]) }}">{{ $convenerAge }} convener standings</a>
    @endforeach
  @endif
@endauth
