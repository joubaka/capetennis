<div class="col-xl-12">
  <div class="row g-4">
    <div class="col-lg-8">
      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')
      <div id="event-draws-mobile" class="d-block d-md-none mb-4" tabindex="-1" data-event-draw-section aria-label="Published draws">
        @include('frontend.event.partials._draws_and_order_of_play')
      </div>
      @include('frontend.event.partials.trial-programme')
      @php
        $nominationsCloseAt = $event->registrationClosesAt();
        $nominationsClosed = $nominationsCloseAt && now()->gt($nominationsCloseAt);
      @endphp
      <details class="card mt-4" @if(!$nominationsClosed) open @endif>
        <summary class="card-header" style="cursor: pointer;">
          <span class="h5 mb-0">Trial nominations</span>
          @if($nominationsClosed)
            <span class="small text-muted ms-2">Registration closed · View nominations</span>
          @endif
        </summary>
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
                          <span>{{ $nomination->display_name }}</span>
                          @if($tupleMatches && $trialInvitation->status === 'admin_declined')
                            <span class="badge bg-label-secondary">Registration unavailable</span>
                          @elseif($nomination->player_id === null)
                            <div class="d-flex flex-wrap align-items-center gap-2">
                              <span class="badge bg-label-warning">Profile required</span>
                              @if($registrationOpen)
                                <a class="btn btn-sm btn-primary" href="{{ route('interprovincial-trials.nominations.profile', [$event, $categoryEvent, $nomination]) }}">Create player profile</a>
                              @endif
                            </div>
                          @elseif(!$trialInvitation && $registrationOpen)
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
                                @auth<form method="POST" action="{{ route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination]) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Resume registration</button></form>
                                @else<a class="btn btn-sm btn-primary" href="{{ route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id]) }}">Sign in to resume</a>@endauth
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
      </details>
    </div>
    <div class="col-lg-4">
      @include('frontend.event.partials.event-about')
      <div id="event-draws-match-times" class="d-none d-md-block" tabindex="-1" data-event-draw-section aria-label="Published draws">
        @include('frontend.event.partials._draws_and_order_of_play')
      </div>
    </div>
  </div>
</div>
