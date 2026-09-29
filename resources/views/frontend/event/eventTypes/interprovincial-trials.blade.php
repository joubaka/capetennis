<div class="col-xl-12">
  <div class="row g-4">
    <div class="col-lg-8">
      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')
      <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Trial nominations</h5></div>
        <div class="card-body">
          @if($publishedCategories->isEmpty())
            <p class="text-muted mb-0">No trial nominations have been published.</p>
          @else
            <div class="row g-3">
              @foreach($publishedCategories as $categoryEvent)
                <div class="col-md-6">
                  <section class="border rounded p-3 h-100">
                    <h6>{{ $categoryEvent->category?->name ?? 'Category' }}</h6>
                    <ul class="list-group list-group-flush">
                      @forelse($categoryEvent->nominations as $nomination)
                        @php
                          $trialInvitation = $nomination->actionableInvitation;
                          $ownsPlayer = auth()->check() && in_array((int) $nomination->player_id, auth()->user()->ownedPlayerIds(), true);
                          $tupleMatches = $trialInvitation
                            && (int) $trialInvitation->event_id === (int) $event->id
                            && (int) $trialInvitation->category_event_id === (int) $categoryEvent->id
                            && (int) $trialInvitation->nomination_id === (int) $nomination->id
                            && (int) $trialInvitation->player_id === (int) $nomination->player_id;
                          $focused = (int) request()->query('player') === (int) $nomination->player_id
                            && (int) request()->query('nomination') === (int) $nomination->id;
                          $registrationOpen = $event->published && $event->hasOpenRegistrationLifecycle() && (int) $event->signUp === 1
                            && (!$event->registrationClosesAt() || now()->lte($event->registrationClosesAt()->endOfDay()));
                        @endphp
                        <li id="trial-nomination-{{ $nomination->id }}" class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2 {{ $focused ? 'border border-primary rounded px-2 bg-label-primary' : '' }}" @if($focused) tabindex="-1" autofocus @endif>
                          <span>{{ $nomination->player?->name }} {{ $nomination->player?->surname }}</span>
                          @if(!$trialInvitation && $registrationOpen)
                            <div class="d-flex flex-wrap align-items-center gap-2">
                              <span class="badge bg-label-secondary">Not registered</span>
                              @auth
                                <form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                              @else
                                <a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to register</a>
                              @endauth
                            </div>
                          @elseif($tupleMatches)
                            <div class="d-flex flex-wrap align-items-center gap-2">
                            @if(in_array($trialInvitation->status, ['queued', 'sent', 'open_registration'], true))
                              <span class="badge bg-label-secondary">Not registered</span>
                              @if($registrationOpen)
                                @auth<form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                @else<a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to register</a>@endauth
                              @else
                                <span class="badge bg-label-secondary">Registration closed</span>
                              @endif
                              @if($ownsPlayer)<form method="POST" action="{{ route('interprovincial-trials.invitations.decline', $trialInvitation) }}" onsubmit="return confirm('Decline this invitation?');">@csrf<button type="submit" class="btn btn-sm btn-outline-danger">Decline</button></form>@endif
                            @elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT)
                              <span class="badge bg-label-secondary">Not registered</span>
                              @if($registrationOpen)
                                @auth<form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                @else<a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to register</a>@endauth
                              @endif
                            @elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED)
                              <span class="badge bg-label-success">Registered</span>
                            @elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::DECLINED)
                              <span class="badge bg-label-secondary">Declined</span>
                              @if($registrationOpen)
                                @auth<form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                @else<a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to register</a>@endauth
                              @endif
                            @elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::WITHDRAWN)
                              <span class="badge bg-label-secondary">Not registered</span>
                              @if($registrationOpen)
                                @auth<form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                @else<a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to register</a>@endauth
                              @endif
                            @endif
                            </div>
                          @endif
                        </li>
                      @empty
                        <li class="list-group-item px-0 text-muted">No nominated players.</li>
                      @endforelse
                    </ul>
                  </section>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      @include('frontend.event.partials.event-about')
    </div>
  </div>
</div>
