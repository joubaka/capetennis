@php
  $programme = \Illuminate\Support\Facades\Schema::hasTable('trial_programmes') ? \App\Models\TrialProgramme::with('currentRun')->where('event_id', $event->id)->first() : null;
  $squad = $programme ? \App\Models\TrialSquadDraft::where('event_id', $event->id)->where('status', 'finalised')->latest('id')->first() : null;
  $publishedSlots = $squad?->slots()->with(['player', 'categoryEvent.category'])->orderBy('category_event_id')->orderBy('tier')->orderBy('slot')->get() ?? collect();
@endphp
@if($programme?->concluded_at && $programme->currentRun)
<div class="card mt-4"><div class="card-body"><h5>Final Trials rankings</h5>
  @foreach(collect($programme->currentRun->positions)->groupBy('category_event_id') as $categoryId => $positions)
    <h6>{{ $event->categoryEvents->firstWhere('id', $categoryId)?->category?->name ?? 'Category' }}</h6>
    <ol>@foreach($positions as $position)<li>{{ $position['name'] }}</li>@endforeach</ol>
  @endforeach
</div></div>
@endif
@if($squad)
<div class="card mt-4"><div class="card-body"><h5>Selected regional teams</h5>
  @if($squad->needs_review)<p class="alert alert-warning">Updated Trials results are under administrative review. The current roster remains published until changes are approved.</p>@endif
  @foreach($publishedSlots->groupBy('category_event_id') as $categorySlots)
    <h6>{{ $categorySlots->first()->categoryEvent?->category?->name }}</h6>
    @foreach($categorySlots->groupBy('tier') as $tier => $teamSlots)
      <h6>Team {{ $tier }}</h6>
      <ul class="list-group mb-3">@foreach($teamSlots as $slot)
        <li id="trial-squad-slot-{{ $slot->id }}" class="list-group-item {{ request()->integer('slot') === $slot->id ? 'bg-label-primary' : '' }}">
          <span>{{ $slot->reserve ? 'Reserve '.($slot->slot - 6) : $slot->slot }}. {{ $slot->player?->full_name ?? 'Vacant place' }}</span>
          @if($slot->player_id && !$slot->reserve)
            @auth
              <form method="POST" class="d-flex flex-wrap gap-2 mt-2" action="{{ route('interprovincial-trials.squads.respond', [$event, $slot]) }}">@csrf
                <button name="response" value="confirmed" class="btn btn-sm btn-outline-success">Confirm participation</button>
                <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline participation</button>
              </form>
              @if($slot->response !== 'declined')
                <form method="POST" class="mt-2" action="{{ route('interprovincial-trials.participation.begin', [$event, $slot]) }}">@csrf<button class="btn btn-sm btn-primary">Register and pay participation fee</button></form>
              @endif
              @if((int) $slot->responded_by === (int) auth()->id())<span class="small">Your response: {{ $slot->response }}</span>@endif
            @else <a href="{{ route('login') }}">Sign in to respond</a> @endauth
          @endif
        </li>
      @endforeach</ul>
    @endforeach
  @endforeach
</div></div>
@endif
